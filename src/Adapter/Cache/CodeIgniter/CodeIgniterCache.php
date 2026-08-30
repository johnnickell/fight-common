<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Cache\CodeIgniter;

use CodeIgniter\Cache\CacheInterface;
use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Throwable;

/**
 * Class CodeIgniterCache
 */
final readonly class CodeIgniterCache implements MutableCache
{
    private const string VALUE = 'fight-common.codeigniter-cache.value';

    /**
     * Constructs CodeIgniterCache
     */
    public function __construct(private CacheInterface $cache)
    {
    }

    /** @inheritDoc */
    public function read(string $key, callable $loader, int $ttl): mixed
    {
        try {
            $value = $this->cache->get($key);

            if (is_array($value) && array_key_exists(self::VALUE, $value)) {
                return $value[self::VALUE];
            }

            if ($value !== null) {
                return $value;
            }

            $value = $loader();

            if (! $this->cache->save($key, [self::VALUE => $value], $ttl)) {
                throw new CacheException('CodeIgniter cache could not save the value.');
            }

            return $value;
        } catch (CacheException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CacheException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /** @inheritDoc */
    public function delete(string $key): void
    {
        try {
            if (! $this->cache->delete($key)) {
                throw new CacheException('CodeIgniter cache could not delete the value.');
            }
        } catch (CacheException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CacheException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /** @inheritDoc */
    public function clear(): void
    {
        try {
            if (! $this->cache->clean()) {
                throw new CacheException('CodeIgniter cache could not clear the store.');
            }
        } catch (CacheException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CacheException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
