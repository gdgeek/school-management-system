<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Minimal Redis interface used by services.
 * Allows injecting either the real Redis extension or a test stub.
 */
interface RedisInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, mixed $options = null): mixed;
    public function setex(string $key, int $ttl, mixed $value): mixed;
    public function del(string ...$keys): int;
    public function expire(string $key, int $ttl): bool;
    public function exists(string ...$keys): int;

    /**
     * Atomically increment an integer counter stored at $key by $by.
     * Creates the key with value 0 before incrementing if it does not exist.
     *
     * @return int New value after increment
     */
    public function incrBy(string $key, int $by = 1): int;

    /**
     * Atomically increment a float counter stored at $key by $by.
     * Creates the key with value 0 before incrementing if it does not exist.
     *
     * @return float New value after increment
     */
    public function incrByFloat(string $key, float $by): float;

    /**
     * Return all keys matching the given pattern (e.g. "metrics:*").
     *
     * @return string[]
     */
    public function keys(string $pattern): array;
}
