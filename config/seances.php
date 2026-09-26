<?php

return [
    /*
    | Synchronise le cycle de vie des séances (génération, démarrage, clôture)
    | au passage des requêtes web, au plus une fois par minute. Désactivé dans
    | les tests (phpunit.xml) pour ne pas modifier les scénarios existants.
    */
    'synchronisation_auto' => env('SEANCES_SYNCHRONISATION_AUTO', true),
];
