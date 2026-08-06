<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessSettlementWebhook;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SettlementWebhookController extends Controller
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        /** @var array{
         *     provider_reference: string,
         *     transaction_reference?: string|null,
         *     status: string
         * } $validated
         */
        $validated = $request->validate([
            'provider_reference' => [
                'required',
                'string',
                'max:120',
            ],

            'transaction_reference' => [
                'nullable',
                'string',
                'max:100',
            ],

            'status' => [
                'required',
                'string',
                Rule::enum(SettlementStatus::class),
            ],
        ]);

        ProcessSettlementWebhook::dispatch([
            'provider_reference' => $validated['provider_reference'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'status' => $validated['status'],
        ]);

        return ApiResponse::accepted(
            message: 'Settlement webhook accepted.',
            data: []
        );
    }
}
