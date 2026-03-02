<?php

declare(strict_types=1);

namespace App\Model;

/**
 * ClassGroup Model
 * 
 * 班级小组关联模型，对应edu_class_group表
 */
class ClassGroup
{
    public int $id;
    public int $class_id;
    public int $group_id;

    public static function tableName(): string
    {
        return 'edu_class_group';
    }

    public static function fromArray(array $data): self
    {
        $model = new self();
        
        if (isset($data['id'])) $model->id = (int)$data['id'];
        if (isset($data['class_id'])) $model->class_id = (int)$data['class_id'];
        if (isset($data['group_id'])) $model->group_id = (int)$data['group_id'];
        
        return $model;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'class_id' => $this->class_id,
            'group_id' => $this->group_id,
        ];
    }
}
