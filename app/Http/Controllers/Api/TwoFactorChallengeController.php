<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Security\CreateElevatedActionToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Security\CreateTwoFactorChallengeRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TwoFactorChallengeController extends Controller
{
    public function store(
        CreateTwoFactorChallengeRequest $request,
        CreateElevatedActionToken $action
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::unauthorized(
                'Authentication is required.'
            );
        }

        $result = $action->execute(
            user: $user,
            totpCode: $request->totpCode(),
            actionPayload: $request->actionPayload()
        );

        return ApiResponse::success(
            message: 'Elevated action token issued successfully.',
            data: $result
        );
    }
}
