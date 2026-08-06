<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Exceptions\InvalidWalletSelectionException;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class LedgerHistoryService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * @return array{
     *     wallet: array{
     *         id: string,
     *         currency: string,
     *         type: string,
     *         status: string,
     *         balance_subunits: int
     *     },
     *     entries: LengthAwarePaginator<int, LedgerEntry>
     * }
     */
    public function get(
        User $user,
        string $walletId,
        int $perPage
    ): array {
        $wallet = Wallet::query()->find($walletId);

        if (! $wallet instanceof Wallet) {
            throw new InvalidWalletSelectionException(
                'The selected wallet does not exist.'
            );
        }

        if ((int) $wallet->user_id !== (int) $user->getKey()) {
            throw new InvalidWalletSelectionException(
                'The selected wallet does not belong to the authenticated user.'
            );
        }

        $entries = LedgerEntry::query()
            ->select([
                'ledger_entries.id',
                'ledger_entries.ledger_transaction_id',
                'ledger_entries.wallet_id',
                'ledger_entries.currency',
                'ledger_entries.entry_type',
                'ledger_entries.amount_subunits',
                'ledger_entries.created_at',
            ])
            ->join(
                'ledger_transactions',
                'ledger_transactions.id',
                '=',
                'ledger_entries.ledger_transaction_id'
            )
            ->where('ledger_entries.wallet_id', $wallet->id)
            ->where(
                'ledger_transactions.status',
                LedgerTransactionStatus::Posted->value
            )
            ->with([
                'transaction:id,reference,type,status,posted_at',
            ])
            ->orderByDesc('ledger_entries.created_at')
            ->orderByDesc('ledger_entries.id')
            ->paginate($perPage);

        return [
            'wallet' => [
                'id' => (string) $wallet->getKey(),
                'currency' => $wallet->currency->value,
                'type' => $wallet->type->value,
                'status' => $wallet->status->value,
                'balance_subunits' => $this->ledgerService->balanceSubunits($wallet),
            ],
            'entries' => $entries,
        ];
    }
}
