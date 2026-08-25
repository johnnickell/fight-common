<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Filesystem;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Application\Filesystem\Exception\FileNotFoundException;
use Fight\Common\Application\Filesystem\Exception\FilesystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/FilesystemFunctionOverrides.php';

class FailStream
{
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }

    public function stream_read(int $count): string|false
    {
        return '';
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_stat(): array|false
    {
        return false;
    }

    public function stream_close(): void
    {
    }
}

#[CoversClass(SymfonyFilesystem::class)]
class SymfonyFilesystemTest extends UnitTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/sfs_test_' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        FilesystemFunctionOverrides::reset();

        $dirIterator = new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($this->tmpDir);
        parent::tearDown();
    }

    private function createFile(string $name, string $content = ''): string
    {
        $path = $this->tmpDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    public function test_that_default_constructor_creates_symfony_filesystem(): void
    {
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->exists(__FILE__);

        self::assertTrue($result);
    }

    public function test_that_mkdir_delegates(): void
    {
        $path = $this->tmpDir . '/newdir';
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('mkdir')->once()->with($path, 0775);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->mkdir($path);
    }

    public function test_that_mkdir_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('mkdir')->once()->andThrow(new IOException('mkdir failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('mkdir failed');

        $filesystem->mkdir('/tmp/x');
    }

    public function test_that_mkdir_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('mkdir')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('unexpected');

        $filesystem->mkdir('/tmp/x');
    }

    public function test_that_touch_delegates(): void
    {
        $file = $this->createFile('touch.txt');
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('touch')->once()->with($file, null, null);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->touch($file);
    }

    public function test_that_touch_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('touch')->once()->andThrow(new IOException('touch failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->touch('/tmp/x');
    }

    public function test_that_rename_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('rename')->once()->with('a.txt', 'b.txt', false);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->rename('a.txt', 'b.txt');
    }

    public function test_that_rename_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('rename')->once()->andThrow(new IOException('rename failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->rename('a.txt', 'b.txt');
    }

    public function test_that_symlink_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('symlink')->once()->with('origin', 'target', false);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->symlink('origin', 'target');
    }

    public function test_that_symlink_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('symlink')->once()->andThrow(new IOException('symlink failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->symlink('origin', 'target');
    }

    public function test_that_copy_delegates(): void
    {
        $src = $this->createFile('src.txt');
        $dst = $this->tmpDir . '/dst.txt';

        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('copy')->once()->with($src, $dst, true);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->copy($src, $dst, true);
    }

    public function test_that_copy_throws_file_not_found_when_origin_missing(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FileNotFoundException::class);

        $filesystem->copy('/nonexistent/file.txt', '/dest.txt');
    }

    public function test_that_copy_wraps_io_exception(): void
    {
        $src = $this->createFile('src.txt');

        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('copy')->once()->andThrow(new IOException('copy failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->copy($src, '/dest.txt');
    }

    public function test_that_copy_wraps_throwable(): void
    {
        $src = $this->createFile('src.txt');

        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('copy')->once()->andThrow(new RuntimeException('boom'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->copy($src, '/dest.txt');
    }

    public function test_that_mirror_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('mirror')->once()->with('origin', 'target', null, [
            'override'        => true,
            'delete'          => false,
            'copy_on_windows' => true,
        ]);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->mirror('origin', 'target', true, false, true);
    }

    public function test_that_mirror_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('mirror')->once()->andThrow(new IOException('mirror failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->mirror('origin', 'target');
    }

    public function test_that_exists_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('exists')->once()->with('/some/path')->andReturn(true);

        $filesystem = new SymfonyFilesystem($symfony);

        self::assertTrue($filesystem->exists('/some/path'));
    }

    public function test_that_remove_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('remove')->once()->with('/tmp/file');

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->remove('/tmp/file');
    }

    public function test_that_remove_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('remove')->once()->andThrow(new IOException('remove failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->remove('/tmp/file');
    }

    public function test_that_remove_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('remove')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->remove('/tmp/file');
    }

    public function test_that_get_reads_file_content(): void
    {
        $file = $this->createFile('content.txt', 'file contents');

        $filesystem = new SymfonyFilesystem();

        self::assertSame('file contents', $filesystem->get($file));
    }

    public function test_that_get_throws_file_not_found_when_file_missing(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->get('/nonexistent/file.txt');
    }

    public function test_that_put_delegates(): void
    {
        $path = $this->tmpDir . '/out.txt';

        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('dumpFile')->once()->with($path, 'content');

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->put($path, 'content');
    }

    public function test_that_put_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('dumpFile')->once()->andThrow(new IOException('put failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->put('/tmp/x', 'content');
    }

    public function test_that_is_file_returns_true_for_file(): void
    {
        $file = $this->createFile('test.txt');
        $filesystem = new SymfonyFilesystem();

        self::assertTrue($filesystem->isFile($file));
    }

    public function test_that_is_file_returns_false_for_directory(): void
    {
        $filesystem = new SymfonyFilesystem();

        self::assertFalse($filesystem->isFile($this->tmpDir));
    }

    public function test_that_is_dir_returns_true_for_directory(): void
    {
        $filesystem = new SymfonyFilesystem();

        self::assertTrue($filesystem->isDir($this->tmpDir));
    }

    public function test_that_is_link_returns_false_for_regular_file(): void
    {
        $file = $this->createFile('test.txt');
        $filesystem = new SymfonyFilesystem();

        self::assertFalse($filesystem->isLink($file));
    }

    public function test_that_is_readable_returns_true_for_readable_file(): void
    {
        $file = $this->createFile('readable.txt');
        $filesystem = new SymfonyFilesystem();

        self::assertTrue($filesystem->isReadable($file));
    }

    public function test_that_is_writable_returns_true_for_writable_file(): void
    {
        $file = $this->createFile('writable.txt');
        $filesystem = new SymfonyFilesystem();

        self::assertTrue($filesystem->isWritable($file));
    }

    public function test_that_is_executable_returns_false_for_regular_file(): void
    {
        $file = $this->createFile('notexec.txt');
        $filesystem = new SymfonyFilesystem();

        self::assertFalse($filesystem->isExecutable($file));
    }

    public function test_that_is_absolute_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('isAbsolutePath')->once()->with('/abs/path')->andReturn(true);

        $filesystem = new SymfonyFilesystem($symfony);

        self::assertTrue($filesystem->isAbsolute('/abs/path'));
    }

    public function test_that_last_modified_returns_timestamp(): void
    {
        $file = $this->createFile('modified.txt');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->lastModified($file);

        self::assertIsInt($result);
    }

    public function test_that_last_modified_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->lastModified('/nonexistent');
    }

    public function test_that_last_modified_throws_filesystem_exception_when_metadata_fetch_fails(): void
    {
        $path = $this->createFile('modified.txt');
        FilesystemFunctionOverrides::fail('Fight\Common\Adapter\Filesystem\Symfony\filemtime', $path);
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('Unable to fetch last modified: ' . $path);

        $filesystem->lastModified($path);
    }

    public function test_that_last_accessed_returns_timestamp(): void
    {
        $file = $this->createFile('accessed.txt');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->lastAccessed($file);

        self::assertIsInt($result);
    }

    public function test_that_last_accessed_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->lastAccessed('/nonexistent');
    }

    public function test_that_last_accessed_throws_filesystem_exception_when_metadata_fetch_fails(): void
    {
        $path = $this->createFile('accessed.txt');
        FilesystemFunctionOverrides::fail('Fight\Common\Adapter\Filesystem\Symfony\fileatime', $path);
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('Unable to fetch last accessed: ' . $path);

        $filesystem->lastAccessed($path);
    }

    public function test_that_file_size_returns_size(): void
    {
        $file = $this->createFile('size.txt', '12345');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->fileSize($file);

        self::assertSame(5, $result);
    }

    public function test_that_file_size_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->fileSize('/nonexistent');
    }

    public function test_that_file_size_throws_filesystem_exception_when_metadata_fetch_fails(): void
    {
        $path = $this->createFile('size.txt');
        FilesystemFunctionOverrides::fail('Fight\Common\Adapter\Filesystem\Symfony\filesize', $path);
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('Unable to fetch file size: ' . $path);

        $filesystem->fileSize($path);
    }

    public function test_that_file_name_returns_filename_with_extension(): void
    {
        $file = $this->createFile('archive.tar.gz');
        $filesystem = new SymfonyFilesystem();

        self::assertSame('archive.tar.gz', $filesystem->fileName($file));
    }

    public function test_that_file_name_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->fileName('/nonexistent');
    }

    public function test_that_file_name_returns_filename_without_extension(): void
    {
        $file = $this->createFile('noext');
        $filesystem = new SymfonyFilesystem();

        self::assertSame('noext', $filesystem->fileName($file));
    }

    public function test_that_file_ext_returns_extension(): void
    {
        $file = $this->createFile('doc.pdf');
        $filesystem = new SymfonyFilesystem();

        self::assertSame('pdf', $filesystem->fileExt($file));
    }

    public function test_that_file_ext_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->fileExt('/nonexistent');
    }

    public function test_that_dir_name_returns_directory(): void
    {
        $file = $this->createFile('in_dir.txt');
        $filesystem = new SymfonyFilesystem();

        self::assertSame($this->tmpDir, $filesystem->dirName($file));
    }

    public function test_that_dir_name_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->dirName('/nonexistent');
    }

    public function test_that_base_name_returns_basename(): void
    {
        $file = $this->createFile('basename.txt');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->baseName($file);

        self::assertSame('basename.txt', $result);
    }

    public function test_that_base_name_with_suffix_strips_suffix(): void
    {
        $file = $this->createFile('config.yaml');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->baseName($file, '.yaml');

        self::assertSame('config', $result);
    }

    public function test_that_base_name_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->baseName('/nonexistent');
    }

    public function test_that_file_type_returns_type(): void
    {
        $file = $this->createFile('type.txt');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->fileType($file);

        self::assertSame('file', $result);
    }

    public function test_that_mime_type_returns_mime(): void
    {
        $file = $this->createFile('mime.txt', 'text content');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->mimeType($file);

        self::assertSame('text/plain', $result);
    }

    public function test_that_mime_type_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->mimeType('/nonexistent');
    }

    public function test_that_mime_type_throws_filesystem_exception_when_detection_fails(): void
    {
        $path = $this->createFile('mime.txt');
        FilesystemFunctionOverrides::fail('Fight\Common\Adapter\Filesystem\Symfony\finfo_file', $path);
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('Unable to fetch mime type: ' . $path);

        $filesystem->mimeType($path);
    }

    public function test_that_get_return_returns_php_result(): void
    {
        $file = $this->createFile('return.php', '<?php return ["key" => "value"];');
        $filesystem = new SymfonyFilesystem();

        $result = $filesystem->getReturn($file);

        self::assertSame(['key' => 'value'], $result);
    }

    public function test_that_get_return_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->getReturn('/nonexistent');
    }

    public function test_that_require_once_includes_file(): void
    {
        $file = $this->createFile('require_once.php', '<?php // just a test');
        $filesystem = new SymfonyFilesystem();

        $filesystem->requireOnce($file);

        self::assertTrue(true);
    }

    public function test_that_require_once_throws_file_not_found(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FileNotFoundException::class);

        $filesystem->requireOnce('/nonexistent');
    }

    public function test_that_chmod_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chmod')->once()->with('/path', 0644, 0000, false);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->chmod('/path', 0644);
    }

    public function test_that_chmod_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chmod')->once()->andThrow(new IOException('chmod failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->chmod('/path', 0644);
    }

    public function test_that_chown_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chown')->once()->with('/path', 'user', false);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->chown('/path', 'user');
    }

    public function test_that_chown_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chown')->once()->andThrow(new IOException('chown failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->chown('/path', 'user');
    }

    public function test_that_chgrp_delegates(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chgrp')->once()->with('/path', 'group', false);

        $filesystem = new SymfonyFilesystem($symfony);
        $filesystem->chgrp('/path', 'group');
    }

    public function test_that_chgrp_wraps_io_exception(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chgrp')->once()->andThrow(new IOException('chgrp failed'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->chgrp('/path', 'group');
    }

    public function test_that_touch_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('touch')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->touch('/tmp/x');
    }

    public function test_that_rename_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('rename')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->rename('a.txt', 'b.txt');
    }

    public function test_that_symlink_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('symlink')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->symlink('origin', 'target');
    }

    public function test_that_mirror_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('mirror')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->mirror('origin', 'target');
    }

    public function test_that_get_throws_filesystem_exception_when_read_fails(): void
    {
        $scheme = 'failread' . bin2hex(random_bytes(4));
        stream_wrapper_register($scheme, FailStream::class, STREAM_IS_URL);

        $filesystem = new SymfonyFilesystem();

        try {
            $this->expectException(FilesystemException::class);
            $filesystem->get($scheme . '://test');
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    public function test_that_put_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('dumpFile')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->put('/tmp/x', 'content');
    }

    public function test_that_file_type_throws_filesystem_exception_for_missing_file(): void
    {
        $filesystem = new SymfonyFilesystem();

        $this->expectException(FilesystemException::class);

        $filesystem->fileType('/nonexistent');
    }

    public function test_that_chmod_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chmod')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->chmod('/path', 0644);
    }

    public function test_that_chown_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chown')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->chown('/path', 'user');
    }

    public function test_that_chgrp_wraps_throwable(): void
    {
        /** @var MockInterface|Filesystem $symfony */
        $symfony = $this->mock(Filesystem::class);
        $symfony->shouldReceive('chgrp')->once()->andThrow(new RuntimeException('unexpected'));

        $filesystem = new SymfonyFilesystem($symfony);

        $this->expectException(FilesystemException::class);

        $filesystem->chgrp('/path', 'group');
    }
}
