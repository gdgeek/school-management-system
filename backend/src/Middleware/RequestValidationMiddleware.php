<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Request Validation Middleware
 *
 * Validates incoming requests before they reach controllers:
 *
 * 1. For POST/PUT/PATCH requests: Content-Type must be application/json
 *    (unless the body is empty, e.g. a POST with no body is allowed).
 * 2. Request body size must not exceed MAX_REQUEST_SIZE (default 1 MB).
 * 3. If Content-Type is application/json, the body must be valid JSON.
 *
 * Returns 400 Bad Request with a standard JSON error body for any violation.
 */
class RequestValidationMiddleware implements MiddlewareInterface
{
    /** Default maximum body size: 1 MB */
    public const DEFAULT_MAX_REQUEST_SIZE = 1_048_576; // 1 MB in bytes

    private int $maxRequestSize;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        ?array $config = null
    ) {
        $config = $config ?? [];
        $this->maxRequestSize = $config['max_request_size']
            ?? (int)($_ENV['MAX_REQUEST_SIZE'] ?? self::DEFAULT_MAX_REQUEST_SIZE);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        // Read the raw body once
        $body = (string)$request->getBody();

        // 1. Validate body size
        if (strlen($body) > $this->maxRequestSize) {
            return $this->badRequest(
                sprintf(
                    'Request body exceeds maximum allowed size of %d bytes.',
                    $this->maxRequestSize
                )
            );
        }

        // 2. For mutating methods, validate Content-Type when a body is present
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $body !== '') {
            $contentType = $request->getHeaderLine('Content-Type');
            // Strip parameters (e.g. "; charset=utf-8") for comparison
            $mediaType = strtolower(trim(explode(';', $contentType)[0]));

            if ($mediaType !== 'application/json') {
                return $this->badRequest(
                    'Content-Type must be application/json for POST, PUT, and PATCH requests.'
                );
            }
        }

        // 3. If Content-Type is application/json, validate JSON structure
        $contentType = $request->getHeaderLine('Content-Type');
        $mediaType   = strtolower(trim(explode(';', $contentType)[0]));

        if ($mediaType === 'application/json' && $body !== '') {
            json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->badRequest('Request body contains invalid JSON.');
            }
        }

        return $handler->handle($request);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function badRequest(string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(400);
        $body     = json_encode([
            'code'      => 400,
            'message'   => $message,
            'data'      => null,
            'timestamp' => time(),
        ]);

        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'application/json');
    }

    // -------------------------------------------------------------------------
    // Accessors (used in tests)
    // -------------------------------------------------------------------------

    public function getMaxRequestSize(): int
    {
        return $this->maxRequestSize;
    }
}
