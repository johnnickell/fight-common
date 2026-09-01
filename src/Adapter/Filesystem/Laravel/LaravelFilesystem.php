<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Filesystem\Laravel;

use Closure;
use ErrorException;
use Fight\Common\Application\Filesystem\Exception\FileNotFoundException;
use Fight\Common\Application\Filesystem\Exception\FilesystemException;
use Fight\Common\Application\Filesystem\Filesystem as FilesystemInterface;
use FilesystemIterator;
use Illuminate\Contracts\Filesystem\FileNotFoundException as LaravelFileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Class LaravelFilesystem
 *
 * Adapts Laravel's local filesystem and PHP filesystem primitives to the complete Fight contract.
 */
final readonly class LaravelFilesystem implements FilesystemInterface
{
    private Filesystem $filesystem;
    private bool $windows;

    /**
     * Constructs LaravelFilesystem
     */
    public function __construct(?Filesystem $filesystem = null, ?bool $windows = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->windows = $windows ?? PHP_OS_FAMILY === 'Windows';
    }

    /**
     * @inheritDoc
     */
    public function mkdir(string|iterable $dirs, int $mode = 0775): void
    {
        foreach ($this->paths($dirs) as $dir) {
            $this->execute($dir, function () use ($dir, $mode): void {
                if ($this->filesystem->isDirectory($dir)) {
                    return;
                }

                $this->successful($this->filesystem->makeDirectory($dir, $mode, true, true), 'create', $dir);
            });
        }
    }

    /**
     * @inheritDoc
     */
    public function touch(string|iterable $files, ?int $time = null, ?int $atime = null): void
    {
        foreach ($this->paths($files) as $file) {
            $this->execute($file, function () use ($file, $time, $atime): void {
                if ($time === null && $atime === null) {
                    $result = @touch($file);
                } else {
                    $modified = $time ?? time();
                    $result = @touch($file, $modified, $atime ?? $modified);
                }

                $this->successful($result, 'touch', $file);
            });
        }
    }

    /**
     * @inheritDoc
     */
    public function rename(string $origin, string $target, bool $override = false): void
    {
        $this->execute($origin, function () use ($origin, $target, $override): void {
            if (!$this->filesystem->exists($origin) && !$this->isLink($origin)) {
                throw FileNotFoundException::fromPath($origin);
            }

            if ($this->filesystem->exists($target) || $this->isLink($target)) {
                if (!$override) {
                    throw new FilesystemException(sprintf('Target already exists: %s', $target), $target);
                }

                $backup = $target.'.fight-backup-'.bin2hex(random_bytes(8));
                $this->successful($this->filesystem->move($target, $backup), 'stage', $target);

                try {
                    $this->successful($this->filesystem->move($origin, $target), 'rename', $origin);
                } catch (Throwable $exception) {
                    $this->successful($this->filesystem->move($backup, $target), 'restore', $target);

                    throw $exception;
                }

                $this->remove($backup);

                return;
            }

            $this->successful($this->filesystem->move($origin, $target), 'rename', $origin);
        });
    }

    /**
     * @inheritDoc
     */
    public function symlink(string $origin, string $target, bool $copyOnWindows = false): void
    {
        $this->execute($origin, function () use ($origin, $target, $copyOnWindows): void {
            $copy = $copyOnWindows && $this->windows;
            $this->mkdir(dirname($target));

            if ($copy && $this->filesystem->isDirectory($origin)) {
                $result = $this->filesystem->copyDirectory($origin, $target);
            } elseif ($copy) {
                $result = $this->filesystem->copy($origin, $target);
            } else {
                if ($this->isLink($target)) {
                    $existingOrigin = @readlink($target);
                    $this->successful($existingOrigin, 'read link', $target);

                    if ($existingOrigin === $origin) {
                        return;
                    }

                    $this->remove($target);
                }

                $result = @symlink($origin, $target);
            }

            $this->successful($result, 'link', $origin);
        });
    }

    /**
     * @inheritDoc
     */
    public function copy(string $originFile, string $targetFile, bool $override = false): void
    {
        if (stream_is_local($originFile) && !$this->filesystem->isFile($originFile)) {
            throw FileNotFoundException::fromPath($originFile);
        }

        $this->execute($originFile, function () use ($originFile, $targetFile, $override): void {
            $this->mkdir(dirname($targetFile));

            if (
                !$override && $this->filesystem->isFile($targetFile)
                && $this->filesystem->lastModified($originFile) <= $this->filesystem->lastModified($targetFile)
            ) {
                return;
            }

            $this->successful($this->filesystem->copy($originFile, $targetFile), 'copy', $originFile);

            if (stream_is_local($originFile)) {
                $mode = fileperms($originFile);
                $modified = $this->filesystem->lastModified($originFile);
                $this->successful($mode !== false, 'read permissions for', $originFile);
                $this->successful($this->filesystem->chmod($targetFile, $mode & 0777 & ~umask()), 'chmod', $targetFile);
                $this->successful(@touch($targetFile, $modified), 'touch', $targetFile);
            }
        });
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
        $originDir = $this->trimDirectoryPath($originDir);
        $targetDir = $this->trimDirectoryPath($targetDir);

        $this->execute($originDir, function () use (
            $originDir,
            $targetDir,
            $override,
            $delete,
            $copyOnWindows
        ): void {
            if (!$this->filesystem->isDirectory($originDir)) {
                throw FileNotFoundException::fromPath($originDir);
            }

            if ($delete && $this->filesystem->isDirectory($targetDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($targetDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $file) {
                    $origin = $originDir.substr($file->getPathname(), strlen($targetDir));

                    if (!$this->filesystem->exists($origin)) {
                        $this->remove($file->getPathname());
                    }
                }
            }

            $followLinks = $copyOnWindows && $this->windows;
            $flags = FilesystemIterator::SKIP_DOTS | ($followLinks ? FilesystemIterator::FOLLOW_SYMLINKS : 0);
            $windows = $this->windows;
            $normalize = static function (string $path) use ($windows): string {
                $normalized = rtrim(str_replace('\\', '/', $path), '/');

                return $windows ? strtolower($normalized) : $normalized;
            };
            $normalizedTarget = $normalize($targetDir);
            $nestedTargetPrefix = $normalizedTarget.'/';
            $joinSeparator = $this->windows && str_contains($targetDir, '\\') ? '\\' : '/';
            $targetJoinPrefix = $targetDir;
            if (!str_ends_with($targetJoinPrefix, '/') && !str_ends_with($targetJoinPrefix, '\\')) {
                $targetJoinPrefix .= $joinSeparator;
            }

            $directory = new RecursiveDirectoryIterator($originDir, $flags);
            $filtered = new RecursiveCallbackFilterIterator(
                $directory,
                static function (SplFileInfo $file) use ($normalize, $normalizedTarget, $nestedTargetPrefix): bool {
                    $path = $normalize($file->getPathname());

                    return $path !== $normalizedTarget && !str_starts_with($path, $nestedTargetPrefix);
                }
            );
            $iterator = new RecursiveIteratorIterator(
                $filtered,
                RecursiveIteratorIterator::SELF_FIRST
            );
            $this->mkdir($targetDir);

            foreach ($iterator as $file) {
                $path = $file->getPathname();
                $target = $targetJoinPrefix.ltrim(substr($path, strlen($originDir)), '/\\');

                if (!$followLinks && $file->isLink()) {
                    $this->symlink($file->getLinkTarget(), $target);
                } elseif ($file->isDir()) {
                    $this->mkdir($target);
                } elseif ($file->isFile()) {
                    $this->copy($path, $target, $override);
                } else {
                    throw new FilesystemException(sprintf('Unknown file type: %s', $path), $originDir);
                }
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function exists(string|iterable $paths): bool
    {
        foreach ($this->paths($paths) as $path) {
            if (!$this->filesystem->exists($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function remove(string|iterable $paths): void
    {
        foreach ($this->paths($paths) as $path) {
            $this->execute($path, function () use ($path): void {
                if ($this->isLink($path) || $this->filesystem->isFile($path)) {
                    $this->successful($this->filesystem->delete($path), 'remove', $path);
                } elseif ($this->filesystem->isDirectory($path)) {
                    $this->successful($this->filesystem->deleteDirectory($path), 'remove', $path);

                    if ($this->filesystem->isDirectory($path)) {
                        throw new FilesystemException(sprintf('Unable to remove path: %s', $path), $path);
                    }
                }
            });
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): string
    {
        return $this->execute($path, fn (): string => $this->filesystem->get($path));
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, string $content): void
    {
        $this->execute($path, function () use ($path, $content): void {
            $this->mkdir(dirname($path));
            $this->successful($this->filesystem->put($path, $content), 'write', $path);
        });
    }

    /**
     * @inheritDoc
     */
    public function isFile(string $path): bool
    {
        return $this->filesystem->isFile($path);
    }

    /**
     * @inheritDoc
     */
    public function isDir(string $path): bool
    {
        return $this->filesystem->isDirectory($path);
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
        return $this->filesystem->isReadable($path);
    }

    /**
     * @inheritDoc
     */
    public function isWritable(string $path): bool
    {
        return $this->filesystem->isWritable($path);
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
        if ($path === '') {
            return false;
        }

        if ((str_contains($path, '://') && parse_url($path, PHP_URL_SCHEME) !== null) || $path[0] === '/') {
            return true;
        }

        return $this->windows
            && ($path[0] === '\\' || preg_match('/^[A-Za-z]:(?:[\\\\\/]|$)/', $path) === 1);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): int
    {
        $this->assertFile($path);

        return $this->execute($path, fn (): int => $this->filesystem->lastModified($path));
    }

    /**
     * @inheritDoc
     */
    public function lastAccessed(string $path): int
    {
        $this->assertFile($path);

        return $this->execute($path, function () use ($path): int {
            $accessed = @fileatime($path);
            $this->successful($accessed, 'read access time for', $path);

            return $accessed;
        });
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): int
    {
        $this->assertFile($path);

        return $this->execute($path, fn (): int => $this->filesystem->size($path));
    }

    /**
     * @inheritDoc
     */
    public function fileName(string $path): string
    {
        $this->assertExists($path);

        return $this->filesystem->basename($path);
    }

    /**
     * @inheritDoc
     */
    public function fileExt(string $path): string
    {
        $this->assertExists($path);

        return $this->filesystem->extension($path);
    }

    /**
     * @inheritDoc
     */
    public function dirName(string $path): string
    {
        $this->assertExists($path);

        return $this->filesystem->dirname($path);
    }

    /**
     * @inheritDoc
     */
    public function baseName(string $path, ?string $suffix = null): string
    {
        $this->assertExists($path);

        return $suffix === null ? $this->filesystem->basename($path) : basename($path, $suffix);
    }

    /**
     * @inheritDoc
     */
    public function fileType(string $path): string
    {
        return $this->execute($path, function () use ($path): string {
            $type = $this->filesystem->type($path);
            $this->successful($type, 'read type for', $path);

            return $type;
        });
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): string
    {
        $this->assertFile($path);

        return $this->execute($path, function () use ($path): string {
            $type = $this->filesystem->mimeType($path);
            $this->successful($type, 'read MIME type for', $path);

            return $type;
        });
    }

    /**
     * @inheritDoc
     */
    public function getReturn(string $path): mixed
    {
        return $this->execute($path, fn (): mixed => $this->filesystem->getRequire($path));
    }

    /**
     * @inheritDoc
     */
    public function requireOnce(string $path): void
    {
        $this->execute($path, fn (): mixed => $this->filesystem->requireOnce($path));
    }

    /**
     * @inheritDoc
     */
    public function chmod(string|iterable $paths, int $mode, int $umask = 0000, bool $recursive = false): void
    {
        $this->modify($paths, $recursive, function (string $path) use ($mode, $umask): void {
            if ($this->isLink($path)) {
                return;
            }

            $this->successful($this->filesystem->chmod($path, $mode & ~$umask), 'chmod', $path);
        });
    }

    /**
     * @inheritDoc
     */
    public function chown(string|iterable $paths, string $user, bool $recursive = false): void
    {
        $owner = ctype_digit($user) ? (int) $user : $user;
        $this->modify($paths, $recursive, function (string $path) use ($owner): void {
            if ($this->isLink($path) && function_exists('lchown')) {
                $result = @lchown($path, $owner);
            } else {
                $result = @chown($path, $owner);
            }

            $this->successful($result, 'chown', $path);
        });
    }

    /**
     * @inheritDoc
     */
    public function chgrp(string|iterable $paths, string $group, bool $recursive = false): void
    {
        $selectedGroup = ctype_digit($group) ? (int) $group : $group;
        $this->modify($paths, $recursive, function (string $path) use ($selectedGroup): void {
            if ($this->isLink($path) && function_exists('lchgrp')) {
                $result = @lchgrp($path, $selectedGroup);
            } else {
                $result = @chgrp($path, $selectedGroup);
            }

            $this->successful($result, 'chgrp', $path);
        });
    }

    /**
     * Returns one or more selected paths
     *
     * @param string|iterable<string> $paths Selected paths.
     *
     * @return iterable<string>
     */
    private function paths(string|iterable $paths): iterable
    {
        if (is_string($paths)) {
            yield $paths;

            return;
        }

        yield from $paths;
    }

    /**
     * Removes trailing separators without collapsing a filesystem root
     */
    private function trimDirectoryPath(string $path): string
    {
        $isRoot = preg_match('/^(?:(?:[A-Za-z]:)?[\\\\\/]+|[A-Za-z][A-Za-z0-9+.-]*:\/\/[\\\\\/]*)$/', $path) === 1;

        return $isRoot ? $path : rtrim($path, '/\\');
    }

    /**
     * Applies a metadata mutation to selected paths and optional descendants
     *
     * @param string|iterable<string> $paths     Selected paths.
     * @param boolean                 $recursive Whether to include descendants.
     * @param Closure                 $operation Metadata operation.
     *
     * @phpstan-param Closure(string): void $operation
     */
    private function modify(string|iterable $paths, bool $recursive, Closure $operation): void
    {
        foreach ($this->paths($paths) as $path) {
            $this->execute($path, function () use ($path, $recursive, $operation): void {
                if ($recursive && $this->filesystem->isDirectory($path) && !$this->isLink($path)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );

                    foreach ($iterator as $entry) {
                        $operation($entry->getPathname());
                    }
                }

                $operation($path);
            });
        }
    }

    /**
     * Requires a native filesystem operation to succeed
     */
    private function successful(mixed $result, string $operation, string $path): void
    {
        if ($result === false) {
            throw new FilesystemException(sprintf('Unable to %s path: %s', $operation, $path), $path);
        }
    }

    /**
     * Requires an existing regular file
     */
    private function assertFile(string $path): void
    {
        if (!$this->filesystem->isFile($path)) {
            throw FileNotFoundException::fromPath($path);
        }
    }

    /**
     * Requires an existing filesystem path
     */
    private function assertExists(string $path): void
    {
        if (!$this->filesystem->exists($path)) {
            throw FileNotFoundException::fromPath($path);
        }
    }

    /**
     * Executes one native operation through Fight's exception boundary
     *
     * @param string  $path      Selected path.
     * @param Closure $operation Native operation.
     *
     * @phpstan-param Closure(): T $operation
     *
     * @template T
     *
     * @return T
     */
    private function execute(string $path, Closure $operation): mixed
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
            E_WARNING | E_NOTICE
        );

        try {
            try {
                return $operation();
            } catch (LaravelFileNotFoundException $exception) {
                throw FileNotFoundException::fromPath($path, $exception);
            } catch (FilesystemException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new FilesystemException($exception->getMessage(), $path, $exception);
            }
        } finally {
            restore_error_handler();
        }
    }
}
