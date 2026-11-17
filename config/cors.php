<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

   'allowed_origins' => [
    'http://localhost:4200',
    'http://127.0.0.1:4200',
    'https://brewlink-cafe.netlify.app',
],

    // Allows testing from a phone on the same Wi-Fi (e.g. http://192.168.1.10:4200) for the table QR codes
    'allowed_origins_patterns' => ['#^http://(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+)(:\d+)?$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
