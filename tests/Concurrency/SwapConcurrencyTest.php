<?php

declare(strict_types=1);

use Database\Seeders\ConcurrencyTestSeeder;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use PragmaRX\Google2FA\Google2FA;

it('allows exactly one of ten concurrent swaps', function (): void {
    $client = new Client([
        'base_uri' => rtrim(
            (string) config('app.url'),
            '/'
        ).'/',
        'timeout' => 15,
        'http_errors' => false,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ]);

    $loginResponse = $client->post('api/login', [
        'json' => [
            'email' => ConcurrencyTestSeeder::EMAIL,
            'password' => ConcurrencyTestSeeder::PASSWORD,
            'device_name' => 'Concurrency Test',
        ],
    ]);

    expect($loginResponse->getStatusCode())->toBe(200);

    /** @var array{
     *     data: array{token: string}
     * } $loginBody
     */
    $loginBody = json_decode(
        (string) $loginResponse->getBody(),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $bearerToken = $loginBody['data']['token'];

    $payload = [
        'action' => 'swap',
        'source_wallet_id' => ConcurrencyTestSeeder::NGN_WALLET_ID,
        'destination_wallet_id' => ConcurrencyTestSeeder::CNY_WALLET_ID,
        'source_amount_subunits' => ConcurrencyTestSeeder::SWAP_AMOUNT_SUBUNITS,
    ];

    $totpCode = app(Google2FA::class)->getCurrentOtp(
        ConcurrencyTestSeeder::TOTP_SECRET
    );

    $elevatedTokens = [];

    for ($index = 0; $index < 10; $index++) {
        $response = $client->post('api/2fa/challenge', [
            'headers' => [
                'Authorization' => 'Bearer '.$bearerToken,
            ],
            'json' => [
                'totp_code' => $totpCode,
                'action_payload' => $payload,
            ],
        ]);

        expect($response->getStatusCode())->toBe(200);

        /** @var array{
         *     data: array{
         *         elevated_action_token: string
         *     }
         * } $body
         */
        $body = json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $elevatedTokens[] =
            $body['data']['elevated_action_token'];
    }

    $promises = array_map(
        fn (string $token) => $client->postAsync(
            'api/swap',
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$bearerToken,
                    'X-Elevated-Action-Token' => $token,
                ],
                'json' => $payload,
            ]
        ),
        $elevatedTokens
    );

    $responses = Utils::settle($promises)->wait();

    $statusCodes = [];

    foreach ($responses as $response) {
        expect($response['state'])->toBe('fulfilled');

        $statusCodes[] =
            $response['value']->getStatusCode();
    }

    $successes = count(
        array_filter(
            $statusCodes,
            static fn (int $status): bool => $status === 200
        )
    );

    $failures = count(
        array_filter(
            $statusCodes,
            static fn (int $status): bool => in_array($status, [409, 422], true)
        )
    );

    expect($successes)
        ->toBe(1)
        ->and($failures)
        ->toBe(9);
});
