<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\CodeIgniter;

use CodeIgniter\Cache\CacheInterface;
use Fight\Common\Adapter\Cache\CodeIgniter\CodeIgniterCache;
use Fight\Common\Application\Cache\MutableCache;

/**
 * Class CacheServices
 */
final class CacheServices
{
    /**
     * Creates a native CodeIgniter cache adapter
     */
    public static function cache(CacheInterface $cache): CodeIgniterCache
    {
        return new CodeIgniterCache($cache);
    }

    /**
     * Creates the cache through its neutral contract
     */
    public static function mutableCache(CacheInterface $cache): MutableCache
    {
        return self::cache($cache);
    }
}
