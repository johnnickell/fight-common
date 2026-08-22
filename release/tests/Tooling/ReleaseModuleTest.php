<?php

declare(strict_types=1);

namespace Fight\Test\Release\Tooling;

use Fight\Release\Application\MachineResult;
use Fight\Test\Common\TestCase\UnitTestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class ReleaseModuleTest extends UnitTestCase
{
    public function test_that_release_source_is_available_only_from_the_maintainer_module(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode(
            (string) file_get_contents($root.'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertTrue(class_exists(MachineResult::class));
        self::assertSame('release/src', $composer['autoload-dev']['psr-4']["Fight\\Release\\"]);
        self::assertSame('release/tests', $composer['autoload-dev']['psr-4']["Fight\\Test\\Release\\"]);
        self::assertArrayNotHasKey("Fight\\Release\\", $composer['autoload']['psr-4']);
        self::assertFileExists($root.'/release/src/Application/MachineResult.php');
        self::assertDirectoryDoesNotExist($root.'/src/Application/Release');
        self::assertDirectoryDoesNotExist($root.'/src/Adapter/Release');
        self::assertFalse(class_exists('Fight\Common\Application\Release\MachineResult'));

        $runtimeSource = $this->readPhpSource($root.'/src');
        $releaseSource = $this->readPhpSource($root.'/release/src').$this->readPhpSource($root.'/release/scripts');
        self::assertStringNotContainsString("Fight\\Release\\", $runtimeSource);
        self::assertStringNotContainsString("Fight\\Common\\Application\\Release\\", $releaseSource);
        self::assertStringNotContainsString("Fight\\Common\\Adapter\\Release\\", $releaseSource);
        self::assertStringNotContainsString('class_alias(', $runtimeSource.$releaseSource);
    }

    public function test_that_a_clean_no_dev_consumer_cannot_autoload_release_classes(): void
    {
        $root = dirname(__DIR__, 3);
        $fixture = $root.'/release/fixtures/ComposerConsumer/composer.json';
        self::assertFileExists($fixture);

        $consumer = json_decode(
            (string) file_get_contents($fixture),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $consumer['repositories'][0]['url'] = $root;

        $directory = sys_get_temp_dir().'/fight-common-release-consumer-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);

        try {
            file_put_contents(
                $directory.'/composer.json',
                json_encode($consumer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
            );

            $install = new Process(
                ['composer', 'install', '--no-dev', '--no-interaction', '--no-progress', '--no-plugins', '--no-scripts'],
                $directory,
                ['COMPOSER_HOME' => $directory.'/.composer'],
                null,
                120,
            );
            $install->run();

            self::assertSame(0, $install->getExitCode(), $install->getErrorOutput());

            $package = $directory.'/vendor/johnnickell/fight-common';
            self::assertFileExists($package.'/composer.json');
            self::assertFalse(is_link($package));
            self::assertFileExists($package.'/release/src/Application/MachineResult.php');

            $autoload = require $directory.'/vendor/composer/autoload_psr4.php';
            self::assertIsArray($autoload);
            self::assertArrayHasKey("Fight\\Common\\", $autoload);
            self::assertSame([$package.'/src'], $autoload["Fight\\Common\\"]);
            self::assertArrayNotHasKey("Fight\\Release\\", $autoload);

            $probe = new Process([
                'php',
                '-r',
                'require $argv[1]; exit(class_exists($argv[2]) ? 1 : 0);',
                $directory.'/vendor/autoload.php',
                MachineResult::class,
            ]);
            $probe->run();

            self::assertSame(0, $probe->getExitCode(), $probe->getErrorOutput());
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    private function readPhpSource(string $directory): string
    {
        $source = '';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ('php' !== $file->getExtension()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (false === $contents) {
                throw new RuntimeException('Unable to read PHP source: '.$file->getPathname());
            }

            $source .= $contents;
        }

        return $source;
    }

    public function test_that_every_canonical_quality_tool_explicitly_inspects_release_code(): void
    {
        $root = dirname(__DIR__, 3);
        $contracts = [
            'phpunit.xml.dist' => [
                '<directory>release/tests</directory>',
                '<directory suffix=".php">release/src</directory>',
            ],
            'bin/coverage' => ["'@codeCoverageIgnore' src release/src"],
            'phpstan.neon.dist' => ['- release/src'],
            'phpcs.xml' => ['<file>release/src</file>'],
            'rector.php' => ["__DIR__.'/release/src'", "__DIR__.'/release/tests'"],
            'deptrac.php' => [
                "__DIR__.'/release/src/Application'",
                "__DIR__.'/release/src/Adapter'",
            ],
        ];

        foreach ($contracts as $path => $expectedFragments) {
            $contents = file_get_contents($root.'/'.$path);
            self::assertIsString($contents, $path);

            foreach ($expectedFragments as $expectedFragment) {
                self::assertStringContainsString($expectedFragment, $contents, $path);
            }
        }
    }
}
