<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class LoginAction
{
    /**
     * @return array{
     *     user: array{
     *         id: int,
     *         name: string,
     *         email: string
     *     },
     *     token: string,
     *     token_type: string
     * }
     */
    public function execute(
        string $email,
        string $password,
        string $deviceName
    ): array {
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (
            ! $user instanceof User
            || ! Hash::check($password, $user->password)
        ) {
            throw new InvalidCredentialsException;
        }

        $token = $user
            ->createToken($deviceName)
            ->plainTextToken;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
