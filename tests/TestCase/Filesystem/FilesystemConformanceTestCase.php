<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Filesystem;

use Fight\Common\Application\Filesystem\Exception\FileNotFoundException;
use Fight\Common\Application\Filesystem\Exception\FilesystemException;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Test\Common\TestCase\UnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Shared behavioral contract for complete Fight filesystem adapters.
 */
abstract class FilesystemConformanceTestCase extends UnitTestCase
{
    private string $conformanceDirectory;

    /**
     * Creates the adapter under test.
     */
    abstract protected function create_filesystem(): Filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conformanceDirectory = sys_get_temp_dir().'/fight-filesystem-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->conformanceDirectory, 0777, true));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['fight_filesystem_require_once_count']);

        if (is_dir($this->conformanceDirectory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->conformanceDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $entry) {
                $path = $entry->getPathname();
                if ($entry->isLink() || $entry->isFile()) {
                    unlink($path);
                } else {
                    rmdir($path);
                }
            }

            rmdir($this->conformanceDirectory);
        }

        parent::tearDown();
    }

    public function test_that_conformance_mkdir_creates_each_directory_recursively(): void
    {
        $first = $this->path('first/nested');
        $second = $this->path('second');

        $this->create_filesystem()->mkdir([$first, $second]);

        self::assertDirectoryExists($first);
        self::assertDirectoryExists($second);
    }

    public function test_that_conformance_touch_creates_each_file_with_selected_times(): void
    {
        $first = $this->path('first.txt');
        $second = $this->path('second.txt');
        $time = 1_700_000_000;
        $accessTime = 1_700_000_100;

        $this->create_filesystem()->touch([$first, $second], $time, $accessTime);

        clearstatcache();
        self::assertSame($time, filemtime($first));
        self::assertSame($accessTime, fileatime($second));
    }

    public function test_that_conformance_rename_moves_a_path_and_honors_override(): void
    {
        $source = $this->file('source.txt', 'source');
        $target = $this->file('target.txt', 'target');

        $this->create_filesystem()->rename($source, $target, true);

        self::assertFileDoesNotExist($source);
        self::assertSame('source', file_get_contents($target));
    }

    public function test_that_conformance_symlink_creates_a_link_to_the_origin(): void
    {
        $origin = $this->file('origin.txt', 'linked');
        $target = $this->path('target.txt');

        $this->create_filesystem()->symlink($origin, $target);

        self::assertTrue(is_link($target));
        self::assertSame('linked', file_get_contents($target));
    }

    public function test_that_conformance_copy_preserves_origin_and_copies_content(): void
    {
        $origin = $this->file('copy-origin.txt', 'copied');
        $target = $this->path('copy-target.txt');

        $this->create_filesystem()->copy($origin, $target);

        self::assertFileExists($origin);
        self::assertSame('copied', file_get_contents($target));
    }

    public function test_that_conformance_copy_rejects_a_missing_origin(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->create_filesystem()->copy($this->path('missing.txt'), $this->path('target.txt'));
    }

    public function test_that_conformance_mirror_copies_nested_content_and_deletes_stale_targets(): void
    {
        $origin = $this->path('mirror-origin');
        $target = $this->path('mirror-target');
        self::assertTrue(mkdir($origin.'/nested', 0777, true));
        self::assertTrue(mkdir($target, 0777, true));
        self::assertIsInt(file_put_contents($origin.'/nested/current.txt', 'current'));
        self::assertIsInt(file_put_contents($target.'/stale.txt', 'stale'));

        $this->create_filesystem()->mirror($origin, $target, true, true);

        self::assertSame('current', file_get_contents($target.'/nested/current.txt'));
        self::assertFileDoesNotExist($target.'/stale.txt');
    }

    public function test_that_conformance_exists_requires_every_selected_path_to_exist(): void
    {
        $first = $this->file('exists-first.txt');
        $second = $this->file('exists-second.txt');
        $filesystem = $this->create_filesystem();

        self::assertTrue($filesystem->exists([$first, $second]));
        self::assertFalse($filesystem->exists([$first, $this->path('missing.txt')]));
    }

    public function test_that_conformance_remove_deletes_files_and_directories_recursively(): void
    {
        $file = $this->file('remove.txt');
        $directory = $this->path('remove-directory/nested');
        self::assertTrue(mkdir($directory, 0777, true));
        self::assertIsInt(file_put_contents($directory.'/file.txt', 'content'));

        $this->create_filesystem()->remove([$file, dirname($directory)]);

        self::assertFileDoesNotExist($file);
        self::assertDirectoryDoesNotExist(dirname($directory));
    }

    public function test_that_conformance_put_and_get_round_trip_content_and_create_parent_directories(): void
    {
        $path = $this->path('put/nested/content.txt');
        $filesystem = $this->create_filesystem();

        $filesystem->put($path, 'round trip');

        self::assertSame('round trip', $filesystem->get($path));
    }

    public function test_that_conformance_get_rejects_a_missing_file(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->create_filesystem()->get($this->path('missing.txt'));
    }

    public function test_that_conformance_path_type_and_access_checks_reflect_the_filesystem(): void
    {
        $file = $this->file('checks.txt');
        $directory = $this->path('checks-directory');
        $link = $this->path('checks-link');
        self::assertTrue(mkdir($directory));
        self::assertTrue(symlink($file, $link));
        $filesystem = $this->create_filesystem();

        self::assertTrue($filesystem->isFile($file));
        self::assertTrue($filesystem->isDir($directory));
        self::assertTrue($filesystem->isLink($link));
        self::assertTrue($filesystem->isReadable($file));
        self::assertTrue($filesystem->isWritable($file));
        self::assertFalse($filesystem->isExecutable($file));
    }

    public function test_that_conformance_is_absolute_distinguishes_absolute_and_relative_paths(): void
    {
        $filesystem = $this->create_filesystem();

        self::assertTrue($filesystem->isAbsolute($this->path('absolute.txt')));
        self::assertFalse($filesystem->isAbsolute('relative/path.txt'));
    }

    public function test_that_conformance_file_metadata_returns_native_values(): void
    {
        $path = $this->file('metadata.txt', '12345');
        $time = 1_700_000_000;
        self::assertTrue(touch($path, $time, $time));
        clearstatcache();
        $filesystem = $this->create_filesystem();

        self::assertSame($time, $filesystem->lastModified($path));
        self::assertSame($time, $filesystem->lastAccessed($path));
        self::assertSame(5, $filesystem->fileSize($path));
        self::assertSame('file', $filesystem->fileType($path));
        self::assertSame('text/plain', $filesystem->mimeType($path));
    }

    public function test_that_conformance_missing_file_metadata_uses_the_fight_exception_boundary(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->create_filesystem()->lastModified($this->path('missing.txt'));
    }

    public function test_that_conformance_path_information_preserves_expected_names(): void
    {
        $path = $this->file('archive.tar.gz');
        $filesystem = $this->create_filesystem();

        self::assertSame('archive.tar.gz', $filesystem->fileName($path));
        self::assertSame('gz', $filesystem->fileExt($path));
        self::assertSame($this->conformanceDirectory, $filesystem->dirName($path));
        self::assertSame('archive.tar.gz', $filesystem->baseName($path));
        self::assertSame('archive.tar', $filesystem->baseName($path, '.gz'));
    }

    public function test_that_conformance_path_information_rejects_a_missing_path(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->create_filesystem()->fileName($this->path('missing.txt'));
    }

    public function test_that_conformance_get_return_preserves_a_php_script_value(): void
    {
        $path = $this->file('return.php', '<?php return ["answer" => 42];');

        self::assertSame(['answer' => 42], $this->create_filesystem()->getReturn($path));
    }

    public function test_that_conformance_require_once_evaluates_a_php_script_only_once(): void
    {
        $path = $this->file(
            'require-once.php',
            '<?php $GLOBALS["fight_filesystem_require_once_count"] = '
            .'($GLOBALS["fight_filesystem_require_once_count"] ?? 0) + 1;'
        );
        $filesystem = $this->create_filesystem();

        $filesystem->requireOnce($path);
        $filesystem->requireOnce($path);

        self::assertSame(1, $GLOBALS['fight_filesystem_require_once_count']);
    }

    public function test_that_conformance_php_loading_rejects_a_missing_file(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->create_filesystem()->getReturn($this->path('missing.php'));
    }

    public function test_that_conformance_chmod_changes_the_selected_mode(): void
    {
        $path = $this->file('mode.txt');

        $this->create_filesystem()->chmod($path, 0600);

        clearstatcache();
        self::assertSame(0600, fileperms($path) & 0777);
    }

    public function test_that_conformance_chown_and_chgrp_reject_unknown_identities_at_the_fight_boundary(): void
    {
        $path = $this->file('ownership.txt');
        $filesystem = $this->create_filesystem();

        try {
            $filesystem->chown($path, '__fight_missing_user__');
            self::fail('Unknown users must be rejected.');
        } catch (FilesystemException) {
            self::assertFileExists($path);
        }

        $this->expectException(FilesystemException::class);
        $filesystem->chgrp($path, '__fight_missing_group__');
    }

    private function path(string $relativePath): string
    {
        return $this->conformanceDirectory.'/'.$relativePath;
    }

    private function file(string $relativePath, string $content = ''): string
    {
        $path = $this->path($relativePath);
        self::assertIsInt(file_put_contents($path, $content));

        return $path;
    }
}
