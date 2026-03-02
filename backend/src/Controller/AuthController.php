<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\JwtHelper;
use App\Service\AuthService;
use App\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * 认证控制器
 * 处理登录、令牌刷新、登出等认证相关操作
 */
class AuthController
{
    private AuthService $authService;
    private JwtHelper $jwtHelper;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(
        AuthService $authService,
        JwtHelper $jwtHelper,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->authService = $authService;
        $this->jwtHelper = $jwtHelper;
        $this->responseFactory = $responseFactory;
    }

    /**
     * POST /api/auth/login
     * 用户登录
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? '';

            if (empty($username) || empty($password)) {
                return $this->errorResponse('Username and password are required', 400);
            }

            // 验证用户凭证
            $user = $this->authService->authenticate($username, $password);
            
            if (!$user) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            // 生成JWT令牌
            $token = $this->jwtHelper->generate([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'roles' => $this->authService->getUserRoles($user['id']),
                'school_id' => $user['school_id'] ?? null,
            ]);

            return $this->successResponse([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nickname' => $user['nickname'] ?? '',
                ],
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/auth/refresh
     * 刷新令牌
     */
    public function refresh(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $token = $this->extractToken($request);
            
            if (empty($token)) {
                return $this->errorResponse('Missing token', 400);
            }

            // 刷新令牌
            $newToken = $this->jwtHelper->refresh($token);

            return $this->successResponse([
                'token' => $newToken,
            ]);

        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (\Exception $e) {
            return $this->errorResponse('Token refresh failed', 500);
        }
    }

    /**
     * POST /api/auth/logout
     * 用户登出
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('user_id');
            
            if ($userId) {
                // 清除Redis中的会话
                $this->authService->logout($userId);
            }

            return $this->successResponse([
                'message' => 'Logged out successfully',
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed', 500);
        }
    }

    /**
     * GET /api/auth/me
     * 获取当前用户信息
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('user_id');
            
            if (!$userId) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $user = $this->authService->getUserInfo($userId);
            
            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }

            return $this->successResponse([
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nickname' => $user['nickname'] ?? '',
                    'avatar' => $user['avatar'] ?? '',
                    'roles' => $request->getAttribute('roles', []),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get user info', 500);
        }
    }

    /**
     * POST /api/auth/verify
     * 验证会话令牌（用于跨系统跳转）
     */
    public function verify(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $sessionToken = $body['session_token'] ?? '';

            if (empty($sessionToken)) {
                return $this->errorResponse('Session token is required', 400);
            }

            // 从Redis验证会话令牌
            $user = $this->authService->verifySessionToken($sessionToken);
            
            if (!$user) {
                return $this->errorResponse('Invalid session token', 401);
            }

            // 生成JWT令牌
            $token = $this->jwtHelper->generate([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'roles' => $this->authService->getUserRoles($user['id']),
                'school_id' => $user['school_id'] ?? null,
            ]);

            return $this->successResponse([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nickname' => $user['nickname'] ?? '',
                ],
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Verification failed', 500);
        }
    }

    /**
     * 从请求中提取令牌
     */
    private function extractToken(ServerRequestInterface $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * 返回成功响应
     */
    private function successResponse(array $data): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write(json_encode([
            'code' => 200,
            'message' => 'Success',
            'data' => $data,
            'timestamp' => time(),
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * 返回错误响应
     */
    private function errorResponse(string $message, int $code = 400): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($code);
        $response->getBody()->write(json_encode([
            'code' => $code,
            'message' => $message,
            'timestamp' => time(),
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
