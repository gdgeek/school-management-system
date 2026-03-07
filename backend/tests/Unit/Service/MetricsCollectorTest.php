<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Contract\RedisInterface;
use App\Service\MetricsCollector;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MetricsCollector.
 *
 * Uses an in-memory Redis stub so no real Redis connection is needed.
 */
class MetricsCollectorTest extends TestCase
{
    // ── Minimal in-memory Redis stub ─────────────────────────────────────────

    private function makeRedis(): RedisInterface
    {
        return new class implements RedisInterface {
            private array $store = [];

            public function get(string $key): mixed
            {
                return $this->store[$key] ?? false;
            }

            public function set(string $key, mixed $value, mixed $options = null): mixed
            {
                $this->store[$key] = $value;
                return true;
            }

            public function setex(string $key, int $ttl, mixed $value): mixed
            {
                $this->store[$key] = $value;
                return true;
            }

            public function del(string ...$keys): int
            {
                $n = 0;
                foreach ($keys as $k) {
                    if (array_key_exists($k, $this->store)) {
                        unset($this->store[$k]);
                        $n++;
                    }
                }
                return $n;
            }

            public function expire(string $key, int $ttl): bool { return true; }

            public function exists(string ...$keys): int
            {
                $n = 0;
                foreach ($keys as $k) {
                    if (array_key_exists($k, $this->store)) $n++;
                }
                return $n;
            }

            public function incrBy(string $key, int $by = 1): int
            {
                $this->store[$key] = (int)($this->store[$key] ?? 0) + $by;
                return $this->store[$key];
            }

            public function incrByFloat(string $key, float $by): float
            {
                $this->store[$key] = (float)($this->store[$key] ?? 0.0) + $by;
                return $this->store[$key];
            }

            public function keys(string $pattern): array
            {
                $regex = '/^' . str_replace(
                    ['\\*', '\\?'],
                    ['.*', '.'],
                    preg_quote($pattern, '/')
                ) . '$/';
                return array_values(
                    array_filter(array_keys($this->store), fn($k) => (bool)preg_match($regex, $k))
                );
            }
        };
    }

    // ── normalisePath ─────────────────────────────────────────────────────────

    public function testNormalisePathStripsLeadingSlash(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $this->assertSame('api/health', $c->normalisePath('/api/health'));
    }

    public function testNormalisePathStripsQueryString(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $this->assertSame('api/schools', $c->normalisePath('/api/schools?page=2&limit=10'));
    }

    public function testNormalisePathReplacesNumericSegments(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $this->assertSame('api/schools/{id}', $c->normalisePath('/api/schools/42'));
        $this->assertSame('api/schools/{id}/groups', $c->normalisePath('/api/schools/99/groups'));
    }

    public function testNormalisePathKeepsNonNumericSegments(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $this->assertSame('api/auth/login', $c->normalisePath('/api/auth/login'));
    }

    // ── record ────────────────────────────────────────────────────────────────

    public function testRecordIncrementsRequestCount(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/health', 200, 5.0);
        $c->record('/api/health', 200, 3.0);

        $summary = $c->summary();
        $this->assertSame(2, $summary['api/health']['requests']);
    }

    public function testRecordAccumulatesDuration(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/health', 200, 4.0);
        $c->record('/api/health', 200, 6.0);

        $summary = $c->summary();
        $this->assertEqualsWithDelta(10.0, $summary['api/health']['total_duration_ms'], 0.01);
    }

    public function testRecordCountsClientErrors(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/schools', 404, 2.0);
        $c->record('/api/schools', 200, 2.0);

        $summary = $c->summary();
        $this->assertSame(1, $summary['api/schools']['errors']);
    }

    public function testRecordCountsServerErrors(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/schools', 500, 2.0);

        $summary = $c->summary();
        $this->assertSame(1, $summary['api/schools']['errors']);
    }

    public function testRecordDoesNotCountSuccessAsError(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/health', 200, 1.0);
        $c->record('/api/health', 201, 1.0);
        $c->record('/api/health', 304, 1.0);

        $summary = $c->summary();
        $this->assertSame(0, $summary['api/health']['errors']);
    }

    // ── summary ───────────────────────────────────────────────────────────────

    public function testSummaryReturnsEmptyArrayWhenNoRequests(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $this->assertSame([], $c->summary());
    }

    public function testSummaryComputesAverageDuration(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/health', 200, 10.0);
        $c->record('/api/health', 200, 20.0);

        $summary = $c->summary();
        $this->assertEqualsWithDelta(15.0, $summary['api/health']['avg_duration_ms'], 0.01);
    }

    public function testSummaryGroupsNumericPathsTogether(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/schools/1', 200, 5.0);
        $c->record('/api/schools/99', 200, 5.0);

        $summary = $c->summary();
        // Both should land in the same bucket
        $this->assertArrayHasKey('api/schools/{id}', $summary);
        $this->assertSame(2, $summary['api/schools/{id}']['requests']);
    }

    public function testSummaryIsSortedByPath(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/schools', 200, 1.0);
        $c->record('/api/auth/login', 200, 1.0);
        $c->record('/api/health', 200, 1.0);

        $keys = array_keys($c->summary());
        $sorted = $keys;
        sort($sorted);
        $this->assertSame($sorted, $keys);
    }

    // ── reset ─────────────────────────────────────────────────────────────────

    public function testResetClearsAllMetrics(): void
    {
        $c = new MetricsCollector($this->makeRedis());
        $c->record('/api/health', 200, 5.0);
        $c->record('/api/schools', 500, 2.0);

        $c->reset();

        $this->assertSame([], $c->summary());
    }
}
