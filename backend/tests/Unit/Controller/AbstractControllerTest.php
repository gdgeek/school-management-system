<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\AbstractController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class AbstractControllerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private AbstractController $controller;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->controller = new class($this->responseFactory) extends AbstractController {
            public function testJson(mixed $data, int $status = 200): ResponseInterface
            {
                return $this->json($data, $status);
            }

            public function testSuccess(mixed $data, string $message = 'ok'): ResponseInterface
            {
                return $this->success($data, $message);
            }

            public function testError(string $message, int $status = 400): ResponseInterface
            {
                return $this->error($message, $status);
            }

            public function testGetJsonBody(ServerRequestInterface $request): array
            {
                return $this->getJsonBody($request);
            }

            public function testGetUserId(ServerRequestInterface $request): ?int
            {
                return $this->getUserId($request);
            }

            public function testGetUserRoles(ServerRequestInterface $request): array
            {
                return $this->getUserRoles($request);
            }
        };
    }

    public function testJsonCreatesResponseWithCorrectData(): void
    {
        $data = ['key' => 'value'];
        $status = 200;

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('write')
            ->with(json_encode($data, JSON_UNESCAPED_UNICODE));

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with($status)
            ->willReturn($response);

        $result = $this->controller->testJson($data, $status);
        $this->assertSame($response, $result);
    }

    public function testSuccessCreatesStandardResponse(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $message = 'Operation successful';

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) use ($data, $message) {
                $decoded = json_decode($json, true);
                return $decoded['code'] === 200
                    && $decoded['message'] === $message
                    && $decoded['data'] === $data
                    && isset($decoded['timestamp']);
            }));

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(200)
            ->willReturn($response);

        $result = $this->controller->testSuccess($data, $message);
        $this->assertSame($response, $result);
    }

    public function testSuccessUsesDefaultMessage(): void
    {
        $data = ['test' => 'data'];

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $decoded = json_decode($json, true);
                return $decoded['message'] === 'ok';
            }));

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->willReturn($response);

        $this->controller->testSuccess($data);
    }

    public function testErrorCreatesStandardErrorResponse(): void
    {
        $message = 'Something went wrong';
        $status = 400;

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) use ($message, $status) {
                $decoded = json_decode($json, true);
                return $decoded['code'] === $status
                    && $decoded['message'] === $message
                    && $decoded['data'] === null
                    && isset($decoded['timestamp']);
            }));

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $response->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with($status)
            ->willReturn($response);

        $result = $this->controller->testError($message, $status);
        $this->assertSame($response, $result);
    }

    public function testErrorUsesDefaultStatus(): void
    {
        $message = 'Error message';

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($json) {
                $decoded = json_decode($json, true);
                return $decoded['code'] === 400;
            }));

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(400)
            ->willReturn($response);

        $this->controller->testError($message);
    }

    public function testGetJsonBodyParsesValidJson(): void
    {
        $data = ['name' => 'Test', 'value' => 123];
        $json = json_encode($data);

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('__toString')
            ->willReturn($json);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $result = $this->controller->testGetJsonBody($request);
        $this->assertEquals($data, $result);
    }

    public function testGetJsonBodyReturnsEmptyArrayForEmptyBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('__toString')
            ->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $result = $this->controller->testGetJsonBody($request);
        $this->assertEquals([], $result);
    }

    public function testGetJsonBodyThrowsExceptionForInvalidJson(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('__toString')
            ->willReturn('{invalid json}');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON:');

        $this->controller->testGetJsonBody($request);
    }

    public function testGetUserIdReturnsIntegerFromAttribute(): void
    {
        $userId = 42;

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);

        $result = $this->controller->testGetUserId($request);
        $this->assertSame($userId, $result);
    }

    public function testGetUserIdReturnsNullWhenNotSet(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn(null);

        $result = $this->controller->testGetUserId($request);
        $this->assertNull($result);
    }

    public function testGetUserIdConvertsStringToInt(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn('123');

        $result = $this->controller->testGetUserId($request);
        $this->assertSame(123, $result);
    }

    public function testGetUserRolesReturnsArrayFromAttribute(): void
    {
        $roles = ['admin', 'teacher'];

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('roles')
            ->willReturn($roles);

        $result = $this->controller->testGetUserRoles($request);
        $this->assertSame($roles, $result);
    }

    public function testGetUserRolesReturnsEmptyArrayWhenNotSet(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('roles')
            ->willReturn(null);

        $result = $this->controller->testGetUserRoles($request);
        $this->assertEquals([], $result);
    }

    public function testGetUserRolesReturnsEmptyArrayForNonArrayValue(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getAttribute')
            ->with('roles')
            ->willReturn('not-an-array');

        $result = $this->controller->testGetUserRoles($request);
        $this->assertEquals([], $result);
    }
}
