#!/usr/bin/env bash
# Retour manuel de presence-paiement-cafab (CAFAB) a la version precedente, sur
# l'hebergement mutualise cPanel. Repris de caisse-depenses. Differences : la
# cible est la version qui precede celle en service (deux retours de suite
# reculent de deux versions), et public/ est resynchronise a la racine.
set -euo pipefail
trap 'echo "[ERREUR] rollback-shared-hosting.sh a echoue a la ligne $LINENO" >&2' ERR

APP_DIR="${APP_DIR:-$HOME/presence.fillesdartsbenin.com}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://presence.fillesdartsbenin.com/up}"

# Recopie public/ d'une version a la racine du sous-domaine.
# A garder identique dans deploy-shared-hosting.sh.
synchroniser_public() {
  local version="$1"

  find "${APP_DIR}" -maxdepth 1 -mindepth 1 \
    ! -name releases ! -name shared ! -name current ! -name current.new \
    ! -name deploy.sh ! -name rollback.sh ! -name .well-known ! -name cgi-bin \
    -exec rm -rf {} +
  cp -a "${version}/public/." "${APP_DIR}/"
  sed -i \
    -e "s#__DIR__\.'/\.\./storage/framework/maintenance\.php'#__DIR__.'/current/storage/framework/maintenance.php'#" \
    -e "s#__DIR__\.'/\.\./vendor/autoload\.php'#__DIR__.'/current/vendor/autoload.php'#" \
    -e "s#__DIR__\.'/\.\./bootstrap/app\.php'#__DIR__.'/current/bootstrap/app.php'#" \
    "${APP_DIR}/index.php"

  if grep -q "__DIR__\.'/\.\./" "${APP_DIR}/index.php"; then
    echo "ERREUR: index.php contient encore une reference '../' non adaptee - verifier public/index.php." >&2
    return 1
  fi

  rm -f "${APP_DIR}/storage"
  ln -s shared/storage/app/public "${APP_DIR}/storage"
}

if [[ ! -L "${APP_DIR}/current" ]]; then
  echo "Aucune version en service (${APP_DIR}/current absent) - retour impossible." >&2
  exit 1
fi

EN_SERVICE="$(basename "$(readlink -f "${APP_DIR}/current")")"
PRECEDENTE="$(ls -1 "${APP_DIR}/releases" | sort | awk -v courante="${EN_SERVICE}" '$0 == courante { print precedente; exit } { precedente = $0 }')"

if [[ -z "${PRECEDENTE}" ]]; then
  echo "Aucune version anterieure a ${EN_SERVICE} - retour impossible." >&2
  exit 1
fi

echo "==> Retour de ${EN_SERVICE} vers ${PRECEDENTE}"

ln -s "${APP_DIR}/releases/${PRECEDENTE}" "${APP_DIR}/current.new"
mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"
synchroniser_public "${APP_DIR}/releases/${PRECEDENTE}"

echo "$(date -Iseconds) retour de=${EN_SERVICE} vers=${PRECEDENTE} statut=RETOUR" >> "${APP_DIR}/shared/deploy.log"

sleep 2
if curl -fsS --max-time 10 "${HEALTHCHECK_URL}" > /dev/null; then
  echo "==> Retour vers ${PRECEDENTE} reussi."
else
  echo "Retour effectue mais healthcheck toujours KO - investigation manuelle requise." >&2
  exit 1
fi

echo ""
echo "Note : ce script ne touche jamais au schema de base de donnees."
echo "Si la version abandonnee a introduit une migration incompatible,"
echo "un 'php artisan migrate:rollback' manuel et reflechi reste necessaire."
