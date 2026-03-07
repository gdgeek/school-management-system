<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\RedisInterface;
use App\Helper\Logger;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ErrorTracker
 *
 * Tracks 5xx errors with full context and triggers CRITICAL alerts when the
 * error rate exceeds a configurable threshold within a sliding time window.
 *
 * Redis key schema
 * ─────────────────────────────────────────────────────────────────────────────
 *   errors:rate:{timestamp_bucket}   – count of 5xx errors in that second
 *
 * The sliding window is implemented by storing one key per second and summing
 * the counts across the window.  Each key expires automatically after the
 * window duration so Redis stays clean.
 */
class ErrorTracker
{
    private const KEY_PREFIX       = 'errors:rate:';
    private const KEY_ALERT_SENT   = 'errors:alert_sent:';
    private const KEY_LAST_ALERT   = 'errors:last_alert_at';

    private int $threshold;
    private int $window;

    public function __construct(
        private RedisInterface $redis,
        private Logger $logger,
        int $threshold = 0,
        int $window = 0
    ) {
        // Allow override via constructor (useful for tests); fall back to env vars
        $this->threshold = $threshold > 0
            ? $threshold
            : (int)($_ENV['ERROR_RATE_THRESHOLD'] ?? 10);

        $this->window = $window > 0
            ? $window
            : (int)($_ENV['ERROR_RATE_WINDOW'] ?? 60);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Record a 5xx error with full request context.
     *
     * @param ServerRequestInterface $request   The request that caused the error
     * @param int                    $status    HTTP status code (should be 5xx)
     * @param \Throwable|null        $exception The exception that caused the error, if any
     */
    public function recordError(
        ServerRequestInterface $request,
        int $status,
        ?\Throwable $exception = null
    ): void {
        $method = $request->getMethod();
        $path   = $request->getUri()->getPath();

        $context = [
            'method'    => $method,
            'path'      => $path,
            'status'    => $status,
            'exception' => $exception ? $exception->getMessage() : null,
        ];

        $this->logger->error(
            "5xx error: {method} {path} → {status}",
            $context
        );

        // Increment the sliding-window counter
        $this->incrementErrorRate();

        // Check threshold and alert if exceeded (with deduplication per window)
        $rate = $this->currentErrorRate();
        if ($rate >= $this->threshold) {
            $this->fireAlertIfNotSent();
        }
    }

    /**
     * Return the number of 5xx errors recorded in the current sliding window.
     */
    public function currentErrorRate(): int
    {
        $now   = time();
        $total = 0;

        for ($t = $now - $this->window + 1; $t <= $now; $t++) {
            $value = $this->redis->get(self::KEY_PREFIX . $t);
            if ($value !== false) {
                $total += (int)$value;
            }
        }

        return $total;
    }

    /**
     * Return the configured error rate threshold.
     */
    public function getThreshold(): int
    {
        return $this->threshold;
    }

    /**
     * Return the configured sliding window duration in seconds.
     */
    public function getWindow(): int
    {
        return $this->window;
    }

    /**
     * Return the Unix timestamp of the last alert, or null if no alert has fired.
     */
    public function getLastAlertAt(): ?int
    {
        $value = $this->redis->get(self::KEY_LAST_ALERT);
        return ($value !== false && $value !== null) ? (int)$value : null;
    }

    /**
     * Return whether an alert is currently active (rate >= threshold).
     */
    public function isAlertActive(): bool
    {
        return $this->currentErrorRate() >= $this->threshold;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fire a CRITICAL alert at most once per window (deduplication via Redis).
     *
     * Uses key `errors:alert_sent:{window_start}` with TTL = window duration.
     * If the key already exists, the alert was already sent in this window.
     */
    private function fireAlertIfNotSent(): void
    {
        $windowStart = (int)(time() / $this->window) * $this->window;
        $dedupKey    = self::KEY_ALERT_SENT . $windowStart;

        // setex returns false/null if key already exists when using NX option.
        // We use exists() to check first, then set atomically.
        if ($this->redis->exists($dedupKey) > 0) {
            // Alert already sent in this window — skip
            return;
        }

        // Mark alert as sent for this window
        $this->redis->setex($dedupKey, $this->window, '1');

        // Store last alert timestamp
        $now = time();
        $this->redis->set(self::KEY_LAST_ALERT, (string)$now);

        $rate = $this->currentErrorRate();
        $this->logger->critical(
            "Error rate alert: {rate} errors in the last {window} seconds (threshold: {threshold})",
            [
                'rate'      => $rate,
                'window'    => $this->window,
                'threshold' => $this->threshold,
            ]
        );
    }

    /**
     * Increment the per-second error counter for the current second.
     * The key expires after the window duration so old buckets are cleaned up.
     */
    private function incrementErrorRate(): void
    {
        $key = self::KEY_PREFIX . time();
        $this->redis->incrBy($key, 1);
        // Expire slightly beyond the window so the key is still readable at
        // the very end of the window period
        $this->redis->expire($key, $this->window + 1);
    }
}
