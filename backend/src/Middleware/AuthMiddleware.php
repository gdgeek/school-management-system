<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helper\JwtHelper;
use App\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * 认证中间件
 * 验证JWT令牌并将用户信息注入到请求上下文
 */
class AuthMiddleware implements MiddlewareInterface
{
    private JwtHelper $jwtHelper;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(JwtHelper $jwtHelper, ResponseFactoryInterface $responseFactory)
    {
        $this->jwtHelper = $jwtHelper;
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // 从请求头中提取令牌
            $token = $this->extractToken($request);
            
            if (empty($token)) {
                return $this->unauthorizedResponse('Missing authentication token');
            }

            // 验证令牌
            $payload = $this->jwtHelper->verify($token);
            
            // 将用户信息注入到请求属性中
            $request = $request
                ->withAttribute('user_id', $payload['user_id'] ?? null)
                ->withAttribute('username', $payload['username'] ?? null)
                ->withAttribute('roles', $payload['roles'] ?? [])
                ->withAttribute('school_id', $payload['school_id'] ?? null)
                ->withAttribute('token_payload', $payload);

            return $handler->handle($request);
            
        } catch (UnauthorizedException $e) {
            return $this->unauthorizedResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Authentication failed');
        }
    }

    /**
     * 从请求中提取JWT令牌
     * 支持从Authorization头和Cookie中提取
     */
    private function extractToken(ServerRequestInterface $request): ?string
    {
        // 1. 从Authorization头提取 (Bearer token)
        $authHeader = $request->getHeaderLine('Authorization');
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 2. 从Cookie中提取
        $cookies = $request->getCookieParams();
        if (isset($cookies['auth_token'])) {
            return $cookies['auth_token'];
        }

        // 3. 从查询参数提取（用于跨系统跳转）
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['token'])) {
            return $queryParams['token'];
        }

        return null;
    }

    /**
     * 返回401未授权响应
     */
    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401);
        $response->getBody()->write(json_encode([
            'code' => 401,
            'message' => $message,
            'timestamp' => time(),
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
