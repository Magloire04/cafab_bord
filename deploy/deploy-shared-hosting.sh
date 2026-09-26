#!/usr/bin/env bash
# Deploiement de presence-paiement-cafab (CAFAB) sur l'hebergement mutualise
# cPanel partage avec caisse-depenses (pas de sudo/systemctl/apt : PHP, MySQL,
# Composer, Node et Git sont fournis par l'hebergeur).
#
# Repris de caisse-depenses/deploy/deploy-shared-hosting.sh. Differences :
# controle de PHP 8.3 (PHP_BIN), retour arriere vers la version qui etait en
# service, public/ resynchronise a chaque bascule, .well-known et cgi-bin
# preserves a la racine, rappel du cron du planificateur.
#
# Pattern releases/current : bascule atomique par symlink, retour arriere
# automatique si le healthcheck echoue apres la bascule.
set -euo pipefail
trap 'echo "[ERREUR] deploy-shared-hosting.sh a echoue a la ligne $LINENO - annulation." >&2' ERR

APP_DIR="${APP_DIR:-$HOME/presence.fillesdartsbenin.com}"
REPO_URL="${REPO_URL:-git@github.com:Magloire04/cafab_bord.git}"
BRANCH="${BRANCH:-main}"
KEEP_RELEASES=5
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://presence.fillesdartsbenin.com/up}"
PHP_BIN="${PHP_BIN:-php}"
PHP_83_EXEMPLE="/opt/cpanel/ea-php83/root/usr/bin/php"

RELEASE="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="${APP_DIR}/releases/${RELEASE}"

# Recopie public/ d'une version a la racine du sous-domaine. La racine de
# document (Document Root) cPanel EST ${APP_DIR} (pas de "current/public"
# configurable sans l'interface cPanel) : index.php y est adapte pour pointer
# vers le symlink stable "current/" plutot que "../".
# A garder identique dans rollback-shared-hosting.sh.
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

  # Les 3 remplacements doivent avoir eu lieu : mieux vaut echouer bruyamment
  # que laisser un index.php casse en production.
  if grep -q "__DIR__\.'/\.\./" "${APP_DIR}/index.php"; then
    echo "ERREUR: index.php contient encore une reference '../' non adaptee - verifier public/index.php." >&2
    return 1
  fi

  # Le lien public/storage cree par `artisan storage:link` est relatif a
  # public/ ; recopie a la racine, sa cible ne serait plus la bonne.
  rm -f "${APP_DIR}/storage"
  ln -s shared/storage/app/public "${APP_DIR}/storage"
}

echo "==> Deploiement release ${RELEASE} (branche ${BRANCH})"

# --- 0. Garde-fous, avant toute action ---------------------------------------
if [[ ! -f "${APP_DIR}/shared/.env" ]]; then
  echo "ERREUR: ${APP_DIR}/shared/.env introuvable. Creez-le avant de deployer (voir README, section Deploiement)." >&2
  exit 1
fi

if ! command -v "${PHP_BIN}" > /dev/null 2>&1; then
  echo "ERREUR: PHP introuvable (${PHP_BIN}). Reglez PHP_BIN sur le PHP 8.3 de cPanel, par exemple ${PHP_83_EXEMPLE}." >&2
  exit 1
fi

if ! "${PHP_BIN}" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
  echo "ERREUR: ${PHP_BIN} est en PHP $("${PHP_BIN}" -r 'echo PHP_VERSION;'), il faut PHP 8.3 ou plus. Reglez PHP_BIN sur le PHP 8.3 de cPanel, par exemple ${PHP_83_EXEMPLE}." >&2
  exit 1
fi

COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
if [[ -z "${COMPOSER_BIN}" ]]; then
  echo "ERREUR: composer introuvable. Reglez COMPOSER_BIN sur le chemin de composer." >&2
  exit 1
fi

# --- 1. Recuperation du code --------------------------------------------------
git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${RELEASE_DIR}"
COMMIT_SHA="$(git -C "${RELEASE_DIR}" rev-parse --short HEAD)"

# --- 2. Liens vers les ressources partagees (jamais recreees) ----------------
ln -s "${APP_DIR}/shared/.env" "${RELEASE_DIR}/.env"
rm -rf "${RELEASE_DIR}/storage"
ln -s "${APP_DIR}/shared/storage" "${RELEASE_DIR}/storage"

cd "${RELEASE_DIR}"

# --- 3. Dependances, par le PHP 8.3 retenu et non celui du shell -------------
"${PHP_BIN}" "${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction

# --- 4. Cle d'application (uniquement si absente du .env partage) ------------
if ! grep -q '^APP_KEY=base64:' "${APP_DIR}/shared/.env"; then
  "${PHP_BIN}" artisan key:generate --force
fi

# --- 5. Migrations (avant bascule : l'ancienne release sert encore le trafic)
# Une migration qui echoue (par exemple celle des emails sur un doublon) arrete
# le script ici : le site reste sur l'ancienne version.
"${PHP_BIN}" artisan migrate --force

# --- 6. Assets front (Vite) ---------------------------------------------------
npm ci
npm run build

# --- 7. Caches Laravel ---------------------------------------------------------
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan storage:link

# --- 8. Bascule atomique du symlink -------------------------------------------
# On note la version en service pour y revenir si le healthcheck echoue.
ANCIENNE_VERSION=""
if [[ -L "${APP_DIR}/current" ]]; then
  ANCIENNE_VERSION="$(readlink -f "${APP_DIR}/current")"
fi
ln -s "${RELEASE_DIR}" "${APP_DIR}/current.new"
mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"

# Pas de sudo/systemctl ici : LSAPI/CloudLinux relit le code a chaque requete,
# la bascule du symlink suffit.

# --- 9. public/ a la racine du sous-domaine -----------------------------------
synchroniser_public "${RELEASE_DIR}"

# --- 10. Healthcheck - echec = retour immediat a la version precedente -------
sleep 2
if ! curl -fsS --max-time 10 "${HEALTHCHECK_URL}" > /dev/null; then
  echo "ERREUR: healthcheck KO sur ${HEALTHCHECK_URL}." >&2
  if [[ -n "${ANCIENNE_VERSION}" ]]; then
    echo "==> Retour a la version precedente $(basename "${ANCIENNE_VERSION}")." >&2
    ln -s "${ANCIENNE_VERSION}" "${APP_DIR}/current.new"
    mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"
    synchroniser_public "${ANCIENNE_VERSION}"
  else
    echo "Premier deploiement : aucune version precedente, le site reste sur ${RELEASE}." >&2
  fi
  echo "$(date -Iseconds) release=${RELEASE} commit=${COMMIT_SHA} branch=${BRANCH} statut=ECHEC_HEALTHCHECK" \
    >> "${APP_DIR}/shared/deploy.log"
  exit 1
fi

# --- 11. Nettoyage des anciennes releases (jamais celle en service) ----------
EN_SERVICE="$(readlink -f "${APP_DIR}/current")"
ls -1d "${APP_DIR}"/releases/*/ | sort -r | tail -n +$((KEEP_RELEASES + 1)) | while read -r ancienne; do
  if [[ "$(readlink -f "${ancienne}")" != "${EN_SERVICE}" ]]; then
    rm -rf "${ancienne}"
  fi
done

# --- 12. Auto-mise a jour des scripts pour le prochain deploiement ----------
# cp en place ecraserait le fichier que bash est en train de lire (ce script
# lui-meme) : on passe par un fichier temporaire puis un mv atomique.
cp "${RELEASE_DIR}/deploy/deploy-shared-hosting.sh" "${APP_DIR}/deploy.sh.new"
cp "${RELEASE_DIR}/deploy/rollback-shared-hosting.sh" "${APP_DIR}/rollback.sh.new"
chmod +x "${APP_DIR}/deploy.sh.new" "${APP_DIR}/rollback.sh.new"
mv -f "${APP_DIR}/deploy.sh.new" "${APP_DIR}/deploy.sh"
mv -f "${APP_DIR}/rollback.sh.new" "${APP_DIR}/rollback.sh"

# --- 13. Rappel du cron du planificateur (n'echoue pas) ----------------------
CRONTAB_ACTUELLE="$(crontab -l 2>/dev/null || true)"
if ! grep -Eq "$(basename "${APP_DIR}")/current.*schedule:run" <<< "${CRONTAB_ACTUELLE}"; then
  echo ""
  echo "ATTENTION: aucune tache cron ne lance le planificateur de cette application."
  echo "Ajoutez cette ligne dans cPanel, Taches Cron :"
  echo "  * * * * * cd ${APP_DIR}/current && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1"
  echo ""
fi

# --- 14. Journal d'audit (aucune donnee personnelle) --------------------------
echo "$(date -Iseconds) release=${RELEASE} commit=${COMMIT_SHA} branch=${BRANCH} statut=OK" \
  >> "${APP_DIR}/shared/deploy.log"

echo "==> Deploiement ${RELEASE} (commit ${COMMIT_SHA}) reussi."
