<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Contract\RedisInterface;
use App\Middleware\RateLimitMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RateLimitMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;
    private RedisInterface $redis;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->redis   = $this->createMock(RedisInterface::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMiddleware(int $maxRequests = 100, int $window = 60): RateLimitMiddleware
    {
        return new RateLimitMiddleware(
            $this->redis,
            $this->factory,
            ['max_requests' => $maxRequests, 'window_seconds' => $window]
        );
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        $response = $this->factory->createResponse($status);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    private function makeRequest(string $ip = '1.2.3.4'): ServerRequest
    {
        return new ServerRequest('POST', '/api/auth/login', [], null, '1.1', ['REMOTE_ADDR' => $ip]);
    }

    // -------------------------------------------------------------------------
    // Under-limit: request passes through
    // -------------------------------------------------------------------------

    public function testRequestUnderLimitPassesThrough(): void
    {
        $this->redis->method('incrBy')->willReturn(1);
        $this->redis->method('expire')->willReturn(true);

        $handler = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestAtLimitPassesThrough(): void
    {
        // Exactly at the limit (count == maxRequests) should still pass
        $this->redis->method('incrBy')->willReturn(10);
        $this->redis->method('expire')->willReturn(true);

        $handler = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Over-limit: 429 response
    // -------------------------------------------------------------------------

    public function testRequestOverLimitReturns429(): void
    {
        $this->redis->method('incrBy')->willReturn(11); // over limit of 10
        $this->redis->method('expire')->willReturn(true);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $handler);

        $this->assertSame(429, $response->getStatusCode());
    }

    public function test429ResponseHasJsonBody(): void
    {
        $this->redis->method('incrBy')->willReturn(11);
        $this->redis->method('expire')->willReturn(true);

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string)$response->getBody(), true);
        $this->assertSame(429, $body['code']);
        $this->assertSame('Too Many Requests', $body['message']);
    }

    public function test429ResponseHasRetryAfterHeader(): void
    {
        $this->redis->method('incrBy')->willReturn(11);
        $this->redis->method('expire')->willReturn(true);

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $this->makeHandler());

        $this->assertTrue($response->hasHeader('Retry-After'));
        $this->assertGreaterThanOrEqual(0, (int)$response->getHeaderLine('Retry-After'));
    }

    // -------------------------------------------------------------------------
    // Rate-limit headers on every response
    // -------------------------------------------------------------------------

    public function testRateLimitHeadersAddedToNormalResponse(): void
    {
        $this->redis->method('incrBy')->willReturn(3);
        $this->redis->method('expire')->willReturn(true);

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $this->makeHandler());

        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
    }

    public function testRateLimitLimitHeaderMatchesConfig(): void
    {
        $this->redis->method('incrBy')->willReturn(1);
        $this->redis->method('expire')->willReturn(true);

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame('10', $response->getHeaderLine('X-RateLimit-Limit'));
    }

    public function testRateLimitRemainingHeaderIsCorrect(): void
    {
        $this->redis->method('incrBy')->willReturn(3); // 3rd request out of 10
        $this->redis->method('expire')->willReturn(true);

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame('7', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testRateLimitRemainingIsZeroWhenAtLimit(): void
    {
        $this->redis->method('incrBy')->willReturn(10); // exactly at limit
        $this->redis->method('expire')->willReturn(true);

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testRateLimitRemainingIsZeroWhenOverLimit(): void
    {
        $this->redis->method('incrBy')->willReturn(15); // over limit
        $this->redis->method('expire')->willReturn(true);

        $response = $this->makeMiddleware(10)->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testRateLimitResetHeaderIsUnixTimestamp(): void
    {
        $this->redis->method('incrBy')->willReturn(1);
        $this->redis->method('expire')->willReturn(true);

        $before   = time();
        $response = $this->makeMiddleware(10, 60)->process($this->makeRequest(), $this->makeHandler());
        $after    = time() + 60;

        $reset = (int)$response->getHeaderLine('X-RateLimit-Reset');
        $this->assertGreaterThanOrEqual($before, $reset);
        $this->assertLessThanOrEqual($after, $reset);
    }

    // -------------------------------------------------------------------------
    // Redis TTL is set on first request in window
    // -------------------------------------------------------------------------

    public function testExpireIsCalledOnFirstRequest(): void
    {
        $this->redis->method('incrBy')->willReturn(1); // first request
        $this->redis->expects($this->once())
            ->method('expire')
            ->with($this->stringContains('rate_limit:'), 120); // 2 × window

        $this->makeMiddleware(10, 60)->process($this->makeRequest(), $this->makeHandler());
    }

    public function testExpireIsNotCalledOnSubsequentRequests(): void
    {
        $this->redis->method('incrBy')->willReturn(5); // not first request
        $this->redis->expects($this->never())->method('expire');

        $this->makeMiddleware(10, 60)->process($this->makeRequest(), $this->makeHandler());
    }

    // -------------------------------------------------------------------------
    // IP resolution
    // -------------------------------------------------------------------------

    public function testResolvesIpFromRemoteAddr(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']);

        $this->assertSame('10.0.0.1', $middleware->resolveClientIp($request));
    }

    public function testResolvesIpFromXForwardedFor(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = (new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']))
            ->withHeader('X-Forwarded-For', '203.0.113.5, 10.0.0.1');

        $this->assertSame('203.0.113.5', $middleware->resolveClientIp($request));
    }

    public function testResolvesIpFromXRealIp(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = (new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']))
            ->withHeader('X-Real-IP', '198.51.100.7');

        $this->assertSame('198.51.100.7', $middleware->resolveClientIp($request));
    }

    public function testXForwardedForTakesPriorityOverXRealIp(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = (new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']))
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->withHeader('X-Real-IP', '198.51.100.7');

        $this->assertSame('203.0.113.5', $middleware->resolveClientIp($request));
    }

    public function testFallbackIpWhenNoServerParams(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = new ServerRequest('GET', '/api/test');

        $this->assertSame('127.0.0.1', $middleware->resolveClientIp($request));
    }

    public function testInvalidIpInXForwardedForFallsBackToRemoteAddr(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = (new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']))
            ->withHeader('X-Forwarded-For', 'not-an-ip-address');

        // Malformed XFF value must be rejected; fall back to REMOTE_ADDR
        $this->assertSame('10.0.0.1', $middleware->resolveClientIp($request));
    }

    public function testInvalidIpInXRealIpFallsBackToRemoteAddr(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = (new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.2']))
            ->withHeader('X-Real-IP', 'invalid!!ip');

        $this->assertSame('10.0.0.2', $middleware->resolveClientIp($request));
    }

    public function testIpv6AddressIsAccepted(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = (new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => '::1']))
            ->withHeader('X-Forwarded-For', '2001:db8::1');

        $this->assertSame('2001:db8::1', $middleware->resolveClientIp($request));
    }

    public function testRedisKeyUsesHashedIpToAvoidCollisions(): void
    {
        // IPv6 addresses contain colons which would break a naive "ip:window" key
        $middleware  = $this->makeMiddleware(10, 60);
        $windowStart = $middleware->currentWindowStart();
        $ipv6        = '2001:db8::1';
        $hashedIp    = md5($ipv6);

        $this->redis->expects($this->once())
            ->method('incrBy')
            ->with('rate_limit:' . $hashedIp . ':' . $windowStart, 1)
            ->willReturn(1);
        $this->redis->method('expire')->willReturn(true);

        $request = (new ServerRequest('GET', '/api/test', [], null, '1.1', ['REMOTE_ADDR' => $ipv6]));
        $middleware->process($request, $this->makeHandler());
    }

    // -------------------------------------------------------------------------
    // Redis key includes IP and window start
    // -------------------------------------------------------------------------

    public function testRedisKeyContainsIpAndWindowStart(): void
    {
        $middleware  = $this->makeMiddleware(10, 60);
        $windowStart = $middleware->currentWindowStart();
        $hashedIp    = md5('1.2.3.4');

        $this->redis->expects($this->once())
            ->method('incrBy')
            ->with('rate_limit:' . $hashedIp . ':' . $windowStart, 1)
            ->willReturn(1);

        $this->redis->method('expire')->willReturn(true);

        $middleware->process($this->makeRequest('1.2.3.4'), $this->makeHandler());
    }

    // -------------------------------------------------------------------------
    // Configuration via constructor
    // -------------------------------------------------------------------------

    public function testDefaultConfigurationValues(): void
    {
        // Unset env vars to test defaults
        unset($_ENV['RATE_LIMIT_REQUESTS'], $_ENV['RATE_LIMIT_WINDOW']);

        $middleware = new RateLimitMiddleware($this->redis, $this->factory);

        $this->assertSame(100, $middleware->getMaxRequests());
        $this->assertSame(60,  $middleware->getWindowSeconds());
    }

    public function testCustomConfigurationOverridesDefaults(): void
    {
        $middleware = $this->makeMiddleware(10, 30);

        $this->assertSame(10, $middleware->getMaxRequests());
        $this->assertSame(30, $middleware->getWindowSeconds());
    }

    public function testLoginRateLimitIsStricter(): void
    {
        // Simulate the login-specific middleware (10 req/min)
        $loginMiddleware   = $this->makeMiddleware(10, 60);
        $generalMiddleware = $this->makeMiddleware(100, 60);

        $this->assertLessThan(
            $generalMiddleware->getMaxRequests(),
            $loginMiddleware->getMaxRequests()
        );
    }
}
