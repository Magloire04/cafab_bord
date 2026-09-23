# Présence & Paiements CAFAB

Application de gestion de la ponctualité aux répétitions et du suivi des cachets de prestation pour l'association **Filles d'Arts Bénin (CAFAB)**.

Deux besoins de suivi, jusqu'ici gérés à l'œil : savoir qui arrive à l'heure aux répétitions (coachs comme filles), et savoir, prestation par prestation et fille par fille, qui a été payée et qui reste à payer. L'application s'intègre avec **Caisse CAFAB**, l'outil de gestion de caisse existant de l'association, pour y créer automatiquement la dépense correspondant à chaque cachet validé.

## Sommaire

- [Contexte](#contexte)
- [Fonctionnalités](#fonctionnalités)
- [Rôles et accès](#rôles-et-accès)
- [Stack technique](#stack-technique)
- [Architecture](#architecture)
- [Intégration Caisse CAFAB](#intégration-caisse-cafab)
- [Installation](#installation)
- [Configuration](#configuration)
- [Comptes utilisateurs](#comptes-utilisateurs)
- [Tâches planifiées](#tâches-planifiées)
- [Tests](#tests)
- [Structure du projet](#structure-du-projet)
- [Documentation](#documentation)
- [Statut du projet](#statut-du-projet)

## Contexte

CAFAB organise des répétitions régulières encadrées par des coachs, et fait ponctuellement participer ses filles à des prestations rémunérées (spectacles, événements). **Présence & Paiements CAFAB** est un second outil, distinct de **Caisse CAFAB**, dédié à deux usages :

- Objectiver la ponctualité des coachs et des filles aux répétitions.
- Suivre l'état de paiement des cachets dus aux filles lors des prestations, avec une visibilité chiffrée sur cette dépense.

Caisse CAFAB continue de couvrir les entrées et dépenses générales de l'association ; ce projet ne la remplace pas, il lui apporte une source de dépenses précise sur le poste des cachets de prestation, via une intégration API.

**Hors périmètre :** le paiement lui-même (remise physique de l'argent à la fille) reste manuel et se passe en dehors de l'outil — celui-ci n'enregistre que la déclaration et la validation du paiement, pas son exécution. La rémunération des coachs n'est pas couverte : le projet porte sur leur ponctualité, pas sur leur paie.

## Fonctionnalités

### Registre des coachs et des filles

- Liste et fiche par personne (nom, prénom, contact, code PIN, statut, historique de présence et de paiements pour une fille).
- Ajout manuel ou import Excel en masse (avec aperçu et signalement des doublons avant validation).
- Désactivation plutôt que suppression : l'historique reste consultable, la personne disparaît des listes actives de pointage.
- Code PIN à 4 chiffres généré automatiquement à la création, régénérable par l'Admin.

### Planning et pointage de présence

- Planning récurrent (jour, heure, coach référent) avec génération automatique des séances à venir.
- Séances extraordinaires, créées ponctuellement par l'Admin ou le coach référent.
- **Mode kiosque** : écran plein écran sans connexion, identification par code PIN, auto-pointage de présence.
- Pointage de groupe par le coach, pour les personnes qui n'ont pas encore pointé elles-mêmes — règle de non-conflit : le premier pointage enregistré fait foi, jamais écrasé.
- Calcul automatique de la ponctualité (à l'heure / en retard / en retard fort au-delà de 15 minutes / absent) et clôture automatique des séances oubliées.
- Calendrier admin (taux de présence par séance) et historique filtrable, avec correction d'un pointage par l'Admin sur motif obligatoire.

### Prestations et cachets

- Création d'une prestation (titre, lieu, date, montant par défaut) et affectation des filles participantes, individuellement ou en groupe, avec montant ajustable par fille.
- Déclaration du cachet par la fille elle-même, en mode kiosque, une fois la prestation passée.
- Validation ou correction par l'Admin (motif obligatoire pour toute correction) — un cachet devient immuable une fois validé ou annulé.
- Annulation d'une prestation : les cachets non finalisés passent à *annulé* plutôt que d'être supprimés ; un cachet déjà validé n'est jamais touché.

### Rapports

- **Ponctualité** : présences, retards (avec retard cumulé) et absences, par personne ou pour tout le groupe, sur une période donnée.
- **Dépenses de prestations** : cachets validés par période, détail par prestation et par fille, avec la référence Caisse CAFAB pour rapprochement.
- Export Excel des deux rapports, à la demande.

## Rôles et accès

| Rôle | Connexion | Peut faire |
|---|---|---|
| **Admin** | Identifiant + mot de passe | Registre, planning, prestations, paiements, rapports, correction de tout pointage ou cachet |
| **Coach** | Identifiant + mot de passe | Pointage de sa propre répétition, appel de groupe, création de séance extraordinaire, son propre historique |
| **Fille** | Aucune — code PIN sur l'appareil du mode kiosque | Pointage de sa présence, déclaration de la réception d'un cachet |

## Stack technique

- **PHP 8.3+**, **Laravel 12**
- **Blade** + **Alpine.js** (pas de framework front lourd)
- **SQLite** en développement, **MySQL** en production
- **Pest** pour les tests, **Laravel Pint** pour le formatage
- **maatwebsite/excel** pour l'import des filles et l'export des rapports

## Architecture

Application Laravel indépendante de Caisse CAFAB, avec sa propre base de données. Deux familles d'écrans :

- des écrans classiques, avec connexion, pour l'Admin et les Coachs ;
- un **mode kiosque** (`/kiosque`) : sans authentification, laissé sur une tablette ou un ordinateur partagé, où une fille (ou un coach) s'identifie par son code PIN pour pointer sa présence ou déclarer un paiement.

Les règles métier sensibles sont centralisées dans des services dédiés, jamais réimplémentées par point d'entrée :

- `PointageService` — seuil de ponctualité et règle de non-conflit entre auto-pointage et pointage par le coach.
- `CachetService` — cycle de vie d'un cachet (déclaration, validation, correction, ajustement de montant) et déclenchement de l'intégration Caisse CAFAB.
- `SeanceGenerator` — génération des séances à partir du planning récurrent.
- `PonctualiteRapportService` — agrégation du rapport de ponctualité, consommée à l'identique par l'écran et par l'export Excel.

## Intégration Caisse CAFAB

Quand l'Admin valide un cachet comme payé, l'application appelle un point d'entrée dédié de **Caisse CAFAB** (`POST /api/operations`) pour y créer la dépense correspondante :

- Authentification par un **jeton de service** dédié à cette intégration, distinct de tout compte utilisateur (`CAISSE_CAFAB_API_TOKEN`).
- Appel **idempotent** : une référence externe stable (`cachet-{id}`) garantit qu'un même cachet ne peut jamais déclencher la création de deux dépenses, y compris en cas de nouvel essai après un échec réseau.
- En cas d'échec de l'appel, la validation locale reste effective (jamais annulée) ; l'erreur est journalisée et un bouton **Réessayer** est proposé, sans jamais dupliquer la dépense déjà créée avec succès.

L'application ne touche jamais directement la base de données de Caisse CAFAB.

## Installation

Prérequis : PHP 8.3+, Composer, Node.js/npm.

```bash
git clone https://github.com/Magloire04/cafab_bord.git presence-paiement-cafab
cd presence-paiement-cafab

composer install
npm install

cp .env.example .env
php artisan key:generate

# Base de données (SQLite par défaut en dev)
touch database/database.sqlite
php artisan migrate

npm run build   # ou npm run dev en développement
php artisan serve
```

## Configuration

Les variables principales de `.env` :

| Variable | Rôle |
|---|---|
| `DB_CONNECTION` | `sqlite` en local (WAMP), `mysql` en production (bloc dédié dans `.env.example`) |
| `APP_TIMEZONE` | `Africa/Porto-Novo` |
| `APP_LOCALE` | `fr` |
| `CAISSE_CAFAB_API_URL` | URL de base de l'API Caisse CAFAB |
| `CAISSE_CAFAB_API_TOKEN` | Jeton de service — doit correspondre au `PRESENCE_PAIEMENT_CAFAB_SERVICE_TOKEN` configuré côté Caisse CAFAB |

En production, le jeton de service et l'URL de l'API sont à configurer avant le premier déploiement : sans eux, toute validation de cachet échoue à créer la dépense (l'échec est journalisé, la validation locale reste effective, et un nouvel essai reste possible depuis l'écran).

## Comptes utilisateurs

Il n'y a pas d'écran d'inscription — les comptes Admin et Coach se créent en ligne de commande, avec un mot de passe généré affiché une seule fois :

```bash
php artisan users:create "Nom Complet" email@exemple.com --role=admin
php artisan users:create "Nom du Coach" coach@exemple.com --role=coach
```

Les codes PIN des coachs et des filles (mode kiosque) sont générés automatiquement à la création de leur fiche dans le registre, et régénérables par l'Admin.

## Tâches planifiées

Le cycle de vie des séances dépend du planificateur Laravel — à exécuter en continu en production (cron `* * * * * php artisan schedule:run`, ou `php artisan schedule:work` en développement) :

| Commande | Fréquence | Rôle |
|---|---|---|
| `seances:generer` | Quotidienne | Génère les séances à venir depuis le planning récurrent actif |
| `seances:demarrer` | Chaque minute | Passe une séance `à_venir` en `en_cours` à l'heure prévue |
| `seances:cloturer` | Chaque minute | Clôture automatiquement une séance oubliée par un coach ou jamais démarrée |

## Tests

```bash
vendor/bin/pest          # suite complète
vendor/bin/pint --test   # vérification du formatage
```

## Structure du projet

```text
app/
├── Enums/                  Statuts métier (StatutSeance, StatutCachet, StatutPonctualite, ...)
├── Exceptions/              Exceptions métier (PointageException, CachetException, ...)
├── Exports/                 Exports Excel des rapports
├── Http/Controllers/
│   ├── Admin/                Registre, planning, prestations, paiements, rapports
│   ├── Coach/                 Pointage et historique du coach
│   └── Kiosque/                Identification PIN, pointage et déclaration sans connexion
├── Models/                  Coach, Fille, Seance, Pointage, Prestation, Cachet, ...
└── Services/                 Logique métier centralisée (PointageService, CachetService, ...)

docs/superpowers/plans/     Plans d'implémentation détaillés, un par incrément
documentations/             Spécifications fonctionnelles et règles métier (CAFAB)
```

## Documentation

Les documents de cadrage (note de cadrage, spécifications fonctionnelles, règles métier) se trouvent dans `documentations/presence-paiement-cafab/` au niveau du dépôt parent. Chaque incrément fonctionnel a son plan d'implémentation détaillé dans `docs/superpowers/plans/` :

1. `2026-09-22-fondations.md` — rôles, registre, sécurité
2. `2026-09-22-planning-pointage.md` — planning, séances, pointage, kiosque
3. `2026-09-23-prestations-paiements.md` — prestations, cachets, intégration Caisse CAFAB
4. `2026-09-23-rapports.md` — rapports de ponctualité et de dépenses

## Statut du projet

Les quatre incréments prévus par la note de cadrage sont fusionnés dans `develop`. Chaque incrément est passé par une revue par tâche puis une revue finale de branche entière avant fusion.

Points encore ouverts, signalés comme hypothèses de travail dans les documents de cadrage :

- Format exact du code personnel (PIN à 4 chiffres retenu comme hypothèse de travail ; QR code envisagé en évolution ultérieure).
- Fréquence et destinataires des rapports : la version actuelle est à la demande uniquement (pas d'envoi programmé).
