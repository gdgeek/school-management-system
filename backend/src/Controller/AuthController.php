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
class AuthController extends AbstractController
{
    private AuthService $authService;
    private JwtHelper $jwtHelper;

    public function __construct(
        AuthService $authService,
        JwtHelper $jwtHelper,
        ResponseFactoryInterface $responseFactory
    ) {
        parent::__construct($responseFactory);
        $this->authService = $authService;
        $this->jwtHelper = $jwtHelper;
    }

    /**
     * POST /api/auth/login
     * 用户登录
     */
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $this->getJsonBody($request);
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? '';

            if (empty($username) || empty($password)) {
                return $this->error('Username and password are required', 400);
            }

            // 验证用户凭证
            $user = $this->authService->authenticate($username, $password);
            
            if (!$user) {
                return $this->error('Invalid credentials', 401);
            }

            // 生成JWT令牌
            $token = $this->jwtHelper->generate([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'roles' => $this->authService->getUserRoles($user['id']),
                'school_id' => $user['school_id'] ?? null,
            ]);

            return $this->success([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nickname' => $user['nickname'] ?? '',
                ],
            ]);

        } catch (\Exception $e) {
            return $this->error('Login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/auth/user
     * 获取当前认证用户信息
     */
    public function user(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Extract user_id from request attributes (set by AuthMiddleware)
            $userId = $this->getUserId($request);
            
            if (!$userId) {
                return $this->error('User not authenticated', 401);
            }

            // Get user details from AuthService
            $user = $this->authService->getUserInfo($userId);
            
            if (!$user) {
                return $this->error('User not found', 404);
            }

            // Return user information in standard API response format
            return $this->success($user);

        } catch (\Exception $e) {
            return $this->error('Failed to get user info: ' . $e->getMessage(), 500);
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
                return $this->error('Missing token', 400);
            }

            // 刷新令牌
            $newToken = $this->jwtHelper->refresh($token);

            return $this->success([
                'token' => $newToken,
            ]);

        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (\Exception $e) {
            return $this->error('Token refresh failed', 500);
        }
    }

    /**
     * POST /api/auth/logout
     * 用户登出
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getUserId($request);
            
            if ($userId) {
                // 清除Redis中的会话
                $this->authService->logout($userId);
            }

            return $this->success([
                'message' => 'Logged out successfully',
            ]);

        } catch (\Exception $e) {
            return $this->error('Logout failed', 500);
        }
    }

    /**
     * GET /api/auth/me
     * 获取当前用户信息
     */
    public function me(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $this->getUserId($request);
            
            if (!$userId) {
                return $this->error('User not authenticated', 401);
            }

            $user = $this->authService->getUserInfo($userId);
            
            if (!$user) {
                return $this->error('User not found', 404);
            }

            return $this->success([
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nickname' => $user['nickname'] ?? '',
                    'avatar' => $user['avatar'] ?? '',
                    'roles' => $this->getUserRoles($request),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->error('Failed to get user info', 500);
        }
    }

    /**
     * POST /api/auth/verify
     * 验证会话令牌（用于跨系统跳转）
     */
    public function verify(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $this->getJsonBody($request);
            $sessionToken = $body['session_token'] ?? '';

            if (empty($sessionToken)) {
                return $this->error('Session token is required', 400);
            }

            // 从Redis验证会话令牌
            $user = $this->authService->verifySessionToken($sessionToken);
            
            if (!$user) {
                return $this->error('Invalid session token', 401);
            }

            // 生成JWT令牌
            $token = $this->jwtHelper->generate([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'roles' => $this->authService->getUserRoles($user['id']),
                'school_id' => $user['school_id'] ?? null,
            ]);

            return $this->success([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'nickname' => $user['nickname'] ?? '',
                ],
            ]);

        } catch (\Exception $e) {
            return $this->error('Verification failed', 500);
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
}
