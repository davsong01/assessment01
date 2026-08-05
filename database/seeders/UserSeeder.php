<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    public function run(): void
    {
        User::query()->updateOrCreate(
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
    }
}
