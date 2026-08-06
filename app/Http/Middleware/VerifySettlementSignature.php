<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifySettlementSignature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $secret = config(
            'services.settlement.webhook_secret'
        );

        if (! is_string($secret) || $secret === '') {
            return ApiResponse::serverError();
        }

        $providedSignature = $request->header(
            'X-Settlement-Signature'
        );

        if (
            ! is_string($providedSignature)
            || $providedSignature === ''
        ) {
            return $this->invalidSignatureResponse();
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            $secret
        );

        if (
            ! hash_equals(
                $expectedSignature,
                $providedSignature
            )
        ) {
            return $this->invalidSignatureResponse();
        }

        return $next($request);
    }

    private function invalidSignatureResponse(): JsonResponse
    {
        return ApiResponse::unauthorized(
            'The settlement webhook signature is invalid.'
        );
    }
}
