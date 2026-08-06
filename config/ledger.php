<?php

declare(strict_types=1);

return [
    'clearing_wallets' => [
        'NGN' => env('NGN_CLEARING_WALLET_ID'),
        'CNY' => env('CNY_CLEARING_WALLET_ID'),
    ],

    'swap_lock_seconds' => (int) env(
        'SWAP_LOCK_SECONDS',
        10
    ),
];
