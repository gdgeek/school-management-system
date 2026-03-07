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
        } catch (\App\Exception\ValidationException|\App\Exception\NotFoundException|\App\Exception\ForbiddenException $e) {
            // 业务异常不属于认证问题，继续冒泡到 ErrorHandlingMiddleware
            throw $e;
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Authentication failed');
        }
    }

    /**
     * 从请求中提取JWT令牌
     * 支持从Authorization头和Cookie中提取
     *
     * Security note: query-parameter token extraction (?token=...) is intentionally
     * NOT supported. Tokens in URLs are logged by web servers, proxies, and browsers,
     * and can leak via the Referer header — violating requirement 2.2.4.
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
            'data' => null,
            'timestamp' => time(),
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
