<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Group;
use PDO;

class GroupRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM \`group\` ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $groups = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $groups[] = Group::fromArray($row);
        }
        return $groups;
    }

    public function findById(int $id): ?Group
    {
        $sql = "SELECT * FROM \`group\` WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Group::fromArray($row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $sql = "SELECT * FROM \`group\` WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $groups = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $groups[] = Group::fromArray($row);
        }
        return $groups;
    }

    public function search(string $keyword, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM \`group\` WHERE name LIKE :keyword ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':keyword', "%$keyword%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $groups = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $groups[] = Group::fromArray($row);
        }
        return $groups;
    }

    public function create(Group $group): int
    {
        $sql = "INSERT INTO \`group\` (name, description, user_id, image_id, info, created_at, updated_at) 
                VALUES (:name, :description, :user_id, :image_id, :info, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $group->name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $group->description, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $group->user_id, PDO::PARAM_INT);
        $stmt->bindValue(':image_id', $group->image_id, PDO::PARAM_INT);
        $stmt->bindValue(':info', json_encode($group->info), PDO::PARAM_STR);
        $stmt->execute();
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(Group $group): bool
    {
        $sql = "UPDATE \`group\` 
                SET name = :name, description = :description, image_id = :image_id, info = :info, updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $group->id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $group->name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $group->description, PDO::PARAM_STR);
        $stmt->bindValue(':image_id', $group->image_id, PDO::PARAM_INT);
        $stmt->bindValue(':info', json_encode($group->info), PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM \`group\` WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM \`group\`";
        $stmt = $this->pdo->query($sql);
        return (int)$stmt->fetchColumn();
    }
}
