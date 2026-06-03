<?php

declare(strict_types=1);

namespace Fight\Common\Application\FileTransfer;

use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\KeyException;

/**
 * Class FileTransferService
 */
final readonly class FileTransferService
{
    /** @var HashTable<string, FileTransport> */
    private HashTable $transports;

    /**
     * Constructs FileTransferService
     */
    public function __construct()
    {
        $this->transports = HashTable::of('string', FileTransport::class);
    }

    /**
     * Retrieves a transport by key
     *
     * @throws KeyException When the transport is not found
     */
    public function getTransport(string $key): FileTransport
    {
        if (!$this->transports->has($key)) {
            throw new KeyException(sprintf('FileTransport "%s" not found', $key));
        }

        return $this->transports->get($key);
    }

    /**
     * Adds a file transport
     *
     * @throws FileTransferException When the key is already in use
     */
    public function addTransport(string $key, FileTransport $transport): void
    {
        if ($this->transports->has($key)) {
            throw new FileTransferException(sprintf('Duplicate transport: "%s"', $key));
        }

        $this->transports->set($key, $transport);
    }
}
