<?php

return [
    'default' => 'main',

    'projects' => [
        'main' => [
            'credentials' => env('FIREBASE_CREDENTIALS', base_path('firebase-credentials.json')),
            'auth' => [
                'token_ttl' => null,
            ],
            'database' => [
                'url' => env('FIREBASE_DATABASE_URL'),
            ],
            'dynamic_links' => [
                'default_domain' => env('FIREBASE_DYNAMIC_LINKS_DEFAULT_DOMAIN'),
            ],
            'storage' => [
                'default_bucket' => env('FIREBASE_STORAGE_DEFAULT_BUCKET'),
            ],
            'cache_store' => 'file',
            'logging' => [
                'http_log_channel' => env('FIREBASE_HTTP_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
                'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
            ],
            'http_client_options' => [
                'proxy' => env('FIREBASE_HTTP_CLIENT_PROXY'),
                'timeout' => env('FIREBASE_HTTP_CLIENT_TIMEOUT'),
            ],
        ],
    ],
];
