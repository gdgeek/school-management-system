<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * 业务逻辑异常
 * 用于业务规则违反的场景
 */
class BusinessException extends \Exception
{
    protected $code = 400;
    
    public function __construct(string $message = 'Business logic error', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
