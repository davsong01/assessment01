<?php

declare(strict_types=1);

namespace App\Actions\Security;

use App\Data\Security\ElevatedActionPayload;
use App\Exceptions\InvalidWalletSelectionException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Security\ElevatedActionTokenService;
use App\Services\Security\TotpService;

final readonly class CreateElevatedActionToken
{
    public function __construct(
        private TotpService $totpService,
        private ElevatedActionTokenService $tokenService
    ) {}

    /**
     * @return array{
     *     elevated_action_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     expires_at: string
     * }
     */
    public function execute(
        User $user,
        string $totpCode,
        ElevatedActionPayload $actionPayload
    ): array {
        $this->validateWallets(
            user: $user,
            actionPayload: $actionPayload
        );

        $this->totpService->verify(
            user: $user,
            code: $totpCode
        );

        $token = $this->tokenService->issue(
            userId: (int) $user->getKey(),
            actionPayload: $actionPayload
        );

        $ttl = config(
            'security.elevated_action_token.ttl_seconds',
            60
        );

        if (! is_int($ttl) || $ttl < 1) {
            $ttl = 60;
        }

        return [
            'elevated_action_token' => $token,
            'token_type' => 'ElevatedAction',
            'expires_in' => $ttl,
            'expires_at' => now()
                ->addSeconds($ttl)
                ->toIso8601String(),
        ];
    }

    private function validateWallets(
        User $user,
        ElevatedActionPayload $actionPayload
    ): void {
        $wallets = Wallet::query()
            ->whereIn('id', [
                $actionPayload->sourceWalletId,
                $actionPayload->destinationWalletId,
            ])
            ->get()
            ->keyBy('id');

        $sourceWallet = $wallets->get(
            $actionPayload->sourceWalletId
        );

        $destinationWallet = $wallets->get(
            $actionPayload->destinationWalletId
        );

        if (
            ! $sourceWallet instanceof Wallet
            || ! $destinationWallet instanceof Wallet
        ) {
            throw new InvalidWalletSelectionException(
                'One or more selected wallets do not exist.'
            );
        }

        $userId = (int) $user->getKey();

        if (
            (int) $sourceWallet->user_id !== $userId
            || (int) $destinationWallet->user_id !== $userId
        ) {
            throw new InvalidWalletSelectionException(
                'The selected wallets do not belong to the authenticated user.'
            );
        }
    }
}
