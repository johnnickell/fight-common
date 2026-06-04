<?php

declare(strict_types=1);

namespace Fight\Common\Application\FileTransfer\Transport;

use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Application\FileTransfer\Resource\Resource;

/**
 * Interface FileTransport
 */
interface FileTransport
{
    /**
     * Sends a file
     *
     * @throws FileTransferException When an error occurs
     */
    public function sendFile(string $path, mixed $contents): void;

    /**
     * Retrieves file contents as a string
     *
     * @throws FileTransferException When an error occurs
     */
    public function retrieveFileContents(string $path): string;

    /**
     * Retrieves file contents as a stream resource
     *
     * @throws FileTransferException When an error occurs
     */
    public function retrieveFileResource(string $path): mixed;

    /**
     * Reads the contents of a directory
     *
     * @return iterable<Resource>
     *
     * @throws FileTransferException When an error occurs
     */
    public function readDirectory(string $directory): iterable;
}
