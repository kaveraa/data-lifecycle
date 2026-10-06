<?php

declare(strict_types=1);

return [

    // Observe mode. Nothing is written, we only say what would happen.
    // Useful for a first start in production.
    'dry_run' => (bool) env('DATA_LIFECYCLE_DRY_RUN', false),

    // Maximum number of rows handled per step and per policy, on each run.
    'limit' => (int) env('DATA_LIFECYCLE_LIMIT', 1000),

    // The columns the package reads and writes. Only the ones your policies
    // need have to exist: without reminders, no counter column;
    // without anonymisation, no anonymisation date.
    'fields' => [
        // Date of the last sign of life. This is the only column always required.
        'since' => 'last_active_at',
        // Number of reminders already sent. Required only with reminders.
        'warn_stage' => 'lifecycle_warn_stage',
        // Date of the last reminder sent. For information only.
        'warned_at' => 'lifecycle_warned_at',
        // Disable date. Required only with a grace period.
        'disabled_at' => 'disabled_at',
        // Anonymisation date. Required only if the ending is an anonymisation.
        'anonymised_at' => 'anonymised_at',
    ],

    // The replacement values used when anonymising.
    'anonymiser' => [
        // Domain of the replacement email addresses. It must stay invalid.
        'email_domain' => 'anonymous.invalid',
        // Text put in place of an erased value.
        'redacted_text' => '[removed]',
        // Name put in place of a person's name.
        'anonymous_name' => 'Anonymous',
        // Salt of the hashes. A different salt = different hashes.
        'pepper' => env('APP_KEY', ''),
    ],

    // Activity signal. Number of minutes between two writes of the date of the
    // last sign of life. One write per request would cost too much.
    // Set 0 to turn the signal off completely: no write at all.
    'activity' => [
        'throttle' => 15,
    ],

    // The policies declared here, in plain config. They win over the attributes.
    'subjects' => [
        // App\Models\User::class => [
        //     'keep_for' => '3 years',
        //     'warn_before' => ['30 days', '7 days'],
        //     'grace' => '30 days',
        //     'anonymise' => ['email' => 'email', 'name' => 'text'],
        // ],
    ],

    // The classes whose policy is read from their PHP attributes.
    // A class without #[KeepFor] is skipped silently.
    'discover' => [
        // App\Models\User::class,
    ],

];
