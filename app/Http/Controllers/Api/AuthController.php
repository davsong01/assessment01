<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        LoginAction $action
    ): JsonResponse {
        try {
            $result = $action->execute(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                deviceName: $request->filled('device_name')
                    ? $request->string('device_name')->toString()
                    : 'api-client'
            );
        } catch (InvalidCredentialsException) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
            ], 401);
        }

        return ApiResponse::success(
            message: 'Authentication successful.',
            data: $result
        );
    }
}
