<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ErrorTracker;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * AlertController
 *
 * GET /api/alerts/status
 *   Protected by METRICS_TOKEN (same pattern as MetricsController).
 *   Returns current error rate, threshold, window, alert status, and last alert timestamp.
 */
class AlertController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private ErrorTracker $errorTracker
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * GET /api/alerts/status
     *
     * Returns:
     *   - current_error_rate  : int   – errors in the current sliding window
     *   - threshold           : int   – alert threshold
     *   - window_seconds      : int   – sliding window duration in seconds
     *   - alert_active        : bool  – true when rate >= threshold
     *   - last_alert_at       : int|null – Unix timestamp of last alert, or null
     */
    public function status(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAuthorised($request)) {
            return $this->error('Forbidden: invalid or missing metrics token', 403);
        }

        $rate      = $this->errorTracker->currentErrorRate();
        $threshold = $this->errorTracker->getThreshold();

        return $this->success([
            'current_error_rate' => $rate,
            'threshold'          => $threshold,
            'window_seconds'     => $this->errorTracker->getWindow(),
            'alert_active'       => $rate >= $threshold,
            'last_alert_at'      => $this->errorTracker->getLastAlertAt(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Validate the request against the configured METRICS_TOKEN.
     * Accepts token via Authorization: Bearer <token> or ?token=<token>.
     * Falls back to open access when METRICS_TOKEN is not set (dev mode).
     */
    private function isAuthorised(ServerRequestInterface $request): bool
    {
        $expected = $_ENV['METRICS_TOKEN'] ?? '';

        if ($expected === '') {
            return true;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if (hash_equals($expected, $token)) {
                return true;
            }
        }

        $queryToken = $request->getQueryParams()['token'] ?? '';
        if ($queryToken !== '' && hash_equals($expected, $queryToken)) {
            return true;
        }

        return false;
    }
}
