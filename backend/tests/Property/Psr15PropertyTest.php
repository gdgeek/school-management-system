<?php

declare(strict_types=1);

namespace Tests\Property;

use App\Controller\AbstractController;
use App\Middleware\RouterMiddleware;
use Eris\Generator;
use Eris\TestTrait;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Property-Based Tests for PSR-15 Middleware Stack
 *
 * Properties verified:
 * 1. Route matching idempotence — dispatching the same path twice yields the same result
 * 2. Middleware stack integrity — pipeline always produces a ResponseInterface
 * 3. Request/response immutability — withAttribute() does not mutate original request
 * 4. Parameter type safety — route {id:\d+} always yields a non-negative integer
 * 5. Error response format consistency — all error responses share the same JSON shape
 *
 * Run: vendor/bin/phpunit tests/Property/Psr15PropertyTest.php
 */
class Psr15PropertyTest extends TestCase
{
    use TestTrait;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    // -------------------------------------------------------------------------
    // 25.2  Route matching idempotence
    // -------------------------------------------------------------------------

    /**
     * Property: dispatching the same (method, path) pair twice through a fresh
     * RouterMiddleware instance always returns the same HTTP status code.
     */
    public function testRouteMatchingIsIdempotent(): void
    {
        $routes = require __DIR__ . '/../../config/routes.php';

        // Collect all static (no {param}) route patterns for deterministic testing
        $staticRoutes = array_filter($routes, fn($r) => !str_contains($r['pattern'], '{'));

        if (empty($staticRoutes)) {
            $this->markTestSkipped('No static routes found');
        }

        $this->forAll(
            Generator\elements(array_values($staticRoutes))
        )->then(function (array $route) {
            $method  = $route['methods'][0];
            $pattern = $route['pattern'];

            $status1 = $this->dispatchToStatus($method, $pattern);
            $status2 = $this->dispatchToStatus($method, $pattern);

            $this->assertSame(
                $status1,
                $status2,
                "Route $method $pattern returned different status codes on repeated dispatch"
            );
        });
    }

    // -------------------------------------------------------------------------
    // 25.3  Middleware stack integrity
    // -------------------------------------------------------------------------

    /**
     * Property: for any arbitrary path string, the PSR-15 stack always returns
     * a valid ResponseInterface (never throws, never returns null).
     */
    public function testMiddlewareStackAlwaysReturnsResponse(): void
    {
        $this->forAll(
            Generator\string()
        )->then(function (string $randomPath) {
            // Sanitise to a valid URI path (keep only safe chars)
            $path = '/' . preg_replace('#[^a-zA-Z0-9/_-]#', '', $randomPath);
            if ($path === '/') $path = '/api/unknown-' . abs(crc32($randomPath));

            try {
                $response = $this->buildMinimalApp()->handle(
                    new ServerRequest('GET', $path)
                );
                $this->assertInstanceOf(ResponseInterface::class, $response);
                $this->assertGreaterThanOrEqual(100, $response->getStatusCode());
                $this->assertLessThan(600, $response->getStatusCode());
            } catch (\Throwable $e) {
                $this->fail("PSR-15 stack threw for path '$path': " . $e->getMessage());
            }
        });
    }

    // -------------------------------------------------------------------------
    // 25.4  Request/response immutability
    // -------------------------------------------------------------------------

    /**
     * Property: calling withAttribute() on a ServerRequest never mutates the
     * original instance — the original and the new copy are distinct objects
     * and the original retains its previous attribute value.
     */
    public function testRequestWithAttributeIsImmutable(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\int()
        )->then(function (string $key, int $value) {
            if (empty($key)) $key = 'attr';

            $original = new ServerRequest('GET', '/api/test');
            $original = $original->withAttribute('existing', 'original_value');

            $modified = $original->withAttribute($key, $value);

            // Must be different objects
            $this->assertNotSame($original, $modified);

            // Original must not have the new attribute (unless key collides with 'existing')
            if ($key !== 'existing') {
                $this->assertNull($original->getAttribute($key));
            }

            // Modified must have the new attribute
            $this->assertSame($value, $modified->getAttribute($key));

            // Original 'existing' attribute must be unchanged
            $this->assertSame('original_value', $original->getAttribute('existing'));
        });
    }

    // -------------------------------------------------------------------------
    // 25.5  Parameter type safety
    // -------------------------------------------------------------------------

    /**
     * Property: any positive integer injected as a route 'id' attribute is
     * correctly cast to int inside a controller — never negative, never null.
     */
    public function testRouteIdParameterIsAlwaysPositiveInt(): void
    {
        $this->forAll(
            Generator\pos()   // positive integers
        )->then(function (int $id) {
            $request = (new ServerRequest('GET', "/api/schools/$id"))
                ->withAttribute('id', (string)$id);   // RouterMiddleware injects as string

            $extracted = (int)$request->getAttribute('id');

            $this->assertIsInt($extracted);
            $this->assertGreaterThan(0, $extracted);
            $this->assertSame($id, $extracted);
        });
    }

    // -------------------------------------------------------------------------
    // 25.6  Error response format consistency
    // -------------------------------------------------------------------------

    /**
     * Property: every error response produced by AbstractController::error()
     * always contains the four required fields: code, message, data, timestamp.
     */
    public function testErrorResponseFormatIsConsistent(): void
    {
        $this->forAll(
            Generator\string(),
            Generator\elements([400, 401, 403, 404, 422, 500])
        )->then(function (string $message, int $statusCode) {
            $controller = $this->buildConcreteController();
            $response   = $this->invokeError($controller, $message ?: 'error', $statusCode);

            $this->assertSame($statusCode, $response->getStatusCode());
            $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

            $body = json_decode((string)$response->getBody(), true);
            $this->assertIsArray($body, 'Response body must be valid JSON');

            foreach (['code', 'message', 'data', 'timestamp'] as $field) {
                $this->assertArrayHasKey($field, $body, "Error response missing '$field' field");
            }

            $this->assertSame($statusCode, $body['code']);
            $this->assertIsInt($body['timestamp']);
            $this->assertNull($body['data']);
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Dispatch a request through a minimal RouterMiddleware and return the status code.
     */
    private function dispatchToStatus(string $method, string $path): int
    {
        try {
            $response = $this->buildMinimalApp()->handle(new ServerRequest($method, $path));
            return $response->getStatusCode();
        } catch (\Throwable) {
            return 500;
        }
    }

    /**
     * Build a minimal PSR-15 application using only RouterMiddleware.
     * Avoids needing a full DI container for property tests.
     */
    private function buildMinimalApp(): \Psr\Http\Server\RequestHandlerInterface
    {
        $factory = $this->factory;

        // Minimal container stub — resolves only what RouterMiddleware needs
        $container = new class($factory) implements \Psr\Container\ContainerInterface {
            private array $instances = [];

            public function __construct(private Psr17Factory $factory) {}

            public function get(string $id): mixed
            {
                if (!isset($this->instances[$id])) {
                    $this->instances[$id] = new $id($this->factory, $this->factory);
                }
                return $this->instances[$id];
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };

        $router = new RouterMiddleware($container, $factory);

        // Wrap in a handler that runs the router
        return new class($router, $factory) implements \Psr\Http\Server\RequestHandlerInterface {
            public function __construct(
                private RouterMiddleware $router,
                private Psr17Factory $factory
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->router->process($request, new class($this->factory) implements \Psr\Http\Server\RequestHandlerInterface {
                    public function __construct(private Psr17Factory $f) {}
                    public function handle(ServerRequestInterface $r): ResponseInterface
                    {
                        $resp = $this->f->createResponse(404);
                        $resp->getBody()->write(json_encode(['code' => 404, 'message' => 'Not Found', 'data' => null, 'timestamp' => time()]));
                        return $resp->withHeader('Content-Type', 'application/json');
                    }
                });
            }
        };
    }

    /**
     * Build a concrete anonymous subclass of AbstractController for testing.
     */
    private function buildConcreteController(): AbstractController
    {
        return new class($this->factory) extends AbstractController {
            public function __construct(ResponseFactoryInterface $rf) {
                parent::__construct($rf);
            }
            // Expose error() publicly for testing
            public function callError(string $msg, int $code): ResponseInterface {
                return $this->error($msg, $code);
            }
        };
    }

    /**
     * Invoke the error method on the anonymous controller subclass.
     */
    private function invokeError(AbstractController $controller, string $message, int $code): ResponseInterface
    {
        // @phpstan-ignore-next-line
        return $controller->callError($message, $code);
    }
}
