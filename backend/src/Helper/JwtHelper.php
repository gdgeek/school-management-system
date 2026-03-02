<?php

declare(strict_types=1);

namespace App\Helper;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use App\Exception\UnauthorizedException;

/**
 * JWT认证辅助类
 * 负责JWT令牌的生成、验证和解析
 */
class JwtHelper
{
    private string $secret;
    private int $expireTime;
    private string $algorithm = 'HS256';

    public function __construct(string $secret, int $expireTime = 3600)
    {
        $this->secret = $secret;
        $this->expireTime = $expireTime;
    }

    /**
     * 生成JWT令牌
     *
     * @param array $payload 令牌载荷数据
     * @return string JWT令牌
     */
    public function generate(array $payload): string
    {
        $now = time();
        
        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $this->expireTime,
        ]);

        return JWT::encode($tokenPayload, $this->secret, $this->algorithm);
    }

    /**
     * 验证并解析JWT令牌
     *
     * @param string $token JWT令牌
     * @return array 解析后的载荷数据
     * @throws UnauthorizedException 令牌无效或过期
     */
    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            throw new UnauthorizedException('Token has expired');
        } catch (SignatureInvalidException $e) {
            throw new UnauthorizedException('Invalid token signature');
        } catch (\Exception $e) {
            throw new UnauthorizedException('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * 从令牌中提取用户ID
     *
     * @param string $token JWT令牌
     * @return int 用户ID
     * @throws UnauthorizedException
     */
    public function getUserId(string $token): int
    {
        $payload = $this->verify($token);
        
        if (!isset($payload['user_id'])) {
            throw new UnauthorizedException('Token does not contain user_id');
        }

        return (int) $payload['user_id'];
    }

    /**
     * 从令牌中提取用户角色
     *
     * @param string $token JWT令牌
     * @return array 用户角色列表
     * @throws UnauthorizedException
     */
    public function getUserRoles(string $token): array
    {
        $payload = $this->verify($token);
        
        if (!isset($payload['roles'])) {
            return [];
        }

        return is_array($payload['roles']) ? $payload['roles'] : [$payload['roles']];
    }

    /**
     * 刷新令牌（生成新的令牌）
     *
     * @param string $token 旧令牌
     * @return string 新令牌
     * @throws UnauthorizedException
     */
    public function refresh(string $token): string
    {
        $payload = $this->verify($token);
        
        // 移除时间相关字段，重新生成
        unset($payload['iat'], $payload['exp']);
        
        return $this->generate($payload);
    }

    /**
     * 检查令牌是否即将过期（剩余时间少于5分钟）
     *
     * @param string $token JWT令牌
     * @return bool
     */
    public function isExpiringSoon(string $token): bool
    {
        try {
            $payload = $this->verify($token);
            $exp = $payload['exp'] ?? 0;
            return ($exp - time()) < 300; // 5分钟
        } catch (\Exception $e) {
            return true;
        }
    }
}
