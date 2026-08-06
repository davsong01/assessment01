<?php

declare(strict_types=1);

namespace App\Services\Locks;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use RuntimeException;

final readonly class RedisLockService
{
    public function __construct(
        private CacheManager $cacheManager
    ) {}

    public function create(
        string $name,
        int $ttlSeconds,
        ?string $owner = null
    ): Lock {
        $store = $this->cacheManager
            ->store('redis')
            ->getStore();

        if (! $store instanceof LockProvider) {
            throw new RuntimeException(
                'The configured Redis cache store does not support locks.'
            );
        }

        return $store->lock(
            name: $name,
            seconds: $ttlSeconds,
            owner: $owner
        );
    }
}
