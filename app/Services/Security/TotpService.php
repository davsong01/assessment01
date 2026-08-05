<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Exceptions\InvalidTotpCodeException;
use App\Exceptions\TotpNotConfiguredException;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

final readonly class TotpService
{
    public function __construct(
        private Google2FA $google2FA
    ) {}

    /**
     * @throws InvalidTotpCodeException
     * @throws TotpNotConfiguredException
     */
    public function verify(User $user, string $code): void
    {
        $secret = $user->totp_secret;

        if (
            ! is_string($secret)
            || $secret === ''
            || $user->totp_enabled_at === null
        ) {
            throw new TotpNotConfiguredException;
        }

        // window: 1 - previous 30-second interval
        // current 30-second interval
        // next 30-second interval
        $isValid = $this->google2FA->verifyKey(
            secret: $secret,
            key: $code,
            window: 1
        );

        if (! $isValid) {
            throw new InvalidTotpCodeException;
        }
    }

    public function generateSecret(): string
    {
        return $this->google2FA->generateSecretKey();
    }

    public function provisioningUri(User $user): string
    {
        $secret = $user->totp_secret;

        if (! is_string($secret) || $secret === '') {
            throw new TotpNotConfiguredException;
        }

        return $this->google2FA->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );
    }
}
