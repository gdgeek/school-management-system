<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;

/**
 * School Model
 * 
 * 学校模型，对应edu_school表
 * 
 * @property int $id
 * @property string|null $name
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @property int|null $image_id
 * @property array|null $info
 * @property int|null $principal_id
 */
class School
{
    public int $id;
    public ?string $name = null;
    public DateTime $created_at;
    public DateTime $updated_at;
    public ?int $image_id = null;
    public ?array $info = null;
    public ?int $principal_id = null;

    /**
     * 表名
     */
    public static function tableName(): string
    {
        return 'edu_school';
    }

    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            ['name', 'string', 'max' => 255],
            ['image_id', 'integer'],
            ['principal_id', 'integer'],
            ['info', 'array'],
        ];
    }

    /**
     * 属性标签
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => '学校名称',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'image_id' => '图片ID',
            'info' => '额外信息',
            'principal_id' => '校长ID',
        ];
    }

    /**
     * 从数组创建模型实例
     */
    public static function fromArray(array $data): self
    {
        $model = new self();
        
        if (isset($data['id'])) {
            $model->id = (int)$data['id'];
        }
        if (isset($data['name'])) {
            $model->name = $data['name'];
        }
        if (isset($data['created_at'])) {
            $model->created_at = new DateTime($data['created_at']);
        }
        if (isset($data['updated_at'])) {
            $model->updated_at = new DateTime($data['updated_at']);
        }
        if (isset($data['image_id'])) {
            $model->image_id = (int)$data['image_id'];
        }
        if (isset($data['info'])) {
            $model->info = is_string($data['info']) ? json_decode($data['info'], true) : $data['info'];
        }
        if (isset($data['principal_id'])) {
            $model->principal_id = (int)$data['principal_id'];
        }
        
        return $model;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'image_id' => $this->image_id,
            'info' => $this->info,
            'principal_id' => $this->principal_id,
        ];
    }
}
