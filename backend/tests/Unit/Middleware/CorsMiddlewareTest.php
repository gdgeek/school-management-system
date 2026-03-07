<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\CorsMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    // -------------------------------------------------------------------------
    // Preflight (OPTIONS) tests
    // -------------------------------------------------------------------------

    public function testPreflightRequestReturns200(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = (new ServerRequest('OPTIONS', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPreflightNeverForwardsToHandler(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = (new ServerRequest('OPTIONS', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $middleware->process($request, $handler);
    }

    public function testPreflightAddsCorsHeaders(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins'     => ['http://localhost:5173'],
            'methods'     => ['GET', 'POST', 'PUT', 'DELETE'],
            'headers'     => ['Authorization', 'Content-Type'],
            'credentials' => true,
            'maxAge'      => 3600,
        ]);

        $request = (new ServerRequest('OPTIONS', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);

        $this->assertEquals('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, PUT, DELETE', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertEquals('Authorization, Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertEquals('3600', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function testPreflightFromDisallowedOriginHasNoCorsHeaders(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = (new ServerRequest('OPTIONS', '/api/test'))
            ->withHeader('Origin', 'http://evil.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    // -------------------------------------------------------------------------
    // Actual request tests
    // -------------------------------------------------------------------------

    public function testActualRequestAddsCorsHeaders(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testDisallowedOriginDoesNotAddCorsHeaders(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://evil.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    // -------------------------------------------------------------------------
    // Vary: Origin header
    // -------------------------------------------------------------------------

    public function testVaryOriginHeaderAddedForAllowedOrigin(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertStringContainsString('Origin', $response->getHeaderLine('Vary'));
    }

    public function testVaryOriginHeaderAddedForDisallowedOrigin(): void
    {
        // Even rejected origins should get Vary: Origin so caches don't serve
        // a cached "no CORS headers" response to an allowed origin.
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://evil.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertStringContainsString('Origin', $response->getHeaderLine('Vary'));
    }

    // -------------------------------------------------------------------------
    // Wildcard origin
    // -------------------------------------------------------------------------

    public function testWildcardOriginWithoutCredentialsReturnsStarHeader(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins'     => ['*'],
            'credentials' => false,
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://any-domain.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testWildcardOriginWithCredentialsEchoesRequestOrigin(): void
    {
        // Browsers reject "Access-Control-Allow-Origin: *" when credentials are
        // enabled. The middleware must echo the specific request origin instead.
        $middleware = new CorsMiddleware($this->factory, [
            'origins'     => ['*'],
            'credentials' => true,
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://any-domain.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        // Must echo the specific origin, NOT '*'
        $this->assertEquals('http://any-domain.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    // -------------------------------------------------------------------------
    // Wildcard pattern matching
    // -------------------------------------------------------------------------

    public function testWildcardPatternMatchesSubdomain(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['https://*.example.com'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'https://app.example.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertEquals('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testWildcardPatternDoesNotMatchDifferentDomain(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['https://*.example.com'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'https://evil.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testWildcardPatternDoesNotMatchNestedSubdomain(): void
    {
        // "https://*.example.com" should NOT match "https://a.b.example.com"
        // because the wildcard only covers a single label.
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['https://*.example.com'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'https://a.b.example.com');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    // -------------------------------------------------------------------------
    // Expose headers
    // -------------------------------------------------------------------------

    public function testExposeHeadersAddedWhenConfigured(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
            'expose'  => ['X-Total-Count', 'X-Request-Id'],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertEquals('X-Total-Count, X-Request-Id', $response->getHeaderLine('Access-Control-Expose-Headers'));
    }

    public function testExposeHeadersNotAddedWhenEmpty(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
            'expose'  => [],
        ]);

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertFalse($response->hasHeader('Access-Control-Expose-Headers'));
    }

    // -------------------------------------------------------------------------
    // Credentials
    // -------------------------------------------------------------------------

    public function testCredentialsCanBeDisabled(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins'     => ['http://localhost:5173'],
            'credentials' => false,
        ]);

        $request = (new ServerRequest('OPTIONS', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $handler = $this->createMock(RequestHandlerInterface::class);

        $response = $middleware->process($request, $handler);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
    }

    // -------------------------------------------------------------------------
    // No Origin header (same-origin / non-browser)
    // -------------------------------------------------------------------------

    public function testNoOriginHeaderSkipsCorsHeaders(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173'],
        ]);

        $request = new ServerRequest('GET', '/api/test');
        // No Origin header

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->factory->createResponse(200));

        $response = $middleware->process($request, $handler);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($response->hasHeader('Vary'));
    }

    // -------------------------------------------------------------------------
    // Multiple allowed origins
    // -------------------------------------------------------------------------

    public function testMultipleAllowedOrigins(): void
    {
        $middleware = new CorsMiddleware($this->factory, [
            'origins' => ['http://localhost:5173', 'http://localhost:3000'],
        ]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        foreach (['http://localhost:5173', 'http://localhost:3000'] as $origin) {
            $request = (new ServerRequest('GET', '/api/test'))
                ->withHeader('Origin', $origin);

            $response = $middleware->process($request, $handler);
            $this->assertEquals($origin, $response->getHeaderLine('Access-Control-Allow-Origin'));
        }
    }

    // -------------------------------------------------------------------------
    // Environment variable parsing
    // -------------------------------------------------------------------------

    public function testParseOriginsFromEnvironmentVariable(): void
    {
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'http://localhost:5173,http://localhost:3000';

        $middleware = new CorsMiddleware($this->factory);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $response = $middleware->process($request, $handler);
        $this->assertEquals('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));

        unset($_ENV['CORS_ALLOWED_ORIGINS']);
    }

    public function testDefaultConfigurationUsesLocalhostWhenNoEnvVar(): void
    {
        unset($_ENV['CORS_ALLOWED_ORIGINS']);

        $middleware = new CorsMiddleware($this->factory);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        // Default should allow localhost:5173
        $request = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://localhost:5173');

        $response = $middleware->process($request, $handler);
        $this->assertEquals('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));

        // And reject unknown origins
        $request2 = (new ServerRequest('GET', '/api/test'))
            ->withHeader('Origin', 'http://unknown.com');

        $response2 = $middleware->process($request2, $handler);
        $this->assertFalse($response2->hasHeader('Access-Control-Allow-Origin'));
    }
}
