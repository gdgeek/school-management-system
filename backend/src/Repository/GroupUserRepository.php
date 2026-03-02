<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\GroupUser;
use PDO;

class GroupUserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByGroupId(int $groupId): array
    {
        $sql = "SELECT * FROM group_user WHERE group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $stmt->execute();
        
        $members = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $members[] = GroupUser::fromArray($row);
        }
        return $members;
    }

    public function findByUserId(int $userId): array
    {
        $sql = "SELECT * FROM group_user WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $members = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $members[] = GroupUser::fromArray($row);
        }
        return $members;
    }

    public function exists(int $userId, int $groupId): bool
    {
        $sql = "SELECT COUNT(*) FROM group_user WHERE user_id = :user_id AND group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(GroupUser $groupUser): int
    {
        $sql = "INSERT INTO group_user (user_id, group_id) VALUES (:user_id, :group_id)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $groupUser->user_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $groupUser->group_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $userId, int $groupId): bool
    {
        $sql = "DELETE FROM group_user WHERE user_id = :user_id AND group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteByGroupId(int $groupId): bool
    {
        $sql = "DELETE FROM group_user WHERE group_id = :group_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
