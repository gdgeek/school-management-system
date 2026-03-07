<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\RedisInterface;
use App\Repository\UserRepository;

/**
 * 认证服务
 * 处理用户认证、会话管理和角色获取
 */
class AuthService
{
    private UserRepository $userRepository;
    private RedisInterface $redis;
    private int $sessionTtl = 86400; // 24小时

    public function __construct(UserRepository $userRepository, RedisInterface $redis)
    {
        $this->userRepository = $userRepository;
        $this->redis = $redis;
    }

    /**
     * 验证用户凭证
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @return array|null 用户信息，失败返回null
     */
    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->userRepository->findByUsername($username);
        
        if (!$user) {
            return null;
        }

        // 验证密码（假设使用password_hash存储）
        // UserRepository returns 'password_hash' field
        $passwordHash = $user['password_hash'] ?? $user['password'] ?? '';
        if (!password_verify($password, $passwordHash)) {
            return null;
        }

        // 创建会话
        $this->createSession($user['id'], $user);

        return $user;
    }

    /**
     * 验证会话令牌（用于跨系统认证）
     *
     * @param string $sessionToken 会话令牌
     * @return array|null 用户信息，失败返回null
     */
    public function verifySessionToken(string $sessionToken): ?array
    {
        // 从Redis获取会话数据（主系统写入的 key 格式为 school_mgmt_token:{token}）
        $sessionKey = "school_mgmt_token:{$sessionToken}";
        $sessionData = $this->redis->get($sessionKey);
        
        if (!$sessionData) {
            return null;
        }

        $session = json_decode($sessionData, true);
        $userId = $session['user_id'] ?? null;
        
        if (!$userId) {
            return null;
        }

        // 令牌一次性使用，验证后立即删除
        $this->redis->del($sessionKey);

        // 获取用户信息
        return $this->userRepository->findById($userId);
    }

    /**
     * 创建用户会话
     *
     * @param int $userId 用户ID
     * @param array $userData 用户数据
     */
    public function createSession(int $userId, array $userData): void
    {
        $sessionKey = "user_session:{$userId}";
        $sessionData = json_encode([
            'user_id' => $userId,
            'username' => $userData['username'] ?? '',
            'login_time' => time(),
        ]);

        $this->redis->setex($sessionKey, $this->sessionTtl, $sessionData);
    }

    /**
     * 刷新会话过期时间
     *
     * @param int $userId 用户ID
     */
    public function refreshSession(int $userId): void
    {
        $sessionKey = "user_session:{$userId}";
        $this->redis->expire($sessionKey, $this->sessionTtl);
    }

    /**
     * 登出（清除会话）
     *
     * @param int $userId 用户ID
     */
    public function logout(int $userId): void
    {
        $sessionKey = "user_session:{$userId}";
        $this->redis->del($sessionKey);
    }

    /**
     * 获取用户信息
     *
     * @param int $userId 用户ID
     * @return array|null 用户信息
     */
    public function getUserInfo(int $userId): ?array
    {
        return $this->userRepository->findById($userId);
    }

    /**
     * 获取用户角色
     *
     * @param int $userId 用户ID
     * @return array 角色列表
     */
    public function getUserRoles(int $userId): array
    {
        // 从缓存获取角色
        $cacheKey = "user_roles:{$userId}";
        $cachedRoles = $this->redis->get($cacheKey);
        
        if ($cachedRoles !== false) {
            return json_decode($cachedRoles, true);
        }

        // 从数据库获取角色
        $roles = $this->determineUserRoles($userId);
        
        // 缓存角色（1小时）
        $this->redis->setex($cacheKey, 3600, json_encode($roles));
        
        return $roles;
    }

    /**
     * 确定用户角色
     *
     * @param int $userId 用户ID
     * @return array 角色列表
     */
    private function determineUserRoles(int $userId): array
    {
        $roles = [];
        
        // 检查是否是系统管理员
        if ($this->userRepository->isAdmin($userId)) {
            $roles[] = 'admin';
        }

        // 检查是否是校长
        if ($this->userRepository->isPrincipal($userId)) {
            $roles[] = 'principal';
        }

        // 检查是否是教师
        if ($this->userRepository->isTeacher($userId)) {
            $roles[] = 'teacher';
        }

        // 检查是否是学生
        if ($this->userRepository->isStudent($userId)) {
            $roles[] = 'student';
        }

        // 默认角色
        if (empty($roles)) {
            $roles[] = 'user';
        }

        return $roles;
    }

    /**
     * 清除用户角色缓存
     *
     * @param int $userId 用户ID
     */
    public function clearRolesCache(int $userId): void
    {
        $cacheKey = "user_roles:{$userId}";
        $this->redis->del($cacheKey);
    }

    /**
     * 检查会话是否存在
     *
     * @param int $userId 用户ID
     * @return bool
     */
    public function hasSession(int $userId): bool
    {
        $sessionKey = "user_session:{$userId}";
        return $this->redis->exists($sessionKey) > 0;
    }
}
