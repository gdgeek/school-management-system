<?php

declare(strict_types=1);

namespace App\Helper;

use PDO;
use Exception;

/**
 * Database Helper
 * 
 * 数据库辅助类，提供事务管理和查询优化功能
 */
class DatabaseHelper
{
    public function __construct(
        private PDO $pdo
    ) {
        // 设置错误模式为异常
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // 设置默认fetch模式为关联数组
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * 执行事务
     * 
     * @param callable $callback 事务回调函数
     * @return mixed 回调函数的返回值
     * @throws Exception
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        
        try {
            $result = $callback($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 批量插入
     * 
     * @param string $table 表名
     * @param array $columns 列名数组
     * @param array $rows 数据行数组
     * @return int 插入的行数
     */
    public function batchInsert(string $table, array $columns, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $columnList = implode(', ', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $values = implode(', ', array_fill(0, count($rows), $placeholders));
        
        $sql = "INSERT INTO $table ($columnList) VALUES $values";
        $stmt = $this->pdo->prepare($sql);
        
        $params = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }
        
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    /**
     * 执行查询并返回结果
     * 
     * @param string $sql SQL语句
     * @param array $params 绑定参数
     * @return array 查询结果
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取PDO实例
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
