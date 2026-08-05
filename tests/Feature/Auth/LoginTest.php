<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\postJson;

it('authenticates a user and returns a sanctum token', function (): void {
    $user = User::factory()->create([
        'email' => 'davsong@gmail.com',
        'password' => Hash::make('password'),
    ]);

    $response = postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'pest',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath(
            'message',
            'Authentication successful.'
        )
        ->assertJsonPath(
            'data.user.id',
            $user->id
        )
        ->assertJsonPath(
            'data.user.email',
            $user->email
        )
        ->assertJsonPath(
            'data.token_type',
            'Bearer'
        )
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'token',
                'token_type',
            ],
        ]);

    expect(
        PersonalAccessToken::query()->count()
    )->toBe(1);
});

it('rejects an invalid password', function (): void {
    User::factory()->create([
        'email' => 'davsong@gmail.com',
        'password' => Hash::make('password'),
    ]);

    postJson('/api/login', [
        'email' => 'davsong@gmail.com',
        'password' => 'incorrect-password',
    ])
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'The provided credentials are invalid.',
        ]);
});

it('does not reveal whether the email exists', function (): void {
    $existingUser = User::factory()->create([
        'email' => 'davsong@gmail.com',
        'password' => Hash::make('password'),
    ]);

    $invalidPasswordResponse = postJson('/api/login', [
        'email' => $existingUser->email,
        'password' => 'wrong-password',
    ]);

    $unknownEmailResponse = postJson('/api/login', [
        'email' => 'unknown@example.com',
        'password' => 'wrong-password',
    ]);

    $invalidPasswordResponse->assertUnauthorized();
    $unknownEmailResponse->assertUnauthorized();

    expect($invalidPasswordResponse->json())
        ->toBe($unknownEmailResponse->json());
});

it('validates the login request', function (): void {
    postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
            'password',
        ]);
});

it('rate limits repeated login attempts', function (): void {
    User::factory()->create([
        'email' => 'davsong@gmail.com',
        'password' => Hash::make('password'),
    ]);

    foreach (range(1, 5) as $attempt) {
        postJson('/api/login', [
            'email' => 'davsong@gmail.com',
            'password' => 'incorrect-password',
        ])->assertUnauthorized();
    }

    postJson('/api/login', [
        'email' => 'davsong@gmail.com',
        'password' => 'incorrect-password',
    ])->assertStatus(429);
});
