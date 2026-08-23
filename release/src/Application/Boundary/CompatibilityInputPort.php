<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface CompatibilityInputPort
 */
interface CompatibilityInputPort
{
    /**
     * Reads one compatibility input
     */
    public function read(string $path): string;

    /**
     * Checks whether one compatibility input is a regular file
     */
    public function isFile(string $path): bool;
}
