<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Filesystem\Laravel;

use ErrorException;
use Fight\Common\Adapter\Filesystem\Laravel\LaravelFilesystem;
use Fight\Common\Application\Filesystem\Exception\FileNotFoundException;
use Fight\Common\Application\Filesystem\Exception\FilesystemException;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Test\Common\TestCase\Filesystem\FilesystemConformanceTestCase;
use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(LaravelFilesystem::class)]
final class LaravelFilesystemTest extends FilesystemConformanceTestCase
{
    protected function create_filesystem(): Filesystem
    {
        return new LaravelFilesystem();
    }

    public function test_that_recursive_chmod_applies_after_descendants_are_discovered(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-recursive-'.bin2hex(random_bytes(8));
        $nested = $root.'/nested';
        $file = $nested.'/file.txt';
        $hidden = $nested.'/.hidden';
        $external = $root.'-external';
        $externalFile = $external.'/file.txt';
        $link = $root.'/linked-external';
        self::assertTrue(mkdir($nested, 0777, true));
        self::assertTrue(mkdir($external, 0777, true));
        self::assertIsInt(file_put_contents($file, 'content'));
        self::assertIsInt(file_put_contents($hidden, 'hidden'));
        self::assertIsInt(file_put_contents($externalFile, 'external'));
        self::assertTrue(chmod($externalFile, 0600));
        self::assertTrue(symlink($external, $link));

        try {
            (new LaravelFilesystem())->chmod($root, 0700, 0000, true);

            clearstatcache();
            self::assertSame(0700, fileperms($root) & 0777);
            self::assertSame(0700, fileperms($nested) & 0777);
            self::assertSame(0700, fileperms($file) & 0777);
            self::assertSame(0700, fileperms($hidden) & 0777);
            self::assertSame(0600, fileperms($externalFile) & 0777);
            self::assertTrue(is_link($link));
        } finally {
            @unlink($link);
            (new IlluminateFilesystem())->deleteDirectory($root);
            (new IlluminateFilesystem())->deleteDirectory($external);
        }
    }

    public function test_that_windows_copy_mode_copies_files_and_directories_instead_of_linking(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-windows-'.bin2hex(random_bytes(8));
        $sourceDirectory = $root.'/source';
        $targetDirectory = $root.'/directory-copy';
        $sourceFile = $sourceDirectory.'/file.txt';
        $targetFile = $root.'/file-copy.txt';
        self::assertTrue(mkdir($sourceDirectory, 0777, true));
        self::assertIsInt(file_put_contents($sourceFile, 'content'));
        $filesystem = new LaravelFilesystem(null, true);

        try {
            $filesystem->symlink($sourceFile, $targetFile, true);
            $filesystem->symlink($sourceDirectory, $targetDirectory, true);

            self::assertFalse(is_link($targetFile));
            self::assertFalse(is_link($targetDirectory));
            self::assertSame('content', file_get_contents($targetFile));
            self::assertSame('content', file_get_contents($targetDirectory.'/file.txt'));
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_non_copy_windows_mode_creates_a_true_nested_link_idempotently(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-link-'.bin2hex(random_bytes(8));
        $origin = $root.'/origin.txt';
        $replacement = $root.'/replacement.txt';
        $target = $root.'/nested/target.txt';
        self::assertTrue(mkdir($root));
        self::assertIsInt(file_put_contents($origin, 'origin'));
        self::assertIsInt(file_put_contents($replacement, 'replacement'));
        $filesystem = new LaravelFilesystem(null, true);

        try {
            $filesystem->symlink($origin, $target);
            $filesystem->symlink($origin, $target);

            self::assertTrue(is_link($target));
            self::assertSame($origin, readlink($target));

            $filesystem->symlink($replacement, $target);

            self::assertTrue(is_link($target));
            self::assertSame($replacement, readlink($target));
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_symlink_rejects_an_existing_regular_target_without_leaking_a_warning(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-link-failure-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root));
        $origin = $root.'/origin.txt';
        $target = $root.'/target.txt';
        self::assertIsInt(file_put_contents($origin, 'origin'));
        self::assertIsInt(file_put_contents($target, 'target'));

        try {
            $this->expectException(FilesystemException::class);

            (new LaravelFilesystem())->symlink($origin, $target);
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_touch_uses_native_default_times_when_none_are_selected(): void
    {
        $path = sys_get_temp_dir().'/fight-laravel-filesystem-touch-'.bin2hex(random_bytes(8));

        try {
            (new LaravelFilesystem())->touch($path);

            self::assertFileExists($path);
            self::assertLessThanOrEqual(time(), filemtime($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_that_rename_rejects_an_existing_target_without_override(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-rename-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root));
        $origin = $root.'/origin.txt';
        $target = $root.'/target.txt';
        self::assertIsInt(file_put_contents($origin, 'origin'));
        self::assertIsInt(file_put_contents($target, 'target'));

        try {
            $this->expectException(FilesystemException::class);
            (new LaravelFilesystem())->rename($origin, $target);
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_rename_moves_to_a_new_target_without_staging(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-rename-new-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root));
        $origin = $root.'/origin.txt';
        $target = $root.'/target.txt';
        self::assertIsInt(file_put_contents($origin, 'origin'));

        try {
            (new LaravelFilesystem())->rename($origin, $target);

            self::assertFileDoesNotExist($origin);
            self::assertSame('origin', file_get_contents($target));
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_rename_preserves_an_existing_target_when_the_origin_is_missing(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-rename-missing-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root));
        $target = $root.'/target.txt';
        self::assertIsInt(file_put_contents($target, 'target'));

        try {
            try {
                (new LaravelFilesystem())->rename($root.'/missing.txt', $target, true);
                self::fail('A missing origin must fail.');
            } catch (FileNotFoundException) {
                self::assertSame('target', file_get_contents($target));
            }
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_rename_restores_a_staged_target_when_the_native_move_fails(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $backup = null;
        $native->shouldReceive('exists')->once()->with('/origin')->andReturnTrue();
        $native->shouldReceive('exists')->once()->with('/target')->andReturnTrue();
        $native->shouldReceive('move')->once()->with(
            '/target',
            Mockery::on(static function (string $path) use (&$backup): bool {
                $backup = $path;

                return str_starts_with($path, '/target.fight-backup-');
            })
        )->andReturnTrue();
        $native->shouldReceive('move')->once()->with('/origin', '/target')->andThrow(
            new RuntimeException('move failed')
        );
        $native->shouldReceive('move')->once()->with(
            Mockery::on(static function (string $path) use (&$backup): bool {
                return $path === $backup;
            }),
            '/target'
        )->andReturnTrue();

        $this->expectException(FilesystemException::class);

        (new LaravelFilesystem($native))->rename('/origin', '/target', true);
    }

    public function test_that_native_warnings_are_translated_without_leaking_to_the_caller(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('get')->once()->with('/warning')->andReturnUsing(
            static fn (): string|false => file_get_contents('/fight-missing-native-warning')
        );
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });

        try {
            (new LaravelFilesystem($native))->get('/warning');
            self::fail('A native warning must fail.');
        } catch (FilesystemException $exception) {
            self::assertInstanceOf(ErrorException::class, $exception->getPrevious());
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
    }

    public function test_that_copy_preserves_a_newer_target_without_override(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-copy-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root));
        $origin = $root.'/origin.txt';
        $target = $root.'/target.txt';
        self::assertIsInt(file_put_contents($origin, 'origin'));
        self::assertIsInt(file_put_contents($target, 'target'));
        self::assertTrue(touch($origin, 1_700_000_000));
        self::assertTrue(touch($target, 1_700_000_100));

        try {
            (new LaravelFilesystem())->copy($origin, $target);

            self::assertSame('target', file_get_contents($target));
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_mirror_rejects_a_missing_origin_directory(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-missing-'.bin2hex(random_bytes(8));

        $this->expectException(FileNotFoundException::class);

        (new LaravelFilesystem())->mirror($root.'/source', $root.'/target');
    }

    public function test_that_mirror_preserves_a_root_origin_in_its_failure_path(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('isDirectory')->once()->with('/')->andReturnFalse();

        try {
            (new LaravelFilesystem($native))->mirror('/', '/target');
            self::fail('A missing root must fail through the Fight boundary.');
        } catch (FileNotFoundException $exception) {
            self::assertSame('/', $exception->getPath());
        }
    }

    public function test_that_mirror_preserves_a_windows_drive_root_in_its_failure_path(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('isDirectory')->once()->with('C:\\')->andReturnFalse();

        try {
            (new LaravelFilesystem($native, true))->mirror('C:\\', 'D:\\target');
            self::fail('A missing drive root must fail through the Fight boundary.');
        } catch (FileNotFoundException $exception) {
            self::assertSame('C:\\', $exception->getPath());
        }
    }

    public function test_that_mirror_translates_iterator_failures_to_the_fight_boundary(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('isDirectory')->once()->with('/raced-origin')->andReturnTrue();

        try {
            (new LaravelFilesystem($native))->mirror('/raced-origin', '/target');
            self::fail('A raced origin must fail through the Fight boundary.');
        } catch (FilesystemException $exception) {
            self::assertSame('/raced-origin', $exception->getPath());
        }
    }

    public function test_that_mirror_excludes_a_nested_target_from_its_origin_iterator(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-nested-mirror-'.bin2hex(random_bytes(8));
        $target = $root.'/target';
        self::assertTrue(mkdir($target, 0777, true));
        self::assertIsInt(file_put_contents($root.'/source.txt', 'source'));
        self::assertIsInt(file_put_contents($target.'/stale.txt', 'stale'));

        try {
            (new LaravelFilesystem())->mirror($root, $target);

            self::assertSame('source', file_get_contents($target.'/source.txt'));
            self::assertSame('stale', file_get_contents($target.'/stale.txt'));
            self::assertDirectoryDoesNotExist($target.'/target');
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_mirror_preserves_links_unless_windows_copy_mode_is_selected(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-mirror-link-'.bin2hex(random_bytes(8));
        $origin = $root.'/origin';
        $target = $root.'/target';
        self::assertTrue(mkdir($origin, 0777, true));
        self::assertIsInt(file_put_contents($origin.'/source.txt', 'linked'));
        self::assertTrue(symlink($origin.'/source.txt', $origin.'/link.txt'));

        try {
            (new LaravelFilesystem())->mirror($origin, $target);

            self::assertTrue(is_link($target.'/link.txt'));
            self::assertSame('linked', file_get_contents($target.'/link.txt'));
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_mirror_follows_links_in_windows_copy_mode(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-mirror-copy-'.bin2hex(random_bytes(8));
        $origin = $root.'/origin';
        $target = $root.'/target';
        self::assertTrue(mkdir($origin, 0777, true));
        self::assertIsInt(file_put_contents($origin.'/source.txt', 'copied'));
        self::assertTrue(symlink($origin.'/source.txt', $origin.'/link.txt'));

        try {
            (new LaravelFilesystem(null, true))->mirror($origin, $target, false, false, true);

            self::assertFalse(is_link($target.'/link.txt'));
            self::assertSame('copied', file_get_contents($target.'/link.txt'));
        } finally {
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_mirror_rejects_an_unknown_native_file_type(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-fifo-'.bin2hex(random_bytes(8));
        $origin = $root.'/origin';
        self::assertTrue(mkdir($origin, 0777, true));
        self::assertTrue(posix_mkfifo($origin.'/pipe', 0600));

        try {
            $this->expectException(FilesystemException::class);
            (new LaravelFilesystem())->mirror($origin, $root.'/target');
        } finally {
            @unlink($origin.'/pipe');
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_native_failures_are_translated_to_the_fight_exception_boundary(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('get')->once()->with('/broken')->andThrow(new RuntimeException('native failure'));
        $filesystem = new LaravelFilesystem($native);

        try {
            $filesystem->get('/broken');
            self::fail('Native failures must be translated.');
        } catch (FilesystemException $exception) {
            self::assertSame('native failure', $exception->getMessage());
            self::assertSame('/broken', $exception->getPath());
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    public function test_that_silent_native_failures_use_the_fight_exception_boundary(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('isDirectory')->once()->with('/failure')->andReturnFalse();
        $native->shouldReceive('makeDirectory')->once()->with('/failure', 0775, true, true)->andReturnFalse();

        $this->expectException(FilesystemException::class);

        (new LaravelFilesystem($native))->mkdir('/failure');
    }

    public function test_that_directory_removal_verifies_laravels_success_result(): void
    {
        /** @var IlluminateFilesystem&MockInterface $native */
        $native = $this->mock(IlluminateFilesystem::class);
        $native->shouldReceive('isFile')->once()->with('/stuck')->andReturnFalse();
        $native->shouldReceive('isDirectory')->twice()->with('/stuck')->andReturnTrue();
        $native->shouldReceive('deleteDirectory')->once()->with('/stuck')->andReturnTrue();

        $this->expectException(FilesystemException::class);

        (new LaravelFilesystem($native))->remove('/stuck');
    }

    public function test_that_link_ownership_uses_link_aware_native_operations(): void
    {
        $root = sys_get_temp_dir().'/fight-laravel-filesystem-link-owner-'.bin2hex(random_bytes(8));
        $target = $root.'/target.txt';
        $link = $root.'/link.txt';
        self::assertTrue(mkdir($root));
        self::assertIsInt(file_put_contents($target, 'target'));
        self::assertTrue(symlink($target, $link));

        try {
            $filesystem = new LaravelFilesystem();
            $filesystem->chown($link, (string) posix_geteuid());
            $filesystem->chgrp($link, (string) posix_getegid());

            self::assertTrue(is_link($link));
            self::assertSame('target', file_get_contents($target));
        } finally {
            @unlink($link);
            (new IlluminateFilesystem())->deleteDirectory($root);
        }
    }

    public function test_that_absolute_path_detection_accepts_roots_and_stream_wrappers(): void
    {
        $filesystem = new LaravelFilesystem();
        $windowsFilesystem = new LaravelFilesystem(null, true);

        self::assertTrue($filesystem->isAbsolute('/var/app'));
        self::assertTrue($filesystem->isAbsolute('file:///tmp/file.txt'));
        self::assertTrue($filesystem->isAbsolute('phar://archive/file.txt'));
        self::assertFalse($filesystem->isAbsolute('\\Windows'));
        self::assertTrue($windowsFilesystem->isAbsolute('C:'));
        self::assertTrue($windowsFilesystem->isAbsolute('C:\\app\\file.txt'));
        self::assertTrue($windowsFilesystem->isAbsolute('\\\\server\\share\\file.txt'));
        self::assertTrue($windowsFilesystem->isAbsolute('\\Windows'));
        self::assertFalse($filesystem->isAbsolute(''));
        self::assertFalse($filesystem->isAbsolute('relative/path'));
    }
}
