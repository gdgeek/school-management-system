<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\School;
use PDO;

/**
 * School Repository
 * 
 * 学校数据访问层
 */
class SchoolRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * 查找所有学校
     */
    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM edu_school ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $schools = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schools[] = School::fromArray($row);
        }
        
        return $schools;
    }

    /**
     * 根据ID查找学校
     */
    public function findById(int $id): ?School
    {
        $sql = "SELECT * FROM edu_school WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? School::fromArray($row) : null;
    }

    /**
     * 根据校长ID查找学校
     */
    public function findByPrincipalId(int $principalId): array
    {
        $sql = "SELECT * FROM edu_school WHERE principal_id = :principal_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':principal_id', $principalId, PDO::PARAM_INT);
        $stmt->execute();
        
        $schools = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schools[] = School::fromArray($row);
        }
        
        return $schools;
    }

    /**
     * 搜索学校
     */
    public function search(string $keyword, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM edu_school WHERE name LIKE :keyword ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':keyword', "%$keyword%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $schools = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schools[] = School::fromArray($row);
        }
        
        return $schools;
    }

    /**
     * 创建学校
     */
    public function create(School $school): int
    {
        $sql = "INSERT INTO edu_school (name, image_id, info, principal_id, created_at, updated_at) 
                VALUES (:name, :image_id, :info, :principal_id, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $school->name, PDO::PARAM_STR);
        $stmt->bindValue(':image_id', $school->image_id, PDO::PARAM_INT);
        $stmt->bindValue(':info', json_encode($school->info), PDO::PARAM_STR);
        $stmt->bindValue(':principal_id', $school->principal_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * 更新学校
     */
    public function update(School $school): bool
    {
        $sql = "UPDATE edu_school 
                SET name = :name, image_id = :image_id, info = :info, principal_id = :principal_id, updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $school->id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $school->name, PDO::PARAM_STR);
        $stmt->bindValue(':image_id', $school->image_id, PDO::PARAM_INT);
        $stmt->bindValue(':info', json_encode($school->info), PDO::PARAM_STR);
        $stmt->bindValue(':principal_id', $school->principal_id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * 删除学校
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM edu_school WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * 统计学校数量
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM edu_school";
        $stmt = $this->pdo->query($sql);
        
        return (int)$stmt->fetchColumn();
    }
}
