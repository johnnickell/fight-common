<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\FilesystemServices;
use Fight\Common\Application\Filesystem\Filesystem;

/** Project-owned filesystem-only Config\Services fixture. */
final class Services extends BaseService
{
    public static function fightFilesystem(bool $getShared = true): Filesystem
    {
        if ($getShared) {
            return static::getSharedInstance('fightFilesystem');
        }

        return FilesystemServices::filesystem();
    }
}
