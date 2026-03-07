<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Contract\RedisInterface;
use App\Middleware\ResponseCacheMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ResponseCacheMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;
    private RedisInterface $redis;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->redis   = $this->createMock(RedisInterface::class);
    }

    private function makeMiddleware(int $ttl = 60): ResponseCacheMiddleware
    {
        return new ResponseCacheMiddleware(
            $this->redis,
            $this->factory,
            $this->factory,
            $ttl
        );
    }

    private function makeHandler(int $status = 200, string $body = '{"ok":true}'): RequestHandlerInterface
    {
        $response = $this->factory->createResponse($status);
        $response->getBody()->write($body);
        $response = $response->withHeader('Content-Type', 'application/json');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    // -----------------------------------------------------------------------
    // Non-GET requests are never cached
    // -----------------------------------------------------------------------

    public function testPostRequestIsNotCached(): void
    {
        $this->redis->expects($this->never())->method('get');
        $this->redis->expects($this->never())->method('setex');

        $request = new ServerRequest('POST', '/api/schools');
        $handler = $this->makeHandler();

        $response = $this->makeMiddleware()->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('X-Cache'));
    }

    public function testPutRequestIsNotCached(): void
    {
        $this->redis->expects($this->never())->method('get');

        $request = new ServerRequest('PUT', '/api/schools/1');
        $response = $this->makeMiddleware()->process($request, $this->makeHandler());

        $this->assertFalse($response->hasHeader('X-Cache'));
    }

    public function testDeleteRequestIsNotCached(): void
    {
        $this->redis->expects($this->never())->method('get');

        $request = new ServerRequest('DELETE', '/api/schools/1');
        $response = $this->makeMiddleware()->process($request, $this->makeHandler());

        $this->assertFalse($response->hasHeader('X-Cache'));
    }

    // -----------------------------------------------------------------------
    // Authenticated requests are never cached
    // -----------------------------------------------------------------------

    public function testAuthenticatedGetRequestIsNotCached(): void
    {
        $this->redis->expects($this->never())->method('get');
        $this->redis->expects($this->never())->method('setex');

        $request = (new ServerRequest('GET', '/api/health'))
            ->withHeader('Authorization', 'Bearer some.jwt.token');

        $response = $this->makeMiddleware()->process($request, $this->makeHandler());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('X-Cache'));
    }

    // -----------------------------------------------------------------------
    // Cache MISS: response is stored in Redis
    // -----------------------------------------------------------------------

    public function testCacheMissStoresResponseInRedis(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->with('response_cache:/api/health')
            ->willReturn(false);

        $this->redis->expects($this->once())
            ->method('setex')
            ->with(
                'response_cache:/api/health',
                60,
                $this->isType('string')
            );

        $request  = new ServerRequest('GET', '/api/health');
        $response = $this->makeMiddleware()->process($request, $this->makeHandler());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('X-Cache')); // MISS — no X-Cache header
    }

    public function testCacheMissWithQueryStringUsesFullKey(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->with('response_cache:/api/schools?page=2&pageSize=10')
            ->willReturn(false);

        $this->redis->expects($this->once())
            ->method('setex');

        $request  = new ServerRequest('GET', '/api/schools?page=2&pageSize=10');
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    // -----------------------------------------------------------------------
    // Cache HIT: response is served from Redis
    // -----------------------------------------------------------------------

    public function testCacheHitServesFromRedis(): void
    {
        $cachedPayload = json_encode([
            'status'  => 200,
            'headers' => ['Content-Type' => ['application/json']],
            'body'    => '{"cached":true}',
        ]);

        $this->redis->expects($this->once())
            ->method('get')
            ->with('response_cache:/api/health')
            ->willReturn($cachedPayload);

        // Handler must NOT be called on a cache hit
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $request  = new ServerRequest('GET', '/api/health');
        $response = $this->makeMiddleware()->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('HIT', $response->getHeaderLine('X-Cache'));
        $this->assertEquals('{"cached":true}', (string) $response->getBody());
    }

    public function testCacheHitPreservesOriginalHeaders(): void
    {
        $cachedPayload = json_encode([
            'status'  => 200,
            'headers' => ['Content-Type' => ['application/json'], 'X-Custom' => ['value']],
            'body'    => '{}',
        ]);

        $this->redis->method('get')->willReturn($cachedPayload);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $request  = new ServerRequest('GET', '/api/health');
        $response = $this->makeMiddleware()->process($request, $handler);

        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('value', $response->getHeaderLine('X-Custom'));
    }

    // -----------------------------------------------------------------------
    // Non-200 responses are not cached
    // -----------------------------------------------------------------------

    public function testNon200ResponseIsNotCached(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->willReturn(false);

        // setex must NOT be called for a 404
        $this->redis->expects($this->never())->method('setex');

        $request  = new ServerRequest('GET', '/api/missing');
        $response = $this->makeMiddleware()->process($request, $this->makeHandler(404, '{"error":"not found"}'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testServerErrorResponseIsNotCached(): void
    {
        $this->redis->method('get')->willReturn(false);
        $this->redis->expects($this->never())->method('setex');

        $request = new ServerRequest('GET', '/api/health');
        $this->makeMiddleware()->process($request, $this->makeHandler(500, '{"error":"oops"}'));
    }

    // -----------------------------------------------------------------------
    // TTL configuration
    // -----------------------------------------------------------------------

    public function testDefaultTtlIsUsedWhenNoAttributeSet(): void
    {
        $this->redis->method('get')->willReturn(false);
        $this->redis->expects($this->once())
            ->method('setex')
            ->with($this->anything(), 60, $this->anything());

        $request = new ServerRequest('GET', '/api/health');
        $this->makeMiddleware(60)->process($request, $this->makeHandler());
    }

    public function testCustomTtlFromRequestAttribute(): void
    {
        $this->redis->method('get')->willReturn(false);
        $this->redis->expects($this->once())
            ->method('setex')
            ->with($this->anything(), 300, $this->anything());

        $request = (new ServerRequest('GET', '/api/health'))
            ->withAttribute('cache_ttl', 300);

        $this->makeMiddleware(60)->process($request, $this->makeHandler());
    }

    public function testInvalidCacheTtlAttributeFallsBackToDefault(): void
    {
        $this->redis->method('get')->willReturn(false);
        $this->redis->expects($this->once())
            ->method('setex')
            ->with($this->anything(), 60, $this->anything());

        // Non-integer attribute — should fall back to default
        $request = (new ServerRequest('GET', '/api/health'))
            ->withAttribute('cache_ttl', 'not-an-int');

        $this->makeMiddleware(60)->process($request, $this->makeHandler());
    }

    // -----------------------------------------------------------------------
    // Corrupt / unexpected cache data is handled gracefully
    // -----------------------------------------------------------------------

    public function testCorruptCacheDataFallsThroughToHandler(): void
    {
        $this->redis->method('get')->willReturn('not-valid-json{{{');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->factory->createResponse(200));

        $request  = new ServerRequest('GET', '/api/health');
        $response = $this->makeMiddleware()->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIncompleteCacheDataFallsThroughToHandler(): void
    {
        // Missing required fields
        $this->redis->method('get')->willReturn(json_encode(['status' => 200]));

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->factory->createResponse(200));

        $request  = new ServerRequest('GET', '/api/health');
        $response = $this->makeMiddleware()->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Redis write failure is non-fatal
    // -----------------------------------------------------------------------

    public function testRedisWriteFailureDoesNotBreakResponse(): void
    {
        $this->redis->method('get')->willReturn(false);
        $this->redis->method('setex')->willThrowException(new \RuntimeException('Redis down'));

        $request  = new ServerRequest('GET', '/api/health');
        $response = $this->makeMiddleware()->process($request, $this->makeHandler());

        // Response should still be returned normally
        $this->assertEquals(200, $response->getStatusCode());
    }
}
