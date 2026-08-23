<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface CompatibilityWorkspacePort
 */
interface CompatibilityWorkspacePort
{
    /**
     * Creates one private disposable compatibility workspace
     */
    public function createWorkspace(): string;

    /**
     * Creates one directory inside the disposable workspace
     */
    public function createDirectory(string $path): void;

    /**
     * Removes one disposable compatibility workspace
     */
    public function remove(string $path): void;
}
