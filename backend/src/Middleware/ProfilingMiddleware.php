<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helper\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Profiling Middleware
 *
 * Measures and logs execution time for each middleware in the pipeline.
 * Only active when APP_ENV=development or PROFILING_ENABLED=true.
 *
 * Log format: [middleware] <name> | path: <path> | time: <ms>ms
 */
class ProfilingMiddleware implements MiddlewareInterface
{
    private bool $enabled;
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->enabled = $this->shouldProfile();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $startTime = microtime(true);

        $response = $handler->handle($request);

        $elapsed = round((microtime(true) - $startTime) * 1000, 3);

        $this->logger->debug('[middleware] pipeline | path: {path} | time: {time}ms', [
            'middleware' => 'pipeline',
            'path'       => $path,
            'time'       => $elapsed,
        ]);

        return $response;
    }

    /**
     * Wrap a single middleware with timing instrumentation.
     *
     * Returns a new MiddlewareInterface that logs the execution time of the
     * wrapped middleware before delegating to the next handler.
     */
    public function wrapMiddleware(MiddlewareInterface $middleware): MiddlewareInterface
    {
        if (!$this->enabled) {
            return $middleware;
        }

        $logger = $this->logger;
        $name   = get_class($middleware);

        return new class($middleware, $logger, $name) implements MiddlewareInterface {
            public function __construct(
                private MiddlewareInterface $inner,
                private Logger $logger,
                private string $name
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $path      = $request->getUri()->getPath();
                $startTime = microtime(true);

                $response = $this->inner->process($request, $handler);

                $elapsed = round((microtime(true) - $startTime) * 1000, 3);

                $this->logger->debug('[middleware] {middleware} | path: {path} | time: {time}ms', [
                    'middleware' => $this->name,
                    'path'       => $path,
                    'time'       => $elapsed,
                ]);

                return $response;
            }
        };
    }

    /**
     * Determine whether profiling should be active.
     */
    private function shouldProfile(): bool
    {
        $appEnv          = $_ENV['APP_ENV'] ?? 'production';
        $profilingEnabled = $_ENV['PROFILING_ENABLED'] ?? 'false';

        return $appEnv === 'development' || $profilingEnabled === 'true';
    }
}
