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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
