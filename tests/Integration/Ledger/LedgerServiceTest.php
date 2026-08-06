<?php

declare(strict_types=1);

use App\Data\Ledger\LedgerEntryData;
use App\Data\Ledger\LedgerPostingData;
use App\Enums\Currency;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletStatus;
use App\Enums\WalletType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidLedgerEntryException;
use App\Exceptions\LedgerImbalanceException;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LedgerTestContext;

uses(RefreshDatabase::class);

function createLedgerContext(): LedgerTestContext
{
    $user = User::factory()->create();

    $userNgnWallet = Wallet::create([
        'user_id' => $user->id,
        'currency' => Currency::NGN,
        'type' => WalletType::User,
        'status' => WalletStatus::Active,
    ]);

    $userCnyWallet = Wallet::create([
        'user_id' => $user->id,
        'currency' => Currency::CNY,
        'type' => WalletType::User,
        'status' => WalletStatus::Active,
    ]);

    $ngnClearingWallet = Wallet::create([
        'user_id' => null,
        'currency' => Currency::NGN,
        'type' => WalletType::Clearing,
        'status' => WalletStatus::Active,
    ]);

    return new LedgerTestContext(
        ledgerService: app(LedgerService::class),
        user: $user,
        userNgnWallet: $userNgnWallet,
        ngnClearingWallet: $ngnClearingWallet,
        userCnyWallet: $userCnyWallet,
    );
}

it('posts a balanced funding transaction', function (): void {

    $ctx = createLedgerContext();

    $posting = new LedgerPostingData(
        reference: 'FUNDING-001',
        type: LedgerTransactionType::InitialFunding,
        entries: [

            new LedgerEntryData(
                walletId: $ctx->ngnClearingWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Debit,
                amountSubunits: -50_000_000,
            ),

            new LedgerEntryData(
                walletId: $ctx->userNgnWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Credit,
                amountSubunits: 50_000_000,
            ),

        ],
    );

    $transaction = $ctx->ledgerService->post($posting);

    expect($transaction->status)
        ->toBe(LedgerTransactionStatus::Posted);

    expect(
        $ctx->ledgerService->balanceSubunits(
            $ctx->userNgnWallet
        )
    )->toBe(50_000_000);

    expect(
        LedgerTransaction::query()->count()
    )->toBe(1);

    expect(
        LedgerEntry::query()->count()
    )->toBe(2);
});

it('rejects an unbalanced transaction', function (): void {

    $ctx = createLedgerContext();

    $posting = new LedgerPostingData(
        reference: 'BAD-001',
        type: LedgerTransactionType::InitialFunding,
        entries: [

            new LedgerEntryData(
                walletId: $ctx->ngnClearingWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Debit,
                amountSubunits: -100_000,
            ),

            new LedgerEntryData(
                walletId: $ctx->userNgnWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Credit,
                amountSubunits: 90_000,
            ),

        ],
    );

    expect(
        fn () => $ctx->ledgerService->post($posting)
    )->toThrow(LedgerImbalanceException::class);

    expect(
        LedgerTransaction::query()->count()
    )->toBe(0);

    expect(
        LedgerEntry::query()->count()
    )->toBe(0);
});

it('rejects positive debit entries', function (): void {

    $ctx = createLedgerContext();

    $posting = new LedgerPostingData(
        reference: 'BAD-002',
        type: LedgerTransactionType::InitialFunding,
        entries: [

            new LedgerEntryData(
                walletId: $ctx->ngnClearingWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Debit,
                amountSubunits: 100_000,
            ),

            new LedgerEntryData(
                walletId: $ctx->userNgnWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Credit,
                amountSubunits: 100_000,
            ),

        ],
    );

    expect(
        fn () => $ctx->ledgerService->post($posting)
    )->toThrow(
        InvalidLedgerEntryException::class
    );
});

it('prevents overdrafts', function (): void {

    $ctx = createLedgerContext();

    $posting = new LedgerPostingData(
        reference: 'OVERDRAW-001',
        type: LedgerTransactionType::Swap,
        entries: [

            new LedgerEntryData(
                walletId: $ctx->userNgnWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Debit,
                amountSubunits: -500_000,
            ),

            new LedgerEntryData(
                walletId: $ctx->ngnClearingWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Credit,
                amountSubunits: 500_000,
            ),

        ],
    );

    expect(
        fn () => $ctx->ledgerService->post($posting)
    )->toThrow(
        InsufficientBalanceException::class
    );
});

it('rejects wallet currency mismatch', function (): void {

    $ctx = createLedgerContext();

    $posting = new LedgerPostingData(
        reference: 'BAD-003',
        type: LedgerTransactionType::Swap,
        entries: [

            new LedgerEntryData(
                walletId: $ctx->ngnClearingWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Debit,
                amountSubunits: -100_000,
            ),

            new LedgerEntryData(
                walletId: $ctx->userCnyWallet->id,
                currency: Currency::NGN,
                entryType: LedgerEntryType::Credit,
                amountSubunits: 100_000,
            ),

        ],
    );

    expect(
        fn () => $ctx->ledgerService->post($posting)
    )->toThrow(
        InvalidLedgerEntryException::class
    );
});

it('prevents updating a posted ledger entry', function (): void {
    $ctx = createLedgerContext();

    $transaction = $ctx->ledgerService->post(
        new LedgerPostingData(
            reference: 'IMMUTABLE-UPDATE-001',
            type: LedgerTransactionType::InitialFunding,
            entries: [
                new LedgerEntryData(
                    walletId: $ctx->ngnClearingWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Debit,
                    amountSubunits: -100_000,
                ),
                new LedgerEntryData(
                    walletId: $ctx->userNgnWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Credit,
                    amountSubunits: 100_000,
                ),
            ]
        )
    );

    $entry = $transaction->entries->firstOrFail();

    expect(
        fn () => DB::table('ledger_entries')
            ->where('id', $entry->id)
            ->update([
                'amount_subunits' => 200_000,
            ])
    )->toThrow(QueryException::class);
});

it('prevents deleting a posted ledger entry', function (): void {
    $ctx = createLedgerContext();

    $transaction = $ctx->ledgerService->post(
        new LedgerPostingData(
            reference: 'IMMUTABLE-DELETE-001',
            type: LedgerTransactionType::InitialFunding,
            entries: [
                new LedgerEntryData(
                    walletId: $ctx->ngnClearingWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Debit,
                    amountSubunits: -100_000,
                ),
                new LedgerEntryData(
                    walletId: $ctx->userNgnWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Credit,
                    amountSubunits: 100_000,
                ),
            ]
        )
    );

    $entry = $transaction->entries->firstOrFail();

    expect(
        fn () => DB::table('ledger_entries')
            ->where('id', $entry->id)
            ->delete()
    )->toThrow(QueryException::class);
});

it('prevents adding entries to a posted transaction', function (): void {
    $ctx = createLedgerContext();

    $transaction = $ctx->ledgerService->post(
        new LedgerPostingData(
            reference: 'IMMUTABLE-INSERT-001',
            type: LedgerTransactionType::InitialFunding,
            entries: [
                new LedgerEntryData(
                    walletId: $ctx->ngnClearingWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Debit,
                    amountSubunits: -100_000,
                ),
                new LedgerEntryData(
                    walletId: $ctx->userNgnWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Credit,
                    amountSubunits: 100_000,
                ),
            ]
        )
    );

    expect(
        fn () => DB::table('ledger_entries')->insert([
            'ledger_transaction_id' => $transaction->id,
            'wallet_id' => $ctx->userNgnWallet->id,
            'currency' => Currency::NGN->value,
            'entry_type' => LedgerEntryType::Credit->value,
            'amount_subunits' => 1,
            'created_at' => now(),
        ])
    )->toThrow(QueryException::class);
});

it('prevents changing a posted transaction back to pending', function (): void {
    $ctx = createLedgerContext();

    $transaction = $ctx->ledgerService->post(
        new LedgerPostingData(
            reference: 'IMMUTABLE-STATUS-001',
            type: LedgerTransactionType::InitialFunding,
            entries: [
                new LedgerEntryData(
                    walletId: $ctx->ngnClearingWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Debit,
                    amountSubunits: -100_000,
                ),
                new LedgerEntryData(
                    walletId: $ctx->userNgnWallet->id,
                    currency: Currency::NGN,
                    entryType: LedgerEntryType::Credit,
                    amountSubunits: 100_000,
                ),
            ]
        )
    );

    expect(
        fn () => DB::table('ledger_transactions')
            ->where('id', $transaction->id)
            ->update([
                'status' => LedgerTransactionStatus::Pending->value,
            ])
    )->toThrow(QueryException::class);
});

it('rejects invalid data at the database layer', function (): void {
    $ctx = createLedgerContext();

    $transactionId = (string) Str::uuid();

    DB::table('ledger_transactions')->insert([
        'id' => $transactionId,
        'reference' => 'DB-INVALID-001',
        'type' => LedgerTransactionType::InitialFunding->value,
        'status' => LedgerTransactionStatus::Pending->value,
        'metadata' => null,
        'posted_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(
        fn () => DB::table('ledger_entries')->insert([
            'ledger_transaction_id' => $transactionId,
            'wallet_id' => $ctx->userNgnWallet->id,
            'currency' => 'USD',
            'entry_type' => LedgerEntryType::Credit->value,
            'amount_subunits' => 100_000,
            'created_at' => now(),
        ])
    )->toThrow(QueryException::class);

    expect(
        LedgerTransaction::query()
            ->findOrFail($transactionId)
            ->status
    )->toBe(LedgerTransactionStatus::Pending);
});

it('rejects posting a transaction that would create a negative user wallet balance', function (): void {
    $ctx = createLedgerContext();

    $transactionId = (string) Str::uuid();

    DB::table('ledger_transactions')->insert([
        'id' => $transactionId,
        'reference' => 'DB-NEGATIVE-BALANCE-001',
        'type' => LedgerTransactionType::Swap->value,
        'status' => LedgerTransactionStatus::Pending->value,
        'metadata' => null,
        'posted_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ledger_entries')->insert([
        'ledger_transaction_id' => $transactionId,
        'wallet_id' => $ctx->userNgnWallet->id,
        'currency' => Currency::NGN->value,
        'entry_type' => LedgerEntryType::Debit->value,
        'amount_subunits' => -100_000,
        'created_at' => now(),
    ]);

    expect(
        fn () => DB::table('ledger_transactions')
            ->where('id', $transactionId)
            ->update([
                'status' => LedgerTransactionStatus::Posted->value,
            ])
    )->toThrow(QueryException::class);
});
