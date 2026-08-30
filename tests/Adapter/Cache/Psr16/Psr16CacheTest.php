<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Cache\Psr16;

use Exception;
use Fight\Common\Adapter\Cache\Psr16\Psr16Cache;
use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16Cache::class)]
class Psr16CacheTest extends UnitTestCase
{
    public function test_that_psr16_cache_implements_the_additive_mutable_cache_contract(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        self::assertInstanceOf(MutableCache::class, new Psr16Cache($simpleCache, $logger));
    }

    public function test_that_read_returns_a_cached_value_without_calling_the_loader(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);
        $simpleCache->shouldReceive('get')->once()->with('key', Mockery::type('object'))->andReturn('cached-value');

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once()->with('Cache HIT: "key"');

        $cache = new Psr16Cache($simpleCache, $logger);

        self::assertSame('cached-value', $cache->read('key', function (): never {
            self::fail('The loader must not be called for a cache hit.');
        }, 300));
    }

    public function test_that_read_stores_values_and_expiry_on_a_cache_miss(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);
        $simpleCache->shouldReceive('get')->once()->with('key', Mockery::type('object'))->andReturnUsing(
            static fn (string $key, object $sentinel): object => $sentinel
        );
        $simpleCache->shouldReceive('set')->once()->with('key', 'loader-value', 300)->andReturn(true);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once()->with('Cache MISS: "key"');

        $cache = new Psr16Cache($simpleCache, $logger);

        self::assertSame('loader-value', $cache->read('key', fn (): string => 'loader-value', 300));
    }

    public function test_that_read_distinguishes_a_cached_null_from_a_cache_miss(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);
        $simpleCache->shouldReceive('get')->once()->with('key', Mockery::type('object'))->andReturnNull();

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->once()->with('Cache HIT: "key"');

        $cache = new Psr16Cache($simpleCache, $logger);

        self::assertSame(null, $cache->read('key', function (): never {
            self::fail('The loader must not be called for a cached null.');
        }, 300));
    }

    public function test_that_read_translates_invalid_key_and_provider_failures(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);
        $simpleCache->shouldReceive('get')->once()->with('invalid/key', Mockery::type('object'))->andThrow(
            new Exception('invalid key')
        );

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $cache = new Psr16Cache($simpleCache, $logger);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('invalid key');

        $cache->read('invalid/key', fn (): null => null, 60);
    }

    public function test_that_delete_and_clear_delegate_to_the_simple_cache(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);
        $simpleCache->shouldReceive('delete')->once()->with('key')->andReturn(true);
        $simpleCache->shouldReceive('clear')->once()->andReturn(true);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $cache = new Psr16Cache($simpleCache, $logger);
        $cache->delete('key');
        $cache->clear();

        self::addToAssertionCount(1);
    }

    public function test_that_delete_translates_simple_cache_failures(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);
        $simpleCache->shouldReceive('delete')->once()->with('key')->andThrow(new Exception('delete error'));

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('delete error');

        (new Psr16Cache($simpleCache, $logger))->delete('key');
    }

    public function test_that_clear_translates_simple_cache_failures(): void
    {
        /** @var MockInterface|CacheInterface $simpleCache */
        $simpleCache = $this->mock(CacheInterface::class);
        $simpleCache->shouldReceive('clear')->once()->andThrow(new Exception('clear error'));

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('clear error');

        (new Psr16Cache($simpleCache, $logger))->clear();
    }
}
