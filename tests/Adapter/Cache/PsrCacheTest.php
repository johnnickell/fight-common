<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Cache;

use Fight\Common\Adapter\Cache\PsrCache;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(PsrCache::class)]
class PsrCacheTest extends UnitTestCase
{
    public function test_that_psr_cache_remains_a_silent_mutable_cache_compatibility_path(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $deprecations = [];
        set_error_handler(
            static function (int $severity, string $message) use (&$deprecations): bool {
                if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                    $deprecations[] = $message;
                }

                return true;
            }
        );

        try {
            $cache = new PsrCache($pool, $logger);
        } finally {
            restore_error_handler();
        }

        self::assertInstanceOf(MutableCache::class, $cache);
        self::assertSame([], $deprecations);
    }

    public function test_that_read_delegates_to_the_canonical_psr6_adapter(): void
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
        $result = $cache->read('key', fn (): string => 'loader-value', 300);

        self::assertSame('loader-value', $result);
    }

    public function test_that_delete_and_clear_delegate_to_the_pool(): void
    {
        /** @var MockInterface|CacheItemPoolInterface $pool */
        $pool = $this->mock(CacheItemPoolInterface::class);
        $pool->shouldReceive('deleteItem')->once()->with('key')->andReturn(true);
        $pool->shouldReceive('clear')->once()->andReturn(true);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $cache = new PsrCache($pool, $logger);
        $cache->delete('key');
        $cache->clear();

        self::addToAssertionCount(1);
    }

}
