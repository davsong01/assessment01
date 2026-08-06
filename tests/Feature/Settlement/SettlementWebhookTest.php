<?php

declare(strict_types=1);

use App\Jobs\ProcessSettlementWebhook;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

function settlementSignature(string $body): string
{
    return hash_hmac(
        'sha256',
        $body,
        'local-settlement-secret'
    );
}

it('accepts a valid signed settlement webhook', function (): void {
    Queue::fake();

    config()->set(
        'services.settlement.webhook_secret',
        'local-settlement-secret'
    );

    $payload = [
        'provider_reference' => 'PROVIDER-TEST-001',
        'transaction_reference' => null,
        'status' => 'completed',
    ];

    $body = json_encode(
        $payload,
        JSON_THROW_ON_ERROR
    );

    $response = postJson(
        '/api/webhooks/settlement',
        $payload,
        [
            'X-Settlement-Signature' => settlementSignature($body),
        ]
    );

    $response
        ->assertAccepted()
        ->assertJsonPath('status', true);

    Queue::assertPushed(
        ProcessSettlementWebhook::class
    );
});

it('rejects an invalid settlement signature', function (): void {
    config()->set(
        'services.settlement.webhook_secret',
        'local-settlement-secret'
    );

    $payload = [
        'provider_reference' => 'PROVIDER-TEST-002',
        'transaction_reference' => null,
        'status' => 'completed',
    ];

    $response = postJson(
        '/api/webhooks/settlement',
        $payload,
        [
            'X-Settlement-Signature' => 'invalid-signature',
        ]
    );

    $response
        ->assertUnauthorized()
        ->assertJsonPath('status', false);
});
