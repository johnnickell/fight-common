# Files

Two complementary file-abstraction components. **FileStorage** provides an abstract interface
for writing, reading, and listing files on any storage backend (local disk, S3, FTP, etc.)
via Flysystem adapters. **Filesystem** operates on the local OS filesystem — creating
directories, changing permissions, reading metadata, and requiring PHP scripts.

```
Application\FileStorage
├── FileStorage (interface)          — 13 methods (putFile, getFileContents, listFiles, …)
├── StorageService                   — Registry of named FileStorage instances
└── Exception\
    ├── FileStorageException
    ├── StorageNotFoundException     — static fromKey(string $key)
    └── DuplicateStorageException    — static fromKey(string $key)

Application\Filesystem
├── Filesystem (interface)           — 31 methods (mkdir, copy, chmod, mimeType, …)
└── Exception\
    ├── FilesystemException          — getPath(): ?string
    └── FileNotFoundException        — static fromPath(string $path)

Adapter\FileStorage
└── FlysystemStorage                — FileStorage → League\Flysystem\FilesystemOperator

Adapter\Filesystem
└── SymfonyFilesystem               — Filesystem → Symfony\Component\Filesystem\Filesystem
```

---

## Table of Contents

1. [FileStorage (Interface)](#filestorage-interface)
2. [StorageService (Registry)](#storageservice-registry)
3. [FlysystemStorage](#flysystemstorage)
4. [FileStorage Exceptions](#filestorage-exceptions)
5. [Filesystem (Interface)](#filesystem-interface)
6. [SymfonyFilesystem](#symfonyfilesystem)
7. [Filesystem Exceptions](#filesystem-exceptions)
8. [Installation](#installation)
9. [Symfony Configuration](#symfony-configuration)
10. [Usage Examples](#usage-examples)

---

## FileStorage (Interface)

`Fight\Common\Application\FileStorage\FileStorage`

A generic file-store abstraction. Every method throws `FileStorageException` on failure.

| Method | Returns | Description |
|---|---|---|
| `putFile(string $path, mixed $contents)` | `void` | Write string or stream resource to a path |
| `getFileContents(string $path)` | `string` | Read file contents as a string |
| `getFileResource(string $path)` | `resource` | Read file contents as a stream resource |
| `hasFile(string $path)` | `bool` | Check whether a file exists |
| `removeFile(string $path)` | `void` | Delete a file |
| `copyFile(string $source, string $destination)` | `void` | Copy a file within storage |
| `moveFile(string $source, string $destination)` | `void` | Move (rename) a file within storage |
| `size(string $path)` | `int` | File size in bytes |
| `lastModified(string $path)` | `DateTimeImmutable` | Last modification timestamp |
| `listFiles(?string $path)` | `array` | List files in a directory (non-recursive) |
| `listFilesRecursively(?string $path)` | `array` | List all files in a directory tree |
| `listDirectories(?string $path)` | `array` | List subdirectories (non-recursive) |
| `listDirectoriesRecursively(?string $path)` | `array` | List all subdirectories recursively |

```php
interface FileStorage
{
    public function putFile(string $path, mixed $contents): void;
    public function getFileContents(string $path): string;
    public function getFileResource(string $path): mixed;
    public function hasFile(string $path): bool;
    public function removeFile(string $path): void;
    public function copyFile(string $source, string $destination): void;
    public function moveFile(string $source, string $destination): void;
    public function size(string $path): int;
    public function lastModified(string $path): DateTimeImmutable;
    public function listFiles(?string $path = null): array;
    public function listFilesRecursively(?string $path = null): array;
    public function listDirectories(?string $path = null): array;
    public function listDirectoriesRecursively(?string $path = null): array;
}
```

---

## StorageService (Registry)

`Fight\Common\Application\FileStorage\StorageService`

A `final readonly` registry of named `FileStorage` instances, backed by a
`HashTable<string, FileStorage>`. Enables cross-storage file operations.

| Method | Returns | Description |
|---|---|---|
| `addStorage(string $key, FileStorage $storage)` | `void` | Register a storage under a name |
| `getStorage(string $key)` | `FileStorage` | Retrieve a named storage |
| `copyStorageToStorage(string $sourceKey, string $sourcePath, string $destinationKey, string $destinationPath)` | `void` | Copy a file from one storage to another |
| `moveStorageToStorage(string $sourceKey, string $sourcePath, string $destinationKey, string $destinationPath)` | `void` | Move a file from one storage to another (copy + remove source) |

```php
final readonly class StorageService
{
    public function __construct() {}

    public function addStorage(string $key, FileStorage $storage): void;
    public function getStorage(string $key): FileStorage;
    public function copyStorageToStorage(string $sourceKey, string $sourcePath, string $destinationKey, string $destinationPath): void;
    public function moveStorageToStorage(string $sourceKey, string $sourcePath, string $destinationKey, string $destinationPath): void;
}
```

`addStorage()` throws `DuplicateStorageException` if the key already exists.
`getStorage()` throws `StorageNotFoundException` if the key is not registered.
`copyStorageToStorage()` and `moveStorageToStorage()` throw `FileStorageException`
on transport failure.

```php
$service = new StorageService();
$service->addStorage('local', $localStorage);
$service->addStorage('s3', $s3Storage);

// Copy a file from local disk to S3
$service->copyStorageToStorage('local', '/tmp/photo.jpg', 's3', 'photos/photo.jpg');

// Move (copy + delete source)
$service->moveStorageToStorage('local', '/tmp/invoice.pdf', 's3', 'invoices/invoice.pdf');
```

---

## FlysystemStorage

`Fight\Common\Adapter\FileStorage\FlysystemStorage`

Wraps a `League\Flysystem\FilesystemOperator`. This is the sole adapter implementation.

```php
final readonly class FlysystemStorage implements FileStorage
{
    public function __construct(private FilesystemOperator $filesystem) {}
}
```

| Method | Delegation | Notable behavior |
|---|---|---|
| `putFile()` | `write()` / `writeStream()` | Accepts string or resource; auto-creates parent directory |
| `getFileContents()` | `read()` | |
| `getFileResource()` | `readStream()` | |
| `hasFile()` | `fileExists()` | |
| `removeFile()` | `delete()` | |
| `copyFile()` | `createDirectory()` + `copy()` | Ensures destination parent exists |
| `moveFile()` | `createDirectory()` + `move()` | Ensures destination parent exists |
| `size()` | `fileSize()` | |
| `lastModified()` | `lastModified()` → `DateTimeImmutable` | Converts Unix timestamp |
| `listFiles()` | `listContents()` | Filters `FileAttributes`, returns path list |
| `listFilesRecursively()` | `listContents(deep: true)` | Filters `FileAttributes` |
| `listDirectories()` | `listContents()` | Filters `DirectoryAttributes` |
| `listDirectoriesRecursively()` | `listContents(deep: true)` | Filters `DirectoryAttributes` |

All methods catch `League\Flysystem\FilesystemException` and wrap it in `FileStorageException`.

---

## FileStorage Exceptions

```
SystemException
└── FileStorageException
    ├── StorageNotFoundException
    └── DuplicateStorageException
```

`Fight\Common\Application\FileStorage\Exception\FileStorageException`

```php
class FileStorageException extends SystemException {}
```

---

`Fight\Common\Application\FileStorage\Exception\StorageNotFoundException`

```php
class StorageNotFoundException extends FileStorageException
{
    public function __construct(string $message, ?string $key = null, ?Throwable $previous = null);
    public static function fromKey(string $key, ?Throwable $previous = null): static;
    public function getKey(): ?string;
}
```

---

`Fight\Common\Application\FileStorage\Exception\DuplicateStorageException`

```php
class DuplicateStorageException extends FileStorageException
{
    public function __construct(string $message, ?string $key = null, ?Throwable $previous = null);
    public static function fromKey(string $key, ?Throwable $previous = null): static;
    public function getKey(): ?string;
}
```

---

## Filesystem (Interface)

`Fight\Common\Application\Filesystem\Filesystem`

A comprehensive interface for local OS filesystem operations. Methods that access file
contents or metadata throw `FilesystemException` (or `FileNotFoundException`) on failure.

### Create / Modify / Delete

| Method | Signature | Description |
|---|---|---|
| `mkdir` | `(string\|iterable $dirs, int $mode = 0775)` | Create directories recursively |
| `touch` | `(string\|iterable $files, ?int $time, ?int $atime)` | Set access and modification times |
| `rename` | `(string $origin, string $target, bool $override = false)` | Rename a file or directory |
| `symlink` | `(string $origin, string $target, bool $copyOnWindows = false)` | Create a symbolic link |
| `copy` | `(string $originFile, string $targetFile, bool $override = false)` | Copy a file |
| `mirror` | `(string $originDir, string $targetDir, bool $override = false, bool $delete = false, bool $copyOnWindows = false)` | Mirror a directory tree |
| `remove` | `(string\|iterable $paths)` | Delete files or directories |
| `put` | `(string $path, string $content)` | Write file contents |

### Read

| Method | Signature | Returns | Description |
|---|---|---|---|
| `get` | `(string $path)` | `string` | Read file contents |
| `getReturn` | `(string $path)` | `mixed` | `require` a PHP script, return its value |
| `requireOnce` | `(string $path)` | `void` | `require_once` a PHP script |
| `fileType` | `(string $path)` | `string` | File type (file, dir, link, etc.) |
| `mimeType` | `(string $path)` | `string` | MIME type via `finfo` |

### Queries

| Method | Signature | Returns | Description |
|---|---|---|---|
| `exists` | `(string\|iterable $paths)` | `bool` | Check if paths exist |
| `isFile` | `(string $path)` | `bool` | Check if path is a regular file |
| `isDir` | `(string $path)` | `bool` | Check if path is a directory |
| `isLink` | `(string $path)` | `bool` | Check if path is a symbolic link |
| `isReadable` | `(string $path)` | `bool` | Check if path is readable |
| `isWritable` | `(string $path)` | `bool` | Check if path is writable |
| `isExecutable` | `(string $path)` | `bool` | Check if path is executable |
| `isAbsolute` | `(string $path)` | `bool` | Check if path is absolute |

### Metadata

| Method | Signature | Returns | Description |
|---|---|---|---|
| `lastModified` | `(string $path)` | `int` | Last modified Unix timestamp |
| `lastAccessed` | `(string $path)` | `int` | Last accessed Unix timestamp |
| `fileSize` | `(string $path)` | `int` | File size in bytes |

### Path Info

| Method | Signature | Returns | Description |
|---|---|---|---|
| `fileName` | `(string $path)` | `string` | File name with extension |
| `fileExt` | `(string $path)` | `string` | File extension only |
| `dirName` | `(string $path)` | `string` | Parent directory path |
| `baseName` | `(string $path, ?string $suffix)` | `string` | Base name, optionally stripping suffix |

### Permissions

| Method | Signature | Description |
|---|---|---|
| `chmod` | `(string\|iterable $paths, int $mode, int $umask = 0000, bool $recursive = false)` | Change mode |
| `chown` | `(string\|iterable $paths, string $user, bool $recursive = false)` | Change owner |
| `chgrp` | `(string\|iterable $paths, string $group, bool $recursive = false)` | Change group |

```php
interface Filesystem
{
    public function mkdir(string|iterable $dirs, int $mode = 0775): void;
    public function touch(string|iterable $files, ?int $time = null, ?int $atime = null): void;
    public function rename(string $origin, string $target, bool $override = false): void;
    public function symlink(string $origin, string $target, bool $copyOnWindows = false): void;
    public function copy(string $originFile, string $targetFile, bool $override = false): void;
    public function mirror(string $originDir, string $targetDir, bool $override = false, bool $delete = false, bool $copyOnWindows = false): void;
    public function exists(string|iterable $paths): bool;
    public function remove(string|iterable $paths): void;
    public function get(string $path): string;
    public function put(string $path, string $content): void;
    public function isFile(string $path): bool;
    public function isDir(string $path): bool;
    public function isLink(string $path): bool;
    public function isReadable(string $path): bool;
    public function isWritable(string $path): bool;
    public function isExecutable(string $path): bool;
    public function isAbsolute(string $path): bool;
    public function lastModified(string $path): int;
    public function lastAccessed(string $path): int;
    public function fileSize(string $path): int;
    public function fileName(string $path): string;
    public function fileExt(string $path): string;
    public function dirName(string $path): string;
    public function baseName(string $path, ?string $suffix = null): string;
    public function fileType(string $path): string;
    public function mimeType(string $path): string;
    public function getReturn(string $path): mixed;
    public function requireOnce(string $path): void;
    public function chmod(string|iterable $paths, int $mode, int $umask = 0000, bool $recursive = false): void;
    public function chown(string|iterable $paths, string $user, bool $recursive = false): void;
    public function chgrp(string|iterable $paths, string $group, bool $recursive = false): void;
}
```

---

## SymfonyFilesystem

`Fight\Common\Adapter\Filesystem\SymfonyFilesystem`

Wraps `Symfony\Component\Filesystem\Filesystem`. This is the sole adapter implementation.

```php
final readonly class SymfonyFilesystem implements Filesystem
{
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new Filesystem();
    }
}
```

### Key behaviors

| Method | Delegation | Notable behavior |
|---|---|---|
| `copy()` | `$this->filesystem->copy()` | Checks `stream_is_local()` + `is_file()` first, throws `FileNotFoundException` if missing |
| `get()` | `file_get_contents()` | Checks `stream_is_local()` + `is_file()` first, throws `FileNotFoundException` if missing |
| `lastModified()` | `filemtime()` | Throws `FileNotFoundException` if not a file |
| `lastAccessed()` | `fileatime()` | Throws `FileNotFoundException` if not a file |
| `fileSize()` | `filesize()` | Throws `FileNotFoundException` if not a file |
| `fileName()` / `fileExt()` / `dirName()` / `baseName()` | `pathinfo()` / `dirname()` / `basename()` | Throw `FileNotFoundException` if path does not exist (checked via `file_exists()`) |
| `mimeType()` | `finfo_file(FILEINFO_MIME_TYPE)` | Throws `FileNotFoundException` if not a file |
| `getReturn()` | `require` | Throws `FileNotFoundException` if not a file |
| `requireOnce()` | `require_once` | Throws `FileNotFoundException` if not a file |

All delegating methods catch `Symfony\Component\Filesystem\Exception\IOException` and wrap it
in `FilesystemException` (preserving the path). Non-IO exceptions are caught as `Throwable`
and wrapped with a null path.

Boolean query methods (`exists`, `isFile`, `isDir`, `isLink`, `isReadable`, `isWritable`,
`isExecutable`) call the PHP native function directly without try/catch and do not throw.

```php
$fs = new SymfonyFilesystem();
$fs->mkdir('/tmp/build/logs', 0755);
$fs->copy('/tmp/source.txt', '/tmp/build/source.txt', true);
$contents = $fs->get('/tmp/build/source.txt');
```

---

## Filesystem Exceptions

```
SystemException
└── FilesystemException (has getPath(): ?string)
    └── FileNotFoundException (static fromPath(string $path): static)
```

`Fight\Common\Application\Filesystem\Exception\FilesystemException`

```php
class FilesystemException extends SystemException
{
    public function __construct(string $message = '', ?string $path = null, ?Throwable $previous = null);
    public function getPath(): ?string;
}
```

---

`Fight\Common\Application\Filesystem\Exception\FileNotFoundException`

```php
class FileNotFoundException extends FilesystemException
{
    public static function fromPath(string $path, ?Throwable $previous = null): static;
}
```

---

## Installation

### FileStorage (Flysystem)

```bash
composer require league/flysystem
```

The specific adapter package (e.g. `league/flysystem-local`, `league/flysystem-aws-s3-v3`)
must also be installed depending on the storage backend.

### Filesystem (Symfony)

```bash
composer require symfony/filesystem
```

`symfony/filesystem` is a standalone component with no additional dependencies.

---

## Symfony Configuration

### FileStorage

```yaml
# config/packages/common_filestorage.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- Flysystem adapters ---
    League\Flysystem\FilesystemOperator $localStorage:
        class: League\Flysystem\Filesystem
        arguments:
            - '@League\Flysystem\Local\LocalFilesystemAdapter'
            - override_visibility: true

    League\Flysystem\FilesystemOperator $s3Storage:
        class: League\Flysystem\Filesystem
        arguments:
            - '@League\Flysystem\AwsS3V3\AwsS3V3Adapter'

    # --- FlysystemStorage adapters ---
    Fight\Common\Adapter\FileStorage\FlysystemStorage $localFileStorage:
        arguments:
            - '@League\Flysystem\FilesystemOperator $localStorage'

    Fight\Common\Adapter\FileStorage\FlysystemStorage $s3FileStorage:
        arguments:
            - '@League\Flysystem\FilesystemOperator $s3Storage'

    # --- StorageService (registry) ---
    Fight\Common\Application\FileStorage\StorageService:
        calls:
            - addStorage: ['local', '@Fight\Common\Adapter\FileStorage\FlysystemStorage $localFileStorage']
            - addStorage: ['s3', '@Fight\Common\Adapter\FileStorage\FlysystemStorage $s3FileStorage']

    # --- Interface alias ---
    Fight\Common\Application\FileStorage\FileStorage:
        alias: Fight\Common\Adapter\FileStorage\FlysystemStorage $localFileStorage
```

### Filesystem

```yaml
# config/packages/common_filesystem.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    Fight\Common\Adapter\Filesystem\SymfonyFilesystem: ~

    Fight\Common\Application\Filesystem\Filesystem:
        alias: Fight\Common\Adapter\Filesystem\SymfonyFilesystem
```

---

## Usage Examples

### FileStorage — Upload to S3

```php
use Fight\Common\Application\FileStorage\FileStorage;

class AvatarService
{
    public function __construct(private FileStorage $storage) {}

    public function upload(string $userId, string $tmpPath): void
    {
        $resource = fopen($tmpPath, 'rb');
        $this->storage->putFile("avatars/{$userId}.jpg", $resource);
        fclose($resource);
    }

    public function getAvatar(string $userId): string
    {
        return $this->storage->getFileContents("avatars/{$userId}.jpg");
    }
}
```

### FileStorage — Cross-storage Copy via StorageService

```php
use Fight\Common\Application\FileStorage\StorageService;

class MediaManager
{
    public function __construct(private StorageService $storages) {}

    public function publish(int $mediaId): void
    {
        // Move from staging (local) to CDN (S3)
        $this->storages->moveStorageToStorage(
            'staging',
            "media/{$mediaId}/original.jpg",
            'cdn',
            "media/{$mediaId}/original.jpg"
        );
    }
}
```

### FileStorage — List Files

```php
$files = $this->storage->listFiles('photos/2024');
// ['photos/2024/img001.jpg', 'photos/2024/img002.jpg']

$allFiles = $this->storage->listFilesRecursively('photos');
// ['photos/2024/img001.jpg', 'photos/2024/12/img003.jpg', …]

$dirs = $this->storage->listDirectories('photos');
// ['photos/2024', 'photos/2023']
```

### Filesystem — Read and Write

```php
use Fight\Common\Application\Filesystem\Filesystem;

class ConfigService
{
    public function __construct(private Filesystem $fs) {}

    public function dump(array $config): void
    {
        $path = sprintf('%s/config.php', $this->fs->dirName(__FILE__));

        $this->fs->put($path, '<?php return ' . var_export($config, true) . ';');
    }

    public function load(): array
    {
        $path = sprintf('%s/config.php', dirname(__DIR__));

        if (!$this->fs->isFile($path)) {
            return [];
        }

        return $this->fs->getReturn($path) ?: [];
    }
}
```

### Filesystem — Directory Operations

```php
class BuildService
{
    public function __construct(private Filesystem $fs) {}

    public function deploy(string $releaseDir, string $sourceDir): void
    {
        // Create release directory
        $this->fs->mkdir($releaseDir, 0755);

        // Mirror source to release
        $this->fs->mirror($sourceDir, $releaseDir, true, true);

        // Remove old cache files
        $this->fs->remove("{$releaseDir}/var/cache/*");

        // Set permissions
        $this->fs->chmod("{$releaseDir}/var", 0775, 0002, true);
    }
}
```

### Testing with In-Memory Flysystem

```php
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Fight\Common\Adapter\FileStorage\FlysystemStorage;

$adapter = new InMemoryFilesystemAdapter();
$flysystem = new Filesystem($adapter);
$storage = new FlysystemStorage($flysystem);

$storage->putFile('test.txt', 'hello');
echo $storage->getFileContents('test.txt'); // 'hello'
echo $storage->hasFile('test.txt');         // true
```
