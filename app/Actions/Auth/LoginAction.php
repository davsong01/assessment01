<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    /**
     * @return array{
     *     user: User,
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
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
