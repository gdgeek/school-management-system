<?php

declare(strict_types=1);

namespace App\Validator;

use App\Helper\ValidatorHelper;

/**
 * Class Validator
 * 
 * 班级数据验证器
 */
class ClassValidator
{
    public function __construct(private ValidatorHelper $validator) {}

    /**
     * 验证创建班级的数据
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

        // 验证school_id字段
        if (!isset($data['school_id'])) {
            $this->validator->required(null, 'school_id');
        } else {
            $this->validator->integer($data['school_id'], 'school_id');
            $this->validator->integerRange((int)$data['school_id'], 'school_id', 1);
        }

        // 验证info（可选，允许字符串或数组）
        if (isset($data['info']) && $data['info'] !== null) {
            if (!is_array($data['info']) && !is_string($data['info'])) {
                $this->validator->isArray($data['info'], 'info');
            }
        }

        return $this->validator->getErrors();
    }

    /**
     * 验证更新班级的数据
     */
    public function validateUpdate(array $data): array
    {
        $this->validator->clearErrors();

        // 验证name字段（可选）
        if (isset($data['name'])) {
            $this->validator->stringLength($data['name'], 'name', 1, 255);
        }

        // 验证school_id字段（可选）
        if (isset($data['school_id'])) {
            $this->validator->integer($data['school_id'], 'school_id');
            $this->validator->integerRange((int)$data['school_id'], 'school_id', 1);
        }

        // 验证info（可选，允许字符串或数组）
        if (isset($data['info']) && $data['info'] !== null) {
            if (!is_array($data['info']) && !is_string($data['info'])) {
                $this->validator->isArray($data['info'], 'info');
            }
        }

        return $this->validator->getErrors();
    }
}
