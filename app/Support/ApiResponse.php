<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Throwable;

final class ApiResponse
{
    public static function success(
        string $message,
        mixed $data = [],
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function created(
        string $message,
        mixed $data = []
    ): JsonResponse {
        return self::success($message, $data, 201);
    }

    public static function accepted(
        string $message,
        mixed $data = []
    ): JsonResponse {
        return self::success($message, $data, 202);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json([], 204);
    }

    /**
     * @param  MessageBag|array<string, array<int, string>|string>  $errors
     */
    public static function validation(
        string $message = 'Validation failed.',
        MessageBag|array $errors = []
    ): JsonResponse {
        if ($errors instanceof MessageBag) {
            $errors = $errors->toArray();
        }

        return self::error(
            message: $message,
            code: ErrorCode::Validation,
            details: $errors,
            status: 422
        );
    }

    public static function unauthorized(
        string $message = 'Unauthorized.'
    ): JsonResponse {
        return self::error(
            message: $message,
            code: ErrorCode::Unauthorized,
            status: 401
        );
    }

    public static function forbidden(
        string $message = 'Forbidden.'
    ): JsonResponse {
        return self::error(
            message: $message,
            code: ErrorCode::Forbidden,
            status: 403
        );
    }

    public static function notFound(
        string $message = 'Resource not found.'
    ): JsonResponse {
        return self::error(
            message: $message,
            code: ErrorCode::NotFound,
            status: 404
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function conflict(
        string $message,
        array $details = []
    ): JsonResponse {
        return self::error(
            message: $message,
            code: ErrorCode::Conflict,
            details: $details,
            status: 409
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function unprocessable(
        string $message,
        array $details = []
    ): JsonResponse {
        return self::error(
            message: $message,
            code: ErrorCode::Unprocessable,
            details: $details,
            status: 422
        );
    }

    public static function tooManyRequests(
        string $message = 'Too many requests.'
    ): JsonResponse {
        return self::error(
            message: $message,
            code: ErrorCode::TooManyRequests,
            status: 429
        );
    }

    public static function serverError(
        Throwable|string|null $exception = null,
        string $message = 'An unexpected server error occurred.'
    ): JsonResponse {
        $reference = (string) Str::ulid();

        if ($exception instanceof Throwable) {
            logger()->error($exception->getMessage(), [
                'reference' => $reference,
                'exception' => $exception,
            ]);
        } elseif (is_string($exception) && $exception !== '') {
            logger()->error($exception, [
                'reference' => $reference,
            ]);
        }

        $error = [
            'type' => ErrorCode::ServerError->value,
            'reference' => $reference,
        ];

        if (
            config('app.debug')
            && $exception instanceof Throwable
        ) {
            $error['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return response()->json([
            'status' => false,
            'message' => $message,
            'error' => $error,
        ], 500);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function failure(
        string $message,
        ErrorCode $code,
        array $details = [],
        int $status = 400
    ): JsonResponse {
        return self::error(
            message: $message,
            code: $code,
            details: $details,
            status: $status
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function error(
        string $message,
        ErrorCode $code,
        array $details = [],
        int $status = 400
    ): JsonResponse {
        return response()->json([
            'status' => false,
            'message' => $message,
            'error' => [
                'type' => $code->value,
                'details' => (object) $details,
            ],
        ], $status);
    }
}
