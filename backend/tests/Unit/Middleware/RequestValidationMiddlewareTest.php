<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\RequestValidationMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestValidationMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMiddleware(int $maxSize = RequestValidationMiddleware::DEFAULT_MAX_REQUEST_SIZE): RequestValidationMiddleware
    {
        return new RequestValidationMiddleware(
            $this->factory,
            ['max_request_size' => $maxSize]
        );
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        $response = $this->factory->createResponse($status);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    private function makeRequest(
        string $method,
        string $body = '',
        string $contentType = ''
    ): ServerRequest {
        $headers = [];
        if ($contentType !== '') {
            $headers['Content-Type'] = $contentType;
        }

        $stream = $this->factory->createStream($body);
        return new ServerRequest($method, '/api/test', $headers, $stream);
    }

    // -------------------------------------------------------------------------
    // GET requests pass through without validation
    // -------------------------------------------------------------------------

    public function testGetRequestPassesThrough(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('GET');
        $handler    = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteRequestPassesThrough(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('DELETE');
        $handler    = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST/PUT/PATCH with valid JSON body pass through
    // -------------------------------------------------------------------------

    public function testPostWithValidJsonPassesThrough(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('POST', '{"name":"test"}', 'application/json');
        $handler    = $this->makeHandler(201);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testPutWithValidJsonPassesThrough(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('PUT', '{"id":1,"name":"updated"}', 'application/json');
        $handler    = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPatchWithValidJsonPassesThrough(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('PATCH', '{"name":"patched"}', 'application/json');
        $handler    = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST/PUT/PATCH with wrong Content-Type returns 400
    // -------------------------------------------------------------------------

    public function testPostWithWrongContentTypeReturns400(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('POST', 'name=test', 'application/x-www-form-urlencoded');
        $handler    = $this->makeHandler();
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testPutWithWrongContentTypeReturns400(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('PUT', 'name=test', 'text/plain');
        $handler    = $this->makeHandler();
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testPatchWithWrongContentTypeReturns400(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('PATCH', 'name=test', 'text/plain');
        $handler    = $this->makeHandler();
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(400, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST with empty body passes through (no Content-Type required)
    // -------------------------------------------------------------------------

    public function testPostWithEmptyBodyPassesThrough(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('POST', '');
        $handler    = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Content-Type with charset parameter is accepted
    // -------------------------------------------------------------------------

    public function testContentTypeWithCharsetIsAccepted(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('POST', '{"key":"value"}', 'application/json; charset=utf-8');
        $handler    = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Invalid JSON body returns 400
    // -------------------------------------------------------------------------

    public function testInvalidJsonBodyReturns400(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('POST', '{invalid json}', 'application/json');
        $handler    = $this->makeHandler();
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testTruncatedJsonBodyReturns400(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('GET', '{"name":', 'application/json');
        $handler    = $this->makeHandler();
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(400, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Body size validation
    // -------------------------------------------------------------------------

    public function testBodyExceedingMaxSizeReturns400(): void
    {
        $middleware = $this->makeMiddleware(10); // 10-byte limit
        $request    = $this->makeRequest('POST', str_repeat('x', 11), 'application/json');
        $handler    = $this->makeHandler();
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testBodyAtExactMaxSizePassesThrough(): void
    {
        $body       = str_repeat('x', 10);
        $middleware = $this->makeMiddleware(10);
        // Use GET so Content-Type and JSON checks are skipped
        $request    = $this->makeRequest('GET', $body);
        $handler    = $this->makeHandler(200);
        $handler->expects($this->once())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Error response format
    // -------------------------------------------------------------------------

    public function testErrorResponseHasJsonContentType(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('POST', 'bad', 'text/plain');

        $response = $middleware->process($request, $this->makeHandler());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testErrorResponseBodyHasRequiredFields(): void
    {
        $middleware = $this->makeMiddleware();
        $request    = $this->makeRequest('POST', 'bad', 'text/plain');

        $response = $middleware->process($request, $this->makeHandler());
        $body     = json_decode((string)$response->getBody(), true);

        $this->assertArrayHasKey('code', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('timestamp', $body);
        $this->assertSame(400, $body['code']);
        $this->assertNull($body['data']);
    }

    // -------------------------------------------------------------------------
    // Accessor
    // -------------------------------------------------------------------------

    public function testGetMaxRequestSizeReturnsConfiguredValue(): void
    {
        $middleware = $this->makeMiddleware(512);

        $this->assertSame(512, $middleware->getMaxRequestSize());
    }

    public function testDefaultMaxRequestSizeIs1MB(): void
    {
        $middleware = new RequestValidationMiddleware($this->factory);

        $this->assertSame(RequestValidationMiddleware::DEFAULT_MAX_REQUEST_SIZE, $middleware->getMaxRequestSize());
    }
}
