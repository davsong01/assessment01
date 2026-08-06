<?php

declare(strict_types=1);

namespace App\Jobs\ExchangeRate;

use App\Enums\Currency;
use App\Services\ExchangeRate\ExchangeRateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RefreshExchangeRate implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @var list<int>
     */
    public array $backoff = [1, 3];

    public function __construct(
        public readonly Currency $from,
        public readonly Currency $to
    ) {}

    public function handle(
        ExchangeRateService $service
    ): void {
        $service->refresh(
            from: $this->from,
            to: $this->to
        );
    }
}
