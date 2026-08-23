<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Fight\Release\Application\Boundary\CompatibilityInputPort;
use UnexpectedValueException;

/**
 * Class LocalCompatibilityInput
 */
final readonly class LocalCompatibilityInput implements CompatibilityInputPort
{
    /**
     * Reads one compatibility input
     */
    public function read(string $path): string
    {
        $contents = file_get_contents($path);
        is_string($contents) || throw new UnexpectedValueException('A compatibility input is unreadable.');

        return $contents;
    }

    /**
     * Checks whether one compatibility input is a regular file
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }
}
