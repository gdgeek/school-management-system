<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contract\RedisInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rate Limiting Middleware
 *
 * Implements a sliding window counter using Redis to limit request rates.
 *
 * Key format: rate_limit:{ip}:{window_start}
 * Default limits: 100 req/min general, 10 req/min for login endpoint.
 *
 * Response headers added to every response:
 *   X-RateLimit-Limit     – max requests allowed in the window
 *   X-RateLimit-Remaining – remaining requests in the current window
 *   X-RateLimit-Reset     – Unix timestamp when the window resets
 *
 * On 429 Too Many Requests, an additional header is added:
 *   Retry-After – seconds until the window resets
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private const KEY_PREFIX = 'rate_limit:';

    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(
        private RedisInterface $redis,
        private ResponseFactoryInterface $responseFactory,
        ?array $config = null
    ) {
        $config = $config ?? [];
        $this->maxRequests   = $config['max_requests']   ?? (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $this->windowSeconds = $config['window_seconds'] ?? (int)($_ENV['RATE_LIMIT_WINDOW']   ?? 60);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip          = $this->resolveClientIp($request);
        $windowStart = $this->currentWindowStart();
        $resetAt     = $windowStart + $this->windowSeconds;
        // Hash the IP to avoid Redis key collisions with IPv6 addresses (which
        // contain colons) and to keep key lengths predictable.
        $cacheKey    = self::KEY_PREFIX . md5($ip) . ':' . $windowStart;

        // Increment counter atomically; set TTL on first hit
        $count = $this->redis->incrBy($cacheKey, 1);
        if ($count === 1) {
            // First request in this window — set expiry so Redis cleans up automatically
            $this->redis->expire($cacheKey, $this->windowSeconds * 2);
        }

        $remaining = max(0, $this->maxRequests - $count);

        if ($count > $this->maxRequests) {
            $retryAfter = $resetAt - time();
            $response   = $this->buildTooManyRequestsResponse($retryAfter);
            return $this->addRateLimitHeaders($response, $this->maxRequests, 0, $resetAt)
                        ->withHeader('Retry-After', (string)max(0, $retryAfter));
        }

        $response = $handler->handle($request);
        return $this->addRateLimitHeaders($response, $this->maxRequests, $remaining, $resetAt);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the client IP address from the request.
     *
     * Security note: X-Forwarded-For and X-Real-IP are client-controlled headers
     * and can be spoofed. They are only trusted when the application is deployed
     * behind a known reverse proxy. Each extracted IP is validated with
     * filter_var() to reject malformed or private-range spoofing attempts.
     *
     * Priority: X-Forwarded-For → X-Real-IP → REMOTE_ADDR (server params).
     */
    public function resolveClientIp(ServerRequestInterface $request): string
    {
        // X-Forwarded-For may contain a comma-separated list; take the first entry
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            $ip    = trim($parts[0]);
            if ($ip !== '' && $this->isValidIp($ip)) {
                return $ip;
            }
        }

        $realIp = $request->getHeaderLine('X-Real-IP');
        if ($realIp !== '') {
            $ip = trim($realIp);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        $serverParams = $request->getServerParams();
        $remoteAddr   = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
        return $this->isValidIp($remoteAddr) ? $remoteAddr : '127.0.0.1';
    }

    /**
     * Return true if the given string is a syntactically valid IPv4 or IPv6 address.
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Return the Unix timestamp of the start of the current window.
     */
    public function currentWindowStart(): int
    {
        return (int)(floor(time() / $this->windowSeconds) * $this->windowSeconds);
    }

    /**
     * Add standard rate-limit headers to a response.
     */
    private function addRateLimitHeaders(
        ResponseInterface $response,
        int $limit,
        int $remaining,
        int $reset
    ): ResponseInterface {
        return $response
            ->withHeader('X-RateLimit-Limit',     (string)$limit)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining)
            ->withHeader('X-RateLimit-Reset',     (string)$reset);
    }

    /**
     * Build a 429 Too Many Requests JSON response.
     */
    private function buildTooManyRequestsResponse(int $retryAfter): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(429);
        $body     = json_encode([
            'code'      => 429,
            'message'   => 'Too Many Requests',
            'data'      => null,
            'timestamp' => time(),
        ]);

        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json');
    }

    // -------------------------------------------------------------------------
    // Accessors (used in tests / DI factory)
    // -------------------------------------------------------------------------

    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }

    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }
}
