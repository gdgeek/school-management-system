<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Contract\RedisInterface;
use App\Helper\Logger;
use App\Middleware\ErrorHandlingMiddleware;
use App\Service\ErrorTracker;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ErrorHandlingMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeRedis(): RedisInterface
    {
        return new class implements RedisInterface {
            private array $store = [];
            public function get(string $key): mixed { return $this->store[$key] ?? false; }
            public function set(string $key, mixed $value, mixed $options = null): mixed { $this->store[$key] = $value; return true; }
            public function setex(string $key, int $ttl, mixed $value): mixed { $this->store[$key] = $value; return true; }
            public function del(string ...$keys): int { $n = 0; foreach ($keys as $k) { if (isset($this->store[$k])) { unset($this->store[$k]); $n++; } } return $n; }
            public function expire(string $key, int $ttl): bool { return true; }
            public function exists(string ...$keys): int { $n = 0; foreach ($keys as $k) { if (isset($this->store[$k])) $n++; } return $n; }
            public function incrBy(string $key, int $by = 1): int { $this->store[$key] = (int)($this->store[$key] ?? 0) + $by; return $this->store[$key]; }
            public function incrByFloat(string $key, float $by): float { $this->store[$key] = (float)($this->store[$key] ?? 0.0) + $by; return $this->store[$key]; }
            public function keys(string $pattern): array { $regex = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/'; return array_values(array_filter(array_keys($this->store), fn($k) => (bool)preg_match($regex, $k))); }
        };
    }

    private function makeTracker(): ErrorTracker
    {
        return new ErrorTracker(
            $this->makeRedis(),
            new Logger(sys_get_temp_dir() . '/error-handling-mw-test-logs'),
            10,
            60
        );
    }

    private function makeMiddleware(?ErrorTracker $tracker = null): ErrorHandlingMiddleware
    {
        return new ErrorHandlingMiddleware(
            $tracker ?? $this->makeTracker(),
            $this->factory
        );
    }

    private function makeRequest(): ServerRequestInterface
    {
        return $this->factory->createServerRequest('GET', 'http://localhost/api/test');
    }

    private function makeHandler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function makeThrowingHandler(\Throwable $e): RequestHandlerInterface
    {
        return new class($e) implements RequestHandlerInterface {
            public function __construct(private \Throwable $e) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->e;
            }
        };
    }

    // ── Pass-through for successful responses ─────────────────────────────────

    public function testPassesThroughSuccessResponse(): void
    {
        $response = $this->factory->createResponse(200);
        $mw = $this->makeMiddleware();

        $result = $mw->process($this->makeRequest(), $this->makeHandler($response));

        $this->assertSame(200, $result->getStatusCode());
    }

    public function testPassesThroughClientErrorResponse(): void
    {
        $response = $this->factory->createResponse(404);
        $mw = $this->makeMiddleware();

        $result = $mw->process($this->makeRequest(), $this->makeHandler($response));

        $this->assertSame(404, $result->getStatusCode());
    }

    // ── Exception handling ────────────────────────────────────────────────────

    public function testConvertsExceptionTo500Response(): void
    {
        $mw = $this->makeMiddleware();
        $handler = $this->makeThrowingHandler(new \RuntimeException('Something broke'));

        $result = $mw->process($this->makeRequest(), $handler);

        $this->assertSame(500, $result->getStatusCode());
    }

    public function testExceptionResponseIsJson(): void
    {
        $mw = $this->makeMiddleware();
        $handler = $this->makeThrowingHandler(new \RuntimeException('DB error'));

        $result = $mw->process($this->makeRequest(), $handler);

        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
        $body = json_decode((string)$result->getBody(), true);
        $this->assertSame(500, $body['code']);
        $this->assertSame('Internal Server Error', $body['message']);
        $this->assertNull($body['data']);
        $this->assertArrayHasKey('timestamp', $body);
    }

    public function testExceptionIsRecordedInErrorTracker(): void
    {
        $tracker = $this->makeTracker();
        $mw = $this->makeMiddleware($tracker);
        $handler = $this->makeThrowingHandler(new \LogicException('Oops'));

        $mw->process($this->makeRequest(), $handler);

        $this->assertSame(1, $tracker->currentErrorRate());
    }

    // ── 5xx response tracking (no exception) ─────────────────────────────────

    public function testRecords5xxResponseWithoutException(): void
    {
        $tracker = $this->makeTracker();
        $mw = $this->makeMiddleware($tracker);
        $response = $this->factory->createResponse(503);

        $mw->process($this->makeRequest(), $this->makeHandler($response));

        $this->assertSame(1, $tracker->currentErrorRate());
    }

    public function testDoesNotRecord2xxAsError(): void
    {
        $tracker = $this->makeTracker();
        $mw = $this->makeMiddleware($tracker);
        $response = $this->factory->createResponse(200);

        $mw->process($this->makeRequest(), $this->makeHandler($response));

        $this->assertSame(0, $tracker->currentErrorRate());
    }
}
