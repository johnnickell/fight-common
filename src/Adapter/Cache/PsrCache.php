<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Cache;

use Fight\Common\Adapter\Cache\Psr6\Psr6Cache;
use Fight\Common\Application\Cache\MutableCache;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PsrCache
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\Cache\Psr6\Psr6Cache. This compatibility path will be
 *             removed in 2.0.0.
 */
final readonly class PsrCache implements MutableCache
{
    private Psr6Cache $cache;

    /**
     * Constructs PsrCache
     */
    public function __construct(CacheItemPoolInterface $cachePool, LoggerInterface $logger)
    {
        $this->cache = new Psr6Cache($cachePool, $logger);
    }

    /**
     * @inheritDoc
     */
    public function read(string $key, callable $loader, int $ttl): mixed
    {
        return $this->cache->read($key, $loader, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        $this->cache->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->cache->clear();
    }
}
