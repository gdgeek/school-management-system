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
        // For a pure JSON API, no scripts/styles/frames are served.
        // default-src 'none' is the strictest possible policy and appropriate here.
        $this->cspPolicy = $config['cspPolicy'] ?? "default-src 'none'; frame-ancestors 'none';";
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
            // X-XSS-Protection: 0 — deprecated header; modern browsers rely on CSP instead
            ->withHeader('X-XSS-Protection', '0')
            // 引用策略
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            // 权限策略（包含 payment）
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()')
            // 严格传输安全（仅HTTPS，含 preload）
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload')
            // 跨域开启策略（防止 Spectre 等旁道攻击）
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin')
            // 跨域资源策略（API 允许跨域读取）
            ->withHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            // 移除服务器技术信息泄露
            ->withoutHeader('X-Powered-By');

        // 内容安全策略
        if ($this->enableCsp) {
            $response = $response->withHeader('Content-Security-Policy', $this->cspPolicy);
        }

        return $response;
    }
}
