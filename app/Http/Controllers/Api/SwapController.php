<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Swap\ExecuteSwap;
use App\Exceptions\InvalidElevatedActionTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Swap\CreateSwapRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SwapController extends Controller
{
    public function store(
        CreateSwapRequest $request,
        ExecuteSwap $action
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::unauthorized(
                'Authentication is required.'
            );
        }

        $token = $request->elevatedActionToken();

        if ($token === null) {
            throw new InvalidElevatedActionTokenException(
                'The elevated action token header is required.'
            );
        }

        $result = $action->execute(
            user: $user,
            payload: $request->actionPayload(),
            elevatedActionToken: $token
        );

        return ApiResponse::success(
            message: 'Swap completed successfully.',
            data: $result
        );
    }
}
