<?php

declare(strict_types=1);

namespace App\Actions\Swap;

use App\Data\Ledger\LedgerEntryData;
use App\Data\Ledger\LedgerPostingData;
use App\Data\Security\ElevatedActionPayload;
use App\Data\Swap\SwapQuoteData;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletStatus;
use App\Enums\WalletType;
use App\Exceptions\InvalidElevatedActionTokenException;
use App\Exceptions\InvalidSwapWalletException;
use App\Exceptions\SwapConflictException;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ExchangeRate\ExchangeRateService;
use App\Services\Ledger\LedgerService;
use App\Services\Locks\RedisLockService;
use App\Services\Security\ElevatedActionTokenService;
use App\Services\Swap\SwapQuoteCalculator;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ExecuteSwap
{
    public function __construct(
        private ElevatedActionTokenService $tokenService,
        private ExchangeRateService $exchangeRateService,
        private SwapQuoteCalculator $quoteCalculator,
        private LedgerService $ledgerService,
        private RedisLockService $lockService
    ) {}

    /**
     * @return array{
     *     transaction_id: string,
     *     reference: string,
     *     source_wallet_id: string,
     *     destination_wallet_id: string,
     *     source_currency: string,
     *     destination_currency: string,
     *     source_amount_subunits: int,
     *     destination_amount_subunits: int,
     *     market_rate: string,
     *     spread_basis_points: int,
     *     spread_amount_subunits: int
     * }
     *
     * @throws InvalidElevatedActionTokenException
     * @throws InvalidSwapWalletException
     * @throws SwapConflictException
     */
    public function execute(
        User $user,
        ElevatedActionPayload $payload,
        string $elevatedActionToken
    ): array {
        $userId = (int) $user->getKey();

        /*
        |--------------------------------------------------------------------------
        | Consume the EAT before financial processing
        |--------------------------------------------------------------------------
        */
        $this->tokenService->consume(
            token: $elevatedActionToken,
            authenticatedUserId: $userId,
            actualPayload: $payload
        );

        [$sourceWallet, $destinationWallet] =
            $this->loadUserWallets(
                userId: $userId,
                payload: $payload
            );

        $locks = [];

        try {
            $locks = $this->acquireLocks(
                userId: $userId,
                sourceWalletId: (string) $sourceWallet->getKey(),
                destinationWalletId: (string) $destinationWallet->getKey()
            );

            DB::statement(
                'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'
            );

            return DB::transaction(
                fn (): array => $this->executeWithinTransaction(
                    user: $user,
                    payload: $payload,
                    sourceWallet: $sourceWallet,
                    destinationWallet: $destinationWallet
                ),
                attempts: 3
            );
        } finally {
            $this->releaseLocks($locks);
        }
    }

    /**
     * @return array{
     *     transaction_id: string,
     *     reference: string,
     *     source_wallet_id: string,
     *     destination_wallet_id: string,
     *     source_currency: string,
     *     destination_currency: string,
     *     source_amount_subunits: int,
     *     destination_amount_subunits: int,
     *     market_rate: string,
     *     spread_basis_points: int,
     *     spread_amount_subunits: int
     * }
     */
    private function executeWithinTransaction(
        User $user,
        ElevatedActionPayload $payload,
        Wallet $sourceWallet,
        Wallet $destinationWallet
    ): array {
        /*
        |--------------------------------------------------------------------------
        | LedgerService locks all participating wallets in sorted ID order
        |--------------------------------------------------------------------------
        */
        $rate = $this->exchangeRateService->get(
            from: $sourceWallet->currency,
            to: $destinationWallet->currency
        );

        $quote = $this->quoteCalculator->calculate(
            sourceAmountSubunits: $payload->sourceAmountSubunits,
            marketRate: $rate->rate
        );

        $sourceClearingWallet = $this->clearingWallet(
            $sourceWallet
        );

        $destinationClearingWallet = $this->clearingWallet(
            $destinationWallet
        );

        $reference = 'SWAP-'.Str::upper(
            (string) Str::ulid()
        );

        $transaction = $this->ledgerService->post(
            new LedgerPostingData(
                reference: $reference,
                type: LedgerTransactionType::Swap,
                entries: $this->ledgerEntries(
                    payload: $payload,
                    quote: $quote,
                    sourceWallet: $sourceWallet,
                    destinationWallet: $destinationWallet,
                    sourceClearingWallet: $sourceClearingWallet,
                    destinationClearingWallet: $destinationClearingWallet
                ),
                metadata: [
                    'user_id' => (int) $user->getKey(),
                    'market_rate' => $quote->marketRate,
                    'spread_basis_points' => $quote->spreadBasisPoints,
                    'gross_destination_subunits' => $quote->grossDestinationSubunits,
                    'spread_amount_subunits' => $quote->spreadAmountSubunits,
                    'destination_amount_subunits' => $quote->destinationAmountSubunits,
                ]
            )
        );

        return $this->responseData(
            transaction: $transaction,
            quote: $quote,
            sourceWallet: $sourceWallet,
            destinationWallet: $destinationWallet
        );
    }

    /**
     * @return list<LedgerEntryData>
     */
    private function ledgerEntries(
        ElevatedActionPayload $payload,
        SwapQuoteData $quote,
        Wallet $sourceWallet,
        Wallet $destinationWallet,
        Wallet $sourceClearingWallet,
        Wallet $destinationClearingWallet
    ): array {
        return [
            new LedgerEntryData(
                walletId: (string) $sourceWallet->getKey(),
                currency: $sourceWallet->currency,
                entryType: LedgerEntryType::Debit,
                amountSubunits: -$payload->sourceAmountSubunits
            ),

            new LedgerEntryData(
                walletId: (string) $sourceClearingWallet->getKey(),
                currency: $sourceWallet->currency,
                entryType: LedgerEntryType::Credit,
                amountSubunits: $payload->sourceAmountSubunits
            ),

            new LedgerEntryData(
                walletId: (string) $destinationClearingWallet->getKey(),
                currency: $destinationWallet->currency,
                entryType: LedgerEntryType::Debit,
                amountSubunits: -$quote->destinationAmountSubunits
            ),

            new LedgerEntryData(
                walletId: (string) $destinationWallet->getKey(),
                currency: $destinationWallet->currency,
                entryType: LedgerEntryType::Credit,
                amountSubunits: $quote->destinationAmountSubunits
            ),
        ];
    }

    /**
     * @return array{0: Wallet, 1: Wallet}
     */
    private function loadUserWallets(
        int $userId,
        ElevatedActionPayload $payload
    ): array {
        /** @var Wallet|null $sourceWallet */
        $sourceWallet = Wallet::query()->find(
            $payload->sourceWalletId
        );

        /** @var Wallet|null $destinationWallet */
        $destinationWallet = Wallet::query()->find(
            $payload->destinationWalletId
        );

        if (
            ! $sourceWallet instanceof Wallet
            || ! $destinationWallet instanceof Wallet
        ) {
            throw new InvalidSwapWalletException(
                'One or more selected wallets do not exist.'
            );
        }

        if (
            (int) $sourceWallet->user_id !== $userId
            || (int) $destinationWallet->user_id !== $userId
        ) {
            throw new InvalidSwapWalletException(
                'The selected wallets do not belong to the authenticated user.'
            );
        }

        if (
            $sourceWallet->type !== WalletType::User
            || $destinationWallet->type !== WalletType::User
        ) {
            throw new InvalidSwapWalletException(
                'Only user wallets may participate in a swap.'
            );
        }

        if (
            $sourceWallet->status !== WalletStatus::Active
            || $destinationWallet->status !== WalletStatus::Active
        ) {
            throw new InvalidSwapWalletException(
                'Both wallets must be active.'
            );
        }

        if ($sourceWallet->currency === $destinationWallet->currency) {
            throw new InvalidSwapWalletException(
                'The source and destination currencies must be different.'
            );
        }

        return [$sourceWallet, $destinationWallet];
    }

    private function clearingWallet(
        Wallet $userWallet
    ): Wallet {
        $walletId = config(
            sprintf(
                'ledger.clearing_wallets.%s',
                $userWallet->currency->value
            )
        );

        if (! is_string($walletId) || $walletId === '') {
            throw new InvalidSwapWalletException(
                sprintf(
                    'No clearing wallet is configured for %s.',
                    $userWallet->currency->value
                )
            );
        }

        /** @var Wallet|null $wallet */
        $wallet = Wallet::query()->find($walletId);

        if (
            ! $wallet instanceof Wallet
            || $wallet->type !== WalletType::Clearing
            || $wallet->currency !== $userWallet->currency
            || $wallet->status !== WalletStatus::Active
        ) {
            throw new InvalidSwapWalletException(
                sprintf(
                    'The %s clearing wallet is invalid.',
                    $userWallet->currency->value
                )
            );
        }

        return $wallet;
    }

    /**
     * @return list<Lock>
     */
    private function acquireLocks(
        int $userId,
        string $sourceWalletId,
        string $destinationWalletId
    ): array {
        $lockNames = [
            'swap:user:'.$userId,
            'swap:wallet:'.$sourceWalletId,
            'swap:wallet:'.$destinationWalletId,
        ];

        sort($lockNames, SORT_STRING);

        $acquiredLocks = [];

        try {
            foreach ($lockNames as $lockName) {
                $lock = $this->lockService->create(
                    name: $lockName,
                    ttlSeconds: $this->lockTtlSeconds()
                );

                if (! $lock->get()) {
                    throw new SwapConflictException(
                        'Another operation is currently using one of the selected wallets.'
                    );
                }

                $acquiredLocks[] = $lock;
            }

            return $acquiredLocks;
        } catch (\Throwable $exception) {
            $this->releaseLocks($acquiredLocks);

            throw $exception;
        }
    }

    /**
     * @param  list<Lock>  $locks
     */
    private function releaseLocks(array $locks): void
    {
        foreach (array_reverse($locks) as $lock) {
            $lock->release();
        }
    }

    private function lockTtlSeconds(): int
    {
        $seconds = config('ledger.swap_lock_seconds', 10);

        return is_int($seconds) && $seconds > 0
            ? $seconds
            : 10;
    }

    /**
     * @return array{
     *     transaction_id: string,
     *     reference: string,
     *     source_wallet_id: string,
     *     destination_wallet_id: string,
     *     source_currency: string,
     *     destination_currency: string,
     *     source_amount_subunits: int,
     *     destination_amount_subunits: int,
     *     market_rate: string,
     *     spread_basis_points: int,
     *     spread_amount_subunits: int
     * }
     */
    private function responseData(
        LedgerTransaction $transaction,
        SwapQuoteData $quote,
        Wallet $sourceWallet,
        Wallet $destinationWallet
    ): array {
        return [
            'transaction_id' => (string) $transaction->getKey(),
            'reference' => $transaction->reference,
            'source_wallet_id' => (string) $sourceWallet->getKey(),
            'destination_wallet_id' => (string) $destinationWallet->getKey(),
            'source_currency' => $sourceWallet->currency->value,
            'destination_currency' => $destinationWallet->currency->value,
            'source_amount_subunits' => $quote->sourceAmountSubunits,
            'destination_amount_subunits' => $quote->destinationAmountSubunits,
            'market_rate' => $quote->marketRate,
            'spread_basis_points' => $quote->spreadBasisPoints,
            'spread_amount_subunits' => $quote->spreadAmountSubunits,
        ];
    }
}
