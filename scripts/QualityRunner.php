<?php

declare(strict_types=1);

namespace Fight\Common\Tooling;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Class QualityRunner
 */
final class QualityRunner
{
    /**
     * Runs the PHP quality gate in fail-fast order
     */
    public function run(): int
    {
        $stages = [
            'Composer validation'   => [['composer', 'validate', '--strict', '--no-interaction']],
            'PHP syntax'            => [$this->lint(...)],
            'PHPCS'                 => [[PHP_BINARY, 'vendor/bin/phpcs']],
            'PHPStan'               => [[PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--memory-limit=512M']],
            'Architecture'          => [
                [PHP_BINARY, 'vendor/bin/deptrac', '--fail-on-uncovered', '--report-uncovered', '--report-skipped'],
                [PHP_BINARY, 'vendor/bin/deptrac', 'debug:unassigned', '--no-cache']
            ],
            'Rector dry-run'        => [[
                PHP_BINARY, 'vendor/bin/rector', 'process', 'src/', 'release/src/', 'release/scripts/',
                'release/consumer/', 'scripts/', '--dry-run'
            ]],
            'Unit PHPUnit coverage' => [
                ['rm', '-f', 'var/reports/coverage/clover.xml'],
                [PHP_BINARY, 'vendor/bin/phpunit', '--testsuite', 'Unit', '--fail-on-skipped']
            ],
            'Exact coverage'        => [['./bin/coverage']],
            'Integration PHPUnit'   => [[
                PHP_BINARY, 'vendor/bin/phpunit', '--testsuite', 'Integration', '--no-coverage', '--fail-on-skipped'
            ]],
            'Functional PHPUnit'    => [[
                PHP_BINARY, 'vendor/bin/phpunit', '--testsuite', 'Functional', '--no-coverage', '--fail-on-skipped'
            ]]
        ];

        foreach ($stages as $stage => $checks) {
            printf('[quality] %s'.PHP_EOL, $stage);
            foreach ($checks as $check) {
                $status = $check instanceof Closure ? $check() : $this->execute($check);
                if ($status !== 0) {
                    return $status;
                }
            }
        }

        return 0;
    }

    /**
     * Checks project PHP syntax in stable path order
     */
    private function lint(): int
    {
        try {
            $paths = $this->phpPaths();
        } catch (UnexpectedValueException $unexpectedValueException) {
            fwrite(STDERR, $unexpectedValueException->getMessage().PHP_EOL);

            return 1;
        }

        foreach ($paths as $path) {
            $command = [PHP_BINARY, '-l', $path];
            if ($this->execute($command, quiet: true) !== 0) {
                // Repeat visibly, preserving the failing exit (or fail if the retry succeeds).
                return $this->execute($command) ?: 1;
            }
        }

        return 0;
    }

    /**
     * Returns the ordered PHP lint paths
     *
     * @phpstan-return list<string>
     */
    private function phpPaths(): array
    {
        $paths = $this->files(new FilesystemIterator('.'));
        foreach (['src', 'tests', 'release', 'scripts'] as $directory) {
            $paths = array_merge($paths, $this->files(new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            )));
        }

        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * Collects regular PHP files without following symbolic links
     *
     * @phpstan-param iterable<SplFileInfo> $entries
     * @phpstan-return list<string>
     */
    private function files(iterable $entries): array
    {
        $paths = [];
        foreach ($entries as $entry) {
            if (!$entry->isLink() && $entry->isFile() && $entry->getExtension() === 'php') {
                $path = $entry->getPathname();
                $paths[] = str_starts_with($path, './') ? substr($path, 2) : $path;
            }
        }

        return $paths;
    }

    /**
     * Executes a command with the current input and output streams
     *
     * @phpstan-param non-empty-list<string> $command
     */
    private function execute(array $command, bool $quiet = false): int
    {
        $descriptors = $quiet ? [1 => ['file', '/dev/null', 'w']] : [];
        // Forward arguments as data; the shell wait preserves conventional command and signal exits.
        $process = proc_open(['sh', '-c', '"$@"; exit $?', 'quality', ...$command], $descriptors, $pipes);
        if ($process === false) {
            return 1;
        }

        return proc_close($process);
    }
}
