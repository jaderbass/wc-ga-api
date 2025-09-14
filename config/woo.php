<?php

return [
    'sync_enabled' => env('WOO_SYNC_ENABLED', false),
    'default_api_version' => env('WOO_API_VERSION', 'wc/v3'),
    'rate_limit' => [
        'rpm'   => env('WOO_RATE_LIMIT_RPM', 100),
        'burst' => env('WOO_RATE_LIMIT_BURST', 40),
    ],
];
