<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Cache\Psr6;

use Exception;
use Fight\Common\Adapter\Cache\Psr6\Psr6Cache;
use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(Psr6Cache::class)]
class Psr6CacheTest extends UnitTestCase
{
    public function test_that_psr6_cache_implements_the_additive_mutable_cache_contract(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        self::assertInstanceOf(MutableCache::class, new Psr6Cache($pool, $logger));
    }

    public function test_that_read_returns_cached_value_when_the_pool_item_is_a_hit(): void
    {
        /** @var MockInterface|CacheItemInterface $item */
        $item = $this->mock(CacheItemInterface::class);
        $item->shouldReceive('isHit')->once()->andReturn(true);
        $item->shouldReceive('get')->once()->andReturn('cached-value');

        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('getItem')->once()->with('key')->andReturn($item);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once()->with('Cache HIT: "key"');

        $cache = new Psr6Cache($pool, $logger);

        self::assertSame('cached-value', $cache->read('key', fn (): string => 'loader-value', 300));
    }

    public function test_that_read_stores_the_loader_value_and_expiry_when_the_pool_item_is_a_miss(): void
    {
        /** @var MockInterface|CacheItemInterface $item */
        $item = $this->mock(CacheItemInterface::class);
        $item->shouldReceive('isHit')->once()->andReturn(false);
        $item->shouldReceive('set')->once()->with('loader-value')->andReturnSelf();
        $item->shouldReceive('expiresAfter')->once()->with(300)->andReturnSelf();
        $item->shouldReceive('get')->once()->andReturn('loader-value');

        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('getItem')->once()->with('key')->andReturn($item);
        $pool->shouldReceive('save')->once()->with($item)->andReturn(true);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once()->with('Cache MISS: "key"');

        $cache = new Psr6Cache($pool, $logger);

        self::assertSame('loader-value', $cache->read('key', fn (): string => 'loader-value', 300));
    }

    public function test_that_read_translates_invalid_key_and_provider_failures(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('getItem')->once()->with('invalid/key')->andThrow(new Exception('invalid key'));

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $cache = new Psr6Cache($pool, $logger);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('invalid key');

        $cache->read('invalid/key', fn (): null => null, 60);
    }

    public function test_that_delete_and_clear_delegate_to_the_pool(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('deleteItem')->once()->with('key')->andReturn(true);
        $pool->shouldReceive('clear')->once()->andReturn(true);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $cache = new Psr6Cache($pool, $logger);
        $cache->delete('key');
        $cache->clear();

        self::addToAssertionCount(1);
    }

    public function test_that_delete_translates_pool_failures(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('deleteItem')->once()->with('key')->andThrow(new Exception('delete error'));

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('delete error');

        (new Psr6Cache($pool, $logger))->delete('key');
    }

    public function test_that_clear_translates_pool_failures(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('clear')->once()->andThrow(new Exception('clear error'));

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('clear error');

        (new Psr6Cache($pool, $logger))->clear();
    }
}
