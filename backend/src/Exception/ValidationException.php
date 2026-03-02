<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * 验证异常
 * 用于请求参数验证失败的场景
 */
class ValidationException extends \Exception
{
    protected $code = 422;
    private array $errors;
    
    public function __construct(array $errors, string $message = 'Validation failed', int $code = 422, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
