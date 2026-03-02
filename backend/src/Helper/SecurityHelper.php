<?php

declare(strict_types=1);

namespace App\Helper;

/**
 * 安全辅助类
 * 提供输入清理、XSS防护和CSRF令牌管理
 */
class SecurityHelper
{
    /**
     * XSS防护 - 清理HTML标签
     *
     * @param string $input 输入字符串
     * @param bool $allowHtml 是否允许安全的HTML标签
     * @return string 清理后的字符串
     */
    public static function sanitizeInput(string $input, bool $allowHtml = false): string
    {
        if ($allowHtml) {
            // 允许安全的HTML标签
            $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
            return strip_tags($input, $allowedTags);
        }

        // 移除所有HTML标签
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * 清理数组中的所有字符串值
     *
     * @param array $data 输入数组
     * @param bool $allowHtml 是否允许HTML
     * @return array 清理后的数组
     */
    public static function sanitizeArray(array $data, bool $allowHtml = false): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $result[$key] = self::sanitizeInput($value, $allowHtml);
            } elseif (is_array($value)) {
                $result[$key] = self::sanitizeArray($value, $allowHtml);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * 生成CSRF令牌
     *
     * @return string CSRF令牌
     */
    public static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 验证CSRF令牌
     *
     * @param string $token 待验证的令牌
     * @param string $expected 期望的令牌
     * @return bool 是否有效
     */
    public static function verifyCsrfToken(string $token, string $expected): bool
    {
        return hash_equals($expected, $token);
    }

    /**
     * 清理SQL输入（防止SQL注入）
     * 注意：使用PDO预处理语句是更好的选择
     *
     * @param string $input 输入字符串
     * @return string 清理后的字符串
     */
    public static function sanitizeSql(string $input): string
    {
        // 移除危险字符
        $input = str_replace(['--', ';', '/*', '*/', 'xp_', 'sp_'], '', $input);
        return addslashes($input);
    }

    /**
     * 验证URL是否安全
     *
     * @param string $url URL地址
     * @param array $allowedDomains 允许的域名列表
     * @return bool 是否安全
     */
    public static function isUrlSafe(string $url, array $allowedDomains = []): bool
    {
        // 检查URL格式
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        
        // 检查协议
        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
            return false;
        }

        // 如果指定了允许的域名，检查域名
        if (!empty($allowedDomains)) {
            $host = $parsedUrl['host'] ?? '';
            
            foreach ($allowedDomains as $domain) {
                if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                    return true;
                }
            }
            
            return false;
        }

        return true;
    }

    /**
     * 生成安全的随机字符串
     *
     * @param int $length 长度
     * @return string 随机字符串
     */
    public static function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 哈希密码
     *
     * @param string $password 明文密码
     * @return string 哈希后的密码
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * 验证密码
     *
     * @param string $password 明文密码
     * @param string $hash 哈希值
     * @return bool 是否匹配
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 检查密码强度
     *
     * @param string $password 密码
     * @return array 强度信息 ['valid' => bool, 'errors' => array]
     */
    public static function checkPasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
