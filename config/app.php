<?php

return [
    'name' => env_value('APP_NAME', "Let's Smile"),
    'env' => env_value('APP_ENV', 'local'),
    'debug' => filter_var(env_value('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'url' => env_value('APP_URL', 'http://localhost/lets-smile/public'),
    'timezone' => env_value('APP_TIMEZONE', 'Indian/Antananarivo'),
    'locale' => env_value('APP_LOCALE', 'fr'),
    'fallback_locale' => env_value('APP_FALLBACK_LOCALE', 'fr'),
    'currency' => env_value('APP_CURRENCY', 'MGA'),
    'currency_symbol' => 'Ar',
    'session_name' => env_value('SESSION_NAME', 'lets_smile_session'),
];
