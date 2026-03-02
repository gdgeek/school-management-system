<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use App\Exception\ValidationException;
use App\Exception\UnauthorizedException;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\BusinessException;

/**
 * 全局异常处理中间件
 * 捕获所有异常并返回统一的JSON响应
 */
class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private ?LoggerInterface $logger = null
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (UnauthorizedException $e) {
            return $this->handleException($e, 401);
        } catch (ForbiddenException $e) {
            return $this->handleException($e, 403);
        } catch (NotFoundException $e) {
            return $this->handleException($e, 404);
        } catch (BusinessException $e) {
            return $this->handleException($e, 400);
        } catch (\PDOException $e) {
            return $this->handleDatabaseException($e);
        } catch (\Throwable $e) {
            return $this->handleUnexpectedException($e);
        }
    }

    /**
     * 处理验证异常
     */
    private function handleValidationException(ValidationException $e): ResponseInterface
    {
        $this->logException($e, 'warning');

        $response = $this->responseFactory->createResponse(422);
        $body = [
            'code' => 422,
            'message' => $e->getMessage(),
            'errors' => $e->getErrors(),
            'timestamp' => time(),
        ];
        
        $response->getBody()->write(json_encode($body));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * 处理已知异常
     */
    private function handleException(\Exception $e, int $statusCode): ResponseInterface
    {
        $this->logException($e, $statusCode >= 500 ? 'error' : 'warning');

        $response = $this->responseFactory->createResponse($statusCode);
        $body = [
            'code' => $statusCode,
            'message' => $e->getMessage(),
            'timestamp' => time(),
        ];
        
        $response->getBody()->write(json_encode($body));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * 处理数据库异常
     */
    private function handleDatabaseException(\PDOException $e): ResponseInterface
    {
        $this->logException($e, 'error');

        $response = $this->responseFactory->createResponse(500);
        $body = [
            'code' => 500,
            'message' => 'Database error occurred',
            'timestamp' => time(),
        ];
        
        // 在开发环境显示详细错误
        if ($_ENV['APP_ENV'] === 'development') {
            $body['debug'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
        
        $response->getBody()->write(json_encode($body));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * 处理未预期的异常
     */
    private function handleUnexpectedException(\Throwable $e): ResponseInterface
    {
        $this->logException($e, 'critical');

        $response = $this->responseFactory->createResponse(500);
        $body = [
            'code' => 500,
            'message' => 'Internal server error',
            'timestamp' => time(),
        ];
        
        // 在开发环境显示详细错误
        if ($_ENV['APP_ENV'] === 'development') {
            $body['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }
        
        $response->getBody()->write(json_encode($body));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * 记录异常日志
     */
    private function logException(\Throwable $e, string $level = 'error'): void
    {
        if ($this->logger) {
            $context = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            match ($level) {
                'critical' => $this->logger->critical($e->getMessage(), $context),
                'error' => $this->logger->error($e->getMessage(), $context),
                'warning' => $this->logger->warning($e->getMessage(), $context),
                default => $this->logger->info($e->getMessage(), $context),
            };
        }
    }
}
