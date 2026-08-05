<?php

declare(strict_types=1);

namespace App\Data\Security;

use App\Enums\ElevatedAction;
use App\Support\CanonicalJson;

final readonly class ElevatedActionPayload
{
    public function __construct(
        public ElevatedAction $action,
        public string $sourceWalletId,
        public string $destinationWalletId,
        public int $sourceAmountSubunits
    ) {}

    /**
     * @param array{
     *     action: string,
     *     source_wallet_id: string,
     *     destination_wallet_id: string,
     *     source_amount_subunits: int
     * } $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            action: ElevatedAction::from($payload['action']),
            sourceWalletId: $payload['source_wallet_id'],
            destinationWalletId: $payload['destination_wallet_id'],
            sourceAmountSubunits: $payload['source_amount_subunits']
        );
    }

    /**
     * @return array{
     *     action: string,
     *     source_wallet_id: string,
     *     destination_wallet_id: string,
     *     source_amount_subunits: int
     * }
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action->value,
            'source_wallet_id' => $this->sourceWalletId,
            'destination_wallet_id' => $this->destinationWalletId,
            'source_amount_subunits' => $this->sourceAmountSubunits,
        ];
    }

    public function hash(): string
    {
        return CanonicalJson::hash($this->toArray());
    }
}
