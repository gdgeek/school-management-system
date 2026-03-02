<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * 资源不存在异常
 * 用于请求的资源不存在的场景
 */
class NotFoundException extends \Exception
{
    protected $code = 404;
    
    public function __construct(string $message = 'Resource not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
