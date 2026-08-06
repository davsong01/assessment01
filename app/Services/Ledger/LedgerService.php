<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Data\Ledger\LedgerEntryData;
use App\Data\Ledger\LedgerPostingData;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\WalletType;
use App\Exceptions\DuplicateLedgerReferenceException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidLedgerEntryException;
use App\Exceptions\LedgerImbalanceException;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LedgerService
{
    /**
     * @throws DuplicateLedgerReferenceException
     * @throws InsufficientBalanceException
     * @throws InvalidLedgerEntryException
     * @throws LedgerImbalanceException
     */
    public function post(
        LedgerPostingData $posting
    ): LedgerTransaction {
        $this->validatePosting($posting);

        try {
            return DB::transaction(
                fn (): LedgerTransaction => $this->postWithinTransaction(
                    $posting
                ),
                attempts: 3
            );
        } catch (QueryException $exception) {
            if ($this->isDuplicateReferenceException($exception)) {
                throw new DuplicateLedgerReferenceException;
            }

            throw $exception;
        }
    }

    /**
     * @throws InsufficientBalanceException
     * @throws InvalidLedgerEntryException
     */
    private function postWithinTransaction(
        LedgerPostingData $posting
    ): LedgerTransaction {
        $walletIds = $this->walletIds($posting);

        /** @var Collection<string, Wallet> $wallets */
        $wallets = Wallet::query()
            ->whereIn('id', $walletIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(
                static fn (Wallet $wallet): string => (string) $wallet->getKey()
            );

        if ($wallets->count() !== count($walletIds)) {
            throw new InvalidLedgerEntryException(
                'One or more ledger wallets do not exist.'
            );
        }

        $this->validateWalletCurrencies(
            posting: $posting,
            wallets: $wallets
        );

        $this->validateResultingUserBalances(
            posting: $posting,
            wallets: $wallets
        );

        $transaction = LedgerTransaction::query()->create([
            'reference' => $posting->reference,
            'type' => $posting->type,
            'status' => LedgerTransactionStatus::Pending,
            'metadata' => $posting->metadata,
        ]);

        $createdAt = now();

        $entries = array_map(
            static fn (LedgerEntryData $entry): array => [
                'ledger_transaction_id' => $transaction->id,
                ...$entry->toArray(),
                'created_at' => $createdAt,
            ],
            $posting->entries
        );

        LedgerEntry::query()->insert($entries);

        $transaction->forceFill([
            'status' => LedgerTransactionStatus::Posted,
            'posted_at' => $createdAt,
        ])->save();

        return $transaction->refresh()->load('entries');
    }

    /**
     * @throws InvalidLedgerEntryException
     * @throws LedgerImbalanceException
     */
    private function validatePosting(
        LedgerPostingData $posting
    ): void {
        if (trim($posting->reference) === '') {
            throw new InvalidLedgerEntryException(
                'The ledger reference is required.'
            );
        }

        if (count($posting->entries) < 2) {
            throw new InvalidLedgerEntryException(
                'A ledger transaction requires at least two entries.'
            );
        }

        foreach ($posting->entries as $entry) {
            $this->validateEntry($entry);
        }

        $this->validateCurrencyBalances($posting);
    }

    /**
     * @throws InvalidLedgerEntryException
     */
    private function validateEntry(
        LedgerEntryData $entry
    ): void {
        if ($entry->amountSubunits === 0) {
            throw new InvalidLedgerEntryException(
                'Ledger entry amounts cannot be zero.'
            );
        }

        if (
            $entry->entryType === LedgerEntryType::Debit
            && $entry->amountSubunits >= 0
        ) {
            throw new InvalidLedgerEntryException(
                'Debit entries must contain negative amounts.'
            );
        }

        if (
            $entry->entryType === LedgerEntryType::Credit
            && $entry->amountSubunits <= 0
        ) {
            throw new InvalidLedgerEntryException(
                'Credit entries must contain positive amounts.'
            );
        }
    }

    /**
     * @throws LedgerImbalanceException
     */
    private function validateCurrencyBalances(
        LedgerPostingData $posting
    ): void {
        /** @var array<string, int> $currencyTotals */
        $currencyTotals = [];

        foreach ($posting->entries as $entry) {
            $currency = $entry->currency->value;

            $currencyTotals[$currency] = (
                $currencyTotals[$currency] ?? 0
            ) + $entry->amountSubunits;
        }

        foreach ($currencyTotals as $currency => $total) {
            if ($total !== 0) {
                throw new LedgerImbalanceException(
                    sprintf(
                        'Ledger entries for %s are unbalanced by %d subunits.',
                        $currency,
                        $total
                    )
                );
            }
        }
    }

    /**
     * @param  Collection<string, Wallet>  $wallets
     *
     * @throws InvalidLedgerEntryException
     */
    private function validateWalletCurrencies(
        LedgerPostingData $posting,
        Collection $wallets
    ): void {
        foreach ($posting->entries as $entry) {
            $wallet = $wallets->get($entry->walletId);

            if (! $wallet instanceof Wallet) {
                throw new InvalidLedgerEntryException(
                    'One or more ledger wallets do not exist.'
                );
            }

            if ($wallet->currency !== $entry->currency) {
                throw new InvalidLedgerEntryException(
                    sprintf(
                        'The entry currency does not match wallet %s.',
                        $entry->walletId
                    )
                );
            }
        }
    }

    /**
     * @param  Collection<string, Wallet>  $wallets
     *
     * @throws InsufficientBalanceException
     * @throws InvalidLedgerEntryException
     */
    private function validateResultingUserBalances(
        LedgerPostingData $posting,
        Collection $wallets
    ): void {
        /** @var array<string, int> $walletDeltas */
        $walletDeltas = [];

        foreach ($posting->entries as $entry) {
            $walletDeltas[$entry->walletId] = (
                $walletDeltas[$entry->walletId] ?? 0
            ) + $entry->amountSubunits;
        }

        foreach ($walletDeltas as $walletId => $delta) {
            $wallet = $wallets->get($walletId);

            if (! $wallet instanceof Wallet) {
                throw new InvalidLedgerEntryException(
                    'One or more ledger wallets do not exist.'
                );
            }

            if ($wallet->type !== WalletType::User) {
                continue;
            }

            $currentBalance = $this->balanceSubunits($wallet);

            if ($currentBalance + $delta < 0) {
                throw new InsufficientBalanceException(
                    sprintf(
                        'Wallet %s has insufficient balance.',
                        $walletId
                    )
                );
            }
        }
    }

    public function balanceSubunits(
        Wallet $wallet
    ): int {
        $balance = LedgerEntry::query()
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
            ->sum('ledger_entries.amount_subunits');

        return (int) $balance;
    }

    /**
     * @return list<string>
     */
    private function walletIds(
        LedgerPostingData $posting
    ): array {
        $walletIds = array_map(
            static fn (LedgerEntryData $entry): string => $entry->walletId,
            $posting->entries
        );

        $walletIds = array_values(array_unique($walletIds));

        sort($walletIds, SORT_STRING);

        return $walletIds;
    }

    private function isDuplicateReferenceException(
        QueryException $exception
    ): bool {
        return $exception->getCode() === '23000';
    }
}
