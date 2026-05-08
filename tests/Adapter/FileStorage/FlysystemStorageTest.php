<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\FileStorage;

use RuntimeException;
use DateTimeImmutable;
use Fight\Common\Adapter\FileStorage\FlysystemStorage;
use Fight\Common\Application\FileStorage\Exception\FileStorageException;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Test\Common\TestCase\UnitTestCase;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FlysystemStorage::class)]
class FlysystemStorageTest extends UnitTestCase
{
    public function test_that_put_file_writes_string_contents(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('write')->once()->with('path/file.txt', 'contents');
        $flysystem->shouldReceive('createDirectory')->once()->with('path');

        $storage = new FlysystemStorage($flysystem);
        $storage->putFile('path/file.txt', 'contents');
    }

    public function test_that_put_file_writes_stream_resource(): void
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'data');
        rewind($resource);

        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('writeStream')->once()->with('path/file.txt', $resource);
        $flysystem->shouldReceive('createDirectory')->once()->with('path');

        $storage = new FlysystemStorage($flysystem);
        $storage->putFile('path/file.txt', $resource);
    }

    public function test_that_put_file_skips_directory_creation_for_root_path(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('write')->once()->with('file.txt', 'contents');
        $flysystem->shouldNotReceive('createDirectory');

        $storage = new FlysystemStorage($flysystem);
        $storage->putFile('file.txt', 'contents');
    }

    public function test_that_put_file_throws_exception_for_invalid_contents(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('createDirectory')->once()->with('path');

        $storage = new FlysystemStorage($flysystem);

        $this->expectException(FileStorageException::class);

        $storage->putFile('path/file.txt', 123);
    }

    public function test_that_get_file_contents_returns_string(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('read')->once()->with('file.txt')->andReturn('contents');

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->getFileContents('file.txt');

        self::assertSame('contents', $result);
    }

    public function test_that_get_file_resource_returns_resource(): void
    {
        $resource = fopen('php://memory', 'r+');

        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('readStream')->once()->with('file.txt')->andReturn($resource);

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->getFileResource('file.txt');

        self::assertIsResource($result);
    }

    public function test_that_has_file_returns_true_when_file_exists(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('fileExists')->once()->with('file.txt')->andReturn(true);

        $storage = new FlysystemStorage($flysystem);

        self::assertTrue($storage->hasFile('file.txt'));
    }

    public function test_that_has_file_returns_false_when_file_does_not_exist(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('fileExists')->once()->with('file.txt')->andReturn(false);

        $storage = new FlysystemStorage($flysystem);

        self::assertFalse($storage->hasFile('file.txt'));
    }

    public function test_that_remove_file_deletes_file(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('delete')->once()->with('file.txt');

        $storage = new FlysystemStorage($flysystem);
        $storage->removeFile('file.txt');
    }

    public function test_that_copy_file_copies_file_and_creates_destination_directory(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('createDirectory')->once()->with('dest');
        $flysystem->shouldReceive('copy')->once()->with('src/file.txt', 'dest/file.txt');

        $storage = new FlysystemStorage($flysystem);
        $storage->copyFile('src/file.txt', 'dest/file.txt');
    }

    public function test_that_move_file_moves_file_and_creates_destination_directory(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('createDirectory')->once()->with('dest');
        $flysystem->shouldReceive('move')->once()->with('src/file.txt', 'dest/file.txt');

        $storage = new FlysystemStorage($flysystem);
        $storage->moveFile('src/file.txt', 'dest/file.txt');
    }

    public function test_that_size_returns_file_size(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('fileSize')->once()->with('file.txt')->andReturn(1024);

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->size('file.txt');

        self::assertSame(1024, $result);
    }

    public function test_that_last_modified_returns_date_time_immutable(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('lastModified')->once()->with('file.txt')->andReturn(1700000000);

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->lastModified('file.txt');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame(1700000000, $result->getTimestamp());
    }

    public function test_that_list_files_returns_file_paths(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('listContents')
            ->once()
            ->with('path', false)
            ->andReturn(new DirectoryListing([
                new FileAttributes('file1.txt'),
                new FileAttributes('file2.txt'),
                new DirectoryAttributes('subdir'),
            ]));

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->listFiles('path');

        self::assertSame(['file1.txt', 'file2.txt'], $result);
    }

    public function test_that_list_files_recursively_returns_file_paths(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('listContents')
            ->once()
            ->with('path', true)
            ->andReturn(new DirectoryListing([
                new FileAttributes('file1.txt'),
                new FileAttributes('sub/file2.txt'),
            ]));

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->listFilesRecursively('path');

        self::assertSame(['file1.txt', 'sub/file2.txt'], $result);
    }

    public function test_that_list_directories_returns_directory_paths(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('listContents')
            ->once()
            ->with('path', false)
            ->andReturn(new DirectoryListing([
                new FileAttributes('file.txt'),
                new DirectoryAttributes('subdir'),
            ]));

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->listDirectories('path');

        self::assertSame(['subdir'], $result);
    }

    public function test_that_list_directories_recursively_returns_directory_paths(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('listContents')
            ->once()
            ->with('path', true)
            ->andReturn(new DirectoryListing([
                new DirectoryAttributes('subdir'),
                new DirectoryAttributes('subdir/nested'),
            ]));

        $storage = new FlysystemStorage($flysystem);
        $result = $storage->listDirectoriesRecursively('path');

        self::assertSame(['subdir', 'subdir/nested'], $result);
    }

    public function test_that_flysystem_exception_is_wrapped_in_file_storage_exception(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $flysystem->shouldReceive('read')
            ->once()
            ->with('missing.txt')
            ->andThrow(new class extends RuntimeException implements FilesystemException {
            });

        $storage = new FlysystemStorage($flysystem);

        $this->expectException(FileStorageException::class);

        $storage->getFileContents('missing.txt');
    }

    public function test_that_storage_implements_file_storage_interface(): void
    {
        $flysystem = $this->mock(FilesystemOperator::class);
        $storage = new FlysystemStorage($flysystem);

        self::assertInstanceOf(FileStorage::class, $storage);
    }
}
