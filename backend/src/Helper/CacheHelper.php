<?php

declare(strict_types=1);

namespace App\Helper;

use Redis;
use Psr\Log\LoggerInterface;

/**
 * Redis缓存辅助类
 * 封装Redis操作，支持TTL、标签和批量失效
 */
class CacheHelper
{
    private Redis $redis;
    private ?LoggerInterface $logger;
    private string $prefix;

    public function __construct(Redis $redis, ?LoggerInterface $logger = null, string $prefix = 'school_mgmt:')
    {
        $this->redis = $redis;
        $this->logger = $logger;
        $this->prefix = $prefix;
    }

    /**
     * 获取缓存值
     *
     * @param string $key 缓存键
     * @return mixed|null 缓存值，不存在返回null
     */
    public function get(string $key): mixed
    {
        try {
            $value = $this->redis->get($this->prefix . $key);
            if ($value === false) {
                return null;
            }
            return json_decode($value, true);
        } catch (\Exception $e) {
            $this->logError('get', $key, $e);
            return null;
        }
    }

    /**
     * 设置缓存值
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒），0表示永不过期
     * @return bool 是否成功
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        try {
            $serialized = json_encode($value);
            $fullKey = $this->prefix . $key;
            
            if ($ttl > 0) {
                return $this->redis->setex($fullKey, $ttl, $serialized);
            } else {
                return $this->redis->set($fullKey, $serialized);
            }
        } catch (\Exception $e) {
            $this->logError('set', $key, $e);
            return false;
        }
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public function delete(string $key): bool
    {
        try {
            return $this->redis->del($this->prefix . $key) > 0;
        } catch (\Exception $e) {
            $this->logError('delete', $key, $e);
            return false;
        }
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function exists(string $key): bool
    {
        try {
            return $this->redis->exists($this->prefix . $key) > 0;
        } catch (\Exception $e) {
            $this->logError('exists', $key, $e);
            return false;
        }
    }

    /**
     * 递增缓存值
     *
     * @param string $key 缓存键
     * @param int $value 递增值
     * @return int|false 递增后的值，失败返回false
     */
    public function increment(string $key, int $value = 1): int|false
    {
        try {
            return $this->redis->incrBy($this->prefix . $key, $value);
        } catch (\Exception $e) {
            $this->logError('increment', $key, $e);
            return false;
        }
    }

    /**
     * 递减缓存值
     *
     * @param string $key 缓存键
     * @param int $value 递减值
     * @return int|false 递减后的值，失败返回false
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        try {
            return $this->redis->decrBy($this->prefix . $key, $value);
        } catch (\Exception $e) {
            $this->logError('decrement', $key, $e);
            return false;
        }
    }

    /**
     * 为缓存添加标签
     *
     * @param string $key 缓存键
     * @param string $tag 标签名
     * @return bool
     */
    public function tag(string $key, string $tag): bool
    {
        try {
            $tagKey = $this->prefix . 'tag:' . $tag;
            return $this->redis->sAdd($tagKey, $this->prefix . $key) !== false;
        } catch (\Exception $e) {
            $this->logError('tag', $key, $e);
            return false;
        }
    }

    /**
     * 根据标签批量删除缓存
     *
     * @param string $tag 标签名
     * @return int 删除的缓存数量
     */
    public function deleteByTag(string $tag): int
    {
        try {
            $tagKey = $this->prefix . 'tag:' . $tag;
            $keys = $this->redis->sMembers($tagKey);
            
            if (empty($keys)) {
                return 0;
            }

            $deleted = $this->redis->del($keys);
            $this->redis->del($tagKey);
            
            return $deleted;
        } catch (\Exception $e) {
            $this->logError('deleteByTag', $tag, $e);
            return 0;
        }
    }

    /**
     * 获取或设置缓存（缓存不存在时执行回调）
     *
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @param int $ttl 过期时间（秒）
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * 清空所有带前缀的缓存
     *
     * @return int 删除的缓存数量
     */
    public function flush(): int
    {
        try {
            $keys = $this->redis->keys($this->prefix . '*');
            if (empty($keys)) {
                return 0;
            }
            return $this->redis->del($keys);
        } catch (\Exception $e) {
            $this->logError('flush', '', $e);
            return 0;
        }
    }

    /**
     * 获取缓存的剩余TTL
     *
     * @param string $key 缓存键
     * @return int TTL（秒），-1表示永不过期，-2表示不存在
     */
    public function ttl(string $key): int
    {
        try {
            return $this->redis->ttl($this->prefix . $key);
        } catch (\Exception $e) {
            $this->logError('ttl', $key, $e);
            return -2;
        }
    }

    /**
     * 记录错误日志
     */
    private function logError(string $operation, string $key, \Exception $e): void
    {
        if ($this->logger) {
            $this->logger->error("Redis {$operation} failed for key '{$key}': {$e->getMessage()}", [
                'exception' => $e,
                'key' => $key,
                'operation' => $operation,
            ]);
        }
    }
}
