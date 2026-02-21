<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allowed origins must be explicit (never '*') when supports_credentials
    | is true, because browsers reject wildcard + credentials combinations.
    | Set CORS_ALLOWED_ORIGINS in .env to a comma-separated list of origins,
    | e.g. "https://admin.example.com,http://localhost:3000".
    |
    */

    // api/* for API routes; sanctum/* for /sanctum/csrf-cookie (SPA cookie auth).
    'paths' => ['api/*', 'sanctum/*'],

    'allowed_methods' => ['*'],

    // Include both common dev origins so cookie-based login works from either port.
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for cross-domain HttpOnly cookie delivery (login/web).
    'supports_credentials' => true,

];
