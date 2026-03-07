<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\TeacherController;
use App\Service\TeacherService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class TeacherControllerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private TeacherService $teacherService;
    private TeacherController $controller;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->teacherService  = $this->createMock(TeacherService::class);
        $this->controller      = new TeacherController($this->responseFactory, $this->teacherService);
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

    public function testIndexReturnsTeacherList(): void
    {
        $expected = ['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pageSize' => 20, 'totalPages' => 0]];

        $this->teacherService->expects($this->once())
            ->method('getList')
            ->with(1, 20, null)
            ->willReturn($expected);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexPassesClassIdFilter(): void
    {
        $this->teacherService->expects($this->once())
            ->method('getList')
            ->with(1, 20, 4)
            ->willReturn(['items' => [], 'pagination' => []]);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest(['class_id' => '4']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexCapsPageSize(): void
    {
        $this->teacherService->expects($this->once())
            ->method('getList')
            ->with(1, 100, null)
            ->willReturn(['items' => [], 'pagination' => []]);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest(['pageSize' => '200']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexHandlesException(): void
    {
        $this->teacherService->method('getList')->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->index($this->createMockRequest());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== show() ====================

    public function testShowReturnsTeacher(): void
    {
        $this->teacherService->expects($this->once())
            ->method('getById')->with(1)
            ->willReturn(['id' => 1, 'user_id' => 5, 'class_id' => 2]);

        $this->createMockResponse(200);
        $response = $this->controller->show($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->teacherService->expects($this->once())
            ->method('getById')->with(99)
            ->willReturn(null);

        $this->createMockResponse(404);
        $response = $this->controller->show($this->createMockRequest([], ['id' => 99]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== create() ====================

    public function testCreateTeacherSuccessfully(): void
    {
        $data = ['user_id' => 5, 'class_id' => 2];

        $this->teacherService->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn(['id' => 1, 'user_id' => 5, 'class_id' => 2]);

        $this->createMockResponse(200);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode($data))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenUserIdMissing(): void
    {
        $this->teacherService->expects($this->never())->method('create');
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['class_id' => 2]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenClassIdMissing(): void
    {
        $this->teacherService->expects($this->never())->method('create');
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['user_id' => 5]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesInvalidArgumentException(): void
    {
        $this->teacherService->method('create')
            ->willThrowException(new \InvalidArgumentException('Teacher already exists'));
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['user_id' => 5, 'class_id' => 2]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesGenericException(): void
    {
        $this->teacherService->method('create')
            ->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['user_id' => 5, 'class_id' => 2]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== delete() ====================

    public function testDeleteTeacherSuccessfully(): void
    {
        $this->teacherService->expects($this->once())
            ->method('delete')->with(1)
            ->willReturn(true);

        $this->createMockResponse(200);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $this->teacherService->method('delete')->willReturn(false);
        $this->createMockResponse(404);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 99]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteHandlesException(): void
    {
        $this->teacherService->method('delete')->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
