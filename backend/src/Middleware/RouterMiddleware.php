<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use function FastRoute\cachedDispatcher;

/**
 * Router Middleware
 * 
 * Matches incoming requests to registered routes and dispatches to appropriate controllers.
 * 
 * Responsibilities:
 * - Match request path and method to registered routes using FastRoute
 * - Inject route parameters into request attributes
 * - Resolve and invoke controller from DI container
 * - Return 404 response for unmatched routes
 */
class RouterMiddleware implements MiddlewareInterface
{
    private Dispatcher $dispatcher;
    private array $routes;

    public function __construct(
        private ContainerInterface $container,
        private ResponseFactoryInterface $responseFactory
    ) {
        // Load routes configuration
        $this->routes = require dirname(__DIR__, 2) . '/config/routes.php';
        
        // Build FastRoute dispatcher — use cached version in production
        $routeDefinition = function (RouteCollector $r): void {
            foreach ($this->routes as $route) {
                $methods = $route['methods'] ?? ['GET'];
                $pattern = $route['pattern'] ?? '/';
                $handler = $route['handler'] ?? null;

                if ($handler) {
                    foreach ($methods as $method) {
                        $r->addRoute($method, $pattern, $handler);
                    }
                }
            }
        };

        $appEnv = $_ENV['APP_ENV'] ?? 'development';

        if ($appEnv === 'production') {
            $cacheDir = dirname(__DIR__, 2) . '/runtime/cache';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            $this->dispatcher = cachedDispatcher($routeDefinition, [
                'cacheFile'     => $cacheDir . '/route.cache',
                'cacheDisabled' => false,
            ]);
        } else {
            // Development / test: always re-parse routes so changes take effect immediately
            $this->dispatcher = simpleDispatcher($routeDefinition);
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        
        // Match the route
        $routeInfo = $this->dispatcher->dispatch($method, $uri);
        
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->notFoundResponse();
                
            case Dispatcher::METHOD_NOT_ALLOWED:
                return $this->methodNotAllowedResponse($routeInfo[1]);
                
            case Dispatcher::FOUND:
                $handlerString = $routeInfo[1];
                $vars = $routeInfo[2];
                
                // Inject route parameters into request attributes
                foreach ($vars as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }
                
                // Find the route configuration to get middleware
                $routeConfig = $this->findRouteConfig($handlerString);
                $routeMiddleware = $routeConfig['middleware'] ?? [];
                
                // If route has specific middleware, execute them before controller
                if (!empty($routeMiddleware)) {
                    return $this->executeRouteMiddleware($request, $routeMiddleware, $handlerString);
                }
                
                // No route-specific middleware, invoke controller directly
                return $this->invokeController($request, $handlerString);
        }
        
        return $this->notFoundResponse();
    }

    /**
     * Find route configuration by handler string
     * 
     * @param string $handler
     * @return array|null
     */
    private function findRouteConfig(string $handler): ?array
    {
        foreach ($this->routes as $route) {
            if (($route['handler'] ?? null) === $handler) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Execute route-specific middleware before invoking controller
     * 
     * @param ServerRequestInterface $request
     * @param array $middlewareClasses
     * @param string $handler
     * @return ResponseInterface
     */
    private function executeRouteMiddleware(
        ServerRequestInterface $request,
        array $middlewareClasses,
        string $handler
    ): ResponseInterface {
        // Build middleware chain with controller as the final handler
        $finalHandler = new class($this, $request, $handler) implements RequestHandlerInterface {
            public function __construct(
                private RouterMiddleware $router,
                private ServerRequestInterface $request,
                private string $handler
            ) {}
            
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Use reflection to call private invokeController method
                $reflection = new \ReflectionClass($this->router);
                $method = $reflection->getMethod('invokeController');
                $method->setAccessible(true);
                return $method->invoke($this->router, $request, $this->handler);
            }
        };
        
        // Build middleware chain from end to beginning
        $handler = $finalHandler;
        $middlewareStack = array_reverse($middlewareClasses);
        
        foreach ($middlewareStack as $middlewareClass) {
            $middleware = $this->container->get($middlewareClass);
            
            $handler = new class($middleware, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private $middleware,
                    private RequestHandlerInterface $next
                ) {}
                
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }
        
        // Execute the middleware chain
        return $handler->handle($request);
    }

    /**
     * Invoke controller action
     * 
     * @param ServerRequestInterface $request
     * @param string $handler Format: "ControllerClass::method"
     * @return ResponseInterface
     */
    private function invokeController(ServerRequestInterface $request, string $handler): ResponseInterface
    {
        // Parse handler string
        if (!str_contains($handler, '::')) {
            return $this->errorResponse('Invalid handler format', 500);
        }
        
        [$controllerClass, $method] = explode('::', $handler, 2);
        
        // Resolve controller from container
        $controller = $this->container->get($controllerClass);
        
        // Verify method exists
        if (!method_exists($controller, $method)) {
            return $this->errorResponse('Controller method not found', 500);
        }
        
        // Invoke controller method — let exceptions bubble up to ErrorHandlingMiddleware
        $response = $controller->$method($request);
        
        if (!$response instanceof ResponseInterface) {
            return $this->errorResponse('Controller must return ResponseInterface', 500);
        }
        
        return $response;
    }

    /**
     * Create a 404 Not Found response
     * 
     * @return ResponseInterface
     */
    private function notFoundResponse(): ResponseInterface
    {
        return $this->errorResponse('Not Found', 404);
    }

    /**
     * Create a 405 Method Not Allowed response
     * 
     * @param array $allowedMethods
     * @return ResponseInterface
     */
    private function methodNotAllowedResponse(array $allowedMethods): ResponseInterface
    {
        $response = $this->errorResponse('Method Not Allowed', 405);
        return $response->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * Create an error response
     * 
     * @param string $message
     * @param int $statusCode
     * @return ResponseInterface
     */
    private function errorResponse(string $message, int $statusCode): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write(json_encode([
            'code' => $statusCode,
            'message' => $message,
            'data' => null,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
