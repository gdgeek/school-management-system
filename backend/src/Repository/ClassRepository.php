<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\EduClass;
use PDO;

/**
 * Class Repository
 * 
 * 班级数据访问层
 */
class ClassRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM edu_class ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $classes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[] = EduClass::fromArray($row);
        }
        
        return $classes;
    }

    public function findById(int $id): ?EduClass
    {
        $sql = "SELECT * FROM edu_class WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? EduClass::fromArray($row) : null;
    }

    public function findBySchoolId(int $schoolId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM edu_class WHERE school_id = :school_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $classes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[] = EduClass::fromArray($row);
        }
        
        return $classes;
    }

    public function countBySchoolId(int $schoolId): int
    {
        $sql = "SELECT COUNT(*) FROM edu_class WHERE school_id = :school_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function create(EduClass $class): int
    {
        $sql = "INSERT INTO edu_class (name, school_id, image_id, info, created_at, updated_at) 
                VALUES (:name, :school_id, :image_id, :info, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $class->name, PDO::PARAM_STR);
        $stmt->bindValue(':school_id', $class->school_id, PDO::PARAM_INT);
        $stmt->bindValue(':image_id', $class->image_id, PDO::PARAM_INT);
        $stmt->bindValue(':info', json_encode($class->info), PDO::PARAM_STR);
        $stmt->execute();
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(EduClass $class): bool
    {
        $sql = "UPDATE edu_class 
                SET name = :name, school_id = :school_id, image_id = :image_id, info = :info, updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $class->id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $class->name, PDO::PARAM_STR);
        $stmt->bindValue(':school_id', $class->school_id, PDO::PARAM_INT);
        $stmt->bindValue(':image_id', $class->image_id, PDO::PARAM_INT);
        $stmt->bindValue(':info', json_encode($class->info), PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM edu_class WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM edu_class";
        $stmt = $this->pdo->query($sql);
        return (int)$stmt->fetchColumn();
    }
}
