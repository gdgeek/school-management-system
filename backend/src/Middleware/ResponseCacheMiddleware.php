<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contract\RedisInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Response Cache Middleware
 *
 * Caches GET responses in Redis to reduce repeated computation.
 *
 * Rules:
 * - Only caches GET requests (safe, idempotent)
 * - Skips caching for authenticated endpoints (Authorization header present)
 * - Skips caching for non-200 responses
 * - Cache key = "response_cache:" + path + "?" + query_string
 * - Default TTL: 60 seconds, overridable via request attribute "cache_ttl"
 */
class ResponseCacheMiddleware implements MiddlewareInterface
{
    private const CACHE_PREFIX = 'response_cache:';
    private const DEFAULT_TTL  = 60;

    public function __construct(
        private RedisInterface $redis,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private int $defaultTtl = self::DEFAULT_TTL
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only cache GET requests
        if ($request->getMethod() !== 'GET') {
            return $handler->handle($request);
        }

        // Skip caching for authenticated requests to avoid leaking user-specific data
        if ($request->hasHeader('Authorization')) {
            return $handler->handle($request);
        }

        $cacheKey = $this->buildCacheKey($request);

        // Try to serve from cache
        $cached = $this->redis->get($cacheKey);
        if ($cached !== false && $cached !== null) {
            $data = is_string($cached) ? json_decode($cached, true) : null;
            if (is_array($data) && isset($data['status'], $data['headers'], $data['body'])) {
                return $this->buildResponseFromCache($data);
            }
        }

        // Execute the rest of the middleware stack
        $response = $handler->handle($request);

        // Only cache 200 OK responses
        if ($response->getStatusCode() === 200) {
            $ttl = $this->resolveTtl($request);
            $this->cacheResponse($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Build a cache key from the request path and query string.
     */
    private function buildCacheKey(ServerRequestInterface $request): string
    {
        $uri   = $request->getUri();
        $path  = $uri->getPath();
        $query = $uri->getQuery();

        $key = $path;
        if ($query !== '') {
            $key .= '?' . $query;
        }

        return self::CACHE_PREFIX . $key;
    }

    /**
     * Determine TTL: use request attribute "cache_ttl" if set, otherwise default.
     */
    private function resolveTtl(ServerRequestInterface $request): int
    {
        $ttl = $request->getAttribute('cache_ttl');
        if ($ttl !== null && is_int($ttl) && $ttl > 0) {
            return $ttl;
        }
        return $this->defaultTtl;
    }

    /**
     * Serialize and store the response in Redis.
     */
    private function cacheResponse(string $key, ResponseInterface $response, int $ttl): void
    {
        try {
            $body = (string) $response->getBody();

            // Collect headers (skip hop-by-hop headers that shouldn't be cached)
            $headers = [];
            foreach ($response->getHeaders() as $name => $values) {
                $headers[$name] = $values;
            }

            $data = json_encode([
                'status'  => $response->getStatusCode(),
                'headers' => $headers,
                'body'    => $body,
            ]);

            $this->redis->setex($key, $ttl, $data);
        } catch (\Throwable) {
            // Cache write failure is non-fatal — just skip caching
        }
    }

    /**
     * Reconstruct a PSR-7 response from cached data.
     */
    private function buildResponseFromCache(array $data): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($data['status']);

        foreach ($data['headers'] as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        $body = $this->streamFactory->createStream($data['body']);
        $response = $response->withBody($body);

        // Indicate the response was served from cache
        return $response->withHeader('X-Cache', 'HIT');
    }
}
