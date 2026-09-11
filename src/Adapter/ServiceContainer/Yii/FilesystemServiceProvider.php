<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Application\Filesystem\Filesystem;
use Yiisoft\Di\ServiceProviderInterface;

/**
 * Class FilesystemServiceProvider
 *
 * Registers the complete Symfony Filesystem fallback without owning application paths or policy.
 */
final class FilesystemServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns the complete filesystem definition without boot side effects
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [Filesystem::class => ['class' => SymfonyFilesystem::class]];
    }

    /**
     * Returns no filesystem extensions
     *
     * @return array<string, callable>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
