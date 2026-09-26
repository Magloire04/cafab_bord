#!/usr/bin/env bash
# Retour manuel de presence-paiement-cafab (CAFAB) a la version precedente, sur
# l'hebergement mutualise cPanel. Repris de caisse-depenses. Differences : la
# cible est la version reussie (.deploiement-ok) qui precede celle en service
# (deux retours de suite reculent de deux versions), public/ est
# resynchronise a la racine, et la page de connexion est verifiee en plus de /up.
# Banc d'essai : tests/deploy/scenarios.sh.
set -euo pipefail
trap 'echo "[ERREUR] rollback-shared-hosting.sh a echoue a la ligne $LINENO" >&2' ERR

APP_DIR="${APP_DIR:-$HOME/presence.fillesdartsbenin.com}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://presence.fillesdartsbenin.com/up}"
PAGE_URL="${PAGE_URL:-https://presence.fillesdartsbenin.com/login}"

# Recopie public/ d'une version a la racine du sous-domaine, en preservant les
# fichiers et les blocs .htaccess de cPanel.
# A garder identique dans deploy-shared-hosting.sh.
synchroniser_public() {
  local version="$1"
  local blocs_cpanel=""

  if [[ -f "${APP_DIR}/.htaccess" ]]; then
    blocs_cpanel="$(sed -n '/BEGIN cPanel-generated/,/END cPanel-generated/p' "${APP_DIR}/.htaccess")"
  fi

  find "${APP_DIR}" -maxdepth 1 -mindepth 1 \
    ! -name releases ! -name shared ! -name current ! -name current.new \
    ! -name deploy.sh ! -name rollback.sh ! -name .well-known ! -name cgi-bin \
    ! -name .user.ini ! -name php.ini ! -name error_log \
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

  if [[ -n "${blocs_cpanel}" ]]; then
    { printf '%s\n\n' "${blocs_cpanel}"; cat "${APP_DIR}/.htaccess"; } > "${APP_DIR}/.htaccess.nouveau"
    mv -f "${APP_DIR}/.htaccess.nouveau" "${APP_DIR}/.htaccess"
  fi

  rm -f "${APP_DIR}/storage"
  ln -s shared/storage/app/public "${APP_DIR}/storage"
}

# /up puis la page de connexion, avec quelques essais.
# A garder identique dans deploy-shared-hosting.sh.
site_repond() {
  local url essai
  for url in "${HEALTHCHECK_URL}" "${PAGE_URL}"; do
    for essai in 1 2 3 4 5; do
      if curl -fsS --max-time 10 -o /dev/null "${url}"; then
        continue 2
      fi
      sleep 2
    done
    echo "ERREUR: ${url} ne repond pas correctement." >&2
    return 1
  done
}

if [[ ! -L "${APP_DIR}/current" ]]; then
  echo "Aucune version en service (${APP_DIR}/current absent) - retour impossible." >&2
  exit 1
fi

EN_SERVICE="$(basename "$(readlink -f "${APP_DIR}/current")")"
# Candidates : les versions reussies, plus celle en service pour s'y reperer.
PRECEDENTE="$(
  for version in "${APP_DIR}"/releases/*/; do
    nom="$(basename "${version}")"
    if [[ -f "${version}.deploiement-ok" || "${nom}" == "${EN_SERVICE}" ]]; then
      echo "${nom}"
    fi
  done | sort | awk -v courante="${EN_SERVICE}" '$0 == courante { print precedente; exit } { precedente = $0 }'
)"

if [[ -z "${PRECEDENTE}" ]]; then
  echo "Aucune version reussie anterieure a ${EN_SERVICE} - retour impossible." >&2
  exit 1
fi

echo "==> Retour de ${EN_SERVICE} vers ${PRECEDENTE}"

ln -s "${APP_DIR}/releases/${PRECEDENTE}" "${APP_DIR}/current.new"
mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"
synchroniser_public "${APP_DIR}/releases/${PRECEDENTE}"

if site_repond; then
  echo "$(date -Iseconds) retour de=${EN_SERVICE} vers=${PRECEDENTE} statut=RETOUR" >> "${APP_DIR}/shared/deploy.log"
  echo "==> Retour vers ${PRECEDENTE} reussi."
else
  echo "$(date -Iseconds) retour de=${EN_SERVICE} vers=${PRECEDENTE} statut=RETOUR_KO" >> "${APP_DIR}/shared/deploy.log"
  echo "Retour effectue mais le site ne repond toujours pas - investigation manuelle requise." >&2
  exit 1
fi

echo ""
echo "Note : ce script ne touche jamais au schema de base de donnees."
echo "Si la version abandonnee a introduit une migration incompatible,"
echo "un 'php artisan migrate:rollback' manuel et reflechi reste necessaire."
