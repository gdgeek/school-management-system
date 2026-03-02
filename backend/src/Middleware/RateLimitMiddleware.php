<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Redis;

/**
 * 请求频率限制中间件
 * 使用Redis实现分布式限流
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $prefix = 'rate_limit:';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Redis $redis,
        ?array $config = null
    ) {
        $config = $config ?? [];
        $this->maxRequests = $config['maxRequests'] ?? 100;
        $this->windowSeconds = $config['windowSeconds'] ?? 60;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->getRateLimitKey($request);
        
        // 获取当前请求计数
        $current = $this->getCurrentCount($key);
        
        // 检查是否超过限制
        if ($current >= $this->maxRequests) {
            return $this->createRateLimitResponse($current);
        }

        // 增加计数
        $this->incrementCount($key);
        
        // 处理请求
        $response = $handler->handle($request);
        
        // 添加速率限制响应头
        return $this->addRateLimitHeaders($response, $current + 1);
    }

    /**
     * 获取速率限制键
     */
    private function getRateLimitKey(ServerRequestInterface $request): string
    {
        // 优先使用用户ID，其次使用IP地址
        $userId = $request->getAttribute('user_id');
        
        if ($userId) {
            return $this->prefix . 'user:' . $userId;
        }

        $ip = $this->getClientIp($request);
        return $this->prefix . 'ip:' . $ip;
    }

    /**
     * 获取客户端IP地址
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
        // 检查代理头
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        if (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }
        
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 获取当前请求计数
     */
    private function getCurrentCount(string $key): int
    {
        try {
            $count = $this->redis->get($key);
            return $count !== false ? (int)$count : 0;
        } catch (\Exception $e) {
            // Redis失败时不限流
            return 0;
        }
    }

    /**
     * 增加请求计数
     */
    private function incrementCount(string $key): void
    {
        try {
            $current = $this->redis->incr($key);
            
            // 首次请求时设置过期时间
            if ($current === 1) {
                $this->redis->expire($key, $this->windowSeconds);
            }
        } catch (\Exception $e) {
            // 忽略Redis错误
        }
    }

    /**
     * 创建速率限制响应
     */
    private function createRateLimitResponse(int $current): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(429);
        
        $body = [
            'code' => 429,
            'message' => 'Too many requests',
            'timestamp' => time(),
        ];
        
        $response->getBody()->write(json_encode($body));
        
        return $this->addRateLimitHeaders(
            $response->withHeader('Content-Type', 'application/json'),
            $current
        );
    }

    /**
     * 添加速率限制响应头
     */
    private function addRateLimitHeaders(ResponseInterface $response, int $current): ResponseInterface
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $this->maxRequests - $current))
            ->withHeader('X-RateLimit-Reset', (string)($this->windowSeconds));
    }
}
