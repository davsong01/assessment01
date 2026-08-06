<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\SettlementWebhookController;
use App\Http\Controllers\Api\SwapController;
use App\Http\Controllers\Api\TwoFactorChallengeController;
use App\Http\Controllers\Api\WalletBalanceController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/2fa/challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor-challenge');
    Route::post('/swap', [SwapController::class, 'store'])->middleware('throttle:swap');
    Route::get('/ledger/{walletId}', [LedgerController::class, 'show']);
    Route::get('/wallets/{walletId}/balance', [WalletBalanceController::class, 'show']);
});

Route::post('/webhooks/settlement', SettlementWebhookController::class)->middleware('settlement.signature');
