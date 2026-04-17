<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Cache;

use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\Cache\Exception\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class PsrCache
 */
final readonly class PsrCache implements Cache
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
        } catch (Throwable $e) {
            throw new CacheException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
