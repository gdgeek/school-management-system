<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\RedisInterface;

/**
 * MetricsCollector
 *
 * Tracks per-endpoint request counts, cumulative response time, and error counts
 * using atomic Redis increments so concurrent requests never lose data.
 *
 * Redis key schema
 * ─────────────────────────────────────────────────────────────────────────────
 *   metrics:requests:{normalised_path}   – total request count  (integer)
 *   metrics:errors:{normalised_path}     – 4xx/5xx count        (integer)
 *   metrics:duration:{normalised_path}   – cumulative ms        (float stored as string)
 *
 * "normalised_path" strips the query string and replaces numeric path segments
 * with {id} so that /api/schools/1 and /api/schools/42 share the same bucket.
 */
class MetricsCollector
{
    private const PREFIX_REQUESTS = 'metrics:requests:';
    private const PREFIX_ERRORS   = 'metrics:errors:';
    private const PREFIX_DURATION = 'metrics:duration:';

    public function __construct(private RedisInterface $redis) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Record a completed request.
     *
     * @param string $path       Raw request path (e.g. /api/schools/5)
     * @param int    $statusCode HTTP response status code
     * @param float  $durationMs Wall-clock time in milliseconds
     */
    public function record(string $path, int $statusCode, float $durationMs): void
    {
        $key = $this->normalisePath($path);

        $this->redis->incrBy(self::PREFIX_REQUESTS . $key, 1);
        $this->redis->incrByFloat(self::PREFIX_DURATION . $key, $durationMs);

        if ($statusCode >= 400) {
            $this->redis->incrBy(self::PREFIX_ERRORS . $key, 1);
        }
    }

    /**
     * Return a summary of all collected metrics.
     *
     * @return array<string, array{requests: int, errors: int, total_duration_ms: float, avg_duration_ms: float}>
     */
    public function summary(): array
    {
        // Discover all tracked paths via the requests prefix
        $requestKeys = $this->redis->keys(self::PREFIX_REQUESTS . '*');

        $summary = [];

        foreach ($requestKeys as $requestKey) {
            $path     = substr($requestKey, strlen(self::PREFIX_REQUESTS));
            $requests = (int)($this->redis->get($requestKey) ?: 0);
            $errors   = (int)($this->redis->get(self::PREFIX_ERRORS . $path) ?: 0);
            $duration = (float)($this->redis->get(self::PREFIX_DURATION . $path) ?: 0.0);

            $summary[$path] = [
                'requests'          => $requests,
                'errors'            => $errors,
                'total_duration_ms' => round($duration, 2),
                'avg_duration_ms'   => $requests > 0 ? round($duration / $requests, 2) : 0.0,
            ];
        }

        // Sort by path for deterministic output
        ksort($summary);

        return $summary;
    }

    /**
     * Reset all metrics (useful for testing or scheduled resets).
     */
    public function reset(): void
    {
        foreach ([self::PREFIX_REQUESTS, self::PREFIX_ERRORS, self::PREFIX_DURATION] as $prefix) {
            $keys = $this->redis->keys($prefix . '*');
            if (!empty($keys)) {
                $this->redis->del(...$keys);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Normalise a raw path into a stable metrics bucket name.
     *
     * Examples:
     *   /api/schools/42        → api/schools/{id}
     *   /api/schools/42/groups → api/schools/{id}/groups
     *   /api/health            → api/health
     */
    public function normalisePath(string $path): string
    {
        // Strip leading slash and query string
        $path = ltrim(strtok($path, '?') ?: $path, '/');

        // Replace purely numeric segments with {id}
        $segments = explode('/', $path);
        $segments = array_map(
            static fn(string $s) => ctype_digit($s) ? '{id}' : $s,
            $segments
        );

        return implode('/', $segments);
    }
}
