<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ClassGroup;
use PDO;

class ClassGroupRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByGroupId(int $groupId): array
    {
        $sql = "SELECT * FROM edu_class_group WHERE group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $stmt->execute();
        
        $relations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $relations[] = ClassGroup::fromArray($row);
        }
        return $relations;
    }

    public function findByClassId(int $classId): array
    {
        $sql = "SELECT * FROM edu_class_group WHERE class_id = :class_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        $relations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $relations[] = ClassGroup::fromArray($row);
        }
        return $relations;
    }

    public function exists(int $classId, int $groupId): bool
    {
        $sql = "SELECT COUNT(*) FROM edu_class_group WHERE class_id = :class_id AND group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(ClassGroup $classGroup): int
    {
        $sql = "INSERT INTO edu_class_group (class_id, group_id) VALUES (:class_id, :group_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classGroup->class_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $classGroup->group_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $classId, int $groupId): bool
    {
        $sql = "DELETE FROM edu_class_group WHERE class_id = :class_id AND group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteByGroupId(int $groupId): bool
    {
        $sql = "DELETE FROM edu_class_group WHERE group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
