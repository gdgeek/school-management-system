<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * CORS跨域中间件
 * 处理跨域请求，支持预检请求
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;
    private int $maxAge;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        ?array $config = null
    ) {
        $config = $config ?? [];
        
        // 从环境变量或配置获取允许的源
        $this->allowedOrigins = $config['origins'] ?? $this->parseOrigins($_ENV['CORS_ALLOWED_ORIGINS'] ?? '*');
        $this->allowedMethods = $config['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'];
        $this->allowedHeaders = $config['headers'] ?? ['Authorization', 'Content-Type', 'X-Requested-With', 'Accept'];
        $this->allowCredentials = $config['credentials'] ?? true;
        $this->maxAge = $config['maxAge'] ?? 86400; // 24小时
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // 处理预检请求
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($origin);
        }

        // 处理实际请求
        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * 处理预检请求
     */
    private function handlePreflightRequest(string $origin): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(204);
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * 添加CORS响应头
     */
    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        // 检查源是否允许
        if (!$this->isOriginAllowed($origin)) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string)$this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * 检查源是否在允许列表中
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        // 允许所有源
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        // 精确匹配
        if (in_array($origin, $this->allowedOrigins)) {
            return true;
        }

        // 通配符匹配
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if ($this->matchWildcard($allowedOrigin, $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 通配符匹配
     */
    private function matchWildcard(string $pattern, string $value): bool
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        return (bool)preg_match('/^' . $pattern . '$/', $value);
    }

    /**
     * 解析源配置字符串
     */
    private function parseOrigins(string $origins): array
    {
        if ($origins === '*') {
            return ['*'];
        }

        return array_map('trim', explode(',', $origins));
    }
}
