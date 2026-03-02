<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * 未授权异常
 * 用于认证失败的场景
 */
class UnauthorizedException extends \Exception
{
    protected $code = 401;
    
    public function __construct(string $message = 'Unauthorized', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
