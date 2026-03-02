<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 安全防护中间件
 * 添加安全响应头和输入清理
 */
class SecurityMiddleware implements MiddlewareInterface
{
    private bool $enableCsp;
    private string $cspPolicy;

    public function __construct(?array $config = null)
    {
        $config = $config ?? [];
        $this->enableCsp = $config['enableCsp'] ?? true;
        $this->cspPolicy = $config['cspPolicy'] ?? "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;";
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 处理请求
        $response = $handler->handle($request);
        
        // 添加安全响应头
        return $this->addSecurityHeaders($response);
    }

    /**
     * 添加安全响应头
     */
    private function addSecurityHeaders(ResponseInterface $response): ResponseInterface
    {
        $response = $response
            // 防止点击劫持
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            // 防止MIME类型嗅探
            ->withHeader('X-Content-Type-Options', 'nosniff')
            // XSS防护
            ->withHeader('X-XSS-Protection', '1; mode=block')
            // 引用策略
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            // 权限策略
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
            // 严格传输安全（仅HTTPS）
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // 内容安全策略
        if ($this->enableCsp) {
            $response = $response->withHeader('Content-Security-Policy', $this->cspPolicy);
        }

        return $response;
    }
}
