<?php

declare(strict_types=1);

namespace App\Services\ExchangeRate;

use App\Data\ExchangeRate\ExchangeRateData;
use App\Enums\Currency;
use App\Jobs\ExchangeRate\RefreshExchangeRate;
use App\Services\Cache\CacheService;
use App\Services\ExchangeRate\Contracts\ExchangeRateProvider;
use App\Services\Locks\RedisLockService;
use Throwable;

final readonly class ExchangeRateService
{
    public function __construct(
        private CacheService $cache,
        private RedisLockService $locks,
        private ExchangeRateProvider $provider
    ) {}

    public function get(
        Currency $from,
        Currency $to
    ): ExchangeRateData {
        if ($from === $to) {
            return new ExchangeRateData(
                from: $from,
                to: $to,
                rate: '1',
                fetchedAt: now()->getTimestamp()
            );
        }

        $cached = $this->cachedRate($from, $to);

        if ($cached instanceof ExchangeRateData) {
            $age = now()->getTimestamp() - $cached->fetchedAt;

            if ($age <= $this->freshSeconds()) {
                return $cached;
            }

            if ($age <= $this->staleSeconds()) {
                $this->dispatchRefresh($from, $to);

                return $cached;
            }
        }

        return $this->refresh($from, $to);
    }

    public function refresh(
        Currency $from,
        Currency $to
    ): ExchangeRateData {
        $rate = $this->provider->fetch($from, $to);

        $this->cache->put(
            key: $this->cacheKey($from, $to),
            value: $rate->toArray(),
            ttlSeconds: $this->staleSeconds()
        );

        return $rate;
    }

    private function dispatchRefresh(
        Currency $from,
        Currency $to
    ): void {
        $lock = $this->locks->create(
            name: $this->refreshLockKey($from, $to),
            ttlSeconds: $this->refreshLockSeconds()
        );

        if (! $lock->get()) {
            return;
        }

        RefreshExchangeRate::dispatch(
            from: $from,
            to: $to
        );

        // Intentionally not released here.
        // The short TTL prevents duplicate refresh jobs.
    }

    private function cachedRate(
        Currency $from,
        Currency $to
    ): ?ExchangeRateData {
        try {
            $cached = $this->cache->get(
                $this->cacheKey($from, $to)
            );
        } catch (Throwable) {
            return null;
        }

        if (! is_array($cached)) {
            return null;
        }

        if (
            ! isset(
                $cached['from'],
                $cached['to'],
                $cached['rate'],
                $cached['fetched_at']
            )
        ) {
            return null;
        }

        try {
            return ExchangeRateData::fromArray([
                'from' => (string) $cached['from'],
                'to' => (string) $cached['to'],
                'rate' => (string) $cached['rate'],
                'fetched_at' => (int) $cached['fetched_at'],
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function cacheKey(
        Currency $from,
        Currency $to
    ): string {
        return sprintf(
            'exchange-rate:%s:%s',
            $from->value,
            $to->value
        );
    }

    private function refreshLockKey(
        Currency $from,
        Currency $to
    ): string {
        return sprintf(
            'exchange-rate-refresh:%s:%s',
            $from->value,
            $to->value
        );
    }

    private function freshSeconds(): int
    {
        $seconds = config(
            'exchange-rate.fresh_seconds',
            30
        );

        return is_int($seconds) && $seconds > 0
            ? $seconds
            : 30;
    }

    private function staleSeconds(): int
    {
        $seconds = config(
            'exchange-rate.stale_seconds',
            300
        );

        return is_int($seconds)
            && $seconds > $this->freshSeconds()
                ? $seconds
                : 300;
    }

    private function refreshLockSeconds(): int
    {
        $seconds = config(
            'exchange-rate.refresh_lock_seconds',
            15
        );

        return is_int($seconds) && $seconds > 0
            ? $seconds
            : 15;
    }
}
