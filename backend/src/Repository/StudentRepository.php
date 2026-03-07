<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Student;
use PDO;

class StudentRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM edu_student LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $students = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $students[] = Student::fromArray($row);
        }
        return $students;
    }

    public function findById(int $id): ?Student
    {
        $sql = "SELECT * FROM edu_student WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Student::fromArray($row) : null;
    }

    public function findByClassId(int $classId): array
    {
        $sql = "SELECT * FROM edu_student WHERE class_id = :class_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        $students = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $students[] = Student::fromArray($row);
        }
        return $students;
    }

    public function findByUserId(int $userId): ?Student
    {
        $sql = "SELECT * FROM edu_student WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Student::fromArray($row) : null;
    }

    public function exists(int $userId, int $classId): bool
    {
        $sql = "SELECT COUNT(*) FROM edu_student WHERE user_id = :user_id AND class_id = :class_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(Student $student): int
    {
        $sql = "INSERT INTO edu_student (user_id, class_id) VALUES (:user_id, :class_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $student->user_id, PDO::PARAM_INT);
        $stmt->bindValue(':class_id', $student->class_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM edu_student WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteByClassId(int $classId): bool
    {
        $sql = "DELETE FROM edu_student WHERE class_id = :class_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM edu_student";
        $stmt = $this->pdo->query($sql);
        return (int)$stmt->fetchColumn();
    }
}
