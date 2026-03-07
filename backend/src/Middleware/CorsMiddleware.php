<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) for the PSR-15 middleware stack.
 *
 * Security notes:
 * - Wildcard '*' is NOT allowed when credentials are enabled (browsers reject it).
 *   If '*' is configured with credentials=true, the middleware falls back to
 *   echoing the request Origin (permissive but functional). For production, always
 *   use an explicit allowlist.
 * - A Vary: Origin header is always added when origin-based matching is used so
 *   shared caches (CDNs, proxies) do not serve the wrong CORS headers.
 * - Preflight (OPTIONS) requests are short-circuited and never reach downstream
 *   middleware or controllers.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private array $exposedHeaders;
    private bool $allowCredentials;
    private int $maxAge;
    /** True when the allowlist contains the bare wildcard '*' */
    private bool $wildcardAll;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        ?array $config = null
    ) {
        $config = $config ?? [];

        // Resolve allowed origins: config array > env var > safe default (localhost only)
        $rawOrigins = $config['origins'] ?? $this->parseOrigins(
            $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:5173'
        );

        $this->allowedOrigins   = $rawOrigins;
        $this->wildcardAll      = in_array('*', $rawOrigins, true);
        $this->allowedMethods   = $config['methods']  ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'];
        $this->allowedHeaders   = $config['headers']  ?? ['Authorization', 'Content-Type', 'X-Requested-With', 'Accept', 'Origin'];
        $this->exposedHeaders   = $config['expose']   ?? [];
        $this->allowCredentials = $config['credentials'] ?? true;
        $this->maxAge           = $config['maxAge']   ?? 86400; // 24 hours
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Short-circuit preflight — never forward OPTIONS to downstream handlers
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflight($origin);
        }

        // Actual request: let the chain run, then attach CORS headers
        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build and return a preflight response (200 OK, no body).
     *
     * Returns 200 (not 204) for maximum compatibility with older clients and
     * some HTTP/1.0 proxies that treat 204 as an error.
     */
    private function handlePreflight(string $origin): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(200);
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Attach CORS headers to a response.
     *
     * If the origin is not on the allowlist, the response is returned unchanged
     * (no CORS headers). The browser will then block the cross-origin read.
     */
    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        if (empty($origin)) {
            // Same-origin or non-browser request — no CORS headers needed
            return $response;
        }

        if (!$this->isOriginAllowed($origin)) {
            // Origin rejected — return response without CORS headers.
            // The Vary header is still useful so caches know the response
            // differs by origin.
            return $response->withAddedHeader('Vary', 'Origin');
        }

        // When credentials are enabled we MUST echo the specific origin back
        // (browsers reject Access-Control-Allow-Origin: * with credentials).
        $allowOriginValue = ($this->wildcardAll && !$this->allowCredentials)
            ? '*'
            : $origin;

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOriginValue)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge)
            ->withAddedHeader('Vary', 'Origin');

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if (!empty($this->exposedHeaders)) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->exposedHeaders)
            );
        }

        return $response;
    }

    /**
     * Return true if the given origin is on the allowlist.
     */
    private function isOriginAllowed(string $origin): bool
    {
        // Bare wildcard — allow everything
        if ($this->wildcardAll) {
            return true;
        }

        // Exact match
        if (in_array($origin, $this->allowedOrigins, true)) {
            return true;
        }

        // Wildcard-pattern match (e.g. "https://*.example.com")
        foreach ($this->allowedOrigins as $allowed) {
            if (str_contains($allowed, '*') && $this->matchWildcard($allowed, $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match an origin against a wildcard pattern such as "https://*.example.com".
     *
     * Only the '*' glob character is supported; '?' is not.
     */
    private function matchWildcard(string $pattern, string $value): bool
    {
        $regex = '/^' . str_replace('\*', '[^.]+', preg_quote($pattern, '/')) . '$/i';
        return (bool) preg_match($regex, $value);
    }

    /**
     * Parse a comma-separated origins string into an array.
     */
    private function parseOrigins(string $origins): array
    {
        if (trim($origins) === '*') {
            return ['*'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $origins))));
    }
}
