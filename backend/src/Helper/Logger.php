<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-3兼容的日志记录器
 * 支持日志级别、文件轮转和上下文信息
 */
class Logger implements LoggerInterface
{
    private string $logPath;
    private string $logLevel;
    private array $logLevels = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    public function __construct(string $logPath, string $logLevel = LogLevel::INFO)
    {
        $this->logPath = rtrim($logPath, '/');
        $this->logLevel = $logLevel;
        
        // 确保日志目录存在
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * 系统不可用
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * 必须立即采取行动
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * 临界条件
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * 运行时错误
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * 警告但不是错误
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * 正常但重要的事件
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * 有趣的事件
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $context);
    }

    /**
     * 详细的调试信息
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * 记录任意级别的日志
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // 检查日志级别
        if (!$this->shouldLog($level)) {
            return;
        }

        // 格式化消息
        $formattedMessage = $this->formatMessage($level, $message, $context);
        
        // 写入日志文件
        $this->writeLog($level, $formattedMessage);
    }

    /**
     * 检查是否应该记录此级别的日志
     */
    private function shouldLog(string $level): bool
    {
        $currentLevel = $this->logLevels[$this->logLevel] ?? 0;
        $messageLevel = $this->logLevels[$level] ?? 0;
        
        return $messageLevel >= $currentLevel;
    }

    /**
     * 格式化日志消息
     */
    private function formatMessage(string $level, string|\Stringable $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);
        
        // 替换消息中的占位符
        $message = $this->interpolate((string)$message, $context);
        
        // 构建日志行
        $logLine = "[{$timestamp}] {$level}: {$message}";
        
        // 添加上下文信息
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $logLine .= " | Context: {$contextJson}";
        }
        
        return $logLine;
    }

    /**
     * 替换消息中的占位符
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        
        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replace['{' . $key . '}'] = $value;
            }
        }
        
        return strtr($message, $replace);
    }

    /**
     * 写入日志文件
     */
    private function writeLog(string $level, string $message): void
    {
        // 根据级别选择日志文件
        $filename = $this->getLogFilename($level);
        $filepath = $this->logPath . '/' . $filename;
        
        // 写入日志
        file_put_contents($filepath, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // 检查文件大小并轮转
        $this->rotateLogIfNeeded($filepath);
    }

    /**
     * 获取日志文件名
     */
    private function getLogFilename(string $level): string
    {
        $date = date('Y-m-d');
        
        // 错误级别使用单独的错误日志
        if (in_array($level, [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY])) {
            return "error-{$date}.log";
        }
        
        return "app-{$date}.log";
    }

    /**
     * 日志轮转（如果文件超过10MB）
     */
    private function rotateLogIfNeeded(string $filepath): void
    {
        if (!file_exists($filepath)) {
            return;
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (filesize($filepath) > $maxSize) {
            $timestamp = date('YmdHis');
            $rotatedPath = $filepath . '.' . $timestamp;
            rename($filepath, $rotatedPath);
            
            // 压缩旧日志
            if (function_exists('gzencode')) {
                $content = file_get_contents($rotatedPath);
                file_put_contents($rotatedPath . '.gz', gzencode($content));
                unlink($rotatedPath);
            }
        }
    }

    /**
     * 清理旧日志文件（保留最近30天）
     */
    public function cleanOldLogs(int $days = 30): int
    {
        $deleted = 0;
        $cutoffTime = time() - ($days * 86400);
        
        $files = glob($this->logPath . '/*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
