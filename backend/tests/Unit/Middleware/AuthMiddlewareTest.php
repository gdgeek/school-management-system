<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Helper\JwtHelper;
use App\Middleware\AuthMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Unit tests for AuthMiddleware.
 *
 * Covers JWT extraction, validation, user-context injection, and security
 * properties (no query-param token, no internal detail leakage).
 */
class AuthMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;
    private JwtHelper $jwt;
    private AuthMiddleware $middleware;

    /** 32-char minimum secret required by JwtHelper */
    private string $secret = 'unit-test-secret-key-32chars-min';

    protected function setUp(): void
    {
        $this->factory    = new Psr17Factory();
        $this->jwt        = new JwtHelper($this->secret, 3600);
        $this->middleware = new AuthMiddleware($this->jwt, $this->factory);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeHandler(): RequestHandlerInterface
    {
        $response = $this->factory->createResponse(200);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    private function validToken(array $extra = []): string
    {
        return $this->jwt->generate(array_merge([
            'user_id'   => 42,
            'username'  => 'testuser',
            'roles'     => ['teacher'],
            'school_id' => 7,
        ], $extra));
    }

    // ── Missing token → 401 ───────────────────────────────────────────────────

    public function testMissingTokenReturns401(): void
    {
        $request  = new ServerRequest('GET', '/api/auth/user');
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testMissingTokenResponseBodyHasCorrectFormat(): void
    {
        $request  = new ServerRequest('GET', '/api/auth/user');
        $response = $this->middleware->process($request, $this->makeHandler());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertSame(401, $body['code']);
        $this->assertSame('Missing authentication token', $body['message']);
        $this->assertNull($body['data']);
        $this->assertArrayHasKey('timestamp', $body);
    }

    // ── Valid Bearer token → passes through with user context ─────────────────

    public function testValidBearerTokenPassesThrough(): void
    {
        $token   = $this->validToken();
        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer ' . $token);

        $handler = $this->makeHandler();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn(
            $this->factory->createResponse(200)
        );

        $response = $this->middleware->process($request, $handler);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testValidTokenInjectsUserIdIntoRequestAttributes(): void
    {
        $token   = $this->validToken(['user_id' => 99]);
        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer ' . $token);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function ($req) use (&$capturedRequest) {
                $capturedRequest = $req;
                return (new Psr17Factory())->createResponse(200);
            }
        );

        $this->middleware->process($request, $handler);

        $this->assertSame(99, $capturedRequest->getAttribute('user_id'));
    }

    public function testValidTokenInjectsUsernameAndRoles(): void
    {
        $token   = $this->validToken(['username' => 'alice', 'roles' => ['admin']]);
        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer ' . $token);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function ($req) use (&$capturedRequest) {
                $capturedRequest = $req;
                return (new Psr17Factory())->createResponse(200);
            }
        );

        $this->middleware->process($request, $handler);

        $this->assertSame('alice', $capturedRequest->getAttribute('username'));
        $this->assertSame(['admin'], $capturedRequest->getAttribute('roles'));
    }

    // ── Invalid / expired token → 401 ────────────────────────────────────────

    public function testInvalidTokenReturns401(): void
    {
        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer invalid.token.here');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testExpiredTokenReturns401(): void
    {
        $expiredJwt = new JwtHelper($this->secret, -1);
        $token      = $expiredJwt->generate(['user_id' => 1, 'roles' => ['admin']]);

        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer ' . $token);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testWrongSecretTokenReturns401(): void
    {
        $otherJwt = new JwtHelper('attacker-secret-key-must-be-32chars', 3600);
        $token    = $otherJwt->generate(['user_id' => 1, 'roles' => ['admin']]);

        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer ' . $token);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── 401 response must not leak internal details ───────────────────────────

    public function testInvalidTokenResponseDoesNotLeakInternals(): void
    {
        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer bad.token.value');

        $response = $this->middleware->process($request, $this->makeHandler());
        $body     = json_decode((string)$response->getBody(), true);

        // Must not expose stack traces, class names, or raw exception messages
        $this->assertStringNotContainsString('Exception', $body['message']);
        $this->assertStringNotContainsString('firebase', strtolower($body['message']));
        $this->assertArrayNotHasKey('trace', $body);
        $this->assertArrayNotHasKey('file', $body);
    }

    // ── Security: query-param token must NOT be accepted ─────────────────────

    public function testQueryParamTokenIsNotAccepted(): void
    {
        // Tokens in URLs appear in server logs, browser history, and Referer headers.
        // The middleware must NOT extract tokens from query parameters.
        $token   = $this->validToken();
        $request = new ServerRequest('GET', '/api/auth/user?token=' . $token);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $this->middleware->process($request, $handler);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── Cookie token extraction ───────────────────────────────────────────────

    public function testCookieTokenIsAccepted(): void
    {
        $token   = $this->validToken();
        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withCookieParams(['auth_token' => $token]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn(
            $this->factory->createResponse(200)
        );

        $response = $this->middleware->process($request, $handler);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Bearer header takes priority over cookie ──────────────────────────────

    public function testBearerHeaderTakesPriorityOverCookie(): void
    {
        $validToken   = $this->validToken(['user_id' => 10]);
        $invalidToken = 'invalid.cookie.token';

        $request = (new ServerRequest('GET', '/api/auth/user'))
            ->withHeader('Authorization', 'Bearer ' . $validToken)
            ->withCookieParams(['auth_token' => $invalidToken]);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function ($req) use (&$capturedRequest) {
                $capturedRequest = $req;
                return (new Psr17Factory())->createResponse(200);
            }
        );

        $response = $this->middleware->process($request, $handler);

        // Should succeed using the Bearer token, not fail on the invalid cookie
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(10, $capturedRequest->getAttribute('user_id'));
    }

    // ── Response Content-Type ─────────────────────────────────────────────────

    public function testUnauthorizedResponseHasJsonContentType(): void
    {
        $request  = new ServerRequest('GET', '/api/auth/user');
        $response = $this->middleware->process($request, $this->makeHandler());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }
}
