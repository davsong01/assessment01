<?php

declare(strict_types=1);

use App\Enums\Currency;
use App\Enums\WalletStatus;
use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Redis::flushdb();

    config()->set(
        'security.elevated_action_token.secret',
        str_repeat('a', 64)
    );

    config()->set(
        'security.elevated_action_token.ttl_seconds',
        60
    );
});

/**
 * @return array{
 *     0: User,
 *     1: string,
 *     2: Wallet,
 *     3: Wallet
 * }
 */
function createChallengeUser(): array
{
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey();

    $user = User::factory()->create([
        'totp_secret' => $secret,
        'totp_enabled_at' => now(),
    ]);

    $sourceWallet = Wallet::query()->create([
        'user_id' => $user->id,
        'currency' => Currency::NGN,
        'type' => WalletType::User,
        'status' => WalletStatus::Active,
    ]);

    $destinationWallet = Wallet::query()->create([
        'user_id' => $user->id,
        'currency' => Currency::CNY,
        'type' => WalletType::User,
        'status' => WalletStatus::Active,
    ]);

    return [
        $user,
        $secret,
        $sourceWallet,
        $destinationWallet,
    ];
}

it('requires sanctum authentication', function (): void {
    postJson('/api/2fa/challenge', [])
        ->assertUnauthorized()
        ->assertJsonPath('status', false)
        ->assertJsonPath('error.type', 'UNAUTHORIZED');
});

it('issues an elevated action token for a valid challenge', function (): void {
    [$user, $secret, $sourceWallet, $destinationWallet] =
        createChallengeUser();

    Sanctum::actingAs($user);

    $code = app(Google2FA::class)
        ->getCurrentOtp($secret);

    $response = postJson('/api/2fa/challenge', [
        'totp_code' => $code,
        'action_payload' => [
            'action' => 'swap',
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $destinationWallet->id,
            'source_amount_subunits' => 100000,
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath(
            'message',
            'Elevated action token issued successfully.'
        )
        ->assertJsonPath('data.token_type', 'ElevatedAction')
        ->assertJsonPath('data.expires_in', 60)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'elevated_action_token',
                'token_type',
                'expires_in',
                'expires_at',
            ],
        ]);
});

it('rejects an invalid totp code', function (): void {
    [$user, $secret, $sourceWallet, $destinationWallet] =
        createChallengeUser();

    Sanctum::actingAs($user);

    $validCode = app(Google2FA::class)
        ->getCurrentOtp($secret);

    $invalidCode = $validCode === '000000'
        ? '999999'
        : '000000';

    postJson('/api/2fa/challenge', [
        'totp_code' => $invalidCode,
        'action_payload' => [
            'action' => 'swap',
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $destinationWallet->id,
            'source_amount_subunits' => 100000,
        ],
    ])
        ->assertUnauthorized()
        ->assertJsonPath('status', false)
        ->assertJsonPath(
            'error.type',
            'TWO_FACTOR_AUTHENTICATION_ERROR'
        );
});

it('rejects wallets belonging to another user', function (): void {
    [$user, $secret, $sourceWallet] = createChallengeUser();

    $otherUser = User::factory()->create();

    $otherWallet = Wallet::query()->create([
        'user_id' => $otherUser->id,
        'currency' => Currency::CNY,
        'type' => WalletType::User,
        'status' => WalletStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $code = app(Google2FA::class)
        ->getCurrentOtp($secret);

    postJson('/api/2fa/challenge', [
        'totp_code' => $code,
        'action_payload' => [
            'action' => 'swap',
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $otherWallet->id,
            'source_amount_subunits' => 100000,
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('status', false)
        ->assertJsonPath(
            'error.type',
            'VALIDATION_ERROR'
        );
});

it('rejects unexpected action payload fields', function (): void {
    [$user, $secret, $sourceWallet, $destinationWallet] =
        createChallengeUser();

    Sanctum::actingAs($user);

    $code = app(Google2FA::class)
        ->getCurrentOtp($secret);

    postJson('/api/2fa/challenge', [
        'totp_code' => $code,
        'action_payload' => [
            'action' => 'swap',
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $destinationWallet->id,
            'source_amount_subunits' => 100000,
            'unexpected_field' => 'not-allowed',
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('status', false)
        ->assertJsonPath(
            'error.type',
            'VALIDATION_ERROR'
        );
});
