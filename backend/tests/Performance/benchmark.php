<?php

/**
 * PSR-15 Migration Benchmark Script
 *
 * Measures response times for key API endpoints after PSR-15 migration.
 * Reports min, max, avg, and p95 response times per endpoint.
 *
 * Usage:
 *   php benchmark.php [iterations] [base_url]
 *
 * Examples:
 *   php benchmark.php
 *   php benchmark.php 100 http://localhost:8084
 *   php benchmark.php 50 http://localhost:8084/api
 */

declare(strict_types=1);

// ─── Configuration ────────────────────────────────────────────────────────────

$iterations = (int)($argv[1] ?? 100);
$baseUrl    = rtrim($argv[2] ?? 'http://localhost:8084', '/');
$apiBase    = $baseUrl . '/api';

$loginUser = 'guanfei';
$loginPass = '123456';

// ANSI colours
$C = [
    'reset'  => "\033[0m",
    'bold'   => "\033[1m",
    'green'  => "\033[0;32m",
    'yellow' => "\033[1;33m",
    'red'    => "\033[0;31m",
    'cyan'   => "\033[0;36m",
    'white'  => "\033[1;37m",
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Execute a single curl request and return elapsed milliseconds + HTTP status.
 *
 * @return array{ms: float, status: int}
 */
function curlRequest(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $start    = microtime(true);
    curl_exec($ch);
    $elapsed  = (microtime(true) - $start) * 1000; // ms

    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['ms' => $elapsed, 'status' => $status];
}

/**
 * Run N iterations of a request and collect timing samples.
 *
 * @return float[] millisecond samples
 */
function collectSamples(int $n, string $method, string $url, array $headers = [], ?string $body = null): array
{
    $samples = [];
    for ($i = 0; $i < $n; $i++) {
        $result    = curlRequest($method, $url, $headers, $body);
        $samples[] = $result['ms'];
    }
    return $samples;
}

/**
 * Compute statistics from a samples array.
 *
 * @param  float[] $samples
 * @return array{min: float, max: float, avg: float, p95: float, count: int}
 */
function stats(array $samples): array
{
    sort($samples);
    $count = count($samples);
    $p95   = $samples[(int)ceil($count * 0.95) - 1];

    return [
        'min'   => round(min($samples), 2),
        'max'   => round(max($samples), 2),
        'avg'   => round(array_sum($samples) / $count, 2),
        'p95'   => round($p95, 2),
        'count' => $count,
    ];
}

/**
 * Print a formatted stats row.
 */
function printRow(string $label, array $s, array $C): void
{
    $avgColor = $C['green'];
    if ($s['avg'] > 500) $avgColor = $C['yellow'];
    if ($s['avg'] > 1000) $avgColor = $C['red'];

    printf(
        "  %-42s  min=%6.1fms  avg=%s%6.1fms%s  p95=%6.1fms  max=%6.1fms\n",
        $label,
        $s['min'],
        $avgColor, $s['avg'], $C['reset'],
        $s['p95'],
        $s['max']
    );
}

// ─── Step 1: Authenticate ─────────────────────────────────────────────────────

echo "\n";
echo $C['bold'] . $C['white'] . "═══════════════════════════════════════════════════════════════" . $C['reset'] . "\n";
echo $C['bold'] . $C['white'] . "  PSR-15 Migration Benchmark" . $C['reset'] . "\n";
echo $C['bold'] . $C['white'] . "═══════════════════════════════════════════════════════════════" . $C['reset'] . "\n";
echo "  Base URL   : {$baseUrl}\n";
echo "  Iterations : {$iterations} per endpoint\n";
echo "  Timestamp  : " . date('Y-m-d H:i:s') . "\n";
echo $C['bold'] . $C['white'] . "═══════════════════════════════════════════════════════════════" . $C['reset'] . "\n\n";

echo $C['cyan'] . "→ Authenticating as '{$loginUser}'..." . $C['reset'] . "\n";

$loginBody = json_encode(['username' => $loginUser, 'password' => $loginPass]);
$loginHeaders = ['Content-Type: application/json', 'Accept: application/json'];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiBase . '/auth/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $loginBody,
    CURLOPT_HTTPHEADER     => $loginHeaders,
]);
$loginResponse = curl_exec($ch);
$loginStatus   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token = null;
if ($loginStatus === 200) {
    $decoded = json_decode($loginResponse, true);
    $token   = $decoded['data']['token'] ?? $decoded['data']['access_token'] ?? null;
}

if ($token === null) {
    echo $C['yellow'] . "  ⚠  Could not obtain auth token (status={$loginStatus}). " .
         "Authenticated endpoints will be skipped.\n" . $C['reset'];
} else {
    echo $C['green'] . "  ✓  Token obtained.\n" . $C['reset'];
}

$authHeaders = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . ($token ?? ''),
];

// ─── Step 2: Define endpoints ─────────────────────────────────────────────────

$endpoints = [
    [
        'label'    => 'GET /api/health (public)',
        'method'   => 'GET',
        'url'      => $baseUrl . '/health',
        'headers'  => ['Accept: application/json'],
        'body'     => null,
        'auth'     => false,
    ],
    [
        'label'    => 'POST /api/auth/login',
        'method'   => 'POST',
        'url'      => $apiBase . '/auth/login',
        'headers'  => $loginHeaders,
        'body'     => $loginBody,
        'auth'     => false,
    ],
    [
        'label'    => 'GET /api/schools (authenticated)',
        'method'   => 'GET',
        'url'      => $apiBase . '/schools?page=1&pageSize=20',
        'headers'  => $authHeaders,
        'body'     => null,
        'auth'     => true,
    ],
    [
        'label'    => 'GET /api/classes (authenticated)',
        'method'   => 'GET',
        'url'      => $apiBase . '/classes?page=1&pageSize=20',
        'headers'  => $authHeaders,
        'body'     => null,
        'auth'     => true,
    ],
];

// ─── Step 3: Run benchmarks ───────────────────────────────────────────────────

$results = [];

foreach ($endpoints as $ep) {
    if ($ep['auth'] && $token === null) {
        echo $C['yellow'] . "  ⚠  Skipping (no token): {$ep['label']}\n" . $C['reset'];
        continue;
    }

    echo $C['cyan'] . "→ Benchmarking: {$ep['label']}..." . $C['reset'] . "\n";

    $samples = collectSamples($iterations, $ep['method'], $ep['url'], $ep['headers'], $ep['body']);
    $s       = stats($samples);

    $results[$ep['label']] = $s;
}

// ─── Step 4: Print results table ─────────────────────────────────────────────

echo "\n";
echo $C['bold'] . $C['white'] . "─── Results (" . $iterations . " iterations each) ──────────────────────────────────────" . $C['reset'] . "\n\n";

foreach ($results as $label => $s) {
    printRow($label, $s, $C);
}

echo "\n";
echo $C['bold'] . $C['white'] . "─── Legend ─────────────────────────────────────────────────────" . $C['reset'] . "\n";
echo "  " . $C['green']  . "green avg" . $C['reset']  . "  = < 500ms   (good)\n";
echo "  " . $C['yellow'] . "yellow avg" . $C['reset'] . " = 500–1000ms (acceptable)\n";
echo "  " . $C['red']    . "red avg" . $C['reset']    . "    = > 1000ms  (slow)\n";
echo "\n";

// ─── Step 5: Write JSON report ────────────────────────────────────────────────

$reportDir  = __DIR__ . '/results';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

$reportFile = $reportDir . '/benchmark-' . date('Ymd-His') . '.json';
$report = [
    'generated_at' => date('c'),
    'base_url'     => $baseUrl,
    'iterations'   => $iterations,
    'results'      => $results,
];

file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT) . "\n");
echo "  Report saved → {$reportFile}\n\n";
