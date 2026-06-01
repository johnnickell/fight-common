<?php

declare(strict_types=1);

namespace Fight\Common\Application\Filesystem\Exception;

use Throwable;

/**
 * Class FileNotFoundException
 *
 * @phpstan-consistent-constructor
 */
class FileNotFoundException extends FilesystemException
{
    /**
     * Creates exception for a given path
     *
     * @return static
     */
    public static function fromPath(string $path, ?Throwable $previous = null): static
    {
        $message = sprintf('File not found: %s', $path);

        return new static($message, $path, $previous);
    }
}
