<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Cache\Psr16;

use Fight\Common\Application\Cache\Exception\CacheException;
use Fight\Common\Application\Cache\MutableCache;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use stdClass;
use Throwable;

/**
 * Class Psr16Cache
 */
final readonly class Psr16Cache implements MutableCache
{
    /**
     * Constructs Psr16Cache
     */
    public function __construct(private CacheInterface $simpleCache, private LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    public function read(string $key, callable $loader, int $ttl): mixed
    {
        try {
            $cacheMiss = new stdClass();
            $value = $this->simpleCache->get($key, $cacheMiss);

            if ($value === $cacheMiss) {
                $this->logger->debug(sprintf('Cache MISS: "%s"', $key));

                $value = $loader();
                $this->simpleCache->set($key, $value, $ttl);
            } else {
                $this->logger->debug(sprintf('Cache HIT: "%s"', $key));
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
            $this->simpleCache->delete($key);
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
            $this->simpleCache->clear();
        } catch (Throwable $throwable) {
            throw new CacheException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
