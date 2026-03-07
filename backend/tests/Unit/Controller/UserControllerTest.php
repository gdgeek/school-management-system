<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\UserController;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class UserControllerTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private UserRepository $userRepository;
    private UserController $controller;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->userRepository  = $this->createMock(UserRepository::class);
        $this->controller      = new UserController($this->responseFactory, $this->userRepository);
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

    private function createMockRequest(array $queryParams = []): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);
        return $request;
    }

    // ==================== search() ====================

    public function testSearchReturnsResults(): void
    {
        $users = [
            ['id' => 1, 'username' => 'alice', 'nickname' => 'Alice'],
            ['id' => 2, 'username' => 'alan', 'nickname' => 'Alan'],
        ];

        $this->userRepository->expects($this->once())
            ->method('search')
            ->with('al', 20, 0)
            ->willReturn($users);

        $this->userRepository->expects($this->once())
            ->method('countSearch')
            ->with('al')
            ->willReturn(2);

        $this->createMockResponse(200);
        $response = $this->controller->search($this->createMockRequest(['keyword' => 'al']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSearchAcceptsQParam(): void
    {
        $this->userRepository->expects($this->once())
            ->method('search')
            ->with('bob', 20, 0)
            ->willReturn([]);

        $this->userRepository->expects($this->once())
            ->method('countSearch')
            ->with('bob')
            ->willReturn(0);

        $this->createMockResponse(200);
        $response = $this->controller->search($this->createMockRequest(['q' => 'bob']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSearchReturns400WhenKeywordMissing(): void
    {
        $this->userRepository->expects($this->never())->method('search');
        $this->createMockResponse(400);
        $response = $this->controller->search($this->createMockRequest());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSearchReturns400WhenKeywordEmpty(): void
    {
        $this->userRepository->expects($this->never())->method('search');
        $this->createMockResponse(400);
        $response = $this->controller->search($this->createMockRequest(['keyword' => '   ']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSearchRespectsPagination(): void
    {
        $this->userRepository->expects($this->once())
            ->method('search')
            ->with('test', 10, 10) // page 2, pageSize 10 → offset 10
            ->willReturn([]);

        $this->userRepository->expects($this->once())
            ->method('countSearch')
            ->willReturn(0);

        $this->createMockResponse(200);
        $response = $this->controller->search(
            $this->createMockRequest(['keyword' => 'test', 'page' => '2', 'pageSize' => '10'])
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSearchCapsPageSize(): void
    {
        $this->userRepository->expects($this->once())
            ->method('search')
            ->with('test', 100, 0)
            ->willReturn([]);

        $this->userRepository->expects($this->once())
            ->method('countSearch')
            ->willReturn(0);

        $this->createMockResponse(200);
        $response = $this->controller->search(
            $this->createMockRequest(['keyword' => 'test', 'pageSize' => '500'])
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSearchHandlesException(): void
    {
        $this->userRepository->method('search')
            ->willThrowException(new \Exception('DB error'));
        $this->createMockResponse(500);
        $response = $this->controller->search($this->createMockRequest(['keyword' => 'test']));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
