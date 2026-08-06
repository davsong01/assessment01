<?php

declare(strict_types=1);

namespace App\Services\Settlement;

use App\Enums\SettlementStatus;
use App\Models\LedgerTransaction;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SettlementService
{
    /**
     * @param array{
     *     provider_reference: string,
     *     transaction_reference: string|null,
     *     status: string
     * } $payload
     */
    public function process(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $incomingStatus = SettlementStatus::from(
                $payload['status']
            );

            $ledgerTransactionId =
                $this->ledgerTransactionId(
                    $payload['transaction_reference']
                );

            /*
            | Atomic idempotency foundation
            | Concurrent deliveries may both reach this point. The unique database
            | constraint allows only one row for the provider reference.
            */

            Settlement::query()->insertOrIgnore([
                'id' => (string) str()->uuid(),
                'provider_reference' => $payload['provider_reference'],
                'ledger_transaction_id' => $ledgerTransactionId,
                'status' => SettlementStatus::Initiated->value,
                'payload' => json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $settlement = Settlement::query()
                ->where(
                    'provider_reference',
                    $payload['provider_reference']
                )
                ->lockForUpdate()
                ->first();

            if (! $settlement instanceof Settlement) {
                throw new RuntimeException(
                    'The settlement could not be created.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Ignore duplicate and backward state transitions
            |--------------------------------------------------------------------------
            */

            if ($settlement->status->isTerminal()) {
                return;
            }

            if (
                $incomingStatus->rank()
                <= $settlement->status->rank()
            ) {
                return;
            }

            $settlement->forceFill([
                'ledger_transaction_id' => $settlement->ledger_transaction_id
                    ?? $ledgerTransactionId,
                'status' => $incomingStatus,
                'payload' => $payload,
                'completed_at' => $incomingStatus ===
                        SettlementStatus::Completed
                            ? now()
                            : null,
                'failed_at' => $incomingStatus ===
                        SettlementStatus::Failed
                            ? now()
                            : null,
            ])->save();
        });
    }

    private function ledgerTransactionId(
        ?string $transactionReference
    ): ?string {
        if ($transactionReference === null) {
            return null;
        }

        $id = LedgerTransaction::query()
            ->where(
                'reference',
                $transactionReference
            )
            ->value('id');

        return is_string($id) ? $id : null;
    }
}
