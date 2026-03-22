<?php

declare(strict_types=1);

return [
    'default' => env('CACHE_STORE', 'redis'),
    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
    ],
    'prefix' => env('CACHE_PREFIX', 'ticketing_cache'),
];
