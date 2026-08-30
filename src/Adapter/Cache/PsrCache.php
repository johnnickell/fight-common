<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Cache;

use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class PsrCache
 */
final readonly class PsrCache implements MutableCache
{
    /**
     * Constructs PsrCache
     */
    public function __construct(private CacheItemPoolInterface $cachePool, private LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    public function read(string $key, callable $loader, int $ttl): mixed
    {
        try {
            $cacheItem = $this->cachePool->getItem($key);

            if (!$cacheItem->isHit()) {
                $this->logger->debug(sprintf('Cache MISS: "%s"', $key));

                $results = $loader();

                $cacheItem->set($results);
                $cacheItem->expiresAfter($ttl);

                $this->cachePool->save($cacheItem);
            } else {
                $this->logger->debug(sprintf('Cache HIT: "%s"', $key));
            }

            return $cacheItem->get();
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
            $this->cachePool->deleteItem($key);
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
            $this->cachePool->clear();
        } catch (Throwable $throwable) {
            throw new CacheException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
