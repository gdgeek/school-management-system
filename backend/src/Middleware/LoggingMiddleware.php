<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * 日志记录中间件
 * 记录所有HTTP请求和响应
 */
class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private ?LoggerInterface $logger = null) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        $requestId = $this->generateRequestId();
        
        // 记录请求
        $this->logRequest($request, $requestId);
        
        // 将请求ID添加到请求属性
        $request = $request->withAttribute('request_id', $requestId);
        
        // 处理请求
        $response = $handler->handle($request);
        
        // 记录响应
        $duration = microtime(true) - $startTime;
        $this->logResponse($request, $response, $duration, $requestId);
        
        // 添加请求ID到响应头
        return $response->withHeader('X-Request-ID', $requestId);
    }

    /**
     * 记录请求信息
     */
    private function logRequest(ServerRequestInterface $request, string $requestId): void
    {
        if (!$this->logger) {
            return;
        }

        $method = $request->getMethod();
        $uri = (string)$request->getUri();
        $ip = $this->getClientIp($request);
        
        $this->logger->info("Request: {$method} {$uri}", [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'ip' => $ip,
            'user_agent' => $request->getHeaderLine('User-Agent'),
        ]);
    }

    /**
     * 记录响应信息
     */
    private function logResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        float $duration,
        string $requestId
    ): void {
        if (!$this->logger) {
            return;
        }

        $method = $request->getMethod();
        $uri = (string)$request->getUri();
        $statusCode = $response->getStatusCode();
        
        $level = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');
        
        $this->logger->log($level, "Response: {$method} {$uri} - {$statusCode}", [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'duration' => round($duration * 1000, 2) . 'ms',
        ]);
    }

    /**
     * 生成请求ID
     */
    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        if (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }
        
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
