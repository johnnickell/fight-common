<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Cache\Laravel;

use Exception;
use Fight\Common\Adapter\Cache\Laravel\LaravelCache;
use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LaravelCache::class)]
final class LaravelCacheTest extends UnitTestCase
{
    public function test_that_read_handles_native_hits_misses_and_cached_null_values(): void
    {
        $cache = new LaravelCache(new Repository(new ArrayStore()));

        self::assertInstanceOf(MutableCache::class, $cache);
        self::assertSame('loader-value', $cache->read('key', fn (): string => 'loader-value', 300));
        self::assertSame('loader-value', $cache->read('key', function (): never {
            self::fail('The loader must not be called for a cache hit.');
        }, 300));
        self::assertSame(null, $cache->read('nullable', fn (): null => null, 300));
        self::assertSame(null, $cache->read('nullable', function (): never {
            self::fail('The loader must not be called for a cached null.');
        }, 300));
    }

    public function test_that_read_passes_the_requested_ttl_to_the_native_repository(): void
    {
        /** @var MockInterface|Repository $repository */
        $repository = $this->mock(Repository::class);
        $repository->shouldReceive('get')->once()->with('key', \Mockery::type('object'))->andReturnUsing(
            static fn (string $key, object $sentinel): object => $sentinel
        );
        $repository->shouldReceive('put')->once()->with(
            'key',
            ['fight-common.laravel-cache.value' => 'loader-value'],
            300
        )->andReturn(true);

        $cache = new LaravelCache($repository);

        self::assertSame('loader-value', $cache->read('key', fn (): string => 'loader-value', 300));
    }

    public function test_that_read_returns_an_existing_legacy_repository_value(): void
    {
        /** @var MockInterface|Repository $repository */
        $repository = $this->mock(Repository::class);
        $repository->shouldReceive('get')->once()->with('key', \Mockery::type('object'))->andReturn('legacy-value');

        $cache = new LaravelCache($repository);

        self::assertSame('legacy-value', $cache->read('key', function (): never {
            self::fail('The loader must not be called for a cache hit.');
        }, 300));
    }

    public function test_that_read_translates_repository_failures(): void
    {
        /** @var MockInterface|Repository $repository */
        $repository = $this->mock(Repository::class);
        $repository->shouldReceive('get')->once()->with('key', \Mockery::type('object'))->andThrow(
            new Exception('provider error')
        );

        $cache = new LaravelCache($repository);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('provider error');

        $cache->read('key', fn (): null => null, 300);
    }

    public function test_that_delete_and_clear_remove_values_from_the_native_repository(): void
    {
        $cache = new LaravelCache(new Repository(new ArrayStore()));
        $cache->read('first', fn (): string => 'first-value', 300);
        $cache->read('second', fn (): string => 'second-value', 300);

        $cache->delete('first');

        self::assertSame('first-replacement', $cache->read('first', fn (): string => 'first-replacement', 300));
        self::assertSame('second-value', $cache->read('second', function (): never {
            self::fail('The loader must not be called before clear.');
        }, 300));

        $cache->clear();

        self::assertSame('second-replacement', $cache->read('second', fn (): string => 'second-replacement', 300));
    }

    public function test_that_mutating_failures_are_translated_to_cache_exceptions(): void
    {
        /** @var MockInterface|Repository $repository */
        $repository = $this->mock(Repository::class);
        $repository->shouldReceive('forget')->once()->with('key')->andThrow(new Exception('provider error'));

        $cache = new LaravelCache($repository);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('provider error');

        $cache->delete('key');
    }

    public function test_that_clear_failures_are_translated_to_cache_exceptions(): void
    {
        /** @var MockInterface|Repository $repository */
        $repository = $this->mock(Repository::class);
        $repository->shouldReceive('flush')->once()->andThrow(new Exception('provider error'));

        $cache = new LaravelCache($repository);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('provider error');

        $cache->clear();
    }
}
