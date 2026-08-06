<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post(
        '/2fa/challenge',
        [TwoFactorChallengeController::class, 'store']
    )->middleware('throttle:two-factor-challenge');
});
