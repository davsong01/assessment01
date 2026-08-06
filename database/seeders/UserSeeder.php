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

final class UserSeeder extends Seeder
{
    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public const NGN_WALLET_ID =
        '11111111-1111-4111-8111-111111111111';

    public const CNY_WALLET_ID =
        '22222222-2222-4222-8222-222222222222';

    public const NGN_CLEARING_WALLET_ID =
        '33333333-3333-4333-8333-333333333333';

    public const CNY_CLEARING_WALLET_ID =
        '44444444-4444-4444-8444-444444444444';

    private const INITIAL_FUNDING_REFERENCE =
        'INITIAL-FUNDING-MAIN-USER-NGN';

    private const INITIAL_FUNDING_AMOUNT_SUBUNITS =
        500_000_000;

    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            [
                'email' => 'davsong@gmail.com',
            ],
            [
                'name' => 'Main User',
                'password' => Hash::make('password'),
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
                'id' => self::NGN_CLEARING_WALLET_ID,
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
                'id' => self::CNY_CLEARING_WALLET_ID,
            ],
            [
                'user_id' => null,
                'currency' => Currency::CNY,
                'type' => WalletType::Clearing,
                'status' => WalletStatus::Active,
            ]
        );

        $alreadyFunded = LedgerTransaction::query()
            ->where(
                'reference',
                self::INITIAL_FUNDING_REFERENCE
            )
            ->exists();

        if ($alreadyFunded) {
            return;
        }

        app(LedgerService::class)->post(
            new LedgerPostingData(
                reference: self::INITIAL_FUNDING_REFERENCE,
                type: LedgerTransactionType::InitialFunding,
                entries: [
                    new LedgerEntryData(
                        walletId: (string) $ngnClearingWallet->getKey(),
                        currency: Currency::NGN,
                        entryType: LedgerEntryType::Debit,
                        amountSubunits: -self::INITIAL_FUNDING_AMOUNT_SUBUNITS
                    ),
                    new LedgerEntryData(
                        walletId: (string) $userNgnWallet->getKey(),
                        currency: Currency::NGN,
                        entryType: LedgerEntryType::Credit,
                        amountSubunits: self::INITIAL_FUNDING_AMOUNT_SUBUNITS
                    ),
                ],
                metadata: [
                    'seeded' => true,
                    'description' => 'Initial NGN wallet funding',
                ]
            )
        );
    }
}
