<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase;

use LogicException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class UnitTestCase
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * Sets up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Removes test-double state before tearing down the test environment
     */
    protected function tearDown(): void
    {
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }

        Mockery::close();

        parent::tearDown();
    }

    /**
     * Creates a mock object
     *
     * Arguments are passed as-is to Mockery::mock()
     */
    protected function mock(...$args): MockInterface
    {
        return Mockery::mock(...$args);
    }

    /**
     * Removes one test-owned direct child of the system temporary directory
     */
    protected function removeTemporaryDirectory(string $directory, string $expectedPrefix): void
    {
        $this->removeTestDirectory($directory, sys_get_temp_dir(), $expectedPrefix);
    }

    /**
     * Removes one test-owned direct child of an explicitly allowed directory
     */
    protected function removeTestDirectory(
        string $directory,
        string $expectedParent,
        string $expectedPrefix
    ): void {
        $resolvedExpectedParent = realpath($expectedParent);
        $directoryMetadata = @lstat($directory);
        $resolvedDirectory = realpath($directory);

        if (
            $resolvedExpectedParent === false
            || $directoryMetadata === false
            || $resolvedDirectory === false
            || is_link($directory)
            || dirname($resolvedDirectory) !== $resolvedExpectedParent
            || preg_match(
                '/\A'.preg_quote($expectedPrefix, '/').'[0-9a-f]{16}\z/D',
                basename($resolvedDirectory)
            ) !== 1
        ) {
            throw new LogicException('Refusing to remove an unverified temporary test directory.');
        }

        $this->removeFilesystemEntryWithoutFollowingLinks($resolvedDirectory);
    }

    /**
     * Removes one filesystem entry without traversing symbolic links
     */
    private function removeFilesystemEntryWithoutFollowingLinks(string $path): void
    {
        $metadata = @lstat($path);

        if ($metadata === false) {
            throw new RuntimeException('Unable to inspect a temporary test filesystem entry.');
        }

        if (is_link($path)) {
            if (!@unlink($path)) {
                throw new RuntimeException('Unable to remove a temporary test symbolic link.');
            }

            return;
        }

        if (($metadata['mode'] & 0170000) !== 0040000) {
            if (!@unlink($path)) {
                throw new RuntimeException('Unable to remove a temporary test filesystem entry.');
            }

            return;
        }

        $entries = @scandir($path);

        if ($entries === false) {
            throw new RuntimeException('Unable to inspect a temporary test directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removeFilesystemEntryWithoutFollowingLinks($path.'/'.$entry);
        }

        if (!@rmdir($path)) {
            throw new RuntimeException('Unable to remove a temporary test directory.');
        }
    }
}
