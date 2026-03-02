<?php

declare(strict_types=1);

namespace App\Helper;

use PDO;
use Psr\Log\LoggerInterface;

/**
 * 查询优化辅助类
 *
 * 提供查询结果缓存、分页优化和预加载辅助，避免 N+1 查询问题。
 */
class QueryOptimizer
{
    private PDO $pdo;
    private CacheHelper $cache;
    private ?LoggerInterface $logger;

    public function __construct(PDO $pdo, CacheHelper $cache, ?LoggerInterface $logger = null)
    {
        $this->pdo    = $pdo;
        $this->cache  = $cache;
        $this->logger = $logger;
    }

    // ─── Query Result Caching ─────────────────────────────────────────────────

    /**
     * 执行查询并缓存结果。
     *
     * @param string   $sql        SQL 语句（含命名占位符）
     * @param array    $params     绑定参数
     * @param string   $cacheKey   缓存键（建议包含业务语义，如 "school:list:page1"）
     * @param int      $ttl        缓存 TTL（秒），默认 60 秒
     * @param string   $cacheTag   可选标签，用于批量失效
     * @return array
     */
    public function cachedQuery(
        string $sql,
        array $params = [],
        string $cacheKey = '',
        int $ttl = 60,
        string $cacheTag = ''
    ): array {
        if ($cacheKey !== '') {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $this->log('cache_hit', $cacheKey);
                return $cached;
            }
        }

        $result = $this->executeQuery($sql, $params);

        if ($cacheKey !== '') {
            $this->cache->set($cacheKey, $result, $ttl);
            if ($cacheTag !== '') {
                $this->cache->tag($cacheKey, $cacheTag);
            }
            $this->log('cache_set', $cacheKey);
        }

        return $result;
    }

    /**
     * 使缓存标签下的所有缓存失效（例如写操作后调用）。
     *
     * @param string $tag 标签名
     * @return int 删除的缓存条目数
     */
    public function invalidateTag(string $tag): int
    {
        $count = $this->cache->deleteByTag($tag);
        $this->log('invalidate_tag', $tag, ['deleted' => $count]);
        return $count;
    }

    // ─── Pagination Optimization ──────────────────────────────────────────────

    /**
     * 优化分页查询：使用相同 WHERE 子句执行 COUNT，避免全表扫描。
     *
     * @param string $baseFrom    FROM + JOIN + WHERE 子句（不含 SELECT / ORDER / LIMIT）
     * @param array  $params      绑定参数
     * @param string $selectCols  SELECT 列（默认 *）
     * @param string $orderBy     ORDER BY 子句（不含关键字，如 "id DESC"）
     * @param int    $page        当前页（从 1 开始）
     * @param int    $pageSize    每页条数（最大 100）
     * @return array{items: array, pagination: array{total: int, page: int, pageSize: int, totalPages: int}}
     */
    public function paginatedQuery(
        string $baseFrom,
        array $params = [],
        string $selectCols = '*',
        string $orderBy = 'id DESC',
        int $page = 1,
        int $pageSize = 20
    ): array {
        $page     = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $offset   = ($page - 1) * $pageSize;

        // COUNT with the same WHERE clause
        $countSql = "SELECT COUNT(*) AS total {$baseFrom}";
        $countRow = $this->executeQuery($countSql, $params);
        $total    = (int) ($countRow[0]['total'] ?? 0);

        // Data query
        $dataSql = "SELECT {$selectCols} {$baseFrom} ORDER BY {$orderBy} LIMIT :_limit OFFSET :_offset";
        $dataParams = array_merge($params, [':_limit' => $pageSize, ':_offset' => $offset]);
        $items = $this->executeQuery($dataSql, $dataParams);

        return [
            'items'      => $items,
            'pagination' => [
                'total'      => $total,
                'page'       => $page,
                'pageSize'   => $pageSize,
                'totalPages' => $total > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ];
    }

    // ─── Eager Loading Helpers ────────────────────────────────────────────────

    /**
     * 批量预加载关联数据，避免 N+1 查询。
     *
     * 示例：给学校列表预加载校长信息
     * ```php
     * $schools = $optimizer->eagerLoad(
     *     $schools,
     *     'principal_id',          // 主表外键字段
     *     'SELECT id, nickname, avatar FROM user WHERE id IN (:ids)',
     *     'id',                    // 关联表主键字段
     *     'principal'              // 挂载到主记录的属性名
     * );
     * ```
     *
     * @param array  $records       主记录列表
     * @param string $foreignKey    主记录中的外键字段名
     * @param string $relatedSql    关联查询 SQL，必须包含 ":ids" 占位符
     * @param string $relatedKey    关联记录中的主键字段名
     * @param string $mountAs       挂载到主记录的属性名
     * @param bool   $many          true = 一对多（挂载数组），false = 一对一（挂载单条）
     * @return array 已挂载关联数据的主记录列表
     */
    public function eagerLoad(
        array $records,
        string $foreignKey,
        string $relatedSql,
        string $relatedKey,
        string $mountAs,
        bool $many = false
    ): array {
        if (empty($records)) {
            return $records;
        }

        // Collect unique foreign key values (skip nulls)
        $ids = array_values(array_unique(array_filter(
            array_column($records, $foreignKey),
            fn($v) => $v !== null
        )));

        if (empty($ids)) {
            return array_map(function ($record) use ($mountAs, $many) {
                $record[$mountAs] = $many ? [] : null;
                return $record;
            }, $records);
        }

        // Build IN clause with individual placeholders
        $placeholders = implode(',', array_map(fn($i) => ":id_{$i}", array_keys($ids)));
        $sql = str_replace(':ids', $placeholders, $relatedSql);
        $params = [];
        foreach ($ids as $i => $id) {
            $params[":id_{$i}"] = $id;
        }

        $related = $this->executeQuery($sql, $params);

        // Index related records by their key
        $indexed = [];
        foreach ($related as $row) {
            $key = $row[$relatedKey];
            if ($many) {
                $indexed[$key][] = $row;
            } else {
                $indexed[$key] = $row;
            }
        }

        // Mount onto parent records
        return array_map(function ($record) use ($foreignKey, $mountAs, $indexed, $many) {
            $fkValue = $record[$foreignKey] ?? null;
            $record[$mountAs] = $fkValue !== null
                ? ($indexed[$fkValue] ?? ($many ? [] : null))
                : ($many ? [] : null);
            return $record;
        }, $records);
    }

    /**
     * 批量预加载多对多关联（通过中间表）。
     *
     * @param array  $records       主记录列表
     * @param string $primaryKey    主记录主键字段名
     * @param string $pivotSql      中间表查询 SQL，必须包含 ":ids" 占位符，
     *                              SELECT 中需包含 $pivotFk 和关联数据字段
     * @param string $pivotFk       中间表中指向主记录的外键字段名
     * @param string $mountAs       挂载到主记录的属性名
     * @return array
     */
    public function eagerLoadMany(
        array $records,
        string $primaryKey,
        string $pivotSql,
        string $pivotFk,
        string $mountAs
    ): array {
        return $this->eagerLoad($records, $primaryKey, $pivotSql, $pivotFk, $mountAs, true);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * 执行 PDO 查询并返回所有行。
     */
    private function executeQuery(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->log('query_error', $sql, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function log(string $event, string $subject, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug("[QueryOptimizer] {$event}: {$subject}", $context);
        }
    }
}
