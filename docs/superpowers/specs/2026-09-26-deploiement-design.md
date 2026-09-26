# Mise en production : spec de conception

Incrément 008 de Présence & Paiements CAFAB. Objet : mettre l'application en ligne sur le même hébergement mutualisé cPanel que Caisse CAFAB, et préparer ce qu'une exposition publique rend nécessaire (kiosque, pages d'erreur).

## 1. Intention et critères de réussite

Jusqu'ici l'application n'a tourné qu'en local. Elle doit maintenant servir l'association : l'admin et les coachs depuis leur téléphone ou leur ordinateur, les filles depuis la tablette du kiosque, et chaque cachet validé doit créer sa dépense dans Caisse CAFAB.

L'incrément est réussi quand :

- `https://presence.fillesdartsbenin.com` répond, en HTTPS ;
- un déploiement et un retour arrière se lancent chacun en une commande depuis la machine d'Elisée, et un déploiement raté ne casse jamais le site en service ;
- les séances démarrent et se clôturent seules (cron du planificateur) ;
- l'email « mot de passe oublié » arrive ;
- le premier compte admin existe ;
- un cachet validé crée sa dépense dans Caisse CAFAB ;
- un inconnu ne peut plus essayer les codes PIN du kiosque à la chaîne ;
- aucune page d'erreur anglaise de Laravel n'est visible, et la tablette du kiosque ne reste jamais bloquée sur une erreur.

## 2. Décisions prises avec Elisée

| Sujet | Décision |
|---|---|
| Hébergement | Même compte cPanel mutualisé que Caisse CAFAB |
| Sous-domaine | `presence.fillesdartsbenin.com` |
| Méthode de déploiement | Adapter les scripts de Caisse CAFAB (approche A) : un script par dépôt, pas de script commun |
| Kiosque | Pas d'appareil autorisé. On durcit la limite des codes faux |
| Caisse CAFAB | Sa PR #3 (`POST /api/operations`) n'est pas encore en production : elle est redéployée en premier |
| Finitions | Faites et fusionnées avant cet incrément (PR #8) |

Hypothèses validées : base MySQL dédiée ; déploiement depuis `main` ; la tablette du kiosque est connectée à internet et partage une seule adresse IP pour toutes les filles ; les actions sur le serveur et sur Caisse CAFAB en production restent à Elisée, ou se font avec son accord explicite à chaque étape.

## 3. Scripts de déploiement

### 3.1 Fichiers

| Fichier | Où il s'exécute | Rôle |
|---|---|---|
| `deploy/deploy-shared-hosting.sh` | Serveur | Déploie une branche (par défaut `main`) |
| `deploy/rollback-shared-hosting.sh` | Serveur | Revient à la version précédente |
| `bin/deploy` | Machine d'Elisée | Se connecte en SSH et lance l'un des deux scripts |

Ils reprennent ceux de `caisse-depenses` (même dossier, mêmes noms), déjà éprouvés sur cet hébergement. Seules les différences décrites ici sont permises.

### 3.2 Arborescence sur le serveur

```text
~/presence.fillesdartsbenin.com/     racine du sous-domaine (Document Root)
├── releases/<AAAAMMJJHHMMSS>/       une version par déploiement, 5 conservées
├── shared/.env                       configuration de production (créée à la main)
├── shared/storage/                   app/public, framework/{cache/data,sessions,views}, logs
├── shared/deploy.log                 journal des déploiements (sans donnée personnelle)
├── current -> releases/<…>          version en service
├── deploy.sh, rollback.sh            copies à jour des scripts, rafraîchies à chaque déploiement
└── contenu de public/                recopié à chaque déploiement, index.php pointant vers current/
```

### 3.3 Déroulé de `deploy-shared-hosting.sh`

Valeurs par défaut, toutes surchargeables par variable d'environnement : `APP_DIR=$HOME/presence.fillesdartsbenin.com`, `REPO_URL=git@github.com:Magloire04/cafab_bord.git`, `BRANCH=main`, `HEALTHCHECK_URL=https://presence.fillesdartsbenin.com/up`, `PHP_BIN=php`.

1. **Garde-fous** (avant toute action) :
   - `shared/.env` doit exister, sinon arrêt avec un message qui renvoie au README ;
   - `$PHP_BIN` doit être en version 8.3 ou plus, sinon arrêt avec la version trouvée et l'indication de régler `PHP_BIN` sur le PHP 8.3 de cPanel (par exemple `/opt/cpanel/ea-php83/root/usr/bin/php`).
2. Clone superficiel de la branche dans `releases/<horodatage>`.
3. Liens vers `shared/.env` et `shared/storage`.
4. `composer install --no-dev --optimize-autoloader --no-interaction`, lancé à travers `$PHP_BIN` pour utiliser la bonne version.
5. `key:generate` seulement si `APP_KEY` est absente de `shared/.env`.
6. `migrate --force` avant la bascule : l'ancienne version sert encore. Si une migration échoue, par exemple celle des emails sur un doublon, le script s'arrête et le site reste sur l'ancienne version.
7. `npm ci` puis `npm run build`.
8. `config:cache`, `route:cache`, `view:cache`, `storage:link`.
9. Bascule atomique du lien `current`.
10. Recopie de `public/` à la racine. Le nettoyage préalable de la racine épargne `releases`, `shared`, `current`, `current.new`, `deploy.sh`, `rollback.sh`, **ainsi que `.well-known` et `cgi-bin`** (différence avec Caisse : `.well-known` sert à la validation du certificat par cPanel). Adaptation des trois chemins de `index.php` vers `current/`, avec arrêt si un chemin `../` subsiste. Recréation du lien `storage`.
11. Vérification de `HEALTHCHECK_URL`. En cas d'échec, retour immédiat à la version précédente et sortie en erreur.
12. Suppression des versions au-delà des 5 plus récentes.
13. Mise à jour de `deploy.sh` et `rollback.sh` à la racine (copie vers un fichier temporaire puis renommage).
14. **Rappel du cron** (différence avec Caisse) : si `crontab -l` ne contient pas `schedule:run` pour `APP_DIR/current`, le script affiche la ligne exacte à ajouter, sans échouer :
    `* * * * * cd ~/presence.fillesdartsbenin.com/current && php artisan schedule:run >> /dev/null 2>&1`, où `php` est remplacé par la valeur de `PHP_BIN` quand elle est réglée.
15. Ligne dans `shared/deploy.log` : date, version, commit, branche, statut.

### 3.4 `rollback-shared-hosting.sh`

Revient à **la version qui précède celle vers laquelle pointe `current`**, dans l'ordre des noms de dossier (horodatages) de `releases/` (différence avec Caisse, qui prend la deuxième plus récente par date de modification : deux retours arrière de suite doivent reculer de deux versions). S'il n'y en a pas, arrêt avec un message. Bascule du lien, vérification de `HEALTHCHECK_URL`, et rappel que le schéma de la base n'est jamais modifié : une migration incompatible demande un `migrate:rollback` réfléchi, à la main.

### 3.5 `bin/deploy`

Variables, définies dans le shell d'Elisée et jamais versionnées : `PRESENCE_DEPLOY_HOST` (obligatoire, `utilisateur@hote`), `PRESENCE_DEPLOY_PORT` (défaut 22), `PRESENCE_DEPLOY_KEY` (clé privée, facultative), `PRESENCE_DEPLOY_APP_DIR` (défaut `presence.fillesdartsbenin.com`), `PRESENCE_DEPLOY_PHP_BIN` (facultative, transmise comme `PHP_BIN`).

Usage : `bin/deploy` (branche `main`), `bin/deploy <branche>`, `bin/deploy rollback`. Connexion SSH non interactive (`BatchMode=yes`, délai de 10 s).

Les fins de ligne Unix sont déjà imposées à tout le dépôt par `.gitattributes` (`* text=auto eol=lf`) : rien à ajouter.

## 4. Configuration de production et mise en ligne

### 4.1 Modèle `.env.production.example`

Nouveau fichier versionné, sans aucun secret, copié en `shared/.env` sur le serveur :

- `APP_NAME="Présence & Paiements CAFAB"`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://presence.fillesdartsbenin.com`, `APP_KEY=` (générée au premier déploiement) ;
- `APP_TIMEZONE=Africa/Porto-Novo`, `APP_LOCALE=fr`, `APP_FALLBACK_LOCALE=en`, `SEANCES_SYNCHRONISATION_AUTO=true` ;
- `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_PORT=3306`, `DB_DATABASE=`, `DB_USERNAME=`, `DB_PASSWORD=` ;
- `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync` ;
- `LOG_CHANNEL=stack`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=30`, `LOG_LEVEL=warning` (journaux gardés 30 jours : ils contiennent des adresses IP) ;
- `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtps`, `MAIL_HOST=mail.fillesdartsbenin.com`, `MAIL_PORT=465`, `MAIL_USERNAME=noreply@fillesdartsbenin.com`, `MAIL_PASSWORD=`, `MAIL_FROM_ADDRESS=noreply@fillesdartsbenin.com`, `MAIL_FROM_NAME="${APP_NAME}"` (serveur et port à confirmer dans cPanel, « Comptes de messagerie », « Connecter les appareils ») ;
- `CAISSE_CAFAB_API_URL=https://caisse.fillesdartsbenin.com`, `CAISSE_CAFAB_API_TOKEN=` (même valeur que `PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN` côté Caisse).

Chaque bloc porte un commentaire d'une ligne qui dit d'où vient la valeur à remplir.

### 4.2 Ordre de mise en ligne (écrit dans le README)

1. **Caisse CAFAB d'abord** : générer un jeton (`openssl rand -hex 32`), l'ajouter comme `PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN` dans son `shared/.env`, puis la redéployer depuis `main` avec son propre `bin/deploy` (sa migration `external_reference` passe à cette occasion).
2. **cPanel** : sous-domaine `presence.fillesdartsbenin.com` avec pour racine `~/presence.fillesdartsbenin.com`, base MySQL et utilisateur dédiés (jamais la base de Caisse), boîte `noreply@fillesdartsbenin.com`, certificat AutoSSL, PHP 8.3 pour le sous-domaine.
3. **Serveur** : dossiers `releases/` et `shared/storage/…`, `shared/.env` depuis le modèle, clé de déploiement GitHub en lecture seule pour `cafab_bord` si le dépôt est privé.
4. **Publication** : PR de `develop` vers `main`, fusionnée par Elisée, puis `bin/deploy`.
5. **Cron** : la ligne affichée par le script, ajoutée dans « Tâches Cron » de cPanel.
6. **Premier admin** : `cd ~/presence.fillesdartsbenin.com/current && php artisan users:create "Nom" email --role=admin`, le mot de passe provisoire s'affiche une fois.
7. **Vérifications** : connexion, email « mot de passe oublié », kiosque sur la tablette, puis la liaison avec Caisse sur le premier vrai cachet validé (pas de cachet de test, pour ne pas créer de fausse dépense dans la comptabilité).

## 5. Kiosque exposé sur internet

### 5.1 Blocage après des codes faux

Constat : `/kiosque` est public, le code fait 4 chiffres et la seule protection est `throttle:20,1` sur `kiosque.identifier`. Avec une quarantaine de filles, un inconnu trouve un code valide en 15 minutes environ.

Exigences :

- Chaque code faux (inconnu, ou format refusé par la validation `size:4`) compte pour l'adresse IP de la requête (`$request->ip()`).
- Au 10e code faux dans une fenêtre de 15 minutes, l'identification est bloquée pour cette adresse pendant 15 minutes à partir de ce 10e code.
- Pendant le blocage, **tout** code est refusé, y compris un bon code, et rien n'est mis en session.
- Le kiosque revient à l'écran du code avec : « Trop de codes erronés. Réessaie dans N minutes, ou demande à ton coach de te pointer. » N est arrondi à la minute supérieure, et vaut au moins 1.
- Un bon code ne compte pas et n'efface pas le compteur.
- Au début de chaque blocage, une ligne `warning` est écrite dans le journal avec l'adresse IP, jamais le code tapé.
- La limite `throttle:20,1` reste en place.
- Les compteurs utilisent le cache de l'application (`CACHE_STORE=database` en production).
- Si un proxy ou un CDN est ajouté un jour devant le site, il faudra déclarer les proxies de confiance pour que `$request->ip()` reste l'adresse du client. Aujourd'hui il n'y en a pas.

Effet attendu : depuis une adresse, au plus 40 codes faux par heure, soit environ 6 heures en moyenne pour tomber sur un code valide. Si la tablette se bloque à l'arrivée des filles, le coach les pointe depuis « Ma répétition ».

### 5.2 Kiosque : page expirée et trop d'essais

- Un envoi du kiosque dont le jeton CSRF a expiré (tablette en veille plusieurs heures, erreur 419) ramène à `kiosque.home` avec : « La page avait expiré. Tape à nouveau ton code. »
- Un envoi refusé par `throttle:20,1` (erreur 429) ramène à `kiosque.home` avec : « Trop d'essais en peu de temps. Attends une minute puis réessaie. »
- Ces deux cas ne concernent que les routes `kiosque/*`. Ailleurs, ce sont les pages d'erreur ci-dessous.

## 6. Pages d'erreur en français

En production (`APP_DEBUG=false`), Laravel affiche ses pages d'erreur anglaises, puisque l'application n'en a pas. Exigences :

- Des vues `resources/views/errors/{403,404,419,429,500,503}.blade.php` sur une mise en page commune, au style de l'écran de connexion (logo CAFAB, carte centrée), sans horloge ni composant qui interroge la base : une page d'erreur doit pouvoir s'afficher même quand la base ne répond pas.
- Textes (titre puis phrase) :
  - 403 « Accès refusé » : « Vous n'avez pas les droits pour ouvrir cette page. »
  - 404 « Page introuvable » : « Cette adresse ne correspond à aucune page. »
  - 419 « Page expirée » : « La page est restée ouverte trop longtemps. Rechargez-la puis recommencez. »
  - 429 « Trop de tentatives » : « Patientez un peu avant de réessayer. »
  - 500 « Erreur inattendue » : « Un problème est survenu de notre côté. Réessayez dans un instant. »
  - 503 « Maintenance en cours » : « L'application revient dans quelques minutes. »
- Un bouton « Retour à l'accueil » vers `/`, sauf sur la 503.
- Aucune trace technique ni message d'exception affiché.

## 7. Divers

- `composer.lock` est remis à jour (`composer update --lock`) : il est en retard sur le nom et la description du projet dans `composer.json`, sans aucune bibliothèque manquante, et Composer l'affiche en avertissement à chaque installation.
- README : la section « Déploiement » (scripts, variables, ordre de mise en ligne du § 4.2), et la section « Tâches planifiées » donne la ligne cron de cPanel.

## 8. Tests

- Blocage du kiosque (Pest) :
  - 9 codes faux puis un bon code : identification réussie ;
  - 10 codes faux puis un bon code : refusé, message avec le nombre de minutes, rien en session ;
  - 15 minutes après le 10e code faux : un bon code passe ;
  - des bons codes glissés entre des codes faux ne remettent pas le compteur à zéro ;
  - une autre adresse IP n'est pas bloquée ;
  - la ligne `warning` est écrite une fois, avec l'adresse et sans le code ;
  - le test existant du `throttle:20,1` reste vert.
- Kiosque 419 et 429 : redirection vers `kiosque.home` avec le bon message ; une route hors kiosque garde sa page d'erreur.
- Pages d'erreur : chaque code rend sa vue en français, avec le logo et sans trace technique ; la 503 n'a pas de bouton.
- Scripts : `bash -n` sur les trois scripts, `shellcheck` s'il est installé, relecture ligne par ligne face aux scripts de Caisse CAFAB. Pas de répétition réelle sur le serveur dans cet incrément.
- Suite complète verte sous SQLite et sous un MySQL 8.4 jetable, `vendor/bin/pint --test` propre, `npm run build` sans erreur.

## 9. Hors périmètre

- La mise à jour des documents TECHNUM (règles métier, spécifications), qui parlent encore d'un seuil de 15 minutes et du « retard fort ».
- Un appareil autorisé pour le kiosque (écarté).
- Les sauvegardes de la base de production : cPanel fournit ses propres sauvegardes, et le script `backup-db.sh` de Caisse vise un VPS. À traiter à part si besoin.
- Les autres statuts affichés bruts (« actif », « annulee ») dans les registres et la liste des prestations.
