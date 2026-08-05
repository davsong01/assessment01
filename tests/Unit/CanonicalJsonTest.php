<?php

declare(strict_types=1);

use App\Exceptions\InvalidActionPayloadException;
use App\Support\CanonicalJson;

it('produces the same hash regardless of associative key order', function (): void {
    $first = [
        'action' => 'swap',
        'source_wallet_id' => 'wallet-a',
        'destination_wallet_id' => 'wallet-b',
        'source_amount_subunits' => 100000,
    ];

    $second = [
        'source_amount_subunits' => 100000,
        'destination_wallet_id' => 'wallet-b',
        'action' => 'swap',
        'source_wallet_id' => 'wallet-a',
    ];

    expect(CanonicalJson::hash($first))
        ->toBe(CanonicalJson::hash($second));
});

it('preserves indexed array order', function (): void {
    $first = [
        'values' => ['one', 'two'],
    ];

    $second = [
        'values' => ['two', 'one'],
    ];

    expect(CanonicalJson::hash($first))
        ->not->toBe(CanonicalJson::hash($second));
});

it('rejects floating point values', function (): void {
    CanonicalJson::hash([
        'source_amount_subunits' => 100.50,
    ]);
})->throws(
    InvalidActionPayloadException::class,
    'Floating-point values are not allowed in action payloads.'
);

it('produces a lowercase sha256 hash', function (): void {
    $hash = CanonicalJson::hash([
        'action' => 'swap',
    ]);

    expect($hash)
        ->toHaveLength(64)
        ->toMatch('/^[a-f0-9]{64}$/');
});
