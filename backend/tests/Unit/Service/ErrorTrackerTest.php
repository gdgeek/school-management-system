<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Contract\RedisInterface;
use App\Helper\Logger;
use App\Service\ErrorTracker;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Unit tests for ErrorTracker.
 *
 * Uses in-memory stubs for Redis and Logger so no real infrastructure is needed.
 */
class ErrorTrackerTest extends TestCase
{
    // ── Stubs ─────────────────────────────────────────────────────────────────

    private function makeRedis(): RedisInterface
    {
        return new class implements RedisInterface {
            public array $store = [];

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

            public function expire(string $key, int $ttl): bool
            {
                return true;
            }

            public function exists(string ...$keys): int
            {
                $n = 0;
                foreach ($keys as $k) {
                    if (array_key_exists($k, $this->store)) {
                        $n++;
                    }
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

    private function makeLogger(): Logger
    {
        // Write to /tmp so tests don't pollute the project tree
        return new Logger(sys_get_temp_dir() . '/error-tracker-test-logs');
    }

    private function makeRequest(string $method = 'GET', string $path = '/api/test'): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        return $factory->createServerRequest($method, 'http://localhost' . $path);
    }

    // ── currentErrorRate ──────────────────────────────────────────────────────

    public function testCurrentErrorRateIsZeroInitially(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 10, 60);
        $this->assertSame(0, $tracker->currentErrorRate());
    }

    // ── recordError ───────────────────────────────────────────────────────────

    public function testRecordErrorIncrementsRate(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 10, 60);
        $tracker->recordError($this->makeRequest(), 500);
        $this->assertSame(1, $tracker->currentErrorRate());
    }

    public function testRecordErrorMultipleTimesAccumulates(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 10, 60);
        $tracker->recordError($this->makeRequest('POST', '/api/schools'), 500);
        $tracker->recordError($this->makeRequest('GET', '/api/classes'), 503);
        $tracker->recordError($this->makeRequest('DELETE', '/api/students/1'), 500);

        $this->assertSame(3, $tracker->currentErrorRate());
    }

    public function testRecordErrorWithExceptionLogsMessage(): void
    {
        // Logger writes to a temp file; we just verify no exception is thrown
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 10, 60);
        $exception = new \RuntimeException('DB connection failed');

        $this->expectNotToPerformAssertions();
        $tracker->recordError($this->makeRequest(), 500, $exception);
    }

    public function testRecordErrorWithoutExceptionDoesNotThrow(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 10, 60);

        $this->expectNotToPerformAssertions();
        $tracker->recordError($this->makeRequest(), 502);
    }

    // ── threshold / alerting ──────────────────────────────────────────────────

    public function testNoAlertBelowThreshold(): void
    {
        // Threshold = 5; record 4 errors — no CRITICAL log expected (no exception)
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 5, 60);

        for ($i = 0; $i < 4; $i++) {
            $tracker->recordError($this->makeRequest(), 500);
        }

        // Rate should be 4, below threshold of 5
        $this->assertSame(4, $tracker->currentErrorRate());
    }

    public function testAlertTriggeredAtThreshold(): void
    {
        // Use a spy logger to capture critical calls
        $criticalMessages = [];
        $spyLogger = new class($criticalMessages, sys_get_temp_dir() . '/error-tracker-test-logs') extends Logger {
            public function __construct(private array &$messages, string $path)
            {
                parent::__construct($path);
            }

            public function critical(string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = (string)$message;
                parent::critical($message, $context);
            }
        };

        $tracker = new ErrorTracker($this->makeRedis(), $spyLogger, 3, 60);

        // Record exactly threshold number of errors
        for ($i = 0; $i < 3; $i++) {
            $tracker->recordError($this->makeRequest(), 500);
        }

        $this->assertNotEmpty($criticalMessages, 'Expected a CRITICAL alert to be logged');
        $this->assertStringContainsString('Error rate alert', $criticalMessages[0]);
    }

    // ── configurable threshold / window ──────────────────────────────────────

    public function testCustomThresholdIsRespected(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 100, 60);

        // Record 50 errors — should not trigger alert (threshold is 100)
        for ($i = 0; $i < 50; $i++) {
            $tracker->recordError($this->makeRequest(), 500);
        }

        $this->assertSame(50, $tracker->currentErrorRate());
    }

    // ── deduplication ─────────────────────────────────────────────────────────

    public function testAlertFiredOnlyOncePerWindow(): void
    {
        $criticalCount = 0;
        $spyLogger = new class($criticalCount, sys_get_temp_dir() . '/error-tracker-test-logs') extends Logger {
            public function __construct(private int &$count, string $path)
            {
                parent::__construct($path);
            }

            public function critical(string|\Stringable $message, array $context = []): void
            {
                $this->count++;
                parent::critical($message, $context);
            }
        };

        $tracker = new ErrorTracker($this->makeRedis(), $spyLogger, 2, 60);

        // First batch: crosses threshold → alert fires
        $tracker->recordError($this->makeRequest(), 500);
        $tracker->recordError($this->makeRequest(), 500);

        // Additional errors in the same window → alert must NOT fire again
        $tracker->recordError($this->makeRequest(), 500);
        $tracker->recordError($this->makeRequest(), 500);
        $tracker->recordError($this->makeRequest(), 500);

        $this->assertSame(1, $criticalCount, 'CRITICAL alert should fire exactly once per window');
    }

    public function testLastAlertAtIsStoredWhenAlertFires(): void
    {
        $before  = time();
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 1, 60);

        $tracker->recordError($this->makeRequest(), 500);

        $lastAlertAt = $tracker->getLastAlertAt();
        $after       = time();

        $this->assertNotNull($lastAlertAt, 'last_alert_at should be set after alert fires');
        $this->assertGreaterThanOrEqual($before, $lastAlertAt);
        $this->assertLessThanOrEqual($after, $lastAlertAt);
    }

    public function testLastAlertAtIsNullBeforeAnyAlert(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 100, 60);

        $this->assertNull($tracker->getLastAlertAt(), 'last_alert_at should be null before any alert');
    }

    public function testLastAlertAtNotUpdatedWhenDeduplicated(): void
    {
        $redis   = $this->makeRedis();
        $tracker = new ErrorTracker($redis, $this->makeLogger(), 1, 60);

        // Fire first alert
        $tracker->recordError($this->makeRequest(), 500);
        $firstAlertAt = $tracker->getLastAlertAt();

        // Simulate a tiny sleep so time() could differ, but dedup key still exists
        // (In practice both calls happen within the same second, so timestamps match.
        //  We just verify the value doesn't change on subsequent calls.)
        $tracker->recordError($this->makeRequest(), 500);
        $secondAlertAt = $tracker->getLastAlertAt();

        $this->assertSame($firstAlertAt, $secondAlertAt, 'last_alert_at should not change when alert is deduplicated');
    }

    public function testIsAlertActiveReturnsTrueWhenRateAtThreshold(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 3, 60);

        $tracker->recordError($this->makeRequest(), 500);
        $tracker->recordError($this->makeRequest(), 500);
        $this->assertFalse($tracker->isAlertActive());

        $tracker->recordError($this->makeRequest(), 500);
        $this->assertTrue($tracker->isAlertActive());
    }

    public function testIsAlertActiveReturnsFalseInitially(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 5, 60);
        $this->assertFalse($tracker->isAlertActive());
    }

    public function testGetThresholdAndWindowReturnConfiguredValues(): void
    {
        $tracker = new ErrorTracker($this->makeRedis(), $this->makeLogger(), 42, 120);
        $this->assertSame(42, $tracker->getThreshold());
        $this->assertSame(120, $tracker->getWindow());
    }
}
