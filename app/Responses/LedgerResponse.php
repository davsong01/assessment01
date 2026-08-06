<?php

declare(strict_types=1);

namespace App\Responses;

use App\Models\LedgerEntry;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class LedgerResponse
{
    /**
     * @param array{
     *     wallet: array{
     *         id: string,
     *         currency: string,
     *         type: string,
     *         status: string,
     *         balance_subunits: int
     *     },
     *     entries: LengthAwarePaginator<int, LedgerEntry>
     * } $history
     */
    public static function history(array $history): JsonResponse
    {
        return ApiResponse::success(
            message: 'Ledger history retrieved successfully.',
            data: $history
        );
    }
}
