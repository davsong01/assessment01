<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Contracts\Security\ElevatedActionTokenStore;
use App\Data\Security\ElevatedActionPayload;
use App\Data\Security\ElevatedActionTokenData;
use App\Exceptions\InvalidElevatedActionTokenException;
use JsonException;

final readonly class ElevatedActionTokenService
{
    public function __construct(
        private ElevatedActionTokenStore $store
    ) {}

    public function issue(
        int $userId,
        ElevatedActionPayload $actionPayload
    ): string {
        $secret = $this->secret();
        $ttl = $this->ttl();

        $issuedAt = now()->getTimestamp();
        $expiresAt = $issuedAt + $ttl;

        $claims = new ElevatedActionTokenData(
            tokenId: bin2hex(random_bytes(32)),
            userId: $userId,
            action: $actionPayload->action,
            actionHash: $actionPayload->hash(),
            issuedAt: $issuedAt,
            expiresAt: $expiresAt
        );

        $payload = $this->encodeClaims($claims);

        $signature = hash_hmac(
            'sha256',
            $payload,
            $secret
        );

        $this->store->put(
            token: $claims,
            ttlSeconds: $ttl
        );

        return $this->base64UrlEncode($payload)
            .'.'
            .$signature;
    }

    public function consume(
        string $token,
        int $authenticatedUserId,
        ElevatedActionPayload $actualPayload
    ): ElevatedActionTokenData {
        [$encodedPayload, $providedSignature] = $this->splitToken($token);

        $payload = $this->base64UrlDecode($encodedPayload);

        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            $this->secret()
        );

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidElevatedActionTokenException;
        }

        $claims = $this->decodeClaims($payload);

        if ($claims->expiresAt < now()->getTimestamp()) {
            throw new InvalidElevatedActionTokenException;
        }

        if ($claims->userId !== $authenticatedUserId) {
            throw new InvalidElevatedActionTokenException;
        }

        if (! hash_equals(
            $claims->actionHash,
            $actualPayload->hash()
        )) {
            throw new InvalidElevatedActionTokenException;
        }

        $storedClaims = $this->store->consume(
            $claims->tokenId
        );

        if (! $storedClaims instanceof ElevatedActionTokenData) {
            throw new InvalidElevatedActionTokenException;
        }

        if (
            $storedClaims->userId !== $claims->userId
            || ! hash_equals(
                $storedClaims->actionHash,
                $claims->actionHash
            )
            || $storedClaims->issuedAt !== $claims->issuedAt
            || $storedClaims->expiresAt !== $claims->expiresAt
            || ! hash_equals(
                $storedClaims->tokenId,
                $claims->tokenId
            )
        ) {
            throw new InvalidElevatedActionTokenException;
        }

        return $storedClaims;
    }

    private function secret(): string
    {
        $secret = config(
            'security.elevated_action_token.secret'
        );

        if (! is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException(
                'Elevated action token secret is not configured.'
            );
        }

        return $secret;
    }

    private function ttl(): int
    {
        $ttl = config(
            'security.elevated_action_token.ttl_seconds'
        );

        if (! is_int($ttl) || $ttl < 1) {
            throw new \RuntimeException(
                'Elevated action token TTL is invalid.'
            );
        }

        return $ttl;
    }

    private function encodeClaims(
        ElevatedActionTokenData $claims
    ): string {
        try {
            return json_encode(
                $claims->toArray(),
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            throw new \RuntimeException(
                'Unable to encode elevated action token.'
            );
        }
    }

    private function decodeClaims(
        string $payload
    ): ElevatedActionTokenData {
        try {
            $decoded = json_decode(
                $payload,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidElevatedActionTokenException;
        }

        if (! is_array($decoded)) {
            throw new InvalidElevatedActionTokenException;
        }

        if (
            ! isset(
                $decoded['token_id'],
                $decoded['user_id'],
                $decoded['action'],
                $decoded['action_hash'],
                $decoded['issued_at'],
                $decoded['expires_at']
            )
        ) {
            throw new InvalidElevatedActionTokenException;
        }

        try {
            return ElevatedActionTokenData::fromArray([
                'token_id' => (string) $decoded['token_id'],
                'user_id' => (int) $decoded['user_id'],
                'action' => (string) $decoded['action'],
                'action_hash' => (string) $decoded['action_hash'],
                'issued_at' => (int) $decoded['issued_at'],
                'expires_at' => (int) $decoded['expires_at'],
            ]);
        } catch (\ValueError) {
            throw new InvalidElevatedActionTokenException;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            throw new InvalidElevatedActionTokenException;
        }

        [$payload, $signature] = $parts;

        if (
            $payload === ''
            || ! preg_match('/^[a-f0-9]{64}$/', $signature)
        ) {
            throw new InvalidElevatedActionTokenException;
        }

        return [$payload, $signature];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(
            strtr($value, '-_', '+/'),
            true
        );

        if (! is_string($decoded)) {
            throw new InvalidElevatedActionTokenException;
        }

        return $decoded;
    }
}
