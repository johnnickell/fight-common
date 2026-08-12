<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class BuildTest extends UnitTestCase
{
    private string $directory;

    private string $gitDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fight-common-build-'.bin2hex(random_bytes(8));
        $this->gitDirectory = $this->directory.'-git-common';
        mkdir($this->directory.'/bin', 0777, true);
        mkdir($this->gitDirectory.'/worktrees/fixture', 0777, true);

        copy(dirname(__DIR__, 2).'/bin/build', $this->directory.'/bin/build');
        chmod($this->directory.'/bin/build', 0755);
        copy(dirname(__DIR__, 2).'/bin/.disposable-database-runtime', $this->directory.'/bin/.disposable-database-runtime');
        file_put_contents($this->directory.'/bin/quality', "#!/usr/bin/env bash\nexit 0\n");
        chmod($this->directory.'/bin/quality', 0755);
        file_put_contents($this->directory.'/composer.lock', "tracked locked resolution\n");

        file_put_contents($this->directory.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -eu

printf '%s ' "$@" >> "${FAKE_DOCKER_LOG}"
printf '\n' >> "${FAKE_DOCKER_LOG}"

if [[ "${1:-}" == "inspect" ]]; then
    echo healthy
fi

if [[ "${1:-}" == "container" && "${2:-}" == "run" && " ${*} " == *" ./bin/quality "* ]]; then
    if [[ " ${*} " == *" composer update "* ]]; then
        if [[ "${FAKE_COMPOSER_STATUS:-0}" != "0" ]]; then
            exit "${FAKE_COMPOSER_STATUS}"
        fi

        printf '%s\n' '{"packages":[{"name":"fixture/required-dependency","version":"2.0.0"}]}' > composer.lock
    fi

    exit "${FAKE_QUALITY_STATUS:-0}"
fi
BASH
        );
        chmod($this->directory.'/docker', 0755);

        file_put_contents($this->directory.'/id', <<<'BASH'
#!/usr/bin/env bash
set -eu

if [[ "${1:-}" == "-u" ]]; then
    echo 501
else
    echo 20
fi
BASH
        );
        chmod($this->directory.'/id', 0755);

        file_put_contents($this->directory.'/git', <<<'BASH'
#!/usr/bin/env bash
set -eu

printf '%s\n' "$*" >> "${FAKE_GIT_LOG}"
printf '%s\n' "${FAKE_GIT_COMMON_DIR}"
BASH
        );
        chmod($this->directory.'/git', 0755);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
        $this->removeDirectory($this->gitDirectory);

        parent::tearDown();
    }

    public function test_that_unsupported_arguments_are_rejected_before_docker_work(): void
    {
        $process = new Process(
            ['bash', 'bin/build', '--unsupported'],
            $this->directory,
            [
                'DOCKER_BIN' => $this->directory.'/missing-docker',
            ],
            null,
            20,
        );
        $process->run();

        self::assertSame(2, $process->getExitCode());
        self::assertSame("Usage: ./bin/build [--latest]\n", $process->getErrorOutput());
    }

    public function test_that_default_build_installs_the_lock_and_runs_quality_once_in_one_owned_container(): void
    {
        $originalLock = file_get_contents($this->directory.'/composer.lock');

        $process = $this->runBuild();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame($originalLock, file_get_contents($this->directory.'/composer.lock'));

        $log = file_get_contents($this->directory.'/docker.log');
        self::assertSame(1, substr_count($log, 'build -t fight-common ./etc/docker/'));
        self::assertSame(1, substr_count($log, 'container run --rm'));
        preg_match('/^container run --rm .*$/m', $log, $gateContainer);
        self::assertNotEmpty($gateContainer[0] ?? '');
        self::assertStringNotContainsString('-it', $gateContainer[0]);
        self::assertStringNotContainsString(' -i ', $gateContainer[0]);
        self::assertStringNotContainsString(' -t ', $gateContainer[0]);
        self::assertStringNotContainsString(':ro', $gateContainer[0]);
        self::assertFileDoesNotExist($this->directory.'/git.log');
        self::assertStringContainsString('-v '.$this->directory.':/app:delegated', $log);
        self::assertStringContainsString('-w /app', $log);
        self::assertStringContainsString('--user 501:20', $log);
        self::assertStringContainsString('--network fight-common-test-network-', $log);
        self::assertStringContainsString(
            'FIGHT_COMMON_MYSQL_DSN=mysql://fight_common:fight_common@mysql:3306/fight_common',
            $log,
        );
        self::assertStringContainsString(
            'FIGHT_COMMON_POSTGRESQL_DSN=postgresql://fight_common:fight_common@postgresql:5432/fight_common',
            $log,
        );
        self::assertStringContainsString(
            "composer install --no-interaction --no-progress --prefer-dist && ./bin/quality",
            $log,
        );
        self::assertStringContainsString('container rm --force', $log);
        self::assertStringContainsString('network rm', $log);
    }

    public function test_that_quality_failure_status_is_preserved_after_database_cleanup(): void
    {
        $process = $this->runBuild(37);

        self::assertSame(37, $process->getExitCode());
        self::assertStringContainsString('container rm --force', file_get_contents($this->directory.'/docker.log'));
        self::assertStringContainsString('network rm', file_get_contents($this->directory.'/docker.log'));
    }

    public function test_that_linked_worktree_metadata_is_mounted_read_only_into_the_gate_container(): void
    {
        file_put_contents(
            $this->directory.'/.git',
            'gitdir: '.$this->gitDirectory."/worktrees/fixture\n",
        );

        $process = $this->runBuild();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame(
            '-C '.$this->directory.' rev-parse --path-format=absolute --git-common-dir'."\n",
            file_get_contents($this->directory.'/git.log'),
        );
        self::assertStringContainsString(
            '-v '.$this->gitDirectory.':'.$this->gitDirectory.':ro',
            file_get_contents($this->directory.'/docker.log'),
        );
    }

    public function test_that_latest_updates_the_lock_and_runs_quality_in_the_same_owned_container(): void
    {
        $process = $this->runBuild(arguments: ['--latest']);

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame(
            "{\"packages\":[{\"name\":\"fixture/required-dependency\",\"version\":\"2.0.0\"}]}\n",
            file_get_contents($this->directory.'/composer.lock'),
        );

        $log = file_get_contents($this->directory.'/docker.log');
        self::assertSame(1, substr_count($log, 'container run --rm'));
        preg_match('/^container run --rm .*$/m', $log, $gateContainer);
        self::assertNotEmpty($gateContainer[0] ?? '');
        self::assertStringNotContainsString('-it', $gateContainer[0]);
        self::assertStringNotContainsString(' -i ', $gateContainer[0]);
        self::assertStringNotContainsString(' -t ', $gateContainer[0]);
        self::assertStringContainsString('--user 501:20', $log);
        self::assertStringContainsString(
            'composer update --no-interaction --no-progress --prefer-dist && ./bin/quality',
            $log,
        );
    }

    public function test_that_latest_preserves_the_updated_lock_when_quality_fails(): void
    {
        $process = $this->runBuild(41, ['--latest']);

        self::assertSame(41, $process->getExitCode());
        self::assertSame(
            "{\"packages\":[{\"name\":\"fixture/required-dependency\",\"version\":\"2.0.0\"}]}\n",
            file_get_contents($this->directory.'/composer.lock'),
        );
        self::assertStringContainsString(
            'composer update --no-interaction --no-progress --prefer-dist && ./bin/quality',
            file_get_contents($this->directory.'/docker.log'),
        );
    }

    public function test_that_latest_propagates_composer_failure(): void
    {
        $originalLock = file_get_contents($this->directory.'/composer.lock');

        $process = $this->runBuild(arguments: ['--latest'], composerStatus: 42);

        self::assertSame(42, $process->getExitCode());
        self::assertSame($originalLock, file_get_contents($this->directory.'/composer.lock'));
    }

    /**
     * @param int          $qualityStatus
     * @param list<string> $arguments
     * @param int          $composerStatus
     */
    private function runBuild(int $qualityStatus = 0, array $arguments = [], int $composerStatus = 0): Process
    {
        $process = new Process(
            ['bash', 'bin/build', ...$arguments],
            $this->directory,
            [
                'DOCKER_BIN' => $this->directory.'/docker',
                'FAKE_DOCKER_LOG' => $this->directory.'/docker.log',
                'FAKE_COMPOSER_STATUS' => (string) $composerStatus,
                'FAKE_GIT_COMMON_DIR' => $this->gitDirectory,
                'FAKE_GIT_LOG' => $this->directory.'/git.log',
                'FAKE_QUALITY_STATUS' => (string) $qualityStatus,
                'GIT_BIN' => $this->directory.'/git',
                'ID_BIN' => $this->directory.'/id',
            ],
            null,
            20,
        );
        $process->run();

        return $process;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
