<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\SchoolController;
use App\Service\SchoolService;
use App\Exception\ValidationException;
use App\Exception\UnauthorizedException;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class SchoolControllerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private SchoolService $schoolService;
    private SchoolController $controller;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->schoolService = $this->createMock(SchoolService::class);
        $this->controller = new SchoolController($this->responseFactory, $this->schoolService);
    }

    private function createMockResponse(int $status = 200): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('write')->willReturn(0); // write() must return int

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

    // ==================== index() Tests ====================

    public function testIndexReturnsSchoolListSuccessfully(): void
    {
        $expectedData = [
            'items' => [
                ['id' => 1, 'name' => 'School 1'],
                ['id' => 2, 'name' => 'School 2'],
            ],
            'total' => 2,
            'page' => 1,
            'pageSize' => 20,
        ];

        $this->schoolService->expects($this->once())
            ->method('getList')
            ->with(1, 20, null)
            ->willReturn($expectedData);

        $this->createMockResponse(200);
        $request = $this->createMockRequest();

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexWithPaginationParameters(): void
    {
        $queryParams = ['page' => '2', 'pageSize' => '10'];
        
        $this->schoolService->expects($this->once())
            ->method('getList')
            ->with(2, 10, null)
            ->willReturn(['items' => [], 'total' => 0]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest($queryParams);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexWithSearchParameter(): void
    {
        $queryParams = ['search' => 'Test School'];
        
        $this->schoolService->expects($this->once())
            ->method('getList')
            ->with(1, 20, 'Test School')
            ->willReturn(['items' => [], 'total' => 0]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest($queryParams);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexLimitsPageSizeToMaximum(): void
    {
        $queryParams = ['pageSize' => '200'];
        
        $this->schoolService->expects($this->once())
            ->method('getList')
            ->with(1, 100, null) // Should be capped at 100
            ->willReturn(['items' => [], 'total' => 0]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest($queryParams);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexLimitsPageSizeToMinimum(): void
    {
        $queryParams = ['pageSize' => '0'];
        
        $this->schoolService->expects($this->once())
            ->method('getList')
            ->with(1, 1, null) // Should be at least 1
            ->willReturn(['items' => [], 'total' => 0]);

        $this->createMockResponse(200);
        $request = $this->createMockRequest($queryParams);

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexHandlesServiceException(): void
    {
        $this->schoolService->expects($this->once())
            ->method('getList')
            ->willThrowException(new \Exception('Database error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest();

        $response = $this->controller->index($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== show() Tests ====================

    public function testShowReturnsSchoolSuccessfully(): void
    {
        $schoolData = ['id' => 1, 'name' => 'Test School', 'address' => '123 Main St'];
        
        $this->schoolService->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($schoolData);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->show($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testShowReturns404WhenSchoolNotFound(): void
    {
        $this->schoolService->expects($this->once())
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
        $this->schoolService->expects($this->once())
            ->method('getById')
            ->willThrowException(new \Exception('Database error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->show($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== create() Tests ====================

    public function testCreateSchoolSuccessfully(): void
    {
        $requestData = ['name' => 'New School', 'address' => '456 Oak Ave'];
        $createdSchool = ['id' => 1, 'name' => 'New School', 'address' => '456 Oak Ave'];

        $this->schoolService->expects($this->once())
            ->method('create')
            ->with($requestData)
            ->willReturn($createdSchool);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenNameIsMissing(): void
    {
        $requestData = ['address' => '456 Oak Ave'];

        $this->schoolService->expects($this->never())
            ->method('create');

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateReturns400WhenNameIsEmpty(): void
    {
        $requestData = ['name' => '', 'address' => '456 Oak Ave'];

        $this->schoolService->expects($this->never())
            ->method('create');

        $this->createMockResponse(400);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesValidationException(): void
    {
        $requestData = ['name' => 'Test School'];

        $this->schoolService->expects($this->once())
            ->method('create')
            ->willThrowException(new ValidationException(['name' => 'Invalid name'], 'Invalid data'));

        $this->createMockResponse(422);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesUnauthorizedException(): void
    {
        $requestData = ['name' => 'Test School'];

        $this->schoolService->expects($this->once())
            ->method('create')
            ->willThrowException(new UnauthorizedException('Not authenticated'));

        $this->createMockResponse(401);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesForbiddenException(): void
    {
        $requestData = ['name' => 'Test School'];

        $this->schoolService->expects($this->once())
            ->method('create')
            ->willThrowException(new ForbiddenException('Access denied'));

        $this->createMockResponse(403);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesNotFoundException(): void
    {
        $requestData = ['name' => 'Test School'];

        $this->schoolService->expects($this->once())
            ->method('create')
            ->willThrowException(new NotFoundException('Resource not found'));

        $this->createMockResponse(404);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCreateHandlesGenericException(): void
    {
        $requestData = ['name' => 'Test School'];

        $this->schoolService->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('Unexpected error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], [], json_encode($requestData));

        $response = $this->controller->create($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== update() Tests ====================

    public function testUpdateSchoolSuccessfully(): void
    {
        $requestData = ['name' => 'Updated School', 'address' => '789 Pine St'];
        $updatedSchool = ['id' => 1, 'name' => 'Updated School', 'address' => '789 Pine St'];

        $this->schoolService->expects($this->once())
            ->method('update')
            ->with(1, $requestData)
            ->willReturn($updatedSchool);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateReturns404WhenSchoolNotFound(): void
    {
        $requestData = ['name' => 'Updated School'];

        $this->schoolService->expects($this->once())
            ->method('update')
            ->with(999, $requestData)
            ->willReturn(null);

        $this->createMockResponse(404);
        $request = $this->createMockRequest([], ['id' => 999], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateHandlesValidationException(): void
    {
        $requestData = ['name' => 'Updated School'];

        $this->schoolService->expects($this->once())
            ->method('update')
            ->willThrowException(new ValidationException(['name' => 'Invalid name'], 'Invalid data'));

        $this->createMockResponse(422);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateHandlesUnauthorizedException(): void
    {
        $requestData = ['name' => 'Updated School'];

        $this->schoolService->expects($this->once())
            ->method('update')
            ->willThrowException(new UnauthorizedException('Not authenticated'));

        $this->createMockResponse(401);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateHandlesForbiddenException(): void
    {
        $requestData = ['name' => 'Updated School'];

        $this->schoolService->expects($this->once())
            ->method('update')
            ->willThrowException(new ForbiddenException('Access denied'));

        $this->createMockResponse(403);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateHandlesNotFoundException(): void
    {
        $requestData = ['name' => 'Updated School'];

        $this->schoolService->expects($this->once())
            ->method('update')
            ->willThrowException(new NotFoundException('Resource not found'));

        $this->createMockResponse(404);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateHandlesGenericException(): void
    {
        $requestData = ['name' => 'Updated School'];

        $this->schoolService->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception('Unexpected error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['id' => 1], json_encode($requestData));

        $response = $this->controller->update($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== delete() Tests ====================

    public function testDeleteSchoolSuccessfully(): void
    {
        $this->schoolService->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturn(true);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteReturns404WhenSchoolNotFound(): void
    {
        $this->schoolService->expects($this->once())
            ->method('delete')
            ->with(999)
            ->willReturn(false);

        $this->createMockResponse(404);
        $request = $this->createMockRequest([], ['id' => 999]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDeleteHandlesServiceException(): void
    {
        $this->schoolService->expects($this->once())
            ->method('delete')
            ->willThrowException(new \Exception('Database error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->delete($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ==================== classes() Tests ====================

    public function testClassesReturnsSchoolClassesSuccessfully(): void
    {
        $classesData = [
            ['id' => 1, 'name' => 'Class 1A'],
            ['id' => 2, 'name' => 'Class 1B'],
        ];

        $this->schoolService->expects($this->once())
            ->method('getClasses')
            ->with(1)
            ->willReturn($classesData);

        $this->createMockResponse(200);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->classes($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testClassesHandlesServiceException(): void
    {
        $this->schoolService->expects($this->once())
            ->method('getClasses')
            ->willThrowException(new \Exception('Database error'));

        $this->createMockResponse(500);
        $request = $this->createMockRequest([], ['id' => 1]);

        $response = $this->controller->classes($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
