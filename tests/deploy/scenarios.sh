#!/usr/bin/env bash
# Banc d'essai des scripts de deploiement, a lancer dans un conteneur Linux
# jetable (vrais liens symboliques, GNU coreutils comme sur le serveur) :
#
#   docker run --rm -v "$PWD":/app:ro -w /app nginx:stable bash tests/deploy/scenarios.sh
#
# deploy/deploy-shared-hosting.sh et deploy/rollback-shared-hosting.sh sont
# executes tels quels. Les outils du serveur (git, php, composer, npm, curl,
# crontab, date, sleep) sont remplaces par des bouchons pilotes par des
# variables FAUX_*. Rien ne sort du conteneur.
set -uo pipefail

DEPOT="$(cd "$(dirname "$0")/../.." && pwd)"
BANC="$(mktemp -d)"
SITE="${BANC}/home/presence.fillesdartsbenin.com"
ECHECS=0

ok() { echo "  ok    $1"; }
ko() { echo "  ECHEC $1"; ECHECS=$((ECHECS + 1)); }
verifier() {
  local libelle="$1"
  shift
  if "$@"; then ok "${libelle}"; else ko "${libelle}"; fi
}
contient() { grep -qF -- "$2" "$1"; }
version_en_service() { cat "${SITE}/current/public/version.txt"; }
nombre_de_versions() { find "${SITE}/releases" -mindepth 1 -maxdepth 1 -type d | wc -l; }

# --- Bouchons -----------------------------------------------------------------
mkdir -p "${BANC}/bin" "${BANC}/faux-depot"

# Faux depot : ce que git clone recopie dans chaque version.
cp -a "${DEPOT}/public" "${BANC}/faux-depot/public"
rm -rf "${BANC}/faux-depot/public/build" "${BANC}/faux-depot/public/storage"
cp -a "${DEPOT}/deploy" "${BANC}/faux-depot/deploy"
mkdir -p "${BANC}/faux-depot/storage"
touch "${BANC}/faux-depot/artisan" "${BANC}/faux-depot/composer.json"

cat > "${BANC}/bin/git" <<'EOF'
#!/usr/bin/env bash
if [[ "$1" == "clone" ]]; then
  cible="${*: -1}"
  mkdir -p "${cible}" && cp -a "${FAUX_DEPOT}/." "${cible}/"
  echo "${FAUX_VERSION}" > "${cible}/public/version.txt"
  exit 0
fi
if [[ "$1" == "-C" ]]; then echo "abc1234"; exit 0; fi
exit 1
EOF

cat > "${BANC}/bin/php" <<'EOF'
#!/usr/bin/env bash
case "$*" in
  *PHP_VERSION_ID*) exit "${FAUX_PHP_TROP_ANCIEN:-0}" ;;
  *"PHP_VERSION;"*) echo "${FAUX_PHP_VERSION:-8.4.5}"; exit 0 ;;
esac
if [[ "$1" == */composer ]]; then
  if [[ "${FAUX_COMPOSER_ECHEC:-0}" == 1 ]]; then echo "composer : echec simule" >&2; exit 1; fi
  mkdir -p vendor && touch vendor/autoload.php
  exit 0
fi
if [[ "$1" == "artisan" ]]; then
  case "$2" in
    migrate) if [[ "${FAUX_MIGRATION_ECHEC:-0}" == 1 ]]; then echo "migration : echec simulee" >&2; exit 1; fi ;;
    storage:link) ln -sfn ../storage/app/public public/storage ;;
  esac
  exit 0
fi
exit 0
EOF

echo "# faux composer" > "${BANC}/bin/composer"

cat > "${BANC}/bin/npm" <<'EOF'
#!/usr/bin/env bash
if [[ "$*" == "run build" ]]; then
  mkdir -p public/build && echo "${FAUX_VERSION}" > public/build/app.css
fi
exit 0
EOF

cat > "${BANC}/bin/curl" <<'EOF'
#!/usr/bin/env bash
case "${*: -1}" in
  */up) [[ "${FAUX_UP:-ok}" == ok ]] ;;
  */login) [[ "${FAUX_LOGIN:-ok}" == ok ]] ;;
  *) exit 1 ;;
esac
EOF

cat > "${BANC}/bin/crontab" <<'EOF'
#!/usr/bin/env bash
if [[ -z "${FAUX_CRONTAB:-}" ]]; then exit 1; fi
printf '%s\n' "${FAUX_CRONTAB}"
EOF

# Horodatages distincts et croissants, meme a quelques millisecondes d'ecart.
cat > "${BANC}/bin/date" <<'EOF'
#!/usr/bin/env bash
if [[ "${1:-}" == "+%Y%m%d%H%M%S" ]]; then
  n=$(( $(cat "${FAUX_COMPTEUR}") + 1 )); echo "${n}" > "${FAUX_COMPTEUR}"
  printf '202601010000%02d\n' "${n}"
  exit 0
fi
exec /usr/bin/date "$@"
EOF

printf '#!/usr/bin/env bash\nexit 0\n' > "${BANC}/bin/sleep"
chmod +x "${BANC}"/bin/*
echo 0 > "${BANC}/compteur"

# --- Serveur de depart (README, etape 3) --------------------------------------
mkdir -p "${SITE}/releases" "${SITE}/shared/storage/app/public" "${SITE}/shared/storage/logs"
echo "APP_KEY=base64:dGVzdA==" > "${SITE}/shared/.env"
cp "${DEPOT}/deploy/deploy-shared-hosting.sh" "${SITE}/deploy.sh"
cp "${DEPOT}/deploy/rollback-shared-hosting.sh" "${SITE}/rollback.sh"

executer() {
  local script="$1"
  shift
  env HOME="${BANC}/home" APP_DIR="${SITE}" PATH="${BANC}/bin:${PATH}" \
    FAUX_DEPOT="${BANC}/faux-depot" FAUX_COMPTEUR="${BANC}/compteur" "$@" \
    bash "${SITE}/${script}" > "${BANC}/sortie.log" 2>&1
}
deployer() { executer deploy.sh "$@"; }
revenir() { executer rollback.sh "$@"; }

# --- Scenarios ----------------------------------------------------------------
echo "1. Premier deploiement"
deployer FAUX_VERSION=v1; code=$?
verifier "le script reussit" test "${code}" -eq 0
verifier "current pointe vers la version v1" test "$(version_en_service)" = v1
verifier "la version est marquee comme reussie" test -f "${SITE}/current/.deploiement-ok"
verifier "index.php de la racine pointe vers current/" contient "${SITE}/index.php" "__DIR__.'/current/vendor/autoload.php'"
verifier "les assets de v1 sont a la racine" test "$(cat "${SITE}/build/app.css")" = v1
verifier "storage pointe vers shared/storage/app/public" test "$(readlink "${SITE}/storage")" = shared/storage/app/public
verifier "shared/ est interdit en HTTP" contient "${SITE}/shared/.htaccess" "Require all denied"
verifier "releases/ est interdit en HTTP" contient "${SITE}/releases/.htaccess" "Require all denied"
verifier "la version elle-meme est interdite en HTTP" contient "${SITE}/current/.htaccess" "Require all denied"
verifier "la racine refuse shared, releases et current" contient "${SITE}/.htaccess" "RewriteRule ^(shared|releases|current)(/|$) - [F,L]"
verifier "la racine refuse deploy.sh et rollback.sh" contient "${SITE}/.htaccess" 'RewriteRule ^(deploy|rollback)\.sh$ - [F,L]'
verifier "la racine redirige vers HTTPS" contient "${SITE}/.htaccess" "RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]"
verifier "le journal note le succes" contient "${SITE}/shared/deploy.log" "statut=OK"
verifier "le rappel du cron s'affiche sans crontab" contient "${BANC}/sortie.log" "ATTENTION: aucune tache cron"

echo "2. Fichiers de cPanel a la racine"
{
  echo "# php -- BEGIN cPanel-generated handler, do not edit"
  echo "AddHandler application/x-httpd-ea-php84 .php"
  echo "# php -- END cPanel-generated handler, do not edit"
  cat "${SITE}/.htaccess"
} > "${SITE}/.htaccess.tmp" && mv "${SITE}/.htaccess.tmp" "${SITE}/.htaccess"
echo "memory_limit = 256M" > "${SITE}/.user.ini"
echo "upload_max_filesize = 20M" > "${SITE}/php.ini"
echo "PHP Warning" > "${SITE}/error_log"
mkdir -p "${SITE}/.well-known/acme-challenge" && echo jeton > "${SITE}/.well-known/acme-challenge/x"
deployer FAUX_VERSION=v2 FAUX_CRONTAB="* * * * * cd ${SITE}/current && php artisan schedule:run >> /dev/null 2>&1"; code=$?
verifier "le script reussit" test "${code}" -eq 0
verifier "current pointe vers v2" test "$(version_en_service)" = v2
verifier ".user.ini est conserve" test -f "${SITE}/.user.ini"
verifier "php.ini est conserve" test -f "${SITE}/php.ini"
verifier "error_log est conserve" test -f "${SITE}/error_log"
verifier ".well-known est conserve" test -f "${SITE}/.well-known/acme-challenge/x"
verifier "le bloc PHP de cPanel est garde dans le .htaccess" contient "${SITE}/.htaccess" "AddHandler application/x-httpd-ea-php84 .php"
verifier "le .htaccess garde les regles de Laravel" contient "${SITE}/.htaccess" "RewriteRule ^ index.php [L]"
verifier "le bloc cPanel n'est pas duplique" test "$(grep -c 'BEGIN cPanel-generated handler' "${SITE}/.htaccess")" -eq 1
verifier "pas de rappel du cron quand il existe" test "$(grep -c 'ATTENTION: aucune tache cron' "${BANC}/sortie.log")" -eq 0

VERSIONS_AVANT="$(nombre_de_versions)"

echo "3. Migration en echec"
deployer FAUX_VERSION=v3 FAUX_MIGRATION_ECHEC=1; code=$?
verifier "le script echoue" test "${code}" -ne 0
verifier "le site reste sur v2" test "$(version_en_service)" = v2
verifier "la version ratee est supprimee" test "$(nombre_de_versions)" -eq "${VERSIONS_AVANT}"
verifier "la racine sert toujours les assets de v2" test "$(cat "${SITE}/build/app.css")" = v2

echo "4. Composer en echec"
deployer FAUX_VERSION=v4 FAUX_COMPOSER_ECHEC=1; code=$?
verifier "le script echoue" test "${code}" -ne 0
verifier "le site reste sur v2" test "$(version_en_service)" = v2
verifier "la version ratee est supprimee" test "$(nombre_de_versions)" -eq "${VERSIONS_AVANT}"

echo "5. Page de connexion en erreur apres la bascule"
deployer FAUX_VERSION=v5 FAUX_LOGIN=ko; code=$?
verifier "le script echoue" test "${code}" -ne 0
verifier "le site revient sur v2" test "$(version_en_service)" = v2
verifier "les assets de v2 sont remis a la racine" test "$(cat "${SITE}/build/app.css")" = v2
verifier "la version ratee est supprimee" test "$(nombre_de_versions)" -eq "${VERSIONS_AVANT}"
verifier "le journal note l'echec" contient "${SITE}/shared/deploy.log" "statut=ECHEC_VERIFICATION"

echo "6. PHP trop ancien pour composer.lock"
deployer FAUX_VERSION=v6 FAUX_PHP_TROP_ANCIEN=1 FAUX_PHP_VERSION=8.3.12; code=$?
verifier "le script echoue" test "${code}" -ne 0
verifier "le message demande PHP 8.4.1" contient "${BANC}/sortie.log" "il faut PHP 8.4.1 ou plus"
verifier "rien n'est clone" test "$(nombre_de_versions)" -eq "${VERSIONS_AVANT}"

echo "7. Retours arriere"
deployer FAUX_VERSION=v7; code=$?
verifier "le deploiement de v7 reussit" test "${code}" -eq 0
V2="$(basename "$(find "${SITE}/releases" -mindepth 2 -maxdepth 2 -name .deploiement-ok -printf '%h\n' | sort | sed -n '2p')")"
mkdir -p "${SITE}/releases/${V2}9/public" && echo orpheline > "${SITE}/releases/${V2}9/public/version.txt"
revenir; code=$?
verifier "le premier retour reussit" test "${code}" -eq 0
verifier "il saute la version non marquee et revient sur v2" test "$(version_en_service)" = v2
verifier "les assets de v2 sont remis a la racine" test "$(cat "${SITE}/build/app.css")" = v2
revenir; code=$?
verifier "le second retour reussit" test "${code}" -eq 0
verifier "il recule encore, sur v1" test "$(version_en_service)" = v1
revenir; code=$?
verifier "un troisieme retour est refuse" test "${code}" -ne 0
verifier "le site reste sur v1" test "$(version_en_service)" = v1

echo ""
if [[ "${ECHECS}" -eq 0 ]]; then
  echo "Tous les scenarios sont passes."
else
  echo "${ECHECS} verification(s) en echec. Derniere sortie :"
  cat "${BANC}/sortie.log"
fi
rm -rf "${BANC}"
exit "$([[ "${ECHECS}" -eq 0 ]] && echo 0 || echo 1)"
