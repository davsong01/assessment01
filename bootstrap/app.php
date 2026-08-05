<?php

use App\Enums\ErrorCode;
use App\Exceptions\InvalidActionPayloadException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidTotpCodeException;
use App\Exceptions\TotpNotConfiguredException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*')
                || $request->expectsJson()
        );
        $exceptions->render(
            function (
                InvalidActionPayloadException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::failure(
                    message: $exception->getMessage(),
                    code: ErrorCode::InvalidAction,
                    status: 422
                );
            }
        );

        $exceptions->render(
            function (
                InvalidTotpCodeException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::failure(
                    message: $exception->getMessage(),
                    code: ErrorCode::TwoFactorAuthentication,
                    status: 401
                );
            }
        );

        $exceptions->render(
            function (
                TotpNotConfiguredException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::failure(
                    message: $exception->getMessage(),
                    code: ErrorCode::TwoFactorAuthentication,
                    status: 422
                );
            }
        );
        $exceptions->render(
            function (
                ValidationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $exception->errors(),
                ], $exception->status);
            }
        );

        $exceptions->render(
            function (
                AuthenticationException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::unauthorized(
                    'Authentication is required.'
                );
            }
        );

        $exceptions->render(
            function (
                InvalidCredentialsException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::unauthorized(
                    $exception->getMessage()
                );
            }
        );

        $exceptions->render(
            function (
                ModelNotFoundException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::notFound();
            }
        );

        $exceptions->render(
            function (
                NotFoundHttpException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::notFound(
                    'API endpoint not found.'
                );
            }
        );

        $exceptions->render(
            function (
                TooManyRequestsHttpException $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::tooManyRequests();
            }
        );

        $exceptions->render(
            function (
                Throwable $exception,
                Request $request
            ) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::serverError(
                    exception: $exception
                );
            }
        );
    })->booting(function (): void {
        RateLimiter::for(
            'login',
            function (Request $request): Limit {
                $email = Str::lower(
                    (string) $request->input('email')
                );

                return Limit::perMinute(5)
                    ->by($email.'|'.$request->ip());
            }
        );
    })->create();
