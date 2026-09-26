# Corrections du 26 septembre 2026 : spec de conception

Incrément 006 de Présence & Paiements CAFAB. Source : `documentations/presence-paiement-cafab/corrections.txt` (19 points remontés par Elisée après ses tests locaux de la refonte visuelle). Chaque exigence ci-dessous renvoie au numéro du point d'origine.

## 1. Intention et critères de réussite

L'application a été testée à la main pour la première fois après la refonte visuelle. Les tests ont révélé trois familles de problèmes : des fonctions cassées ou invisibles (œil du login, séances introuvables, pages sans lien dans le menu), des règles métier à ajuster (retard, droits du coach, mot de passe provisoire), et des finitions d'interface.

L'incrément est réussi quand :

- chacun des 19 points est traité et vérifiable dans le navigateur ;
- une séance programmée apparaît aussitôt chez son coach et au kiosque, et démarre ou se clôture à l'heure même si le planificateur n'a pas été lancé ;
- un coach peut gérer les filles et ses propres créneaux, sans pouvoir sortir de ces droits, y compris en tapant une URL à la main ;
- un compte peut toujours être récupéré, par email ou par l'admin ;
- la suite de tests reste verte, avec un test pour chaque règle nouvelle ou corrigée.

## 2. Décisions prises avec Elisée

| Sujet | Décision |
|---|---|
| Mot de passe oublié (#3) | Lien par email en français et réinitialisation par l'admin en secours |
| Filles et coach (#15) | Le coach peut tout faire sur les filles sauf désactiver une fiche |
| Planning et coach (#11) | Le coach voit tout le planning, mais ne crée, modifie et désactive que ses propres créneaux |
| Notifications (#12) | Bandeau dans l'app mis à jour en continu et notification du navigateur au démarrage d'une séance |
| Retard (#14, #17) | Tolérance de 10 minutes incluses. Au-delà, « En retard », minutes comptées depuis l'heure prévue (12:19 pour 12:06 donne 13 min) |
| Cycle de vie des séances | Synchronisation à la demande (approche 1A) |
| Droits du coach | Pages partagées entre rôles, règles d'autorisation par policies (approche 2A) |

Hypothèses validées : l'application n'est pas en production, donc les pointages existants peuvent être recalculés ; le menu « … » s'applique aussi aux tableaux Filles et Planning ; l'ensemble part en une seule branche `feature/006-corrections` et une PR.

## 3. Temps réel et séances

### 3.1 Cycle de vie des séances (#9, #12)

Constat : une séance ne naît que par `seances:generer` (tâche quotidienne) et ne change d'état que par `seances:demarrer` et `seances:cloturer` (chaque minute). Sans planificateur, rien ne bouge. En plus, `PlanningController::store()` n'appelle pas le générateur, contrairement à `update()` et `toggleActif()` : un créneau tout juste créé n'a aucune séance avant la nuit suivante. C'est la cause du point #9 (vérifié sur la base locale : créneau « lundi 17:00, Coach Test » créé le 26/09 à 10:37, aucune séance associée).

Exigences :

- Un service `SeanceCycleDeVie` expose `synchroniser()`, qui enchaîne dans cet ordre : génération des séances des 14 prochains jours (logique actuelle de `SeanceGenerator`), démarrage des séances du jour dont l'heure est arrivée (logique de `seances:demarrer`), clôture des séances dont la date est passée (logique de `seances:cloturer`, qui passe par `Seance::clore()` et matérialise les absences).
- Les trois commandes artisan existantes restent, et délèguent au service. Le planificateur (`routes/console.php`) est inchangé.
- Un middleware `SynchroniserSeances` appelle `synchroniser()` sur les requêtes web authentifiées et sur le kiosque, au plus une fois par minute, grâce à un verrou en cache (`Cache::add` avec une durée de 60 s). Il est piloté par une option de configuration `seances.synchronisation_auto`, vraie par défaut et fausse dans `phpunit.xml` : sans cela, chaque requête des tests existants déclencherait génération et clôture et fausserait leurs scénarios. Les tests du middleware et du service activent l'option explicitement.
- Créer un créneau génère immédiatement ses séances à venir, comme le font déjà la modification et la réactivation.
- « Ma répétition » (coach) garde sa logique : la séance en cours du jour, sinon la prochaine séance à venir à partir d'aujourd'hui (les séances « à venir » dont la date est passée sont exclues, ce que la clôture automatique rend de toute façon rare).

### 3.2 Bandeau de séance et notifications (#12)

- Une route JSON `GET /etat-seances` (utilisateurs connectés, admin et coach) renvoie les séances en cours (date, heure prévue, coach, nombre de filles présentes, nombre de filles actives) et la prochaine séance à venir dans les 14 jours. L'admin reçoit toutes les séances, le coach seulement les siennes.
- Un bandeau en haut du contenu des écrans admin et coach affiche, selon l'état :
  - « Répétition en cours depuis 17:00 · 8 présentes sur 12 » (une ligne par séance en cours, avec le nom du coach pour l'admin) ;
  - sinon « Prochaine répétition : lundi 28 septembre à 17:00 » ;
  - sinon rien.
- Le bandeau interroge `/etat-seances` toutes les 30 secondes, sans recharger la page.
- Un bouton « Activer les notifications » dans le bandeau appelle `Notification.requestPermission()` (clic obligatoire, exigence des navigateurs). Il disparaît une fois la permission accordée ou refusée.
- Quand une séance apparaît « en cours » pour la première fois, le navigateur affiche une notification système (« Répétition en cours · 17:00 · Coach Test »). Les identifiants déjà notifiés sont mémorisés dans `localStorage` pour ne jamais notifier deux fois la même séance, même avec plusieurs onglets.
- Limite assumée : les notifications exigent un onglet de l'app ouvert et un contexte sécurisé (`127.0.0.1` en local, HTTPS en production). Le bandeau fonctionne dans tous les cas.

### 3.3 Kiosque (#12, #16)

- L'accueil du kiosque affiche « Prochaine répétition : lundi 28 septembre à 17:00 » quand aucune séance n'est en cours.
- Il interroge une route publique `GET /kiosque/etat` (aucune donnée personnelle : identifiant de la séance en cours, heure prévue, prochaine séance) toutes les 30 secondes, et se recharge seul quand la séance en cours change. Un kiosque posé à l'entrée passe donc tout seul à « Séance en cours » à l'heure prévue.
- Le bloc « Tape ton code » (titre, sous-titre, cases du code, pavé) est centré horizontalement dans la page (#16).

### 3.4 Horloge en temps réel (#13)

- Format : jour, date, heure à la seconde, en français, par exemple « samedi 26 septembre 2026 · 12:27:48 ».
- Emplacements : sous le logo CAFAB de l'écran de connexion (et des autres écrans d'authentification qui partagent sa mise en page), sous le logo de la sidebar admin et coach, dans la barre du haut du kiosque (elle remplace l'horloge HH:MM actuelle).
- L'heure affichée est celle du serveur dans le fuseau `config('app.timezone')` (Africa/Porto-Novo), pas celle du PC : la page reçoit l'heure serveur au chargement, le script calcule l'écart avec l'horloge locale et avance chaque seconde. L'horloge ne peut donc pas contredire le calcul des retards.
- Implémentation dans un module JS chargé par Vite (pas de script en ligne, CSP oblige).

### 3.5 Ponctualité (#14, #17)

- Le statut « Retard fort » disparaît. Il reste « À l'heure », « En retard » et « Absent » (posé à la clôture).
- Règle : écart en minutes entières (arrondi à la minute inférieure, comme aujourd'hui) entre l'arrivée et l'heure prévue. Écart ≤ 10 : « À l'heure », `minutes_retard` = 0. Écart > 10 : « En retard », `minutes_retard` = écart complet depuis l'heure prévue.
- La tolérance est une constante unique (`PointageService::TOLERANCE_MINUTES = 10`) ; l'ancienne `SEUIL_RETARD_FORT_MINUTES` disparaît.
- Une migration de données :
  - pointages non corrigés par l'admin (`motif_correction` vide) et ayant une heure d'arrivée : statut et minutes recalculés avec la nouvelle règle ;
  - pointages corrigés par l'admin : on respecte la correction, seul `retard_fort` devient `en_retard` (minutes conservées) ;
  - absences : inchangées.
  La migration n'a pas de retour arrière métier (le `down()` ne recrée pas les anciens statuts), ce qui est acceptable hors production.
- Alignement : enum `StatutPonctualite` (suppression du cas), formulaire de correction admin, composant `badge-ponctualite`, vues coach, `PonctualiteRapportService`, tests existants.

## 4. Comptes, mots de passe et droits

### 4.1 Connexion (#1, #2)

- L'œil « afficher le mot de passe » cible son champ par un attribut explicite (`data-toggle-password="id-du-champ"`), au lieu de chercher un conteneur `.input-group` qui n'existe plus depuis la refonte. Il est présent sur tous les champs mot de passe : connexion, profil, réinitialisation, changement obligatoire, confirmation, formulaire coach.
- « Se souvenir de moi » est déjà branché (`Auth::attempt(..., $remember)`). Un test prouve : cookie de rappel posé quand la case est cochée, absent sinon, reconnexion automatique quand la session a disparu, cookie supprimé à la déconnexion.

### 4.2 Mot de passe provisoire et changement obligatoire (#18)

- Nouvelle colonne `users.must_change_password` (booléen, faux par défaut).
- Un service `GenerateurMotDePasse` produit des mots de passe de 10 caractères avec au moins une majuscule, une minuscule, un chiffre et un caractère spécial, via `random_int` (aléa cryptographique).
- « Ajouter un coach » gagne un champ « Mot de passe » pré-rempli par le serveur, avec deux boutons : « Générer » (nouveau tirage côté navigateur via `crypto.getRandomValues`, même règle) et « Copier ». L'admin peut aussi saisir le sien, validé par la même règle que le générateur (10 caractères minimum, majuscule, minuscule, chiffre, caractère spécial).
- Après enregistrement, la liste des coachs affiche une seule fois un encadré « Identifiants à transmettre » (email et mot de passe, bouton Copier). Le mot de passe ne réapparaît jamais ensuite.
- Le compte est créé avec `must_change_password = true`. Même chose pour `php artisan users:create`, qui continue d'afficher le mot de passe généré une fois.
- Un middleware `ExigerChangementMotDePasse`, appliqué à toutes les routes authentifiées, redirige vers `GET /mot-de-passe/changer` tant que le drapeau est vrai. Seules cette page, sa soumission et la déconnexion restent accessibles.
- L'écran « Choisis ton mot de passe » demande le nouveau mot de passe et sa confirmation (pas l'ancien : il vient d'être saisi à la connexion). Après validation : drapeau remis à faux, autres sessions fermées (§4.4), redirection vers le tableau de bord avec un message de confirmation.

### 4.3 Mot de passe oublié (#3)

Voie email :

- Le formulaire « Mot de passe oublié » envoie le lien Laravel standard, avec un email entièrement en français (traductions, §4.6).
- Anti-énumération : le message affiché après envoi est le même que l'adresse existe, n'existe pas, ou soit déjà en attente d'un lien (« Si un compte correspond à cette adresse, un lien de réinitialisation vient d'être envoyé. »).
- L'écran précise qu'en cas de problème il faut contacter l'administrateur.
- Définir un nouveau mot de passe par le lien remet `must_change_password` à faux et ferme les autres sessions.
- En local, `MAIL_MAILER=log` : l'email est écrit dans `storage/logs/laravel.log`. `.env.example` documente les variables SMTP à renseigner en production et remplace l'expéditeur `hello@example.com` par une valeur à configurer, commentée.

Voie admin :

- Le menu « … » d'un coach propose « Réinitialiser le mot de passe » (avec confirmation). L'action génère un mot de passe provisoire, met `must_change_password` à vrai, ferme toutes les sessions du coach et affiche les identifiants une seule fois, comme à la création.
- Nouvelle commande `php artisan users:reset-password {email}` pour tout compte, admin compris : même effet, mot de passe affiché une fois dans le terminal.

### 4.4 Profil, email et mot de passe (#8, #19)

- Règle commune à tout mot de passe choisi par un utilisateur (profil, réinitialisation, changement obligatoire) : 8 caractères minimum, majuscules, minuscules et chiffres. Définie une fois via `Password::defaults()` dans `AppServiceProvider`. Le mot de passe provisoire (§4.2) la respecte.
- Section « Mot de passe » du profil : mot de passe actuel, nouveau, confirmation. Tests : succès, mot de passe actuel faux, confirmation différente, règle non respectée.
- Changer de mot de passe, quelle que soit la voie : les autres sessions de l'utilisateur sont supprimées (table `sessions`, pilote `database`), le jeton « se souvenir de moi » est renouvelé pour invalider les autres navigateurs, et la session courante reste connectée.
- Section « Informations du profil » : changer d'adresse email exige de saisir son mot de passe actuel (champ affiché avec l'email). Unicité de l'email vérifiée, email mis en minuscules. Changer uniquement le nom ne demande rien de plus.
- Tout le profil est traduit en français (titres, boutons, messages « Enregistré »).

### 4.5 Modifier un coach (#6)

- Le formulaire « Modifier » couvre nom, email, contact et date d'entrée. L'email est unique (en ignorant le compte du coach modifié).
- Le PIN, le statut et le mot de passe restent des actions du menu « … ».
- L'admin qui change l'email d'un coach n'a pas besoin de mot de passe ; le coach se connecte ensuite avec le nouvel email.

### 4.6 Traductions françaises

- Ajout de `lang/fr/auth.php`, `lang/fr/passwords.php`, `lang/fr/validation.php`, `lang/fr/pagination.php` et `lang/fr.json` (textes de l'email de réinitialisation et des gabarits d'email Laravel). Les messages de validation passent en français dans toute l'app (« Le champ email est obligatoire. »), avec les noms de champs traduits (`attributes`).

### 4.7 Droits du coach (#10, #11, #15)

- Les écrans Filles (liste, ajout, modification, régénération du PIN, import Excel) et Planning récurrent (liste, ajout, modification, activation) deviennent communs aux deux rôles. Ils quittent le préfixe `/admin` pour `/registre/filles` et `/planning`, avec les noms de routes `filles.*` et `plannings.*`. Les routes Coachs restent sous `/admin` (admin seulement).
- `FillePolicy` : admin, tout ; coach, tout sauf `toggleStatut` (désactiver ou réactiver).
- `PlanningRepetitionPolicy` : admin, tout ; coach, lecture de tous les créneaux, création pour lui-même, modification et activation de ses propres créneaux seulement. À la création ou modification par un coach, `coach_id` est forcé au coach connecté, même si le formulaire envoie autre chose (même principe que la séance extraordinaire).
- Toute action refusée par une policy renvoie 403. Les boutons correspondants ne s'affichent pas.
- La séance extraordinaire, déjà autorisée aux deux rôles côté serveur, devient visible pour les deux dans la section Planning (#10).

### 4.8 Navigation

- Sidebar admin : Tableau de bord, Registre, Planning, Prestations & cachets, Rapports, Mon profil.
- Sidebar coach : Tableau de bord, Ma répétition, Mon historique, Registre, Planning, Mon profil.
- Onglets internes, dans un composant Blade réutilisable, sous l'en-tête de page :
  - Registre : Coachs (admin seulement), Filles ;
  - Planning : Planning récurrent, Calendrier (admin), Pointages (admin), Séance extraordinaire ;
  - Prestations & cachets : Prestations, Paiements.
- L'entrée « Séance extraordinaire » isolée de la sidebar coach disparaît, remplacée par l'onglet.

## 5. Interface

### 5.1 Tableau de bord admin (#4)

- Suppression des trois cartes Pointages, Paiements et Rapports. Restent le bandeau de séance (§3.2) et la rangée d'indicateurs. Le tableau de bord coach ne change pas.

### 5.2 Menu « … » des tableaux (#5)

- Dans les tableaux Coachs, Filles et Planning, la colonne Actions contient un seul bouton « … » qui ouvre un menu déroulant (dropdown Bootstrap, déjà chargé).
- Actions par tableau, filtrées par rôle et par policy :
  - Coachs : Modifier, Désactiver ou Réactiver, Régénérer le PIN, Réinitialiser le mot de passe ;
  - Filles : Modifier, Désactiver ou Réactiver (admin), Régénérer le PIN ;
  - Planning : Modifier, Désactiver ou Réactiver.
- Les actions qui modifient un état (désactiver, régénérer le PIN, réinitialiser le mot de passe) demandent une confirmation dans une fenêtre modale Bootstrap avant d'envoyer le formulaire.
- Les libellés existants sont conservés à l'identique.

### 5.3 Détail d'une prestation (#7)

- En-tête : titre, lieu, date, pastille de statut en français (Active, Annulée), bouton retour.
- Rangée de quatre indicateurs : total dû, validé payé, reste à payer, déclarations à traiter, calculés sur les cachets de la prestation.
- Tableau « Détail par fille » : Fille, Montant (au format `number_format(..., 0, ',', ' ')` suivi de F, comme ailleurs), Déclaration (composant `badge-cachet`, avec la date de déclaration quand elle existe), Action.
- Action d'un cachet non finalisé : bouton « Valider » vert, et un bouton « … » qui propose « Ajuster le montant » et « Corriger la déclaration » ; chacun ouvre une fenêtre modale avec son formulaire (montant ; statut corrigé et motif obligatoire).
- Cachet validé : mention « Dépense créée dans Caisse CAFAB », ou « Dépense non créée » avec le bouton « Réessayer ».
- Les routes, contrôleurs et règles de `CachetService` ne changent pas.

## 6. Hors périmètre

- Pas d'envoi d'emails autres que la réinitialisation de mot de passe (pas de rappel de répétition par email).
- Pas de notifications quand aucun onglet n'est ouvert (pas de service worker ni de push serveur).
- Pas de vue « Semaine » du calendrier.
- Pas de changement du fonctionnement de Caisse CAFAB.

## 7. Tests

Tests Pest à ajouter ou adapter, au minimum :

- ponctualité : 10 minutes pile à l'heure, 11 minutes en retard avec 11 minutes, cas du #17 (12:06 / 12:07 à l'heure) ; migration de recalcul sur des pointages corrigés et non corrigés ;
- cycle de vie : `synchroniser()` génère, démarre et clôture ; le middleware ne relance pas le service avant 60 s ; créer un créneau génère ses séances ;
- `/etat-seances` : périmètre admin et coach ; `/kiosque/etat` sans donnée personnelle ;
- droits : un coach crée, modifie une fille, régénère son PIN, importe ; reçoit 403 en désactivant ; crée un créneau forcé à son nom ; reçoit 403 en modifiant le créneau d'un autre coach ;
- mot de passe provisoire : généré conforme à la règle ; redirection forcée tant que le drapeau est vrai ; déconnexion toujours possible ; drapeau levé après changement ; réinitialisation par l'admin ; commande `users:reset-password` ;
- mot de passe oublié : même message pour une adresse connue et inconnue ; notification envoyée à l'adresse connue ;
- profil : changement de mot de passe (succès et erreurs) ; changement d'email refusé sans mot de passe actuel ; autres sessions supprimées après changement de mot de passe ;
- « se souvenir de moi » : cookie posé, reconnexion, déconnexion ;
- modification d'un coach : nom et email modifiés, email en double refusé.

Vérification manuelle dans le navigateur à la fin, écran par écran, comme pour la refonte.
