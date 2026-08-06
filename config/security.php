<?php

declare(strict_types=1);

return [
    'elevated_action_token' => [
        'secret' => env('ELEVATED_ACTION_TOKEN_SECRET'),
        'ttl_seconds' => (int) env(
            'ELEVATED_ACTION_TOKEN_TTL_SECONDS',
            60
        ),
    ],
];
