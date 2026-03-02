<?php

declare(strict_types=1);

namespace App\Validator;

use App\Helper\ValidatorHelper;

/**
 * School Validator
 * 
 * 学校数据验证器
 */
class SchoolValidator
{
    public function __construct(private ValidatorHelper $validator) {}

    /**
     * 验证创建学校的数据
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

        // 验证image_id（可选）
        if (isset($data['image_id']) && $data['image_id'] !== null) {
            $this->validator->integer($data['image_id'], 'image_id');
            $this->validator->integerRange((int)$data['image_id'], 'image_id', 1);
        }

        // 验证principal_id（可选）
        if (isset($data['principal_id']) && $data['principal_id'] !== null) {
            $this->validator->integer($data['principal_id'], 'principal_id');
            $this->validator->integerRange((int)$data['principal_id'], 'principal_id', 1);
        }

        // 验证info（可选）
        if (isset($data['info']) && $data['info'] !== null) {
            $this->validator->isArray($data['info'], 'info');
        }

        return $this->validator->getErrors();
    }

    /**
     * 验证更新学校的数据
     */
    public function validateUpdate(array $data): array
    {
        $this->validator->clearErrors();

        // 验证name字段（可选）
        if (isset($data['name'])) {
            $this->validator->stringLength($data['name'], 'name', 1, 255);
        }

        // 验证image_id（可选）
        if (isset($data['image_id']) && $data['image_id'] !== null) {
            $this->validator->integer($data['image_id'], 'image_id');
            $this->validator->integerRange((int)$data['image_id'], 'image_id', 1);
        }

        // 验证principal_id（可选）
        if (isset($data['principal_id']) && $data['principal_id'] !== null) {
            $this->validator->integer($data['principal_id'], 'principal_id');
            $this->validator->integerRange((int)$data['principal_id'], 'principal_id', 1);
        }

        // 验证info（可选）
        if (isset($data['info']) && $data['info'] !== null) {
            $this->validator->isArray($data['info'], 'info');
        }

        return $this->validator->getErrors();
    }
}
