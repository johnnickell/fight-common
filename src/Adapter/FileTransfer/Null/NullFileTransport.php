<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\FileTransfer\Null;

use Fight\Common\Application\FileTransfer\Transport\FileTransport;

/**
 * Class NullFileTransport
 */
final class NullFileTransport implements FileTransport
{
    /**
     * @inheritDoc
     */
    public function sendFile(string $path, mixed $contents): void
    {
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileContents(string $path): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileResource(string $path): mixed
    {
        return fopen('php://memory', 'r');
    }

    /**
     * @inheritDoc
     */
    public function readDirectory(string $directory): iterable
    {
        return [];
    }
}
