<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Cache;

use Exception;
use Fight\Common\Adapter\Cache\PsrCache;
use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PsrCache::class)]
class PsrCacheTest extends UnitTestCase
{
    public function test_that_read_returns_cached_value_on_cache_hit(): void
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

        $cache = new PsrCache($pool, $logger);

        $result = $cache->read('key', fn (): string => 'loader-value', 300);

        self::assertSame('cached-value', $result);
    }

    public function test_that_read_calls_loader_and_stores_value_on_cache_miss(): void
    {
        /** @var MockInterface|CacheItemInterface $item */
        $item = $this->mock(CacheItemInterface::class);
        $item->shouldReceive('isHit')->once()->andReturn(false);
        $item->shouldReceive('set')->once()->with('loader-value');
        $item->shouldReceive('expiresAfter')->once()->with(300);
        $item->shouldReceive('get')->once()->andReturn('loader-value');

        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('getItem')->once()->with('key')->andReturn($item);
        $pool->shouldReceive('save')->once()->with($item);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once()->with('Cache MISS: "key"');

        $cache = new PsrCache($pool, $logger);
        $loaderCalled = false;

        $result = $cache->read('key', function () use (&$loaderCalled) {
            $loaderCalled = true;
            return 'loader-value';
        }, 300);

        self::assertTrue($loaderCalled);
        self::assertSame('loader-value', $result);
    }

    public function test_that_read_wraps_pool_exception_in_cache_exception(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('getItem')->once()->andThrow(new Exception('pool error'));

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $cache = new PsrCache($pool, $logger);

        $this->expectException(CacheException::class);

        $cache->read('key', fn (): null => null, 60);
    }
}
