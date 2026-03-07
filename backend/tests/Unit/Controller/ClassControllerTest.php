<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\ClassController;
use App\Service\ClassService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Unit tests for ClassController
 *
 * Tests all controller methods with mocked ClassService:
 * - index(): list classes with optional school_id filter
 * - show(): get single class by id
 * - create(): create class with auto-group creation
 * - update(): update class
 * - delete(): delete class with optional deleteGroups param
 */
class ClassControllerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private ClassService $classService;
    private ClassController $controller;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->classService    = $this->createMock(ClassService::class);
        $this->controller      = new ClassController($this->responseFactory, $this->classService);
    }

    /**
     * Create a mock ResponseInterface that the responseFactory will return for the given status.
     */
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

    /**
     * Create a mock ServerRequestInterface with the given query params, attributes, and body.
     */
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

    // ==================== index() Tests ====================

    public function testIndexReturnsClassListSuccessfully(): void
    {
        $expectedData = [
            'items' => [
                ['id' => 1, 'name' => 'Class 1A', 'school_id' => 1],
                ['id' => 2, 'name' => 'Class 1B', 'school_id' => 1],
            ],
            'pagination' => ['total' => 2, 'page' => 1, 'pageSize' => 20, 'totalPages' => 1],
        ];

        $this->classService->expects($this->once())
            ->method('getList')
            ->with(1, 20, null)
            ->willReturn($expectedData);

        $this->createMockResponse(200);
        $request = $this->createMockRequest();

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexPassesSchoolIdFilterToService(): void
    {
        $this->classService->expects($this->once())
            ->method('getList')
            ->with(1, 20, 5)
            ->willReturn(['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pageSize' => 20, 'totalPages' => 0]]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest(['school_id' => '5']);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexWithPaginationParameters(): void
    {
        $this->classService->expects($this->once())
            ->method('getList')
            ->with(2, 10, null)
            ->willReturn(['items' => [], 'pagination' => ['total' => 0, 'page' => 2, 'pageSize' => 10, 'totalPages' => 0]]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest(['page' => '2', 'pageSize' => '10']);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexLimitsPageSizeToMaximum(): void
    {
        $this->classService->expects($this->once())
            ->method('getList')
            ->with(1, 100, null) // capped at 100
            ->willReturn(['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pageSize' => 100, 'totalPages' => 0]]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest(['pageSize' => '999']);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexLimitsPageSizeToMinimum(): void
    {
        $this->classService->expects($this->once())
            ->method('getList')
            ->with(1, 1, null) // minimum 1
            ->willReturn(['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'pageSize' => 1, 'totalPages' => 0]]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest(['pageSize' => '0']);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexHandlesServiceException(): void
    {
        $this->classService->expects($this->once())
            ->method('getList')
            ->willThrowException(new \Exception('Database error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest();

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== show() Tests ====================

    public function testShowReturnsClassSuccessfully(): void
    {
        $classData = ['id' => 1, 'name' => 'Class 1A', 'school_id' => 1];

        $this->classService->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($classData);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->show($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testShowReturns404WhenClassNotFound(): void
    {
        $this->classService->expects($this->once())
            ->method('getById')
            ->with(999)
            ->willReturn(null);

        $this->createMockResponse(404);
        $request = $this->createMockRequest([], ['id' => 999]);

        $response = $this->controller->show($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testShowHandlesServiceException(): void
    {
        $this->classService->expects($this->once())
            ->method('getById')
            ->willThrowException(new \Exception('Database error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->show($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== create() Tests ====================

    public function testCreateClassSuccessfully(): void
    {
        $requestData = ['name' => 'New Class', 'school_id' => 1];
        $createdClass = ['id' => 1, 'name' => 'New Class', 'school_id' => 1, 'group_id' => 10];

        $this->classService->expects($this->once())
            ->method('create')
            ->with($requestData, 42) // user_id = 42 from request attribute
            ->willReturn($createdClass);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['user_id' => 42], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenNameIsMissing(): void
    {
        $requestData = ['school_id' => 1];

        $this->classService->expects($this->never())
            ->method('create');

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenNameIsEmpty(): void
    {
        $requestData = ['name' => '', 'school_id' => 1];

        $this->classService->expects($this->never())
            ->method('create');

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenSchoolIdIsMissing(): void
    {
        $requestData = ['name' => 'New Class'];

        $this->classService->expects($this->never())
            ->method('create');

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenSchoolIdIsEmpty(): void
    {
        $requestData = ['name' => 'New Class', 'school_id' => ''];

        $this->classService->expects($this->never())
            ->method('create');

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreatePassesUserIdFromRequestAttribute(): void
    {
        $requestData = ['name' => 'New Class', 'school_id' => 3];

        $this->classService->expects($this->once())
            ->method('create')
            ->with($requestData, 7) // user_id = 7
            ->willReturn(['id' => 1, 'name' => 'New Class', 'school_id' => 3, 'group_id' => 5]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['user_id' => 7], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesInvalidArgumentException(): void
    {
        $requestData = ['name' => 'New Class', 'school_id' => 999];

        $this->classService->expects($this->once())
            ->method('create')
            ->willThrowException(new \InvalidArgumentException('Invalid school ID'));

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], ['user_id' => 1], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesGenericException(): void
    {
        $requestData = ['name' => 'New Class', 'school_id' => 1];

        $this->classService->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('Unexpected error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['user_id' => 1], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== update() Tests ====================

    public function testUpdateClassSuccessfully(): void
    {
        $requestData  = ['name' => 'Updated Class'];
        $updatedClass = ['id' => 1, 'name' => 'Updated Class', 'school_id' => 1];

        $this->classService->expects($this->once())
            ->method('update')
            ->with(1, $requestData)
            ->willReturn($updatedClass);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateReturns404WhenClassNotFound(): void
    {
        $requestData = ['name' => 'Updated Class'];

        $this->classService->expects($this->once())
            ->method('update')
            ->with(999, $requestData)
            ->willReturn(null);

        $this->createMockResponse(404);
        $request = $this->createMockRequest([], ['id' => 999], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateHandlesInvalidArgumentException(): void
    {
        $requestData = ['school_id' => 999];

        $this->classService->expects($this->once())
            ->method('update')
            ->willThrowException(new \InvalidArgumentException('Invalid school ID'));

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateHandlesGenericException(): void
    {
        $requestData = ['name' => 'Updated Class'];

        $this->classService->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception('Unexpected error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== delete() Tests ====================

    public function testDeleteClassSuccessfully(): void
    {
        $this->classService->expects($this->once())
            ->method('delete')
            ->with(1, false) // deleteGroups defaults to false
            ->willReturn(true);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteWithDeleteGroupsTrueParam(): void
    {
        $this->classService->expects($this->once())
            ->method('delete')
            ->with(1, true) // deleteGroups = true
            ->willReturn(true);

        $this->createMockResponse(200);
        $request = $this->createMockRequest(['deleteGroups' => 'true'], ['id' => 1]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteWithDeleteGroupsFalseParam(): void
    {
        $this->classService->expects($this->once())
            ->method('delete')
            ->with(1, false) // deleteGroups = false when param is 'false'
            ->willReturn(true);

        $this->createMockResponse(200);
        $request = $this->createMockRequest(['deleteGroups' => 'false'], ['id' => 1]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteReturns404WhenClassNotFound(): void
    {
        $this->classService->expects($this->once())
            ->method('delete')
            ->with(999, false)
            ->willReturn(false);

        $this->createMockResponse(404);
        $request = $this->createMockRequest([], ['id' => 999]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteHandlesServiceException(): void
    {
        $this->classService->expects($this->once())
            ->method('delete')
            ->willThrowException(new \Exception('Database error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
