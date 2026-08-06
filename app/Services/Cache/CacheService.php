<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Psr\SimpleCache\InvalidArgumentException;

readonly class CacheService
{
    public function __construct(
        private Repository $cache
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        return $this->cache->get($key, $default);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function put(
        string $key,
        mixed $value,
        int $ttlSeconds
    ): bool {
        return $this->cache->put(
            $key,
            $value,
            $ttlSeconds
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forget(string $key): bool
    {
        return $this->cache->forget($key);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     *
     * @throws InvalidArgumentException
     */
    public function remember(
        string $key,
        int $ttlSeconds,
        Closure $callback
    ): mixed {
        return $this->cache->remember(
            $key,
            $ttlSeconds,
            $callback
        );
    }
}
