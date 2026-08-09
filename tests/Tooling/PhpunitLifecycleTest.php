<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class PhpunitLifecycleTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/fight-common-phpunit-lifecycle-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -eu

printf '%q ' "$@" >> "${FAKE_DOCKER_LOG}"
printf '\n' >> "${FAKE_DOCKER_LOG}"

if [[ "${1:-}" == "inspect" ]]; then
    if [[ "${FAKE_DOCKER_MODE:-}" == "health-failure" ]]; then
        echo unhealthy
    else
        echo healthy
    fi
    exit 0
fi

if [[ "${1:-}" == "container" && "${2:-}" == "run" && " ${*} " == *" php vendor/bin/phpunit "* ]]; then
    if [[ "${FAKE_DOCKER_MODE:-}" == "test-failure" ]]; then
        exit 23
    fi
    if [[ "${FAKE_DOCKER_MODE:-}" == "interrupt" ]]; then
        sleep 60
    fi
fi
BASH
        );
        chmod($this->directory.'/docker', 0755);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    public function test_that_complete_mode_pins_services_injects_dsns_and_cleans_up(): void
    {
        $result = $this->runLifecycle();

        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        $log = $this->log();
        self::assertStringContainsString('mysql:8.4.11', $log);
        self::assertStringContainsString('postgres:17', $log);
        self::assertStringContainsString('FIGHT_COMMON_MYSQL_DSN=mysql://fight_common:fight_common@mysql:3306/fight_common', $log);
        self::assertStringContainsString('FIGHT_COMMON_POSTGRESQL_DSN=postgresql://fight_common:fight_common@postgresql:5432/fight_common', $log);
        self::assertStringContainsString('container rm --force', $log);
        self::assertStringContainsString('network rm', $log);

        preg_match_all('/fight-common-test-network-[^ ]+/', $log, $networks);
        self::assertNotEmpty($networks[0]);
        self::assertCount(1, array_unique($networks[0]));
    }

    public function test_that_each_complete_run_uses_a_new_resource_name(): void
    {
        $this->runLifecycle();
        $firstLog = $this->log();
        $firstNetwork = $this->resourceName($firstLog, 'network create');

        file_put_contents($this->directory.'/docker.log', '');
        $this->runLifecycle();
        $secondNetwork = $this->resourceName($this->log(), 'network create');

        self::assertNotSame($firstNetwork, $secondNetwork);
    }

    public function test_that_fast_mode_excludes_server_databases_before_running_phpunit(): void
    {
        $result = $this->runLifecycle(['--fast']);

        self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        $log = $this->log();
        self::assertStringContainsString('--exclude-group server-database', $log);
        self::assertStringNotContainsString('network create', $log);
        self::assertStringNotContainsString('mysql:8.4.11', $log);
        self::assertStringNotContainsString('postgres:17', $log);
    }

    public function test_that_health_failure_propagates_and_cleans_up_before_phpunit(): void
    {
        $result = $this->runLifecycle([], 'health-failure');

        self::assertNotSame(0, $result->getExitCode());
        self::assertStringContainsString('did not become healthy', $result->getErrorOutput());
        self::assertStringNotContainsString('php vendor/bin/phpunit', $this->log());
        self::assertStringContainsString('container rm --force', $this->log());
        self::assertStringContainsString('network rm', $this->log());
    }

    public function test_that_phpunit_exit_status_is_preserved_after_cleanup(): void
    {
        $result = $this->runLifecycle([], 'test-failure');

        self::assertSame(23, $result->getExitCode());
        self::assertStringContainsString('container rm --force', $this->log());
        self::assertStringContainsString('network rm', $this->log());
    }

    public function test_that_interruption_still_cleans_up_the_run_resources(): void
    {
        $process = new Process(
            ['bash', __DIR__.'/../../bin/phpunit'],
            dirname(__DIR__, 2),
            [
                'DOCKER_BIN' => $this->directory.'/docker',
                'FAKE_DOCKER_LOG' => $this->directory.'/docker.log',
                'FAKE_DOCKER_MODE' => 'interrupt',
            ],
            null,
            20,
        );
        $process->start();
        usleep(100_000);
        $process->stop(1, SIGTERM);

        self::assertStringContainsString('container rm --force', $this->log());
        self::assertStringContainsString('network rm', $this->log());
    }

    private function runLifecycle(array $arguments = [], ?string $mode = null): Process
    {
        $process = new Process(
            array_merge(['bash', __DIR__.'/../../bin/phpunit'], $arguments),
            dirname(__DIR__, 2),
            [
                'DOCKER_BIN' => $this->directory.'/docker',
                'FAKE_DOCKER_LOG' => $this->directory.'/docker.log',
                'FAKE_DOCKER_MODE' => $mode ?? '',
            ],
            null,
            20,
        );
        $process->run();

        return $process;
    }

    private function log(): string
    {
        return (string) file_get_contents($this->directory.'/docker.log');
    }

    private function resourceName(string $log, string $operation): string
    {
        preg_match('/'.preg_quote($operation, '/').' ([^ ]+)/', $log, $matches);

        return $matches[1] ?? '';
    }
}
