<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\WalletStatus;
use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public const NGN_WALLET_ID = '11111111-1111-4111-8111-111111111111';

    public const CNY_WALLET_ID = '22222222-2222-4222-8222-222222222222';

    public const NGN_CLEARING_WALLET_ID = '33333333-3333-4333-8333-333333333333';

    public const CNY_CLEARING_WALLET_ID = '44444444-4444-4444-8444-444444444444';

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

        Wallet::query()->updateOrCreate(
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
    }
}
