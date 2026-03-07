<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helper\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verbose Development Logging Middleware
 *
 * Logs full request and response details to a dedicated dev log file
 * (runtime/logs/dev-requests.log) for debugging purposes.
 *
 * This middleware is a no-op in production:
 *   - APP_DEBUG=true  → active
 *   - APP_ENV=development → active
 *   - anything else   → passes through without any logging
 *
 * What is logged:
 *   REQUEST:
 *     - Method, full URL (path + query string)
 *     - All request headers (Authorization redacted, Cookie redacted)
 *     - Query parameters
 *     - Request body (truncated at 2 KB)
 *
 *   RESPONSE:
 *     - HTTP status code
 *     - All response headers
 *     - Response body (truncated at 2 KB)
 *     - Request/response timing in milliseconds
 *
 * Output is formatted as readable multi-line blocks separated by
 * "════" dividers for easy scanning in a log viewer.
 *
 * Sensitive headers that are redacted:
 *   - Authorization → "Bearer [REDACTED]"
 *   - Cookie        → "[REDACTED]"
 */
class VerboseLoggingMiddleware implements MiddlewareInterface
{
    /** Maximum body size (bytes) to include in the log. */
    private const BODY_TRUNCATE_BYTES = 2048;

    /** Headers whose values must never appear in logs. */
    private const REDACTED_HEADERS = ['authorization', 'cookie'];

    private bool $active;
    private Logger $devLogger;

    public function __construct(Logger $logger)
    {
        $env   = $_ENV['APP_ENV']   ?? $_SERVER['APP_ENV']   ?? 'production';
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';

        $this->active = ($env === 'development')
            || in_array(strtolower((string) $debug), ['true', '1', 'yes'], true);

        // Use a separate Logger instance pointing at the dev log directory.
        // The Logger constructor creates the directory if it does not exist.
        $logPath = dirname(__DIR__, 2) . '/runtime/logs';
        $this->devLogger = new DevRequestLogger($logPath);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->active) {
            return $handler->handle($request);
        }

        $startTime = microtime(true);

        // ── Capture request details before delegating ─────────────────────
        $requestBlock = $this->formatRequest($request);

        // ── Delegate to the rest of the middleware stack ──────────────────
        $response = $handler->handle($request);

        // ── Capture response details ──────────────────────────────────────
        $elapsed       = round((microtime(true) - $startTime) * 1000, 2);
        $responseBlock = $this->formatResponse($response, $elapsed);

        // ── Write to dev-requests.log ─────────────────────────────────────
        $separator = str_repeat('═', 80);
        $entry     = "\n{$separator}\n{$requestBlock}\n{$responseBlock}\n{$separator}\n";

        $this->devLogger->debug($entry);

        return $response;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function formatRequest(ServerRequestInterface $request): string
    {
        $method = $request->getMethod();
        $uri    = (string) $request->getUri();
        $query  = $request->getUri()->getQuery();

        $lines   = [];
        $lines[] = "▶ REQUEST  {$method} {$uri}";
        $lines[] = '';

        // Headers
        $lines[] = '  Headers:';
        foreach ($request->getHeaders() as $name => $values) {
            $value   = $this->redactHeader((string) $name, implode(', ', $values));
            $lines[] = '    ' . $name . ': ' . $value;
        }

        // Query params
        if ($query !== '') {
            $lines[] = '';
            $lines[] = '  Query Params:';
            parse_str($query, $params);
            foreach ($params as $key => $value) {
                $lines[] = '    ' . $key . ' = ' . (is_array($value) ? json_encode($value) : (string) $value);
            }
        }

        // Body
        $body = (string) $request->getBody();
        if ($body !== '') {
            $lines[] = '';
            $lines[] = '  Body (' . strlen($body) . ' bytes):';
            $lines[] = '    ' . $this->truncate($body);
        }

        return implode("\n", $lines);
    }

    private function formatResponse(ResponseInterface $response, float $elapsedMs): string
    {
        $status = $response->getStatusCode() . ' ' . $response->getReasonPhrase();

        $lines   = [];
        $lines[] = "◀ RESPONSE {$status}  [{$elapsedMs}ms]";
        $lines[] = '';

        // Headers
        $lines[] = '  Headers:';
        foreach ($response->getHeaders() as $name => $values) {
            $lines[] = "    {$name}: " . implode(', ', $values);
        }

        // Body — rewind so downstream code can still read it
        $body = (string) $response->getBody();
        $response->getBody()->rewind();

        if ($body !== '') {
            $lines[] = '';
            $lines[] = '  Body (' . strlen($body) . ' bytes):';
            $lines[] = '    ' . $this->truncate($body);
        }

        return implode("\n", $lines);
    }

    /**
     * Redact sensitive header values.
     */
    private function redactHeader(string $name, string $value): string
    {
        $lower = strtolower($name);

        if ($lower === 'authorization') {
            // Keep the scheme (e.g. "Bearer") but hide the token
            if (preg_match('/^(\S+)\s+\S+/', $value, $m)) {
                return $m[1] . ' [REDACTED]';
            }
            return '[REDACTED]';
        }

        if ($lower === 'cookie') {
            return '[REDACTED]';
        }

        return $value;
    }

    /**
     * Truncate a string to BODY_TRUNCATE_BYTES, appending a notice if cut.
     */
    private function truncate(string $data): string
    {
        if (strlen($data) <= self::BODY_TRUNCATE_BYTES) {
            return $data;
        }

        return substr($data, 0, self::BODY_TRUNCATE_BYTES)
            . ' … [truncated, ' . strlen($data) . ' bytes total]';
    }
}

/**
 * Internal logger that writes exclusively to dev-requests.log.
 *
 * Extends Logger but overrides the filename logic so every entry —
 * regardless of level — goes to a single, stable file name that is
 * easy to tail during development.
 */
class DevRequestLogger extends Logger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $logPath = dirname(__DIR__, 2) . '/runtime/logs';
        $file    = $logPath . '/dev-requests.log';

        // Ensure directory exists
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        // Write raw message (already formatted by VerboseLoggingMiddleware)
        file_put_contents($file, (string) $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
