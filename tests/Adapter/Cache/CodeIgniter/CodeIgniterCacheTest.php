<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Cache\CodeIgniter;

use CodeIgniter\Cache\CacheInterface;
use Exception;
use Fight\Common\Adapter\Cache\CodeIgniter\CodeIgniterCache;
use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CodeIgniterCache::class)]
final class CodeIgniterCacheTest extends UnitTestCase
{
    public function test_that_read_handles_native_misses_hits_and_cached_null_values(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('get')->with('value')->twice()->andReturn(null, ['fight-common.codeigniter-cache.value' => 'value']);
        $native->shouldReceive('get')->with('nullable')->twice()->andReturn(null, ['fight-common.codeigniter-cache.value' => null]);
        $native->shouldReceive('get')->with('raw')->once()->andReturn('raw value');
        $native->shouldReceive('save')->with('value', ['fight-common.codeigniter-cache.value' => 'value'], 300)->once()->andReturnTrue();
        $native->shouldReceive('save')->with('nullable', ['fight-common.codeigniter-cache.value' => null], 300)->once()->andReturnTrue();

        $cache = new CodeIgniterCache($native);

        self::assertInstanceOf(MutableCache::class, $cache);
        self::assertSame('value', $cache->read('value', fn (): string => 'value', 300));
        self::assertSame('value', $cache->read('value', function (): never {
            self::fail('The loader must not run for a native cache hit.');
        }, 300));
        self::assertSame(null, $cache->read('nullable', fn (): null => null, 300));
        self::assertSame(null, $cache->read('nullable', function (): never {
            self::fail('The loader must not run for a cached null.');
        }, 300));
        self::assertSame('raw value', $cache->read('raw', function (): never {
            self::fail('The loader must not run for a legacy native cache hit.');
        }, 300));
    }

    public function test_that_read_passes_ttl_and_translates_save_failure(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('get')->once()->with('key')->andReturn(null);
        $native->shouldReceive('save')->once()->with('key', ['fight-common.codeigniter-cache.value' => 'value'], 42)->andReturnFalse();

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('CodeIgniter cache could not save the value.');

        (new CodeIgniterCache($native))->read('key', fn (): string => 'value', 42);
    }

    public function test_that_read_translates_native_failures(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('get')->once()->with('key')->andThrow(new Exception('native cache failure'));

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('native cache failure');

        (new CodeIgniterCache($native))->read('key', fn (): string => 'value', 60);
    }

    public function test_that_delete_translates_a_false_native_result(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('delete')->once()->with('missing')->andReturnFalse();
        $cache = new CodeIgniterCache($native);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('CodeIgniter cache could not delete the value.');

        $cache->delete('missing');
    }

    public function test_that_delete_preserves_a_fight_cache_failure(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('delete')->once()->with('key')->andThrow(new CacheException('known cache failure'));

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('known cache failure');

        (new CodeIgniterCache($native))->delete('key');
    }

    public function test_that_delete_translates_native_failures(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('delete')->once()->with('key')->andThrow(new Exception('native delete failure'));

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('native delete failure');

        (new CodeIgniterCache($native))->delete('key');
    }

    public function test_that_clear_translates_a_false_native_result(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('clean')->once()->andReturnFalse();

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('CodeIgniter cache could not clear the store.');

        (new CodeIgniterCache($native))->clear();
    }

    public function test_that_clear_translates_native_failures(): void
    {
        /** @var CacheInterface&MockInterface $native */
        $native = $this->mock(CacheInterface::class);
        $native->shouldReceive('clean')->once()->andThrow(new Exception('native clean failure'));

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('native clean failure');

        (new CodeIgniterCache($native))->clear();
    }
}
