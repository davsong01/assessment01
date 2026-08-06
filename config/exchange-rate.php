<?php

declare(strict_types=1);

return [
    'endpoint' => env('FX_RATE_ENDPOINT'),

    'timeout_seconds' => (int) env(
        'FX_RATE_TIMEOUT_SECONDS',
        3
    ),

    'fresh_seconds' => (int) env(
        'FX_RATE_FRESH_SECONDS',
        30
    ),

    'stale_seconds' => (int) env(
        'FX_RATE_STALE_SECONDS',
        300
    ),

    'refresh_lock_seconds' => (int) env(
        'FX_RATE_REFRESH_LOCK_SECONDS',
        15
    ),
];
