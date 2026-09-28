<?php

declare(strict_types=1);

return [

    // Mode observation. Rien n'est écrit, on dit seulement ce qui se passerait.
    // Utile pour une première mise en route en production.
    'dry_run' => (bool) env('DATA_LIFECYCLE_DRY_RUN', false),

    // Nombre maximum de lignes traitées par étape et par règle, à chaque passage.
    'limit' => (int) env('DATA_LIFECYCLE_LIMIT', 1000),

    // Les colonnes lues et écrites par le paquet. Seules celles dont vos règles
    // ont besoin doivent exister : sans rappel, pas de colonne de comptage ;
    // sans anonymisation, pas de date d'anonymisation.
    'fields' => [
        // Date du dernier signe de vie. C'est la seule colonne toujours requise.
        'since' => 'last_active_at',
        // Nombre de rappels déjà envoyés. Requise seulement avec des rappels.
        'warn_stage' => 'lifecycle_warn_stage',
        // Date du dernier rappel envoyé. Informative.
        'warned_at' => 'lifecycle_warned_at',
        // Date de désactivation. Requise seulement avec une période de grâce.
        'disabled_at' => 'disabled_at',
        // Date d'anonymisation. Requise seulement si la fin est une anonymisation.
        'anonymised_at' => 'anonymised_at',
    ],

    // Les valeurs de remplacement au moment de l'anonymisation.
    'anonymiser' => [
        // Domaine des adresses de remplacement. Doit rester invalide.
        'email_domain' => 'anonymous.invalid',
        // Texte mis à la place d'une donnée effacée.
        'redacted_text' => '[removed]',
        // Nom mis à la place d'un nom de personne.
        'anonymous_name' => 'Anonymous',
        // Sel des empreintes. Change de sel = empreintes différentes.
        'pepper' => env('APP_KEY', ''),
    ],

    // Signal d'activité. Nombre de minutes entre deux écritures de la date du
    // dernier signe de vie. Une écriture par requête serait trop coûteuse.
    // Mettre 0 éteint complètement le signal : plus aucune écriture.
    'activity' => [
        'throttle' => 15,
    ],

    // Les règles déclarées ici, en clair. Elles l'emportent sur les attributs.
    'subjects' => [
        // App\Models\User::class => [
        //     'keep_for' => '3 years',
        //     'warn_before' => ['30 days', '7 days'],
        //     'grace' => '30 days',
        //     'anonymise' => ['email' => 'email', 'name' => 'text'],
        // ],
    ],

    // Les classes dont la règle est lue sur leurs attributs PHP.
    // Une classe sans #[KeepFor] est ignorée sans bruit.
    'discover' => [
        // App\Models\User::class,
    ],

];
