<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Teacher;
use PDO;

/**
 * Teacher Repository
 * 
 * 教师数据访问层
 */
class TeacherRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM edu_teacher LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $teachers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $teachers[] = Teacher::fromArray($row);
        }
        
        return $teachers;
    }

    public function findById(int $id): ?Teacher
    {
        $sql = "SELECT * FROM edu_teacher WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? Teacher::fromArray($row) : null;
    }

    public function findByClassId(int $classId): array
    {
        $sql = "SELECT * FROM edu_teacher WHERE class_id = :class_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        $teachers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $teachers[] = Teacher::fromArray($row);
        }
        
        return $teachers;
    }

    public function findByUserId(int $userId): array
    {
        $sql = "SELECT * FROM edu_teacher WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $teachers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $teachers[] = Teacher::fromArray($row);
        }
        
        return $teachers;
    }

    public function exists(int $userId, int $classId): bool
    {
        $sql = "SELECT COUNT(*) FROM edu_teacher WHERE user_id = :user_id AND class_id = :class_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(Teacher $teacher): int
    {
        $sql = "INSERT INTO edu_teacher (user_id, class_id) VALUES (:user_id, :class_id)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $teacher->user_id, PDO::PARAM_INT);
        $stmt->bindValue(':class_id', $teacher->class_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM edu_teacher WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function deleteByClassId(int $classId): bool
    {
        $sql = "DELETE FROM edu_teacher WHERE class_id = :class_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM edu_teacher";
        $stmt = $this->pdo->query($sql);
        return (int)$stmt->fetchColumn();
    }
}
