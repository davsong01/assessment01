<?php

declare(strict_types=1);

use App\Contracts\Security\ElevatedActionTokenStore;
use App\Data\Security\ElevatedActionPayload;
use App\Data\Security\ElevatedActionTokenData;
use App\Enums\ElevatedAction;
use App\Exceptions\InvalidElevatedActionTokenException;
use App\Services\Security\ElevatedActionTokenService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config()->set(
        'security.elevated_action_token.secret',
        str_repeat('a', 64)
    );

    config()->set(
        'security.elevated_action_token.ttl_seconds',
        60
    );
});

function createTokenStore(): ElevatedActionTokenStore
{
    return new class implements ElevatedActionTokenStore
    {
        /**
         * @var array<string, ElevatedActionTokenData>
         */
        private array $tokens = [];

        public function put(
            ElevatedActionTokenData $token,
            int $ttlSeconds
        ): void {
            $this->tokens[$token->tokenId] = $token;
        }

        public function consume(
            string $tokenId
        ): ?ElevatedActionTokenData {
            $token = $this->tokens[$tokenId] ?? null;

            unset($this->tokens[$tokenId]);

            return $token;
        }
    };
}

function createActionPayload(
    int $amount = 100000
): ElevatedActionPayload {
    return new ElevatedActionPayload(
        action: ElevatedAction::Swap,
        sourceWalletId: '11111111-1111-4111-8111-111111111111',
        destinationWalletId: '22222222-2222-4222-8222-222222222222',
        sourceAmountSubunits: $amount
    );
}

it('issues and consumes an elevated action token', function (): void {
    $service = new ElevatedActionTokenService(
        createTokenStore()
    );

    $payload = createActionPayload();

    $token = $service->issue(
        userId: 10,
        actionPayload: $payload
    );

    $claims = $service->consume(
        token: $token,
        authenticatedUserId: 10,
        actualPayload: $payload
    );

    expect($claims->userId)->toBe(10)
        ->and($claims->action)->toBe(ElevatedAction::Swap)
        ->and($claims->actionHash)->toBe($payload->hash());
});

it('rejects token replay', function (): void {
    $service = new ElevatedActionTokenService(
        createTokenStore()
    );

    $payload = createActionPayload();

    $token = $service->issue(
        userId: 10,
        actionPayload: $payload
    );

    $service->consume(
        token: $token,
        authenticatedUserId: 10,
        actualPayload: $payload
    );

    $service->consume(
        token: $token,
        authenticatedUserId: 10,
        actualPayload: $payload
    );
})->throws(InvalidElevatedActionTokenException::class);

it('rejects a token for a changed amount', function (): void {
    $service = new ElevatedActionTokenService(
        createTokenStore()
    );

    $token = $service->issue(
        userId: 10,
        actionPayload: createActionPayload(100000)
    );

    $service->consume(
        token: $token,
        authenticatedUserId: 10,
        actualPayload: createActionPayload(200000)
    );
})->throws(InvalidElevatedActionTokenException::class);

it('rejects a token belonging to another user', function (): void {
    $service = new ElevatedActionTokenService(
        createTokenStore()
    );

    $payload = createActionPayload();

    $token = $service->issue(
        userId: 10,
        actionPayload: $payload
    );

    $service->consume(
        token: $token,
        authenticatedUserId: 20,
        actualPayload: $payload
    );
})->throws(InvalidElevatedActionTokenException::class);

it('rejects a token with an invalid signature', function (): void {
    $service = new ElevatedActionTokenService(
        createTokenStore()
    );

    $payload = createActionPayload();

    $token = $service->issue(
        userId: 10,
        actionPayload: $payload
    );

    $lastCharacter = substr($token, -1);

    $tampered = substr($token, 0, -1)
        .($lastCharacter === '0' ? '1' : '0');

    $service->consume(
        token: $tampered,
        authenticatedUserId: 10,
        actualPayload: $payload
    );
})->throws(InvalidElevatedActionTokenException::class);
it('rejects an expired token', function (): void {
    Carbon::setTestNow('2026-08-06 10:00:00');

    config()->set(
        'security.elevated_action_token.ttl_seconds',
        60
    );

    $service = new ElevatedActionTokenService(
        createTokenStore()
    );

    $payload = createActionPayload();

    $token = $service->issue(
        userId: 10,
        actionPayload: $payload
    );

    Carbon::setTestNow('2026-08-06 10:01:01');

    $service->consume(
        token: $token,
        authenticatedUserId: 10,
        actualPayload: $payload
    );
})->throws(InvalidElevatedActionTokenException::class);
