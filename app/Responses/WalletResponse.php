<?php

declare(strict_types=1);

namespace App\Responses;

use App\Models\Wallet;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class WalletResponse
{
    public static function balance(
        Wallet $wallet,
        int $balanceSubunits
    ): JsonResponse {
        return ApiResponse::success(
            message: 'Wallet balance retrieved successfully.',
            data: [
                'wallet_id' => (string) $wallet->getKey(),
                'currency' => $wallet->currency->value,
                'balance_subunits' => $balanceSubunits,
            ]
        );
    }
}
