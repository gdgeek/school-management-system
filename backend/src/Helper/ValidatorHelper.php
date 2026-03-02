<?php

declare(strict_types=1);

namespace App\Helper;

/**
 * Validator Helper
 * 
 * 请求参数验证辅助类
 */
class ValidatorHelper
{
    private array $errors = [];

    /**
     * 验证必填字段
     */
    public function required(mixed $value, string $field): bool
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->errors[$field][] = "$field is required";
            return false;
        }
        return true;
    }

    /**
     * 验证字符串长度
     */
    public function stringLength(string $value, string $field, int $min = 0, int $max = PHP_INT_MAX): bool
    {
        $length = mb_strlen($value);
        if ($length < $min) {
            $this->errors[$field][] = "$field must be at least $min characters";
            return false;
        }
        if ($length > $max) {
            $this->errors[$field][] = "$field must not exceed $max characters";
            return false;
        }
        return true;
    }

    /**
     * 验证整数
     */
    public function integer(mixed $value, string $field): bool
    {
        if (!is_numeric($value) || (int)$value != $value) {
            $this->errors[$field][] = "$field must be an integer";
            return false;
        }
        return true;
    }

    /**
     * 验证整数范围
     */
    public function integerRange(int $value, string $field, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool
    {
        if ($value < $min || $value > $max) {
            $this->errors[$field][] = "$field must be between $min and $max";
            return false;
        }
        return true;
    }

    /**
     * 验证邮箱格式
     */
    public function email(string $value, string $field): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "$field must be a valid email address";
            return false;
        }
        return true;
    }

    /**
     * 验证URL格式
     */
    public function url(string $value, string $field): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = "$field must be a valid URL";
            return false;
        }
        return true;
    }

    /**
     * 验证数组
     */
    public function isArray(mixed $value, string $field): bool
    {
        if (!is_array($value)) {
            $this->errors[$field][] = "$field must be an array";
            return false;
        }
        return true;
    }

    /**
     * 验证枚举值
     */
    public function in(mixed $value, string $field, array $allowedValues): bool
    {
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            $this->errors[$field][] = "$field must be one of: $allowed";
            return false;
        }
        return true;
    }

    /**
     * 验证正则表达式
     */
    public function regex(string $value, string $field, string $pattern): bool
    {
        if (!preg_match($pattern, $value)) {
            $this->errors[$field][] = "$field format is invalid";
            return false;
        }
        return true;
    }

    /**
     * 自定义验证
     */
    public function custom(mixed $value, string $field, callable $callback): bool
    {
        $result = $callback($value);
        if ($result !== true) {
            $this->errors[$field][] = is_string($result) ? $result : "$field is invalid";
            return false;
        }
        return true;
    }

    /**
     * 获取所有错误
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 检查是否有错误
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * 清空错误
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * 验证数据
     * 
     * @param array $data 要验证的数据
     * @param array $rules 验证规则
     * @return bool 验证是否通过
     * 
     * 规则格式示例:
     * [
     *     'name' => ['required', ['stringLength', 1, 255]],
     *     'email' => ['required', 'email'],
     *     'age' => ['integer', ['integerRange', 0, 150]],
     * ]
     */
    public function validate(array $data, array $rules): bool
    {
        $this->clearErrors();

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                if (is_string($rule)) {
                    // 简单规则，如 'required', 'email'
                    $this->$rule($value, $field);
                } elseif (is_array($rule)) {
                    // 带参数的规则，如 ['stringLength', 1, 255]
                    $method = array_shift($rule);
                    $this->$method($value, $field, ...$rule);
                }
            }
        }

        return !$this->hasErrors();
    }
}
