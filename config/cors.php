<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('FRONTEND_URLS', 'http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    // false: bearer-token API. Do NOT set true unless SPA cookie auth is introduced.
    'supports_credentials' => false,

];
