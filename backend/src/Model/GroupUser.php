<?php

declare(strict_types=1);

namespace App\Model;

/**
 * GroupUser Model
 * 
 * 小组成员模型，对应group_user表
 */
class GroupUser
{
    public int $id;
    public int $user_id;
    public int $group_id;

    public static function tableName(): string
    {
        return 'group_user';
    }

    public static function fromArray(array $data): self
    {
        $model = new self();
        
        if (isset($data['id'])) $model->id = (int)$data['id'];
        if (isset($data['user_id'])) $model->user_id = (int)$data['user_id'];
        if (isset($data['group_id'])) $model->group_id = (int)$data['group_id'];
        
        return $model;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'user_id' => $this->user_id,
            'group_id' => $this->group_id,
        ];
    }
}
