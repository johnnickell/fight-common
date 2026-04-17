<?php

declare(strict_types=1);

namespace Fight\Common\Application\FileStorage;

use Fight\Common\Application\FileStorage\Exception\DuplicateStorageException;
use Fight\Common\Application\FileStorage\Exception\FileStorageException;
use Fight\Common\Application\FileStorage\Exception\StorageNotFoundException;
use Fight\Common\Domain\Collection\HashTable;

/**
 * Class StorageService
 */
final readonly class StorageService
{
    private HashTable $storage;

    /**
     * Constructs StorageService
     */
    public function __construct()
    {
        $this->storage = HashTable::of('string', FileStorage::class);
    }

    /**
     * Retrieves file storage
     *
     * @throws StorageNotFoundException When the key is not found
     */
    public function getStorage(string $key): FileStorage
    {
        if (!$this->storage->has($key)) {
            throw StorageNotFoundException::fromKey($key);
        }

        return $this->storage->get($key);
    }

    /**
     * Copies a file across file storage instances
     *
     * @throws FileStorageException When an error occurs
     */
    public function copyStorageToStorage(
        string $sourceKey,
        string $sourcePath,
        string $destinationKey,
        string $destinationPath
    ): void {
        $this->getStorage($destinationKey)->putFile(
            $destinationPath,
            $this->getStorage($sourceKey)->getFileResource($sourcePath)
        );
    }

    /**
     * Moves a file across file storage instances
     *
     * @throws FileStorageException When an error occurs
     */
    public function moveStorageToStorage(
        string $sourceKey,
        string $sourcePath,
        string $destinationKey,
        string $destinationPath
    ): void {
        $this->getStorage($destinationKey)->putFile(
            $destinationPath,
            $this->getStorage($sourceKey)->getFileResource($sourcePath)
        );

        $this->getStorage($sourceKey)->removeFile($sourcePath);
    }

    /**
     * Adds file storage
     *
     * @throws DuplicateStorageException When the key is already in use
     */
    public function addStorage(string $key, FileStorage $storage): void
    {
        if ($this->storage->has($key)) {
            throw DuplicateStorageException::fromKey($key);
        }

        $this->storage->set($key, $storage);
    }
}
