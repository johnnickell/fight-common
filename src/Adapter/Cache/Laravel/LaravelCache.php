<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Cache\Laravel;

use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Illuminate\Cache\Repository;
use stdClass;
use Throwable;

/**
 * Class LaravelCache
 */
final readonly class LaravelCache implements MutableCache
{
    private const string VALUE = 'fight-common.laravel-cache.value';

    /**
     * Constructs LaravelCache
     */
    public function __construct(private Repository $cache)
    {
    }

    /**
     * @inheritDoc
     */
    public function read(string $key, callable $loader, int $ttl): mixed
    {
        try {
            $cacheMiss = new stdClass();
            $value = $this->cache->get($key, $cacheMiss);

            if ($value === $cacheMiss) {
                $value = $loader();
                $this->cache->put($key, [self::VALUE => $value], $ttl);

                return $value;
            }

            if (is_array($value) && array_key_exists(self::VALUE, $value)) {
                return $value[self::VALUE];
            }

            return $value;
        } catch (Throwable $throwable) {
            throw new CacheException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        try {
            $this->cache->forget($key);
        } catch (Throwable $throwable) {
            throw new CacheException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        try {
            $this->cache->flush();
        } catch (Throwable $throwable) {
            throw new CacheException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
