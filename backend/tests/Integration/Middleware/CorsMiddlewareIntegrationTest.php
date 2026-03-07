<?php

declare(strict_types=1);

namespace Tests\Integration\Middleware;

use App\Middleware\CorsMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Integration tests for CorsMiddleware
 * 
 * Tests the middleware in a more realistic scenario with actual request/response flow
 */
class CorsMiddlewareIntegrationTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testCorsWorkflowForFrontendOrigin(): void
    {
        // Simulate the frontend origin (http://localhost:5173)
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
            'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'headers' => ['Authorization', 'Content-Type'],
            'credentials' => true,
            'maxAge' => 86400
        ]);

        // Step 1: Browser sends preflight request
        $preflightRequest = new ServerRequest('OPTIONS', '/api/schools');
        $preflightRequest = $preflightRequest
            ->withHeader('Origin', 'http://localhost:5173')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'Authorization, Content-Type');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle'); // Preflight should not reach handler

        $preflightResponse = $middleware->process($preflightRequest, $handler);

        // Verify preflight response (200 for maximum client compatibility)
        $this->assertEquals(200, $preflightResponse->getStatusCode());
        $this->assertEquals('http://localhost:5173', $preflightResponse->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('POST', $preflightResponse->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('Authorization', $preflightResponse->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertEquals('true', $preflightResponse->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertEquals('86400', $preflightResponse->getHeaderLine('Access-Control-Max-Age'));

        // Step 2: Browser sends actual request
        $actualRequest = new ServerRequest('POST', '/api/schools');
        $actualRequest = $actualRequest
            ->withHeader('Origin', 'http://localhost:5173')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer fake-token');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $request) {
                // Simulate controller response
                $response = $this->factory->createResponse(201);
                $response->getBody()->write(json_encode([
                    'code' => 201,
                    'message' => 'School created successfully',
                    'data' => ['id' => 1, 'name' => 'Test School']
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            });

        $actualResponse = $middleware->process($actualRequest, $handler);

        // Verify actual response has CORS headers
        $this->assertEquals(201, $actualResponse->getStatusCode());
        $this->assertEquals('http://localhost:5173', $actualResponse->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('true', $actualResponse->getHeaderLine('Access-Control-Allow-Credentials'));
        
        // Verify response body is intact
        $body = json_decode((string)$actualResponse->getBody(), true);
        $this->assertEquals(201, $body['code']);
        $this->assertEquals('School created successfully', $body['message']);
    }

    public function testCorsBlocksUnauthorizedOrigin(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173']
        ]);

        // Request from unauthorized origin
        $request = new ServerRequest('GET', '/api/schools');
        $request = $request->withHeader('Origin', 'http://malicious-site.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        // Response should not have CORS headers
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
    }

    public function testCorsWithMultipleAllowedOrigins(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173', 'http://localhost:3000', 'https://app.example.com']
        ]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        // Test each allowed origin
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:3000',
            'https://app.example.com'
        ];

        foreach ($allowedOrigins as $origin) {
            $request = new ServerRequest('GET', '/api/test');
            $request = $request->withHeader('Origin', $origin);

            $response = $middleware->process($request, $handler);

            $this->assertEquals($origin, $response->getHeaderLine('Access-Control-Allow-Origin'));
        }
    }

    public function testCorsPreservesExistingResponseHeaders(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173']
        ]);

        $request = new ServerRequest('GET', '/api/test');
        $request = $request->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () {
                $response = $this->factory->createResponse(200);
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('X-Custom-Header', 'custom-value')
                    ->withHeader('Cache-Control', 'no-cache');
            });

        $response = $middleware->process($request, $handler);

        // Verify CORS headers are added
        $this->assertEquals('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));

        // Verify existing headers are preserved
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('custom-value', $response->getHeaderLine('X-Custom-Header'));
        $this->assertEquals('no-cache', $response->getHeaderLine('Cache-Control'));
    }

    public function testCorsWithEnvironmentVariableConfiguration(): void
    {
        // Set environment variable
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'http://localhost:5173,http://localhost:3000';

        $middleware = new CorsMiddleware($this->factory);

        $request = new ServerRequest('GET', '/api/test');
        $request = $request->withHeader('Origin', 'http://localhost:3000');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertEquals('http://localhost:3000', $response->getHeaderLine('Access-Control-Allow-Origin'));

        // Clean up
        unset($_ENV['CORS_ALLOWED_ORIGINS']);
    }
}
