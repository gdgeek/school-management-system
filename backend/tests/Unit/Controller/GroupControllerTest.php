<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\GroupController;
use App\Service\GroupService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class GroupControllerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private GroupService $groupService;
    private GroupController $controller;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->groupService    = $this->createMock(GroupService::class);
        $this->controller      = new GroupController($this->responseFactory, $this->groupService);
    }

    private function createMockResponse(int $status = 200): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('write')->willReturn(0);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnSelf();

        $this->responseFactory->method('createResponse')
            ->with($status)
            ->willReturn($response);

        return $response;
    }

    private function createMockRequest(
        array $queryParams = [],
        array $attributes = [],
        ?string $body = null
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getAttribute')
            ->willReturnCallback(fn($key) => $attributes[$key] ?? null);

        if ($body !== null) {
            $stream = $this->createMock(StreamInterface::class);
            $stream->method('__toString')->willReturn($body);
            $request->method('getBody')->willReturn($stream);
        }

        return $request;
    }

    // ==================== index() ====================

    public function testIndexReturnsGroupList(): void
    {
        $expected = ['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pageSize' => 20, 'totalPages' => 0]];

        $this->groupService->expects($this->once())
            ->method('getList')
            ->with(1, 20, null)
            ->willReturn($expected);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexPassesSearchParam(): void
    {
        $this->groupService->expects($this->once())
            ->method('getList')
            ->with(1, 20, 'math')
            ->willReturn(['items' => [], 'pagination' => []]);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest(['search' => 'math']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexCapsPageSize(): void
    {
        $this->groupService->expects($this->once())
            ->method('getList')
            ->with(1, 100, null)
            ->willReturn(['items' => [], 'pagination' => []]);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest(['pageSize' => '500']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexHandlesException(): void
    {
        $this->groupService->method('getList')->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->index($this->createMockRequest());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== show() ====================

    public function testShowReturnsGroup(): void
    {
        $this->groupService->expects($this->once())
            ->method('getById')->with(1)
            ->willReturn(['id' => 1, 'name' => 'Group A']);

        $this->createMockResponse(200);
        $response = $this->controller->show($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->groupService->expects($this->once())
            ->method('getById')->with(99)
            ->willReturn(null);

        $this->createMockResponse(404);
        $response = $this->controller->show($this->createMockRequest([], ['id' => 99]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testShowHandlesException(): void
    {
        $this->groupService->method('getById')->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->show($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== create() ====================

    public function testCreateGroupSuccessfully(): void
    {
        $data = ['name' => 'New Group'];

        $this->groupService->expects($this->once())
            ->method('create')
            ->with($data, 5)
            ->willReturn(['id' => 1, 'name' => 'New Group']);

        $this->createMockResponse(200);
        $response = $this->controller->create(
            $this->createMockRequest([], ['user_id' => 5], json_encode($data))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenNameMissing(): void
    {
        $this->groupService->expects($this->never())->method('create');
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], ['user_id' => 1], json_encode([]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns401WhenNotAuthenticated(): void
    {
        $this->groupService->expects($this->never())->method('create');
        $this->createMockResponse(401);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['name' => 'Group']))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesInvalidArgumentException(): void
    {
        $this->groupService->method('create')
            ->willThrowException(new \InvalidArgumentException('Invalid data'));
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], ['user_id' => 1], json_encode(['name' => 'Group']))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== update() ====================

    public function testUpdateGroupSuccessfully(): void
    {
        $data = ['name' => 'Updated'];

        $this->groupService->expects($this->once())
            ->method('update')->with(1, $data)
            ->willReturn(['id' => 1, 'name' => 'Updated']);

        $this->createMockResponse(200);
        $response = $this->controller->update(
            $this->createMockRequest([], ['id' => 1], json_encode($data))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateReturns404WhenNotFound(): void
    {
        $this->groupService->method('update')->willReturn(null);
        $this->createMockResponse(404);
        $response = $this->controller->update(
            $this->createMockRequest([], ['id' => 99], json_encode(['name' => 'X']))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== delete() ====================

    public function testDeleteGroupSuccessfully(): void
    {
        $this->groupService->expects($this->once())
            ->method('delete')->with(1)
            ->willReturn(true);

        $this->createMockResponse(200);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $this->groupService->method('delete')->willReturn(false);
        $this->createMockResponse(404);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 99]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteHandlesException(): void
    {
        $this->groupService->method('delete')->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
