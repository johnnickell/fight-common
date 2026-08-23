<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Fight\Release\Application\Boundary\CompatibilityWorkspacePort;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Class LocalCompatibilityWorkspace
 */
final readonly class LocalCompatibilityWorkspace implements CompatibilityWorkspacePort
{
    /**
     * Creates one private disposable compatibility workspace
     */
    public function createWorkspace(): string
    {
        $workspace = sys_get_temp_dir().'/fight-common-compatibility-'.bin2hex(random_bytes(16));
        $this->createDirectory($workspace);

        return $workspace;
    }

    /**
     * Creates one directory inside the disposable workspace
     */
    public function createDirectory(string $path): void
    {
        @mkdir($path, 0700, true) || throw new RuntimeException('The compatibility workspace is unavailable.');
    }

    /**
     * Removes one disposable compatibility workspace
     */
    public function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            assert($entry instanceof SplFileInfo);
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());

                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($path);
    }
}
