<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\MetricsCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MetricsMiddleware
 *
 * Sits in the global middleware stack and records per-endpoint metrics
 * (request count, error count, response duration) via MetricsCollector.
 *
 * It is intentionally placed *after* CorsMiddleware / SecurityMiddleware so
 * that OPTIONS preflight requests are handled before we start timing, but
 * *before* RouterMiddleware so every routed request is captured.
 */
class MetricsMiddleware implements MiddlewareInterface
{
    public function __construct(private MetricsCollector $metrics) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);

        $response = $handler->handle($request);

        $durationMs = (microtime(true) - $start) * 1000;
        $path       = $request->getUri()->getPath();
        $status     = $response->getStatusCode();

        $this->metrics->record($path, $status, $durationMs);

        return $response;
    }
}
