<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Settlement\SettlementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessSettlementWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [1, 5, 15];

    /**
     * @param array{
     *     provider_reference: string,
     *     transaction_reference: string|null,
     *     status: string
     * } $payload
     */
    public function __construct(
        public readonly array $payload
    ) {}

    public function handle(
        SettlementService $settlementService
    ): void {
        $settlementService->process(
            $this->payload
        );
    }
}
