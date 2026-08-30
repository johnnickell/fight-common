<?php

declare(strict_types=1);

namespace Fight\Common\Application\Cache;

use Fight\Common\Application\Cache\Exception\CacheException;

/**
 * Interface MutableCache
 */
interface MutableCache extends Cache
{
    /**
     * Removes a value from cache
     *
     * @throws CacheException When an error occurs
     */
    public function delete(string $key): void;

    /**
     * Removes all values from cache
     *
     * @throws CacheException When an error occurs
     */
    public function clear(): void;
}
