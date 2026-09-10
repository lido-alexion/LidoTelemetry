<?php

return [
    'brand_name' => env('TELEMETRY_BRAND_NAME', 'Lido Telemetry'),

    'retention' => [
        'raw_days' => (int) env('TELEMETRY_RAW_RETENTION_DAYS', 90),
        'aggregate_days' => (int) env('TELEMETRY_AGGREGATE_RETENTION_DAYS', 730),
    ],

    'ingestion' => [
        'max_batch_size' => (int) env('TELEMETRY_MAX_BATCH_SIZE', 500),
        'max_metadata_depth' => 8,
        'max_metadata_keys' => 100,
        'max_string_length' => 1024,
        'clock_skew_threshold_seconds' => (int) env('TELEMETRY_CLOCK_SKEW_SECONDS', 300),
        'forbidden_metadata_keys' => [
            'password',
            'passwd',
            'token',
            'secret',
            'api_key',
            'apikey',
            'note',
            'notes',
            'prompt',
            'search_text',
            'search_query',
            'query_text',
            'description',
            'body',
            'request_body',
            'response_body',
            'credential',
            'credentials',
            'authorization',
            'auth_header',
        ],
        'forbidden_metadata_patterns' => [
            '/password/i',
            '/secret/i',
            '/token/i',
            '/credential/i',
            '/\bnote\b/i',
            '/prompt/i',
            '/search[_-]?text/i',
        ],
        'high_cardinality_threshold' => 1000,
    ],

    'query' => [
        'default_limit' => 100,
        'max_limit' => 1000,
        'near_realtime_seconds' => 60,
    ],

    'sdk' => [
        'default_heartbeat_seconds' => 60,
        'default_batch_size' => 0,
    ],

    'roles' => ['admin', 'analyst', 'viewer'],

    'scopes' => [
        'events:read',
        'analytics:read',
        'exports:create',
        'products:manage',
        'credentials:manage',
    ],
];
