<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Filesystem;

use Fight\Common\Application\Filesystem\Exception\FileNotFoundException;
use Fight\Common\Application\Filesystem\Exception\FilesystemException;
use Fight\Common\Application\Filesystem\Filesystem as FilesystemInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * Class SymfonyFilesystem
 */
final readonly class SymfonyFilesystem implements FilesystemInterface
{
    private Filesystem $filesystem;

    /**
     * Constructs SymfonyFilesystem
     */
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    /**
     * @inheritDoc
     */
    public function mkdir(string|iterable $dirs, int $mode = 0775): void
    {
        try {
            $this->filesystem->mkdir($dirs, $mode);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function touch(string|iterable $files, ?int $time = null, ?int $atime = null): void
    {
        try {
            $this->filesystem->touch($files, $time, $atime);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function rename(string $origin, string $target, bool $override = false): void
    {
        try {
            $this->filesystem->rename($origin, $target, $override);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function symlink(string $origin, string $target, bool $copyOnWindows = false): void
    {
        try {
            $this->filesystem->symlink($origin, $target, $copyOnWindows);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function copy(string $originFile, string $targetFile, bool $override = false): void
    {
        if (stream_is_local($originFile) && !is_file($originFile)) {
            throw FileNotFoundException::fromPath($originFile);
        }

        try {
            $this->filesystem->copy($originFile, $targetFile, $override);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function mirror(
        string $originDir,
        string $targetDir,
        bool $override = false,
        bool $delete = false,
        bool $copyOnWindows = false
    ): void {
        $options = [
            'override'        => $override,
            'delete'          => $delete,
            'copy_on_windows' => $copyOnWindows
        ];

        try {
            $this->filesystem->mirror($originDir, $targetDir, null, $options);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string|iterable $paths): bool
    {
        return $this->filesystem->exists($paths);
    }

    /**
     * @inheritDoc
     */
    public function remove(string|iterable $paths): void
    {
        try {
            $this->filesystem->remove($paths);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): string
    {
        if (stream_is_local($path) && !is_file($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            $message = sprintf('Unable to read file content: %s', $path);
            throw new FilesystemException($message, $path);
        }

        return $content;
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, string $content): void
    {
        try {
            $this->filesystem->dumpFile($path, $content);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * @inheritDoc
     */
    public function isDir(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * @inheritDoc
     */
    public function isLink(string $path): bool
    {
        return is_link($path);
    }

    /**
     * @inheritDoc
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * @inheritDoc
     */
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(string $path): bool
    {
        return is_executable($path);
    }

    /**
     * @inheritDoc
     */
    public function isAbsolute(string $path): bool
    {
        return $this->filesystem->isAbsolutePath($path);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): int
    {
        if (!is_file($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        $timestamp = @filemtime($path);

        // @codeCoverageIgnoreStart
        if ($timestamp === false) {
            $message = sprintf('Unable to fetch last modified: %s', $path);
            throw new FilesystemException($message, $path);
        }
        // @codeCoverageIgnoreEnd

        return $timestamp;
    }

    /**
     * @inheritDoc
     */
    public function lastAccessed(string $path): int
    {
        if (!is_file($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        $timestamp = @fileatime($path);

        // @codeCoverageIgnoreStart
        if ($timestamp === false) {
            $message = sprintf('Unable to fetch last accessed: %s', $path);
            throw new FilesystemException($message, $path);
        }
        // @codeCoverageIgnoreEnd

        return $timestamp;
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): int
    {
        if (!is_file($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        $size = @filesize($path);

        // @codeCoverageIgnoreStart
        if ($size === false) {
            $message = sprintf('Unable to fetch file size: %s', $path);
            throw new FilesystemException($message, $path);
        }
        // @codeCoverageIgnoreEnd

        return $size;
    }

    /**
     * @inheritDoc
     */
    public function fileName(string $path): string
    {
        if (!file_exists($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        $parts = pathinfo($path);

        if (isset($parts['extension'])) {
            return sprintf('%s.%s', $parts['filename'], $parts['extension']);
        }

        return $parts['filename'];
    }

    /**
     * @inheritDoc
     */
    public function fileExt(string $path): string
    {
        if (!file_exists($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * @inheritDoc
     */
    public function dirName(string $path): string
    {
        if (!file_exists($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        return dirname($path);
    }

    /**
     * @inheritDoc
     */
    public function baseName(string $path, ?string $suffix = null): string
    {
        if (!file_exists($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        if ($suffix === null) {
            return basename($path);
        }

        return basename($path, $suffix);
    }

    /**
     * @inheritDoc
     */
    public function fileType(string $path): string
    {
        $type = @filetype($path);

        if ($type === false) {
            $message = sprintf('Unable to fetch file type: %s', $path);
            throw new FilesystemException($message, $path);
        }

        return $type;
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): string
    {
        if (!is_file($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        $mime = @finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);

        // @codeCoverageIgnoreStart
        if ($mime === false) {
            $message = sprintf('Unable to fetch mime type: %s', $path);
            throw new FilesystemException($message, $path);
        }
        // @codeCoverageIgnoreEnd

        return $mime;
    }

    /**
     * @inheritDoc
     */
    public function getReturn(string $path): mixed
    {
        if (!is_file($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        return require $path;
    }

    /**
     * @inheritDoc
     */
    public function requireOnce(string $path): void
    {
        if (!is_file($path)) {
            throw FileNotFoundException::fromPath($path);
        }

        require_once $path;
    }

    /**
     * @inheritDoc
     */
    public function chmod(string|iterable $paths, int $mode, int $umask = 0000, bool $recursive = false): void
    {
        try {
            $this->filesystem->chmod($paths, $mode, $umask, $recursive);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function chown(string|iterable $paths, string $user, bool $recursive = false): void
    {
        try {
            $this->filesystem->chown($paths, $user, $recursive);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function chgrp(string|iterable $paths, string $group, bool $recursive = false): void
    {
        try {
            $this->filesystem->chgrp($paths, $group, $recursive);
        } catch (IOException $e) {
            throw new FilesystemException($e->getMessage(), $e->getPath(), $e);
        } catch (Throwable $e) {
            throw new FilesystemException($e->getMessage(), null, $e);
        }
    }
}
