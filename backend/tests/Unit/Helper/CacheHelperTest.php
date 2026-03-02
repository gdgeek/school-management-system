<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Helper\CacheHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Redis;

/**
 * Unit tests for CacheHelper.
 *
 * Uses a mocked Redis instance to test cache logic without a real Redis server.
 */
class CacheHelperTest extends TestCase
{
    private CacheHelper $cache;
    private MockObject&Redis $redis;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->cache = new CacheHelper($this->redis, null, 'test:');
    }

    public function testGetReturnsDecodedValue(): void
    {
        $this->redis->method('get')
            ->with('test:my_key')
            ->willReturn(json_encode(['id' => 1, 'name' => 'School']));

        $result = $this->cache->get('my_key');
        $this->assertSame(['id' => 1, 'name' => 'School'], $result);
    }

    public function testGetReturnsNullWhenKeyMissing(): void
    {
        $this->redis->method('get')
            ->with('test:missing')
            ->willReturn(false);

        $this->assertNull($this->cache->get('missing'));
    }

    public function testGetReturnsNullOnRedisException(): void
    {
        $this->redis->method('get')
            ->willThrowException(new \Exception('Connection refused'));

        $this->assertNull($this->cache->get('key'));
    }

    public function testSetWithTtlCallsSetex(): void
    {
        $this->redis->expects($this->once())
            ->method('setex')
            ->with('test:key', 600, json_encode('value'))
            ->willReturn(true);

        $this->assertTrue($this->cache->set('key', 'value', 600));
    }

    public function testSetWithZeroTtlCallsSet(): void
    {
        $this->redis->expects($this->once())
            ->method('set')
            ->with('test:key', json_encode('value'))
            ->willReturn(true);

        $this->assertTrue($this->cache->set('key', 'value', 0));
    }

    public function testDeleteCallsDel(): void
    {
        $this->redis->expects($this->once())
            ->method('del')
            ->with('test:key')
            ->willReturn(1);

        $this->assertTrue($this->cache->delete('key'));
    }

    public function testDeleteReturnsFalseWhenKeyNotFound(): void
    {
        $this->redis->method('del')
            ->willReturn(0);

        $this->assertFalse($this->cache->delete('nonexistent'));
    }

    public function testExistsReturnsTrue(): void
    {
        $this->redis->method('exists')
            ->with('test:key')
            ->willReturn(1);

        $this->assertTrue($this->cache->exists('key'));
    }

    public function testExistsReturnsFalse(): void
    {
        $this->redis->method('exists')
            ->with('test:key')
            ->willReturn(0);

        $this->assertFalse($this->cache->exists('key'));
    }

    public function testIncrementCallsIncrBy(): void
    {
        $this->redis->method('incrBy')
            ->with('test:counter', 1)
            ->willReturn(5);

        $this->assertSame(5, $this->cache->increment('counter'));
    }

    public function testDecrementCallsDecrBy(): void
    {
        $this->redis->method('decrBy')
            ->with('test:counter', 1)
            ->willReturn(3);

        $this->assertSame(3, $this->cache->decrement('counter'));
    }

    public function testRememberReturnsCachedValue(): void
    {
        $this->redis->method('get')
            ->with('test:key')
            ->willReturn(json_encode('cached'));

        $result = $this->cache->remember('key', fn() => 'fresh', 600);
        $this->assertSame('cached', $result);
    }

    public function testRememberCallsCallbackOnMiss(): void
    {
        $this->redis->method('get')
            ->willReturn(false);

        $this->redis->expects($this->once())
            ->method('setex')
            ->willReturn(true);

        $result = $this->cache->remember('key', fn() => 'fresh', 600);
        $this->assertSame('fresh', $result);
    }

    public function testTtlReturnsValue(): void
    {
        $this->redis->method('ttl')
            ->with('test:key')
            ->willReturn(300);

        $this->assertSame(300, $this->cache->ttl('key'));
    }

    public function testTagAddsKeyToSet(): void
    {
        $this->redis->expects($this->once())
            ->method('sAdd')
            ->with('test:tag:schools', 'test:school_1')
            ->willReturn(1);

        $this->assertTrue($this->cache->tag('school_1', 'schools'));
    }
}
