<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\FileStorage;

use DateTimeImmutable;
use Fight\Common\Application\FileStorage\Exception\FileStorageException;
use Fight\Common\Application\FileStorage\FileStorage as FileStorageInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Throwable;

final readonly class FlysystemStorage implements FileStorageInterface
{
    public function __construct(private FilesystemOperator $filesystem)
    {
    }

    /**
     * @inheritDoc
     */
    public function putFile(string $path, mixed $contents): void
    {
        $this->ensureDirectoryExists($path);

        try {
            if (is_string($contents)) {
                $this->filesystem->write($path, $contents);
            } elseif (is_resource($contents)) {
                $this->filesystem->writeStream($path, $contents);
            } else {
                throw new FileStorageException('File contents must be a string or resource');
            }
        } catch (FileStorageException $e) {
            throw $e;
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getFileContents(string $path): string
    {
        try {
            return $this->filesystem->read($path);
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getFileResource(string $path): mixed
    {
        try {
            return $this->filesystem->readStream($path);
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function hasFile(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function removeFile(string $path): void
    {
        try {
            $this->filesystem->delete($path);
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function copyFile(string $source, string $destination): void
    {
        $this->ensureDirectoryExists($destination);

        try {
            $this->filesystem->copy($source, $destination);
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function moveFile(string $source, string $destination): void
    {
        $this->ensureDirectoryExists($destination);

        try {
            $this->filesystem->move($source, $destination);
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): int
    {
        try {
            return $this->filesystem->fileSize($path);
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable('@' . $this->filesystem->lastModified($path));
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function listFiles(?string $path = null): array
    {
        return $this->doListFiles($path ?? '', false);
    }

    /**
     * @inheritDoc
     */
    public function listFilesRecursively(?string $path = null): array
    {
        return $this->doListFiles($path ?? '', true);
    }

    /**
     * @inheritDoc
     */
    public function listDirectories(?string $path = null): array
    {
        return $this->doListDirectories($path ?? '', false);
    }

    /**
     * @inheritDoc
     */
    public function listDirectoriesRecursively(?string $path = null): array
    {
        return $this->doListDirectories($path ?? '', true);
    }

    /**
     * @throws FileStorageException
     */
    private function doListFiles(string $path, bool $deep): array
    {
        try {
            return $this->filesystem->listContents($path, $deep)
                ->filter(fn (StorageAttributes $item) => $item->isFile())
                ->map(fn (StorageAttributes $item) => $item->path())
                ->toArray();
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws FileStorageException
     */
    private function doListDirectories(string $path, bool $deep): array
    {
        try {
            return $this->filesystem->listContents($path, $deep)
                ->filter(fn (StorageAttributes $item) => $item->isDir())
                ->map(fn (StorageAttributes $item) => $item->path())
                ->toArray();
        } catch (FilesystemException|Throwable $e) {
            throw new FileStorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws FilesystemException
     */
    private function ensureDirectoryExists(string $path): void
    {
        $dirname = dirname($path);

        if ($dirname !== '.' && $dirname !== '') {
            $this->filesystem->createDirectory($dirname);
        }
    }
}
