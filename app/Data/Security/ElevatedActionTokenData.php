<?php

declare(strict_types=1);

namespace App\Data\Security;

use App\Enums\ElevatedAction;

final readonly class ElevatedActionTokenData
{
    public function __construct(
        public string $tokenId,
        public int $userId,
        public ElevatedAction $action,
        public string $actionHash,
        public int $issuedAt,
        public int $expiresAt
    ) {}

    /**
     * @return array{
     *     token_id: string,
     *     user_id: int,
     *     action: string,
     *     action_hash: string,
     *     issued_at: int,
     *     expires_at: int
     * }
     */
    public function toArray(): array
    {
        return [
            'token_id' => $this->tokenId,
            'user_id' => $this->userId,
            'action' => $this->action->value,
            'action_hash' => $this->actionHash,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /**
     * @param array{
     *     token_id: string,
     *     user_id: int,
     *     action: string,
     *     action_hash: string,
     *     issued_at: int,
     *     expires_at: int
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tokenId: $data['token_id'],
            userId: $data['user_id'],
            action: ElevatedAction::from($data['action']),
            actionHash: $data['action_hash'],
            issuedAt: $data['issued_at'],
            expiresAt: $data['expires_at']
        );
    }
}
