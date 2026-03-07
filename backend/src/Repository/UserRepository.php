<?php

declare(strict_types=1);

namespace App\Repository;

use App\Helper\DatabaseHelper;

/**
 * 用户数据访问层
 * 只读访问user表
 */
class UserRepository
{
    private DatabaseHelper $db;

    public function __construct(DatabaseHelper $db)
    {
        $this->db = $db;
    }

    /**
     * 根据ID查找用户
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT id, username, nickname, email, created_at 
                FROM user 
                WHERE id = :id AND status = 10";
        
        $result = $this->db->query($sql, ['id' => $id]);
        $user = $result[0] ?? null;
        
        // 添加 avatar 字段（当前数据库表中没有此字段，设置为 null）
        if ($user) {
            $user['avatar'] = null;
        }
        
        return $user;
    }

    /**
     * 根据用户名查找用户
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "SELECT id, username, password_hash, nickname, email, created_at 
                FROM user 
                WHERE username = :username AND status = 10";
        
        $result = $this->db->query($sql, ['username' => $username]);
        return $result[0] ?? null;
    }

    /**
     * 检查用户是否是系统管理员
     */
    public function isAdmin(int $userId): bool
    {
        // user 表没有 role 字段，暂时返回 false
        // TODO: 根据实际权限系统实现
        return false;
    }

    /**
     * 检查用户是否是校长
     */
    public function isPrincipal(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM edu_school 
                WHERE principal = :user_id";
        
        $result = $this->db->query($sql, ['user_id' => $userId]);
        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * 检查用户是否是教师
     */
    public function isTeacher(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM edu_teacher 
                WHERE user_id = :user_id";
        
        $result = $this->db->query($sql, ['user_id' => $userId]);
        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * 检查用户是否是学生
     */
    public function isStudent(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM edu_student 
                WHERE user_id = :user_id";
        
        $result = $this->db->query($sql, ['user_id' => $userId]);
        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * 批量获取用户信息
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, username, nickname, email 
                FROM user 
                WHERE id IN ($placeholders) AND status = 10";
        
        $users = $this->db->query($sql, array_values($ids));
        
        // 添加 avatar 字段（当前数据库表中没有此字段，设置为 null）
        return array_map(function($user) {
            $user['avatar'] = null;
            return $user;
        }, $users);
    }

    /**
     * 搜索用户（按昵称或用户名）
     */
    public function search(string $keyword, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, username, nickname, email 
                FROM user 
                WHERE (nickname LIKE ? OR username LIKE ?) 
                AND status = 10 
                ORDER BY id DESC 
                LIMIT " . $limit . " OFFSET " . $offset;
        
        $like = "%{$keyword}%";
        return $this->db->query($sql, [$like, $like]);
    }

    /**
     * 统计搜索结果数量
     */
    public function countSearch(string $keyword): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM user 
                WHERE (nickname LIKE ? OR username LIKE ?) 
                AND status = 10";
        
        $like = "%{$keyword}%";
        $result = $this->db->query($sql, [$like, $like]);
        return (int)($result[0]['count'] ?? 0);
    }
}
