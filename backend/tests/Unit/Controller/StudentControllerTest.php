<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\StudentController;
use App\Service\StudentService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class StudentControllerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private StudentService $studentService;
    private StudentController $controller;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->studentService  = $this->createMock(StudentService::class);
        $this->controller      = new StudentController($this->responseFactory, $this->studentService);
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

    public function testIndexReturnsStudentList(): void
    {
        $expected = ['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pageSize' => 20, 'totalPages' => 0]];

        $this->studentService->expects($this->once())
            ->method('getList')
            ->with(1, 20, null)
            ->willReturn($expected);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexPassesClassIdFilter(): void
    {
        $this->studentService->expects($this->once())
            ->method('getList')
            ->with(1, 20, 3)
            ->willReturn(['items' => [], 'pagination' => []]);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest(['class_id' => '3']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexCapsPageSize(): void
    {
        $this->studentService->expects($this->once())
            ->method('getList')
            ->with(1, 100, null)
            ->willReturn(['items' => [], 'pagination' => []]);

        $this->createMockResponse(200);
        $response = $this->controller->index($this->createMockRequest(['pageSize' => '999']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexHandlesException(): void
    {
        $this->studentService->method('getList')->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->index($this->createMockRequest());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== show() ====================

    public function testShowReturnsStudent(): void
    {
        $this->studentService->expects($this->once())
            ->method('getById')->with(1)
            ->willReturn(['id' => 1, 'user_id' => 10, 'class_id' => 2]);

        $this->createMockResponse(200);
        $response = $this->controller->show($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->studentService->expects($this->once())
            ->method('getById')->with(99)
            ->willReturn(null);

        $this->createMockResponse(404);
        $response = $this->controller->show($this->createMockRequest([], ['id' => 99]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== create() ====================

    public function testCreateStudentSuccessfully(): void
    {
        $data = ['user_id' => 10, 'class_id' => 2];

        $this->studentService->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn(['id' => 1, 'user_id' => 10, 'class_id' => 2]);

        $this->createMockResponse(200);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode($data))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenUserIdMissing(): void
    {
        $this->studentService->expects($this->never())->method('create');
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['class_id' => 2]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenClassIdMissing(): void
    {
        $this->studentService->expects($this->never())->method('create');
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['user_id' => 10]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesInvalidArgumentException(): void
    {
        $this->studentService->method('create')
            ->willThrowException(new \InvalidArgumentException('User already a student'));
        $this->createMockResponse(400);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['user_id' => 10, 'class_id' => 2]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesGenericException(): void
    {
        $this->studentService->method('create')
            ->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->create(
            $this->createMockRequest([], [], json_encode(['user_id' => 10, 'class_id' => 2]))
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== delete() ====================

    public function testDeleteStudentSuccessfully(): void
    {
        $this->studentService->expects($this->once())
            ->method('delete')->with(1)
            ->willReturn(true);

        $this->createMockResponse(200);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $this->studentService->method('delete')->willReturn(false);
        $this->createMockResponse(404);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 99]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteHandlesException(): void
    {
        $this->studentService->method('delete')->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->delete($this->createMockRequest([], ['id' => 1]));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
