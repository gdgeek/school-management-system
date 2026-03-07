<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helper\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Request/Response Logging Middleware
 *
 * Logs incoming requests and outgoing responses for observability.
 *
 * Log format:
 *   [REQUEST] METHOD /path?query | IP | User-Agent → [RESPONSE] STATUS | Xms
 *
 * Sensitive data is sanitised:
 *   - Authorization header value is never logged
 *   - Request body is never logged (may contain passwords / tokens)
 */
class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private Logger $logger) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);

        // ── Collect request metadata ──────────────────────────────────────
        $method    = $request->getMethod();
        $path      = $request->getUri()->getPath();
        $query     = $request->getUri()->getQuery();
        $ip        = $this->resolveClientIp($request);
        $userAgent = $this->sanitiseUserAgent($request->getHeaderLine('User-Agent'));

        $fullPath  = $query !== '' ? "{$path}?{$query}" : $path;

        // ── Delegate to next middleware / controller ───────────────────────
        $response = $handler->handle($request);

        // ── Collect response metadata ─────────────────────────────────────
        $elapsed    = round((microtime(true) - $startTime) * 1000, 2);
        $statusCode = $response->getStatusCode();

        // ── Write log entry ───────────────────────────────────────────────
        $message = "[REQUEST] {$method} {$fullPath} | {$ip} | {$userAgent}"
                 . " → [RESPONSE] {$statusCode} | {$elapsed}ms";

        $this->logger->info($message, [
            'method'      => $method,
            'path'        => $fullPath,
            'ip'          => $ip,
            'status'      => $statusCode,
            'duration_ms' => $elapsed,
        ]);

        return $response;
    }

    /**
     * Resolve the real client IP, honouring common proxy headers.
     */
    private function resolveClientIp(ServerRequestInterface $request): string
    {
        // X-Forwarded-For may contain a comma-separated list; take the first entry
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            $ip    = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        $realIp = $request->getHeaderLine('X-Real-IP');
        if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
            return $realIp;
        }

        // Fall back to REMOTE_ADDR from server params
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Truncate very long User-Agent strings to avoid bloating logs.
     */
    private function sanitiseUserAgent(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'unknown';
        }

        // Truncate to 200 characters to keep log lines readable
        return mb_substr($userAgent, 0, 200);
    }
}
