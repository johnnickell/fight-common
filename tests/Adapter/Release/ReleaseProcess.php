<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release;

use Symfony\Component\Process\Process;

/**
 * Class ReleaseProcess
 *
 * Test-only handoff for journeys already executing in the canonical PHP runtime.
 */
final class ReleaseProcess
{
    /** @var list<string> */
    private const array RELEASE_ENVIRONMENT = [
        'HOME=/tmp/fight-common-release/home',
        'COMPOSER_HOME=/tmp/fight-common-release/composer',
        'GNUPGHOME=/tmp/fight-common-release/gnupg',
        'GH_CONFIG_DIR=/tmp/fight-common-release/gh',
        'GIT_CONFIG_GLOBAL=/dev/null',
        'GIT_CONFIG_NOSYSTEM=1',
        'LANG=C.UTF-8',
        'LC_ALL=C.UTF-8',
        'PATH=/usr/local/bin:/usr/bin:/bin',
        'FIGHT_COMMON_RELEASE_TEST_RUNTIME=fight-common-release-direct-test-v1'
    ];

    /**
     * Creates a release process already executing in the canonical test runtime
     *
     * @param array $command Command arguments.
     * @param array $environment Additional isolated test environment.
     *
     * @phpstan-param list<string> $command
     * @phpstan-param array<string, string> $environment
     */
    public static function create(array $command, array $environment = []): Process
    {
        $release = $command[0] ?? '';

        if (basename($release) === 'release' && basename(dirname($release)) === 'bin') {
            $root = dirname($release, 2);

            return new Process([
                '/usr/bin/env',
                '-i',
                ...self::RELEASE_ENVIRONMENT,
                ...array_map(
                    static fn (string $name, string $value): string => $name.'='.$value,
                    array_keys($environment),
                    array_values($environment)
                ),
                PHP_BINARY,
                '-d',
                'display_errors=stderr',
                $root.'/scripts/release.php',
                ...array_slice($command, 1)
            ]);
        }

        return new Process($command);
    }
}
