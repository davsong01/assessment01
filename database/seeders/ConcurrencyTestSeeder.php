<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\Ledger\LedgerEntryData;
use App\Data\Ledger\LedgerPostingData;
use App\Enums\Currency;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletStatus;
use App\Enums\WalletType;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class ConcurrencyTestSeeder extends Seeder
{
    public const EMAIL = 'concurrency@example.com';

    public const PASSWORD = 'password';

    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public const NGN_WALLET_ID =
        '55555555-5555-4555-8555-555555555555';

    public const CNY_WALLET_ID =
        '66666666-6666-4666-8666-666666666666';

    public const SWAP_AMOUNT_SUBUNITS = 10_000_000;

    private const FUNDING_REFERENCE =
        'CONCURRENCY-TEST-FUNDING';

    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            [
                'email' => self::EMAIL,
            ],
            [
                'name' => 'Concurrency Test User',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'totp_secret' => self::TOTP_SECRET,
                'totp_enabled_at' => now(),
            ]
        );

        $userNgnWallet = Wallet::query()->updateOrCreate(
            [
                'id' => self::NGN_WALLET_ID,
            ],
            [
                'user_id' => $user->id,
                'currency' => Currency::NGN,
                'type' => WalletType::User,
                'status' => WalletStatus::Active,
            ]
        );

        Wallet::query()->updateOrCreate(
            [
                'id' => self::CNY_WALLET_ID,
            ],
            [
                'user_id' => $user->id,
                'currency' => Currency::CNY,
                'type' => WalletType::User,
                'status' => WalletStatus::Active,
            ]
        );

        $ngnClearingWallet = Wallet::query()->updateOrCreate(
            [
                'id' => UserSeeder::NGN_CLEARING_WALLET_ID,
            ],
            [
                'user_id' => null,
                'currency' => Currency::NGN,
                'type' => WalletType::Clearing,
                'status' => WalletStatus::Active,
            ]
        );

        Wallet::query()->updateOrCreate(
            [
                'id' => UserSeeder::CNY_CLEARING_WALLET_ID,
            ],
            [
                'user_id' => null,
                'currency' => Currency::CNY,
                'type' => WalletType::Clearing,
                'status' => WalletStatus::Active,
            ]
        );

        $alreadyFunded = LedgerTransaction::query()
            ->where('reference', self::FUNDING_REFERENCE)
            ->exists();

        if ($alreadyFunded) {
            return;
        }

        app(LedgerService::class)->post(
            new LedgerPostingData(
                reference: self::FUNDING_REFERENCE,
                type: LedgerTransactionType::InitialFunding,
                entries: [
                    new LedgerEntryData(
                        walletId: (string) $ngnClearingWallet->getKey(),
                        currency: Currency::NGN,
                        entryType: LedgerEntryType::Debit,
                        amountSubunits: -self::SWAP_AMOUNT_SUBUNITS
                    ),
                    new LedgerEntryData(
                        walletId: (string) $userNgnWallet->getKey(),
                        currency: Currency::NGN,
                        entryType: LedgerEntryType::Credit,
                        amountSubunits: self::SWAP_AMOUNT_SUBUNITS
                    ),
                ],
                metadata: [
                    'seeded' => true,
                    'purpose' => 'concurrency_test',
                ]
            )
        );
    }
}
