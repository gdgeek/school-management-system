<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Helper\Logger;
use App\Middleware\RequestLoggingMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

class RequestLoggingMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->logger  = $this->createMock(Logger::class);
    }

    private function makeMiddleware(): RequestLoggingMiddleware
    {
        return new RequestLoggingMiddleware($this->logger);
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        $response = $this->factory->createResponse($status);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    // -----------------------------------------------------------------------
    // Basic logging behaviour
    // -----------------------------------------------------------------------

    public function testLogsRequestAndResponse(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('[REQUEST] GET /api/health'));

        $request  = new ServerRequest('GET', '/api/health');
        $response = $this->makeMiddleware()->process($request, $this->makeHandler(200));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLogMessageContainsResponseStatus(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('[RESPONSE] 404'));

        $request = new ServerRequest('GET', '/api/missing');
        $this->makeMiddleware()->process($request, $this->makeHandler(404));
    }

    public function testLogMessageContainsMethod(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('[REQUEST] POST'));

        $request = new ServerRequest('POST', '/api/schools');
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testLogMessageContainsQueryString(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('/api/schools?page=2&pageSize=10'));

        $request = new ServerRequest('GET', '/api/schools?page=2&pageSize=10');
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testLogMessageOmitsQueryStringWhenEmpty(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->logicalNot($this->stringContains('?')));

        $request = new ServerRequest('GET', '/api/health');
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    // -----------------------------------------------------------------------
    // Sensitive data is NOT logged
    // -----------------------------------------------------------------------

    public function testAuthorizationHeaderValueIsNotLogged(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.secret.signature';

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->logicalNot($this->stringContains($token)));

        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', "Bearer {$token}");

        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    // -----------------------------------------------------------------------
    // IP resolution
    // -----------------------------------------------------------------------

    public function testLogsRemoteAddrWhenNoProxyHeaders(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('192.168.1.1'));

        $request = new ServerRequest('GET', '/api/health', [], null, '1.1', ['REMOTE_ADDR' => '192.168.1.1']);
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testPrefersXForwardedForOverRemoteAddr(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('10.0.0.5'));

        $request = (new ServerRequest('GET', '/api/health', [], null, '1.1', ['REMOTE_ADDR' => '172.16.0.1']))
            ->withHeader('X-Forwarded-For', '10.0.0.5, 172.16.0.1');

        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testUsesXRealIpWhenXForwardedForAbsent(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('203.0.113.42'));

        $request = (new ServerRequest('GET', '/api/health'))
            ->withHeader('X-Real-IP', '203.0.113.42');

        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testFallsBackToUnknownWhenNoIpAvailable(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('unknown'));

        $request = new ServerRequest('GET', '/api/health');
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    // -----------------------------------------------------------------------
    // User-Agent handling
    // -----------------------------------------------------------------------

    public function testLogsUserAgent(): void
    {
        $ua = 'Mozilla/5.0 (compatible; TestBot/1.0)';

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains($ua));

        $request = (new ServerRequest('GET', '/api/health'))
            ->withHeader('User-Agent', $ua);

        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testLogsUnknownWhenUserAgentAbsent(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('unknown'));

        $request = new ServerRequest('GET', '/api/health');
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testTruncatesVeryLongUserAgent(): void
    {
        $longUa = str_repeat('A', 300);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->logicalNot($this->stringContains($longUa)));

        $request = (new ServerRequest('GET', '/api/health'))
            ->withHeader('User-Agent', $longUa);

        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    // -----------------------------------------------------------------------
    // Response is passed through unchanged
    // -----------------------------------------------------------------------

    public function testResponseIsReturnedUnmodified(): void
    {
        $this->logger->method('info');

        $request  = new ServerRequest('DELETE', '/api/schools/5');
        $response = $this->makeMiddleware()->process($request, $this->makeHandler(204));

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testHandlerIsAlwaysCalled(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->factory->createResponse(200));

        $this->logger->method('info');

        $request = new ServerRequest('GET', '/api/health');
        $this->makeMiddleware()->process($request, $handler);
    }
}
