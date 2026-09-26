#!/usr/bin/env bash
# Deploiement de presence-paiement-cafab (CAFAB) sur l'hebergement mutualise
# cPanel partage avec caisse-depenses (pas de sudo/systemctl/apt : PHP, MySQL,
# Composer, Node et Git sont fournis par l'hebergeur).
#
# Repris de caisse-depenses/deploy/deploy-shared-hosting.sh. Differences :
# controle de la version de PHP (PHP_BIN), dossiers du deploiement interdits
# en HTTP, fichiers de cPanel preserves a la racine, version ratee supprimee,
# versions reussies marquees (.deploiement-ok) pour le retour arriere, retour
# automatique vers la version qui etait en service, verification de la page
# de connexion en plus de /up, rappel du cron du planificateur.
#
# Pattern releases/current : bascule atomique par symlink, retour arriere
# automatique si le site ne repond pas apres la bascule.
# Banc d'essai : tests/deploy/scenarios.sh.
set -euo pipefail
trap 'echo "[ERREUR] deploy-shared-hosting.sh a echoue a la ligne $LINENO - annulation." >&2' ERR

APP_DIR="${APP_DIR:-$HOME/presence.fillesdartsbenin.com}"
REPO_URL="${REPO_URL:-git@github.com:Magloire04/cafab_bord.git}"
BRANCH="${BRANCH:-main}"
KEEP_RELEASES=5
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://presence.fillesdartsbenin.com/up}"
PAGE_URL="${PAGE_URL:-https://presence.fillesdartsbenin.com/login}"
PHP_BIN="${PHP_BIN:-php}"
# Minimum exige par composer.lock (paquets Symfony 8). A relever si
# `composer check-platform-reqs --lock --no-dev` en demande davantage.
PHP_MIN_ID=80401
PHP_MIN="8.4.1"
PHP_EXEMPLE="/opt/cpanel/ea-php84/root/usr/bin/php"

RELEASE="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="${APP_DIR}/releases/${RELEASE}"
BASCULE_FAITE=0

# Interdit tout acces HTTP direct a un dossier. La racine du sous-domaine
# contient shared/, releases/ et current/ : sans cela, .env, journaux et .git
# seraient telechargeables.
interdire_acces_web() {
  cat > "$1/.htaccess" <<'HTACCESS'
# Genere par deploy.sh : aucun fichier de ce dossier n'est servi en HTTP.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
HTACCESS
}

# Recopie public/ d'une version a la racine du sous-domaine. La racine de
# document (Document Root) cPanel EST ${APP_DIR} (pas de "current/public"
# configurable sans l'interface cPanel) : index.php y est adapte pour pointer
# vers le symlink stable "current/" plutot que "../". Les fichiers que cPanel
# ecrit a la racine sont preserves, et les blocs qu'il ecrit dans .htaccess
# (version de PHP choisie dans MultiPHP, par exemple) sont reportes en tete du
# nouveau .htaccess.
# A garder identique dans rollback-shared-hosting.sh.
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

  # Les 3 remplacements doivent avoir eu lieu : mieux vaut echouer bruyamment
  # que laisser un index.php casse en production.
  if grep -q "__DIR__\.'/\.\./" "${APP_DIR}/index.php"; then
    echo "ERREUR: index.php contient encore une reference '../' non adaptee - verifier public/index.php." >&2
    return 1
  fi

  if [[ -n "${blocs_cpanel}" ]]; then
    { printf '%s\n\n' "${blocs_cpanel}"; cat "${APP_DIR}/.htaccess"; } > "${APP_DIR}/.htaccess.nouveau"
    mv -f "${APP_DIR}/.htaccess.nouveau" "${APP_DIR}/.htaccess"
  fi

  # Le lien public/storage cree par `artisan storage:link` est relatif a
  # public/ ; recopie a la racine, sa cible ne serait plus la bonne.
  rm -f "${APP_DIR}/storage"
  ln -s shared/storage/app/public "${APP_DIR}/storage"
}

# Le site repond-il vraiment ? /up prouve que Laravel demarre ; la page de
# connexion prouve en plus la session, la base, les middlewares web et les
# assets Vite. Quelques essais laissent aux processus PHP le temps de voir la
# bascule.
# A garder identique dans rollback-shared-hosting.sh.
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

# Une version abandonnee avant la bascule (migration, composer ou build en
# echec) est supprimee : elle ne doit jamais devenir une cible de retour.
supprimer_version_ratee() {
  local code=$?
  if [[ ${code} -ne 0 && ${BASCULE_FAITE} -eq 0 && -d "${RELEASE_DIR}" ]]; then
    rm -rf "${RELEASE_DIR}"
    echo "Version ${RELEASE} supprimee : le site reste sur la version en service." >&2
    echo "$(date -Iseconds) release=${RELEASE} branch=${BRANCH} statut=ECHEC_AVANT_BASCULE" \
      >> "${APP_DIR}/shared/deploy.log"
  fi
}

echo "==> Deploiement release ${RELEASE} (branche ${BRANCH})"

# --- 0. Garde-fous, avant toute action ---------------------------------------
if [[ ! -f "${APP_DIR}/shared/.env" ]]; then
  echo "ERREUR: ${APP_DIR}/shared/.env introuvable. Creez-le avant de deployer (voir README, section Deploiement)." >&2
  exit 1
fi

if ! command -v "${PHP_BIN}" > /dev/null 2>&1; then
  echo "ERREUR: PHP introuvable (${PHP_BIN}). Reglez PHP_BIN sur un PHP ${PHP_MIN} ou plus de cPanel, par exemple ${PHP_EXEMPLE}." >&2
  exit 1
fi

if ! "${PHP_BIN}" -r "exit(PHP_VERSION_ID >= ${PHP_MIN_ID} ? 0 : 1);"; then
  echo "ERREUR: ${PHP_BIN} est en PHP $("${PHP_BIN}" -r 'echo PHP_VERSION;'), il faut PHP ${PHP_MIN} ou plus (exige par composer.lock). Reglez PHP_BIN, par exemple ${PHP_EXEMPLE}." >&2
  exit 1
fi

COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
if [[ -z "${COMPOSER_BIN}" ]]; then
  echo "ERREUR: composer introuvable. Reglez COMPOSER_BIN sur le chemin de composer." >&2
  exit 1
fi

mkdir -p "${APP_DIR}/releases"
interdire_acces_web "${APP_DIR}/shared"
interdire_acces_web "${APP_DIR}/releases"

trap supprimer_version_ratee EXIT

# --- 1. Recuperation du code --------------------------------------------------
git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${RELEASE_DIR}"
COMMIT_SHA="$(git -C "${RELEASE_DIR}" rev-parse --short HEAD)"
interdire_acces_web "${RELEASE_DIR}"

# --- 2. Liens vers les ressources partagees (jamais recreees) ----------------
ln -s "${APP_DIR}/shared/.env" "${RELEASE_DIR}/.env"
rm -rf "${RELEASE_DIR}/storage"
ln -s "${APP_DIR}/shared/storage" "${RELEASE_DIR}/storage"

cd "${RELEASE_DIR}"

# --- 3. Dependances, par le PHP retenu et non celui du shell ------------------
"${PHP_BIN}" "${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction

# --- 4. Cle d'application (uniquement si absente du .env partage) ------------
if ! grep -q '^APP_KEY=base64:' "${APP_DIR}/shared/.env"; then
  "${PHP_BIN}" artisan key:generate --force
fi

# --- 5. Migrations (avant bascule : l'ancienne release sert encore le trafic)
# Une migration qui echoue (par exemple celle des emails sur un doublon) arrete
# le script ici : la version est supprimee et le site reste sur l'ancienne.
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
# On note la version en service pour y revenir si le site ne repond pas.
ANCIENNE_VERSION=""
if [[ -L "${APP_DIR}/current" ]]; then
  ANCIENNE_VERSION="$(readlink -f "${APP_DIR}/current")"
fi
ln -s "${RELEASE_DIR}" "${APP_DIR}/current.new"
mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"
BASCULE_FAITE=1

# Pas de sudo/systemctl ici : LSAPI/CloudLinux relit le code a chaque requete,
# la bascule du symlink suffit.

# --- 9. public/ a la racine du sous-domaine -----------------------------------
synchroniser_public "${RELEASE_DIR}"

# --- 10. Verification - echec = retour immediat a la version precedente ------
if ! site_repond; then
  if [[ -n "${ANCIENNE_VERSION}" ]]; then
    echo "==> Retour a la version precedente $(basename "${ANCIENNE_VERSION}")." >&2
    ln -s "${ANCIENNE_VERSION}" "${APP_DIR}/current.new"
    mv -Tf "${APP_DIR}/current.new" "${APP_DIR}/current"
    synchroniser_public "${ANCIENNE_VERSION}"
    rm -rf "${RELEASE_DIR}"
  else
    echo "Premier deploiement : aucune version precedente, le site reste sur ${RELEASE}." >&2
  fi
  echo "$(date -Iseconds) release=${RELEASE} commit=${COMMIT_SHA} branch=${BRANCH} statut=ECHEC_VERIFICATION" \
    >> "${APP_DIR}/shared/deploy.log"
  exit 1
fi

# Seules les versions marquees servent de cible a un retour arriere.
touch "${RELEASE_DIR}/.deploiement-ok"

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
