<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        LoginAction $action
    ): JsonResponse {
        try {
            $result = $action->execute(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                deviceName: $request->string('device_name')
                    ->value() ?: 'api-client',
            );
        } catch (InvalidCredentialsException) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
            ], 401);
        }

        return response()->json([
            'message' => 'Authentication successful.',
            'data' => [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                ],
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ],
        ]);
    }
}
