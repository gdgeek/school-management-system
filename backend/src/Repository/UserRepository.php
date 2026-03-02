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
        $sql = "SELECT id, username, nickname, avatar, email, created_at 
                FROM user 
                WHERE id = :id AND deleted_at IS NULL";
        
        $result = $this->db->query($sql, ['id' => $id]);
        return $result[0] ?? null;
    }

    /**
     * 根据用户名查找用户
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "SELECT id, username, password, nickname, avatar, email, created_at 
                FROM user 
                WHERE username = :username AND deleted_at IS NULL";
        
        $result = $this->db->query($sql, ['username' => $username]);
        return $result[0] ?? null;
    }

    /**
     * 检查用户是否是系统管理员
     */
    public function isAdmin(int $userId): bool
    {
        // 这里需要根据实际的权限表结构实现
        // 暂时通过user表的role字段判断
        $sql = "SELECT role FROM user WHERE id = :id AND deleted_at IS NULL";
        $result = $this->db->query($sql, ['id' => $userId]);
        
        if (empty($result)) {
            return false;
        }

        return ($result[0]['role'] ?? '') === 'admin';
    }

    /**
     * 检查用户是否是校长
     */
    public function isPrincipal(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM edu_school 
                WHERE principal_id = :user_id AND deleted_at IS NULL";
        
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
                WHERE user_id = :user_id AND deleted_at IS NULL";
        
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
                WHERE user_id = :user_id AND deleted_at IS NULL";
        
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
        $sql = "SELECT id, username, nickname, avatar, email 
                FROM user 
                WHERE id IN ($placeholders) AND deleted_at IS NULL";
        
        return $this->db->query($sql, $ids);
    }

    /**
     * 搜索用户（按昵称或用户名）
     */
    public function search(string $keyword, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, username, nickname, avatar, email 
                FROM user 
                WHERE (nickname LIKE :keyword OR username LIKE :keyword) 
                AND deleted_at IS NULL 
                ORDER BY id DESC 
                LIMIT :limit OFFSET :offset";
        
        return $this->db->query($sql, [
            'keyword' => "%{$keyword}%",
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * 统计搜索结果数量
     */
    public function countSearch(string $keyword): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM user 
                WHERE (nickname LIKE :keyword OR username LIKE :keyword) 
                AND deleted_at IS NULL";
        
        $result = $this->db->query($sql, ['keyword' => "%{$keyword}%"]);
        return (int)($result[0]['count'] ?? 0);
    }
}
