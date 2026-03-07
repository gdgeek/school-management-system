<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\ResponseHelper;
use App\Helper\DatabaseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 健康检查控制器
 * 提供系统健康状态和版本信息
 */
class HealthController
{
    public function __construct(
        private ResponseHelper $responseHelper,
        private DatabaseHelper $dbHelper,
        private mixed $redis = null
    ) {}

    /**
     * GET /api/health
     * 基础健康检查
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseHelper->success([
            'status' => 'healthy',
            'timestamp' => time(),
        ]);
    }

    /**
     * GET /api/health/detailed
     * 详细健康检查（包含数据库、Redis、磁盘空间）
     */
    public function detailed(ServerRequestInterface $request): ResponseInterface
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk' => $this->checkDiskSpace(),
        ];

        $allHealthy = true;
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $allHealthy = false;
                break;
            }
        }

        $statusCode = $allHealthy ? 200 : 503;

        return $this->responseHelper->json([
            'code' => $statusCode,
            'message' => $allHealthy ? 'All systems healthy' : 'Some systems unhealthy',
            'data' => [
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'timestamp' => time(),
            ],
        ], $statusCode);
    }

    /**
     * GET /api/version
     * 获取API版本和构建信息
     */
    public function version(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseHelper->success([
            'version' => '1.0.0',
            'api_version' => 'v1',
            'build_time' => '2026-03-02',
            'php_version' => PHP_VERSION,
            'environment' => getenv('APP_ENV') ?: 'production',
        ]);
    }

    /**
     * 检查数据库连接
     */
    private function checkDatabase(): array
    {
        try {
            $result = $this->dbHelper->query('SELECT 1 as test');
            
            if (!empty($result) && $result[0]['test'] == 1) {
                return [
                    'status' => 'healthy',
                    'message' => 'Database connection successful',
                ];
            }
            
            return [
                'status' => 'unhealthy',
                'message' => 'Database query failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 检查Redis连接
     */
    private function checkRedis(): array
    {
        if ($this->redis === null) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis not configured',
            ];
        }
        try {
            $this->redis->ping();
            
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 检查磁盘空间
     */
    private function checkDiskSpace(): array
    {
        try {
            $path = __DIR__ . '/../../';
            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            
            if ($freeSpace === false || $totalSpace === false) {
                return [
                    'status' => 'unknown',
                    'message' => 'Unable to check disk space',
                ];
            }
            
            $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            
            // 如果磁盘使用超过90%，标记为不健康
            if ($usedPercent > 90) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Disk space critically low',
                    'free_space' => $this->formatBytes($freeSpace),
                    'total_space' => $this->formatBytes($totalSpace),
                    'used_percent' => round($usedPercent, 2),
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Disk space sufficient',
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'used_percent' => round($usedPercent, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'message' => 'Disk space check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 格式化字节数为可读格式
     */
    private function formatBytes(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
