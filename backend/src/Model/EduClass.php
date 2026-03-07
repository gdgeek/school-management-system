<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;

/**
 * EduClass Model
 * 
 * 班级模型，对应edu_class表
 */
class EduClass
{
    public int $id;
    public ?string $name = null;
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;
    public ?int $school_id = null;
    public ?int $image_id = null;
    public ?array $info = null;

    public static function tableName(): string
    {
        return 'edu_class';
    }

    public static function fromArray(array $data): self
    {
        $model = new self();
        
        if (isset($data['id'])) $model->id = (int)$data['id'];
        if (isset($data['name'])) $model->name = $data['name'];
        if (isset($data['created_at'])) $model->created_at = new DateTime($data['created_at']);
        if (isset($data['updated_at'])) $model->updated_at = new DateTime($data['updated_at']);
        if (isset($data['school_id'])) $model->school_id = (int)$data['school_id'];
        if (isset($data['image_id'])) $model->image_id = (int)$data['image_id'];
        if (isset($data['info'])) {
            $model->info = is_string($data['info']) ? json_decode($data['info'], true) : $data['info'];
        }
        
        return $model;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'school_id' => $this->school_id,
            'image_id' => $this->image_id,
            'info' => $this->info,
        ];
    }
}
