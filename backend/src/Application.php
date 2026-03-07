<?php

declare(strict_types=1);

namespace App;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

/**
 * PSR-15 Application Bootstrap
 * 
 * Initializes the PSR-15 middleware pipeline and handles request/response lifecycle.
 */
class Application
{
    private ContainerInterface $container;
    private array $middleware = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        
        // Load middleware configuration
        $middlewareConfig = require dirname(__DIR__) . '/config/middleware.php';
        $globalMiddleware = $middlewareConfig['global'] ?? [];
        
        // Build middleware stack: global middleware + router middleware
        $this->middleware = array_merge(
            $globalMiddleware,
            [\App\Middleware\RouterMiddleware::class]
        );
    }

    /**
     * Handle a PSR-7 ServerRequest and return a PSR-7 Response
     * 
     * @param ServerRequestInterface $request The incoming request
     * @return ResponseInterface The response to send to the client
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Create a fallback handler that returns 404 if no middleware handles the request
        $fallbackHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $factory = new Psr17Factory();
                $response = $factory->createResponse(404);
                $response->getBody()->write(json_encode([
                    'code' => 404,
                    'message' => 'Not Found',
                    'data' => null,
                    'timestamp' => time(),
                ]));
                
                return $response->withHeader('Content-Type', 'application/json');
            }
        };
        
        // Build middleware chain and execute
        return $this->executeMiddlewareStack($request, $fallbackHandler);
    }

    /**
     * Execute middleware stack
     * 
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $fallbackHandler
     * @return ResponseInterface
     */
    private function executeMiddlewareStack(
        ServerRequestInterface $request,
        RequestHandlerInterface $fallbackHandler
    ): ResponseInterface {
        // Build the middleware chain from the end to the beginning
        $handler = $fallbackHandler;
        
        // Reverse the middleware array so we build the chain correctly
        $middlewareStack = array_reverse($this->middleware);
        
        foreach ($middlewareStack as $middlewareClass) {
            $middleware = $this->container->get($middlewareClass);
            
            // Create a new handler that wraps the current handler
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
        
        // Execute the chain
        return $handler->handle($request);
    }

    /**
     * Bootstrap the application from PHP globals and emit the response
     * 
     * This method:
     * 1. Creates a PSR-7 ServerRequest from PHP superglobals
     * 2. Dispatches the request through the middleware stack
     * 3. Emits the PSR-7 response to the client
     * 
     * @return void
     */
    public function run(): void
    {
        // Create PSR-7 request from PHP globals
        $request = $this->createServerRequestFromGlobals();
        
        // Handle the request through middleware pipeline
        $response = $this->handle($request);
        
        // Emit the response
        $this->emitResponse($response);
    }

    /**
     * Create a PSR-7 ServerRequest from PHP superglobals
     * 
     * @return ServerRequestInterface
     */
    private function createServerRequestFromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        
        $creator = new ServerRequestCreator(
            $psr17Factory, // ServerRequestFactory
            $psr17Factory, // UriFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // StreamFactory
        );
        
        return $creator->fromGlobals();
    }

    /**
     * Emit a PSR-7 Response to the client
     * 
     * @param ResponseInterface $response The response to emit
     * @return void
     */
    private function emitResponse(ResponseInterface $response): void
    {
        // Emit status line
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $protocolVersion = $response->getProtocolVersion();
        
        header(
            sprintf('HTTP/%s %d %s', $protocolVersion, $statusCode, $reasonPhrase),
            true,
            $statusCode
        );
        
        // Emit headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        
        // Emit body (rewind stream first — write() leaves pointer at end)
        $body = $response->getBody();
        $body->rewind();
        echo $body;
    }
}
