<?php

declare(strict_types=1);

namespace App\Validator;

use App\Helper\ValidatorHelper;

/**
 * Group Validator
 * 
 * 小组数据验证器
 */
class GroupValidator
{
    public function __construct(private ValidatorHelper $validator) {}

    /**
     * 验证创建小组的数据
     */
    public function validateCreate(array $data): array
    {
        $this->validator->clearErrors();

        // 验证name字段
        if (!isset($data['name']) || empty($data['name'])) {
            $this->validator->required($data['name'] ?? null, 'name');
        } else {
            $this->validator->stringLength($data['name'], 'name', 1, 255);
        }

        // 验证description（可选）
        if (isset($data['description']) && $data['description'] !== null) {
            $this->validator->stringLength($data['description'], 'description', 0, 1000);
        }

        // 验证image_id（可选）
        if (isset($data['image_id']) && $data['image_id'] !== null) {
            $this->validator->integer($data['image_id'], 'image_id');
            $this->validator->integerRange((int)$data['image_id'], 'image_id', 1);
        }

        // 验证info（可选）
        if (isset($data['info']) && $data['info'] !== null) {
            $this->validator->isArray($data['info'], 'info');
        }

        return $this->validator->getErrors();
    }

    /**
     * 验证更新小组的数据
     */
    public function validateUpdate(array $data): array
    {
        $this->validator->clearErrors();

        // 验证name字段（可选）
        if (isset($data['name'])) {
            $this->validator->stringLength($data['name'], 'name', 1, 255);
        }

        // 验证description（可选）
        if (isset($data['description']) && $data['description'] !== null) {
            $this->validator->stringLength($data['description'], 'description', 0, 1000);
        }

        // 验证image_id（可选）
        if (isset($data['image_id']) && $data['image_id'] !== null) {
            $this->validator->integer($data['image_id'], 'image_id');
            $this->validator->integerRange((int)$data['image_id'], 'image_id', 1);
        }

        // 验证info（可选）
        if (isset($data['info']) && $data['info'] !== null) {
            $this->validator->isArray($data['info'], 'info');
        }

        return $this->validator->getErrors();
    }
}
