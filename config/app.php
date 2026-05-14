<?php

return [
    'name' => env('APP_NAME', 'Zen Retreats Portal'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Karachi'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store'  => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    // Portal-specific settings
    'currency' => [
        'default' => env('DEFAULT_CURRENCY', 'PKR'),
        'accepted' => explode(',', env('ACCEPTED_CURRENCIES', 'PKR,USD,GBP,EUR,AED')),
        'fx_source' => env('FX_RATE_SOURCE', 'manual'),
    ],
    'reminders' => [
        'quiet_start' => (int) env('REMINDER_QUIET_HOURS_START', 21),
        'quiet_end'   => (int) env('REMINDER_QUIET_HOURS_END', 9),
        'upcoming_days' => [15, 7, 3, 1],
        'overdue_days'  => [1, 7, 15, 30],
    ],
    'branding' => [
        'primary' => '#'.env('APP_PRIMARY_COLOR', '1B3A2F'),
        'accent'  => '#'.env('APP_ACCENT_COLOR', '5B8C7B'),
    ],
];
