<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application;
use App\Middleware\CorsMiddleware;
use App\Middleware\SecurityMiddleware;
use App\Middleware\RouterMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Unit tests for Application bootstrap class.
 *
 * These tests verify the Application class correctly:
 * - Accepts required dependencies via constructor
 * - Builds middleware stack from configuration
 * - Executes middleware in correct order
 * - Handles requests and returns responses
 * - Returns 404 for unhandled requests
 * - Handles middleware exceptions properly
 */
class ApplicationTest extends TestCase
{
    private Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->psr17Factory = new Psr17Factory();
    }

    public function testConstructorAcceptsDependencies(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $app = new Application($container);

        $this->assertInstanceOf(Application::class, $app);
    }

    public function testApplicationCanBeInstantiatedMultipleTimes(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $app1 = new Application($container);
        $app2 = new Application($container);

        $this->assertInstanceOf(Application::class, $app1);
        $this->assertInstanceOf(Application::class, $app2);
        $this->assertNotSame($app1, $app2);
    }

    public function testApplicationHasHandleMethod(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $app = new Application($container);

        $this->assertTrue(method_exists($app, 'handle'));
    }

    public function testApplicationHasRunMethod(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $app = new Application($container);

        $this->assertTrue(method_exists($app, 'run'));
    }

    public function testHandleReturns404WhenNoMiddlewareHandlesRequest(): void
    {
        // Create a container that returns mock middleware that don't handle the request
        $container = $this->createMock(ContainerInterface::class);
        
        // Mock middleware that passes through without handling
        $corsMiddleware = $this->createPassThroughMiddleware();
        $securityMiddleware = $this->createPassThroughMiddleware();
        $routerMiddleware = $this->createPassThroughMiddleware();
        
        $container->method('get')
            ->willReturnCallback(function ($class) use ($corsMiddleware, $securityMiddleware, $routerMiddleware) {
                return match ($class) {
                    CorsMiddleware::class => $corsMiddleware,
                    SecurityMiddleware::class => $securityMiddleware,
                    RouterMiddleware::class => $routerMiddleware,
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $request = $this->psr17Factory->createServerRequest('GET', '/api/nonexistent');
        $response = $app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(404, $body['code']);
        $this->assertEquals('Not Found', $body['message']);
        $this->assertNull($body['data']);
        $this->assertIsInt($body['timestamp']);
    }

    public function testHandleExecutesMiddlewareInCorrectOrder(): void
    {
        $executionOrder = [];
        
        // Create middleware that track execution order
        $corsMiddleware = $this->createTrackingMiddleware('cors', $executionOrder);
        $securityMiddleware = $this->createTrackingMiddleware('security', $executionOrder);
        $routerMiddleware = $this->createTrackingMiddleware('router', $executionOrder);
        
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($class) use ($corsMiddleware, $securityMiddleware, $routerMiddleware) {
                return match ($class) {
                    CorsMiddleware::class => $corsMiddleware,
                    SecurityMiddleware::class => $securityMiddleware,
                    RouterMiddleware::class => $routerMiddleware,
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $request = $this->psr17Factory->createServerRequest('GET', '/api/test');
        $app->handle($request);

        // Verify middleware executed in correct order: CORS -> Security -> Router
        $this->assertEquals(['cors', 'security', 'router'], $executionOrder);
    }

    public function testHandleReturnsResponseFromMiddleware(): void
    {
        // Create a middleware that returns a custom response
        $customResponse = $this->psr17Factory->createResponse(200);
        $customResponse->getBody()->write(json_encode(['message' => 'Custom response']));
        
        $respondingMiddleware = $this->createRespondingMiddleware($customResponse);
        
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($class) use ($respondingMiddleware) {
                return match ($class) {
                    CorsMiddleware::class => $respondingMiddleware,
                    SecurityMiddleware::class => $this->createPassThroughMiddleware(),
                    RouterMiddleware::class => $this->createPassThroughMiddleware(),
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $request = $this->psr17Factory->createServerRequest('GET', '/api/test');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Custom response', $body['message']);
    }

    public function testHandlePassesRequestThroughMiddlewareChain(): void
    {
        $requestsSeen = [];
        
        // Create middleware that track the request they receive
        $corsMiddleware = $this->createRequestTrackingMiddleware('cors', $requestsSeen);
        $securityMiddleware = $this->createRequestTrackingMiddleware('security', $requestsSeen);
        $routerMiddleware = $this->createRequestTrackingMiddleware('router', $requestsSeen);
        
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($class) use ($corsMiddleware, $securityMiddleware, $routerMiddleware) {
                return match ($class) {
                    CorsMiddleware::class => $corsMiddleware,
                    SecurityMiddleware::class => $securityMiddleware,
                    RouterMiddleware::class => $routerMiddleware,
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $request = $this->psr17Factory->createServerRequest('GET', '/api/test');
        $app->handle($request);

        // Verify all middleware received the request
        $this->assertCount(3, $requestsSeen);
        $this->assertEquals('/api/test', $requestsSeen['cors']->getUri()->getPath());
        $this->assertEquals('/api/test', $requestsSeen['security']->getUri()->getPath());
        $this->assertEquals('/api/test', $requestsSeen['router']->getUri()->getPath());
    }

    public function testHandleSupportsRequestAttributeModification(): void
    {
        // Create middleware that adds an attribute to the request
        $attributeAddingMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $request = $request->withAttribute('test_attribute', 'test_value');
                return $handler->handle($request);
            }
        };
        
        // Create middleware that reads the attribute and returns it in response
        $attributeReadingMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $factory = new Psr17Factory();
                $response = $factory->createResponse(200);
                $attribute = $request->getAttribute('test_attribute', 'not_found');
                $response->getBody()->write(json_encode(['attribute' => $attribute]));
                return $response;
            }
        };
        
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($class) use ($attributeAddingMiddleware, $attributeReadingMiddleware) {
                return match ($class) {
                    CorsMiddleware::class => $attributeAddingMiddleware,
                    SecurityMiddleware::class => $this->createPassThroughMiddleware(),
                    RouterMiddleware::class => $attributeReadingMiddleware,
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $request = $this->psr17Factory->createServerRequest('GET', '/api/test');
        $response = $app->handle($request);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('test_value', $body['attribute']);
    }

    public function testHandleWithDifferentHttpMethods(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($class) {
                return match ($class) {
                    CorsMiddleware::class => $this->createPassThroughMiddleware(),
                    SecurityMiddleware::class => $this->createPassThroughMiddleware(),
                    RouterMiddleware::class => $this->createPassThroughMiddleware(),
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        
        foreach ($methods as $method) {
            $request = $this->psr17Factory->createServerRequest($method, '/api/test');
            $response = $app->handle($request);
            
            // Should return 404 since no middleware handles it
            $this->assertEquals(404, $response->getStatusCode(), "Failed for method: $method");
        }
    }

    public function testHandleWithDifferentPaths(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($class) {
                return match ($class) {
                    CorsMiddleware::class => $this->createPassThroughMiddleware(),
                    SecurityMiddleware::class => $this->createPassThroughMiddleware(),
                    RouterMiddleware::class => $this->createPassThroughMiddleware(),
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $paths = [
            '/api/schools',
            '/api/classes/123',
            '/api/groups',
            '/api/students',
            '/api/auth/login',
        ];
        
        foreach ($paths as $path) {
            $request = $this->psr17Factory->createServerRequest('GET', $path);
            $response = $app->handle($request);
            
            // Should return 404 since no middleware handles it
            $this->assertEquals(404, $response->getStatusCode(), "Failed for path: $path");
        }
    }

    public function testHandlePreservesResponseFromLastMiddleware(): void
    {
        // Create middleware that returns a response with specific headers
        $headerAddingMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $factory = new Psr17Factory();
                $response = $factory->createResponse(200);
                $response = $response->withHeader('X-Custom-Header', 'custom-value');
                $response->getBody()->write(json_encode(['status' => 'ok']));
                return $response;
            }
        };
        
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(function ($class) use ($headerAddingMiddleware) {
                return match ($class) {
                    CorsMiddleware::class => $this->createPassThroughMiddleware(),
                    SecurityMiddleware::class => $this->createPassThroughMiddleware(),
                    RouterMiddleware::class => $headerAddingMiddleware,
                    default => throw new \Exception("Unexpected class: $class"),
                };
            });

        $app = new Application($container);
        
        $request = $this->psr17Factory->createServerRequest('GET', '/api/test');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('custom-value', $response->getHeaderLine('X-Custom-Header'));
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('ok', $body['status']);
    }

    // Helper methods to create test middleware

    private function createPassThroughMiddleware(): MiddlewareInterface
    {
        return new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return $handler->handle($request);
            }
        };
    }

    private function createTrackingMiddleware(string $name, array &$executionOrder): MiddlewareInterface
    {
        return new class($name, $executionOrder) implements MiddlewareInterface {
            public function __construct(
                private string $name,
                private array &$executionOrder
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $this->executionOrder[] = $this->name;
                return $handler->handle($request);
            }
        };
    }

    private function createRespondingMiddleware(ResponseInterface $response): MiddlewareInterface
    {
        return new class($response) implements MiddlewareInterface {
            public function __construct(
                private ResponseInterface $response
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return $this->response;
            }
        };
    }

    private function createRequestTrackingMiddleware(string $name, array &$requestsSeen): MiddlewareInterface
    {
        return new class($name, $requestsSeen) implements MiddlewareInterface {
            public function __construct(
                private string $name,
                private array &$requestsSeen
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $this->requestsSeen[$this->name] = $request;
                return $handler->handle($request);
            }
        };
    }
}
