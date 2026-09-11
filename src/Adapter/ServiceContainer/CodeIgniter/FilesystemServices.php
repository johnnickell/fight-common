<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\CodeIgniter;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Application\Filesystem\Filesystem;

/**
 * Class FilesystemServices
 */
final class FilesystemServices
{
    /**
     * Creates the Symfony filesystem fallback
     */
    public static function filesystem(): Filesystem
    {
        return new SymfonyFilesystem();
    }
}
