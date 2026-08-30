<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Config\BaseService;
use Fight\Common\Adapter\Cache\CodeIgniter\CodeIgniterCache;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\CacheServices;
use Fight\Common\Application\Cache\MutableCache;
use RuntimeException;

/** Project-owned cache-only Config\Services fixture. */
final class Services extends BaseService
{
    public static function fightCodeIgniterCache(bool $getShared = true): CodeIgniterCache
    {
        if ($getShared) {
            return static::getSharedInstance('fightCodeIgniterCache');
        }

        return CacheServices::cache(static::fightCache());
    }

    public static function fightMutableCache(bool $getShared = true): MutableCache
    {
        return static::fightCodeIgniterCache($getShared);
    }

    private static function fightCache(): CacheInterface
    {
        $cache = static::get('fightCacheCollaborator');

        if (! $cache instanceof CacheInterface) {
            throw new RuntimeException('The project cache collaborator is unavailable.');
        }

        return $cache;
    }
}
