<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Responses\WalletResponse;
use App\Services\Ledger\LedgerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WalletBalanceController extends Controller
{
    public function show(
        Request $request,
        string $walletId,
        LedgerService $ledgerService
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::unauthorized(
                'Authentication is required.'
            );
        }

        $wallet = Wallet::query()->find($walletId);

        if (
            ! $wallet instanceof Wallet
            || (int) $wallet->user_id !== (int) $user->getKey()
        ) {
            return ApiResponse::notFound(
                'Wallet not found.'
            );
        }

        return WalletResponse::balance(
            wallet: $wallet,
            balanceSubunits: $ledgerService->balanceSubunits(
                $wallet
            )
        );
    }
}
