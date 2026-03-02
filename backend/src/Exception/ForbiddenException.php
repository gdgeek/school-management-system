<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * 禁止访问异常
 * 用于权限不足的场景
 */
class ForbiddenException extends \Exception
{
    protected $code = 403;
    
    public function __construct(string $message = 'Forbidden', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
