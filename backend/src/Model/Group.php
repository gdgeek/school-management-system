<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;

/**
 * Group Model
 * 
 * 小组模型，对应group表
 */
class Group
{
    public int $id;
    public ?string $name = null;
    public ?string $description = null;
    public int $user_id;
    public ?int $image_id = null;
    public ?array $info = null;
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;

    public static function tableName(): string
    {
        return 'group';
    }

    public static function fromArray(array $data): self
    {
        $model = new self();
        
        if (isset($data['id'])) $model->id = (int)$data['id'];
        if (isset($data['name'])) $model->name = $data['name'];
        if (isset($data['description'])) $model->description = $data['description'];
        if (isset($data['user_id'])) $model->user_id = (int)$data['user_id'];
        if (isset($data['image_id'])) $model->image_id = (int)$data['image_id'];
        if (isset($data['info'])) {
            $model->info = is_string($data['info']) ? json_decode($data['info'], true) : $data['info'];
        }
        if (isset($data['created_at'])) $model->created_at = new DateTime($data['created_at']);
        if (isset($data['updated_at'])) $model->updated_at = new DateTime($data['updated_at']);
        
        return $model;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'image_id' => $this->image_id,
            'info' => $this->info,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
