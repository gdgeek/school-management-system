<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Teacher Model
 * 
 * 教师模型，对应edu_teacher表
 */
class Teacher
{
    public int $id;
    public int $user_id;
    public int $class_id;

    public static function tableName(): string
    {
        return 'edu_teacher';
    }

    public static function fromArray(array $data): self
    {
        $model = new self();
        
        if (isset($data['id'])) $model->id = (int)$data['id'];
        if (isset($data['user_id'])) $model->user_id = (int)$data['user_id'];
        if (isset($data['class_id'])) $model->class_id = (int)$data['class_id'];
        
        return $model;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'user_id' => $this->user_id,
            'class_id' => $this->class_id,
        ];
    }
}
