<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\SecurityMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    private function makeHandler(?ResponseInterface $response = null): RequestHandlerInterface
    {
        $factory = $this->factory;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(
            $response ?? $factory->createResponse(200)
        );
        return $handler;
    }

    private function makeMiddleware(array $config = []): SecurityMiddleware
    {
        return new SecurityMiddleware($config);
    }

    // ── X-Frame-Options ──────────────────────────────────────────────────────

    public function testXFrameOptionsIsSameOrigin(): void
    {
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
    }

    // ── X-Content-Type-Options ───────────────────────────────────────────────

    public function testXContentTypeOptionsIsNoSniff(): void
    {
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    // ── X-XSS-Protection ─────────────────────────────────────────────────────

    public function testXXssProtectionIsZero(): void
    {
        // Deprecated header — modern browsers use CSP; value must be 0
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame('0', $response->getHeaderLine('X-XSS-Protection'));
    }

    // ── Referrer-Policy ──────────────────────────────────────────────────────

    public function testReferrerPolicy(): void
    {
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
    }

    // ── Permissions-Policy ───────────────────────────────────────────────────

    public function testPermissionsPolicyIncludesPayment(): void
    {
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $policy = $response->getHeaderLine('Permissions-Policy');
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('payment=()', $policy);
    }

    // ── Strict-Transport-Security ────────────────────────────────────────────

    public function testHstsIncludesPreload(): void
    {
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $hsts = $response->getHeaderLine('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts);
    }

    // ── Cross-Origin-Opener-Policy ───────────────────────────────────────────

    public function testCrossOriginOpenerPolicy(): void
    {
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Opener-Policy'));
    }

    // ── Cross-Origin-Resource-Policy ─────────────────────────────────────────

    public function testCrossOriginResourcePolicyIsCrossOrigin(): void
    {
        // API must allow cross-origin reads
        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame('cross-origin', $response->getHeaderLine('Cross-Origin-Resource-Policy'));
    }

    // ── X-Powered-By removal ─────────────────────────────────────────────────

    public function testXPoweredByIsRemoved(): void
    {
        $factory = $this->factory;
        // Simulate a response that already carries X-Powered-By (e.g. set by PHP)
        $upstreamResponse = $factory->createResponse(200)->withHeader('X-Powered-By', 'PHP/8.1');

        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler($upstreamResponse));

        $this->assertFalse($response->hasHeader('X-Powered-By'));
    }

    // ── Content-Security-Policy ──────────────────────────────────────────────

    public function testCspHeaderPresentByDefault(): void
    {
        $middleware = $this->makeMiddleware(['enableCsp' => true]);
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertTrue($response->hasHeader('Content-Security-Policy'));
        $csp = $response->getHeaderLine('Content-Security-Policy');
        $this->assertNotEmpty($csp);
        // Default policy for a pure API must not allow unsafe-inline scripts
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        // Must include frame-ancestors to prevent clickjacking via CSP
        $this->assertStringContainsString('frame-ancestors', $csp);
    }

    public function testCspHeaderAbsentWhenDisabled(): void
    {
        $middleware = $this->makeMiddleware(['enableCsp' => false]);
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertFalse($response->hasHeader('Content-Security-Policy'));
    }

    public function testCustomCspPolicyIsUsed(): void
    {
        $customPolicy = "default-src 'none'";
        $middleware = $this->makeMiddleware([
            'enableCsp' => true,
            'cspPolicy' => $customPolicy,
        ]);
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame($customPolicy, $response->getHeaderLine('Content-Security-Policy'));
    }

    // ── All required headers present in a single pass ─────────────────────────

    public function testAllRequiredSecurityHeadersArePresent(): void
    {
        $middleware = $this->makeMiddleware(['enableCsp' => true]);
        $request = new ServerRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->makeHandler());

        $required = [
            'X-Frame-Options',
            'X-Content-Type-Options',
            'X-XSS-Protection',
            'Referrer-Policy',
            'Permissions-Policy',
            'Strict-Transport-Security',
            'Cross-Origin-Opener-Policy',
            'Cross-Origin-Resource-Policy',
            'Content-Security-Policy',
        ];

        foreach ($required as $header) {
            $this->assertTrue(
                $response->hasHeader($header),
                "Missing required security header: $header"
            );
        }
    }

    // ── Downstream response is passed through unchanged (except headers) ──────

    public function testResponseStatusCodeIsPreserved(): void
    {
        $factory = $this->factory;
        $upstream = $factory->createResponse(201);

        $middleware = $this->makeMiddleware();
        $request = new ServerRequest('POST', '/api/test');
        $response = $middleware->process($request, $this->makeHandler($upstream));

        $this->assertSame(201, $response->getStatusCode());
    }
}
