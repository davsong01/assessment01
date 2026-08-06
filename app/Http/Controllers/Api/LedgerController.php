<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\GetLedgerRequest;
use App\Models\User;
use App\Responses\LedgerResponse;
use App\Services\Ledger\LedgerHistoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class LedgerController extends Controller
{
    public function show(
        GetLedgerRequest $request,
        string $walletId,
        LedgerHistoryService $ledgerHistoryService
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::unauthorized(
                'Authentication is required.'
            );
        }

        $history = $ledgerHistoryService->get(
            user: $user,
            walletId: $walletId,
            perPage: $request->perPage()
        );

        return LedgerResponse::history($history);
    }
}
