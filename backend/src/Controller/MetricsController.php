<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ErrorTracker;
use App\Service\MetricsCollector;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MetricsController
 *
 * Exposes collected performance metrics via HTTP endpoints.
 *
 * GET /api/metrics
 *   Protected by a static bearer token (METRICS_TOKEN env var).
 *   Returns comprehensive dashboard payload including per-endpoint stats,
 *   error rates, system info, top slowest/most-errored endpoints.
 *
 * GET /api/metrics/dashboard
 *   Returns a self-contained HTML dashboard page that auto-refreshes every 30s.
 *   Also protected by METRICS_TOKEN.
 *
 * POST /api/metrics/reset
 *   Resets all counters (useful for testing / scheduled resets).
 *   Also protected by METRICS_TOKEN.
 */
class MetricsController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private MetricsCollector $metrics,
        private ErrorTracker $errorTracker
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * GET /api/metrics
     *
     * Returns a comprehensive dashboard payload:
     *   - Per-endpoint stats
     *   - Current error rate (from ErrorTracker sliding window)
     *   - System info: PHP version, memory usage, uptime
     *   - Top 5 slowest endpoints (by avg_duration_ms)
     *   - Top 5 most errored endpoints (by error count)
     *   - Overall request count and error rate
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAuthorised($request)) {
            return $this->error('Forbidden: invalid or missing metrics token', 403);
        }

        $summary = $this->metrics->summary();

        // Aggregate totals
        $totalRequests = 0;
        $totalErrors   = 0;
        foreach ($summary as $stats) {
            $totalRequests += $stats['requests'];
            $totalErrors   += $stats['errors'];
        }

        $overallErrorRate = $totalRequests > 0
            ? round(($totalErrors / $totalRequests) * 100, 2)
            : 0.0;

        // Top 5 slowest endpoints by average duration
        $byAvgDuration = $summary;
        uasort($byAvgDuration, fn($a, $b) => $b['avg_duration_ms'] <=> $a['avg_duration_ms']);
        $top5Slowest = array_slice(
            array_map(
                fn($path, $stats) => ['endpoint' => $path] + $stats,
                array_keys($byAvgDuration),
                array_values($byAvgDuration)
            ),
            0,
            5
        );

        // Top 5 most errored endpoints by error count
        $byErrors = $summary;
        uasort($byErrors, fn($a, $b) => $b['errors'] <=> $a['errors']);
        $top5Errored = array_slice(
            array_map(
                fn($path, $stats) => ['endpoint' => $path] + $stats,
                array_keys($byErrors),
                array_values($byErrors)
            ),
            0,
            5
        );

        // System info
        $systemInfo = $this->getSystemInfo();

        return $this->success([
            'overview' => [
                'total_requests'       => $totalRequests,
                'total_errors'         => $totalErrors,
                'overall_error_rate'   => $overallErrorRate,
                'current_5xx_rate'     => $this->errorTracker->currentErrorRate(),
                'total_endpoints'      => count($summary),
            ],
            'system' => $systemInfo,
            'top_slowest_endpoints'  => $top5Slowest,
            'top_errored_endpoints'  => $top5Errored,
            'endpoints'              => $summary,
            'generated_at'           => time(),
        ]);
    }

    /**
     * GET /api/metrics/dashboard
     *
     * Returns a self-contained HTML page with an auto-refreshing metrics dashboard.
     */
    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAuthorised($request)) {
            return $this->error('Forbidden: invalid or missing metrics token', 403);
        }

        $summary      = $this->metrics->summary();
        $currentRate  = $this->errorTracker->currentErrorRate();
        $systemInfo   = $this->getSystemInfo();

        $totalRequests = 0;
        $totalErrors   = 0;
        foreach ($summary as $stats) {
            $totalRequests += $stats['requests'];
            $totalErrors   += $stats['errors'];
        }
        $overallErrorRate = $totalRequests > 0
            ? round(($totalErrors / $totalRequests) * 100, 2)
            : 0.0;

        // Build endpoint rows
        $endpointRows = '';
        foreach ($summary as $path => $stats) {
            $errPct = $stats['requests'] > 0
                ? round(($stats['errors'] / $stats['requests']) * 100, 1)
                : 0.0;
            $rowClass = $errPct > 10 ? 'row-error' : ($errPct > 0 ? 'row-warn' : '');
            $endpointRows .= sprintf(
                '<tr class="%s"><td>%s</td><td>%d</td><td>%d</td><td>%.1f%%</td><td>%.2f ms</td><td>%.2f ms</td></tr>',
                htmlspecialchars($rowClass),
                htmlspecialchars($path),
                $stats['requests'],
                $stats['errors'],
                $errPct,
                $stats['avg_duration_ms'],
                $stats['total_duration_ms']
            );
        }

        if ($endpointRows === '') {
            $endpointRows = '<tr><td colspan="6" style="text-align:center;color:#888">No data yet</td></tr>';
        }

        $generatedAt = date('Y-m-d H:i:s');
        $token       = $request->getQueryParams()['token'] ?? '';
        $tokenParam  = $token !== '' ? '?token=' . urlencode($token) : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PSR-15 Metrics Dashboard</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
  header { background: #1e293b; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #334155; }
  header h1 { font-size: 1.25rem; font-weight: 600; color: #f8fafc; }
  header .refresh-info { font-size: 0.8rem; color: #94a3b8; }
  .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
  .card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 16px; }
  .card .label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
  .card .value { font-size: 1.75rem; font-weight: 700; color: #f8fafc; }
  .card .value.warn { color: #fbbf24; }
  .card .value.danger { color: #f87171; }
  .section { background: #1e293b; border: 1px solid #334155; border-radius: 8px; margin-bottom: 24px; overflow: hidden; }
  .section-title { padding: 12px 16px; font-size: 0.875rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #334155; background: #0f172a; }
  table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
  th { padding: 10px 14px; text-align: left; color: #64748b; font-weight: 500; border-bottom: 1px solid #334155; }
  td { padding: 10px 14px; border-bottom: 1px solid #1e293b; color: #cbd5e1; }
  tr:last-child td { border-bottom: none; }
  tr.row-warn td { background: rgba(251,191,36,0.05); }
  tr.row-error td { background: rgba(248,113,113,0.08); }
  .sys-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0; }
  .sys-item { padding: 12px 16px; border-right: 1px solid #334155; }
  .sys-item:last-child { border-right: none; }
  .sys-item .key { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
  .sys-item .val { font-size: 0.9rem; color: #e2e8f0; font-weight: 500; }
  footer { text-align: center; padding: 16px; font-size: 0.75rem; color: #475569; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
  .badge-ok { background: #14532d; color: #86efac; }
  .badge-warn { background: #713f12; color: #fde68a; }
  .badge-danger { background: #7f1d1d; color: #fca5a5; }
</style>
<script>
  let countdown = 30;
  function tick() {
    countdown--;
    const el = document.getElementById('countdown');
    if (el) el.textContent = countdown + 's';
    if (countdown <= 0) { window.location.reload(); }
  }
  setInterval(tick, 1000);
</script>
</head>
<body>
<header>
  <h1>🚀 PSR-15 Metrics Dashboard</h1>
  <span class="refresh-info">Auto-refresh in <span id="countdown">30s</span> &nbsp;|&nbsp; Generated: {$generatedAt}</span>
</header>
<div class="container">

  <!-- Overview cards -->
  <div class="cards">
    <div class="card">
      <div class="label">Total Requests</div>
      <div class="value">{$totalRequests}</div>
    </div>
    <div class="card">
      <div class="label">Total Errors</div>
      <div class="value {$this->severityClass($totalErrors, 1, 10)}">{$totalErrors}</div>
    </div>
    <div class="card">
      <div class="label">Overall Error Rate</div>
      <div class="value {$this->severityClass((int)$overallErrorRate, 5, 15)}">{$overallErrorRate}%</div>
    </div>
    <div class="card">
      <div class="label">5xx Rate (window)</div>
      <div class="value {$this->severityClass($currentRate, 1, 5)}">{$currentRate}</div>
    </div>
    <div class="card">
      <div class="label">Tracked Endpoints</div>
      <div class="value">{$this->countEndpoints($summary)}</div>
    </div>
    <div class="card">
      <div class="label">PHP Version</div>
      <div class="value" style="font-size:1.1rem">{$systemInfo['php_version']}</div>
    </div>
  </div>

  <!-- System info -->
  <div class="section">
    <div class="section-title">System Info</div>
    <div class="sys-grid">
      <div class="sys-item"><div class="key">Memory Usage</div><div class="val">{$systemInfo['memory_usage']}</div></div>
      <div class="sys-item"><div class="key">Memory Peak</div><div class="val">{$systemInfo['memory_peak']}</div></div>
      <div class="sys-item"><div class="key">Server Uptime</div><div class="val">{$systemInfo['uptime']}</div></div>
      <div class="sys-item"><div class="key">Server Software</div><div class="val">{$systemInfo['server_software']}</div></div>
      <div class="sys-item"><div class="key">OS</div><div class="val">{$systemInfo['os']}</div></div>
    </div>
  </div>

  <!-- Endpoint stats table -->
  <div class="section">
    <div class="section-title">All Endpoints</div>
    <table>
      <thead>
        <tr>
          <th>Endpoint</th>
          <th>Requests</th>
          <th>Errors</th>
          <th>Error Rate</th>
          <th>Avg Duration</th>
          <th>Total Duration</th>
        </tr>
      </thead>
      <tbody>
        {$endpointRows}
      </tbody>
    </table>
  </div>

</div>
<footer>PSR-15 Middleware Stack &mdash; School Management System</footer>
</body>
</html>
HTML;

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * POST /api/metrics/reset
     */
    public function reset(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAuthorised($request)) {
            return $this->error('Forbidden: invalid or missing metrics token', 403);
        }

        $this->metrics->reset();

        return $this->success(['reset' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Validate the request against the configured METRICS_TOKEN.
     *
     * Accepts the token via:
     *   - Authorization: Bearer <token>
     *   - ?token=<token> query parameter
     *
     * Falls back to allowing any request when METRICS_TOKEN is not set
     * (development convenience — set the env var in production).
     */
    private function isAuthorised(ServerRequestInterface $request): bool
    {
        $expected = $_ENV['METRICS_TOKEN'] ?? '';

        // No token configured → open access (dev mode)
        if ($expected === '') {
            return true;
        }

        // Check Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if (hash_equals($expected, $token)) {
                return true;
            }
        }

        // Check query parameter
        $queryToken = $request->getQueryParams()['token'] ?? '';
        if ($queryToken !== '' && hash_equals($expected, $queryToken)) {
            return true;
        }

        return false;
    }

    /**
     * Collect system information for the dashboard.
     *
     * @return array{php_version: string, memory_usage: string, memory_peak: string, uptime: string, server_software: string, os: string}
     */
    private function getSystemInfo(): array
    {
        $memUsage = memory_get_usage(true);
        $memPeak  = memory_get_peak_usage(true);

        // Server uptime via /proc/uptime (Linux only; graceful fallback)
        $uptime = 'N/A';
        if (is_readable('/proc/uptime')) {
            $rawSecs = (int)(float)explode(' ', file_get_contents('/proc/uptime'))[0];
            $days    = intdiv($rawSecs, 86400);
            $hours   = intdiv($rawSecs % 86400, 3600);
            $minutes = intdiv($rawSecs % 3600, 60);
            $uptime  = "{$days}d {$hours}h {$minutes}m";
        }

        return [
            'php_version'     => PHP_VERSION,
            'memory_usage'    => $this->formatBytes($memUsage),
            'memory_peak'     => $this->formatBytes($memPeak),
            'uptime'          => $uptime,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os'              => PHP_OS_FAMILY,
        ];
    }

    /** Format bytes to human-readable string. */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /** Return a CSS class name based on numeric severity thresholds. */
    private function severityClass(int $value, int $warnAt, int $dangerAt): string
    {
        if ($value >= $dangerAt) {
            return 'danger';
        }
        if ($value >= $warnAt) {
            return 'warn';
        }
        return '';
    }

    /** Count non-zero-request endpoints. */
    private function countEndpoints(array $summary): int
    {
        return count($summary);
    }
}
