<?php

declare(strict_types=1);

use App\Exceptions\InvalidTotpCodeException;
use App\Exceptions\TotpNotConfiguredException;
use App\Models\User;
use App\Services\Security\TotpService;
use PragmaRX\Google2FA\Google2FA;

it('accepts a valid totp code', function (): void {
    $google2FA = new Google2FA;
    $secret = $google2FA->generateSecretKey();

    $user = new User([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $user->totp_secret = $secret;
    $user->totp_enabled_at = now();

    $service = new TotpService($google2FA);

    $service->verify(
        $user,
        $google2FA->getCurrentOtp($secret)
    );

    expect(true)->toBeTrue();
});

it('rejects an invalid totp code', function (): void {
    $google2FA = new Google2FA;
    $secret = $google2FA->generateSecretKey();

    $user = new User([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $user->totp_secret = $secret;
    $user->totp_enabled_at = now();

    $currentCode = $google2FA->getCurrentOtp($secret);

    $invalidCode = $currentCode === '000000'
        ? '999999'
        : '000000';

    $service = new TotpService($google2FA);

    $service->verify($user, $invalidCode);
})->throws(InvalidTotpCodeException::class);

it('rejects a user without configured totp', function (): void {
    $user = new User([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $service = new TotpService(new Google2FA);

    $service->verify($user, '000000');
})->throws(TotpNotConfiguredException::class);
