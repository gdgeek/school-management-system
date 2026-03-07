<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Debug Middleware
 *
 * Adds X-Debug-* response headers to aid development-time inspection of the
 * PSR-15 middleware stack.  The middleware is a no-op in production:
 *   - APP_DEBUG=true  → active
 *   - APP_ENV=development → active
 *   - anything else   → passes through without touching the response
 *
 * Headers added (dev only):
 *   X-Debug-Middleware-Stack  Comma-separated list of middleware class names
 *                             that processed the request (set by other middleware
 *                             via the 'debug.middleware_stack' request attribute).
 *   X-Debug-Route             Matched route name (request attribute 'route_name').
 *   X-Debug-Handler           Controller::method that handled the request
 *                             (request attribute 'handler').
 *   X-Debug-Memory            Peak memory usage in MB at response time.
 *   X-Debug-Time              Total request processing time in milliseconds.
 *
 * Upstream middleware can register themselves by appending to the
 * 'debug.middleware_stack' request attribute (array of class names).
 * DebugMiddleware itself is placed last in the global stack so it sees all
 * headers added by earlier middleware before emitting the debug headers.
 */
class DebugMiddleware implements MiddlewareInterface
{
    private bool $active;

    public function __construct()
    {
        $env   = $_ENV['APP_ENV']   ?? $_SERVER['APP_ENV']   ?? 'production';
        $debug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';

        $this->active = ($env === 'development') || in_array(strtolower((string)$debug), ['true', '1', 'yes'], true);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->active) {
            return $handler->handle($request);
        }

        $startTime = microtime(true);

        // Delegate to the rest of the stack
        $response = $handler->handle($request);

        // ── Collect debug data ────────────────────────────────────────────

        // Middleware stack recorded by upstream middleware
        $stack = $request->getAttribute('debug.middleware_stack', []);
        if (is_array($stack) && count($stack) > 0) {
            // Shorten fully-qualified class names to the short class name for readability
            $shortNames = array_map(
                static fn(string $fqcn): string => class_exists($fqcn)
                    ? (new \ReflectionClass($fqcn))->getShortName()
                    : basename(str_replace('\\', '/', $fqcn)),
                $stack
            );
            $response = $response->withHeader('X-Debug-Middleware-Stack', implode(', ', $shortNames));
        }

        // Route name set by RouterMiddleware
        $routeName = $request->getAttribute('route_name', '');
        if ($routeName !== '' && $routeName !== null) {
            $response = $response->withHeader('X-Debug-Route', (string)$routeName);
        }

        // Handler (Controller::method) set by RouterMiddleware
        $handler_ = $request->getAttribute('handler', '');
        if ($handler_ !== '' && $handler_ !== null) {
            // Shorten the FQCN part for readability: App\Controller\SchoolController::index → SchoolController::index
            $handlerStr = (string)$handler_;
            if (str_contains($handlerStr, '\\')) {
                $parts = explode('::', $handlerStr, 2);
                $shortClass = class_exists($parts[0])
                    ? (new \ReflectionClass($parts[0]))->getShortName()
                    : basename(str_replace('\\', '/', $parts[0]));
                $handlerStr = isset($parts[1]) ? "{$shortClass}::{$parts[1]}" : $shortClass;
            }
            $response = $response->withHeader('X-Debug-Handler', $handlerStr);
        }

        // Peak memory in MB
        $memoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $response = $response->withHeader('X-Debug-Memory', $memoryMb . 'MB');

        // Total request time in ms
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        $response = $response->withHeader('X-Debug-Time', $elapsed . 'ms');

        return $response;
    }
}
