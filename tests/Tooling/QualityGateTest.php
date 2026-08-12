<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class QualityGateTest extends UnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fight-common-quality-gate-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/bin', 0777, true);
        mkdir($this->directory.'/src', 0777, true);
        mkdir($this->directory.'/tests', 0777, true);

        foreach (['deptrac.php', 'rector.php', 'src/Example.php', 'tests/ExampleTest.php'] as $phpFile) {
            file_put_contents($this->directory.'/'.$phpFile, "<?php\n");
        }

        $this->writeCommand('composer');
        $this->writeCommand('php');
        $this->writeCommand('planning-check');
        $this->writeCommand('coverage');

        $qualityGate = dirname(__DIR__, 2).'/bin/quality';
        if (is_file($qualityGate)) {
            copy($qualityGate, $this->directory.'/bin/quality');
            chmod($this->directory.'/bin/quality', 0755);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    public function test_that_quality_gate_announces_and_executes_every_step_once_in_the_accepted_order(): void
    {
        $process = new Process(
            ['bash', 'bin/quality'],
            $this->directory,
            [
                'PATH' => $this->directory.'/bin:'.(getenv('PATH') ?: ''),
                'QUALITY_GATE_COMMAND_LOG' => $this->directory.'/commands.log',
            ],
            null,
            20,
        );
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame(
            <<<'OUTPUT'
[quality] Composer validation
[quality] PHP syntax
[quality] Planning integrity
[quality] PHPCS
[quality] PHPStan
[quality] Architecture
[quality] Rector dry-run
[quality] PHPUnit
[quality] Exact coverage

OUTPUT,
            $process->getOutput(),
        );
        self::assertSame(
            <<<'COMMANDS'
composer validate --strict --no-interaction
php -l deptrac.php
php -l rector.php
php -l src/Example.php
php -l tests/ExampleTest.php
planning-check
php vendor/bin/phpcs
php vendor/bin/phpstan analyse
php vendor/bin/deptrac --fail-on-uncovered --report-uncovered --report-skipped
php vendor/bin/deptrac debug:unassigned --no-cache
php vendor/bin/rector process src/ --dry-run
php vendor/bin/phpunit --fail-on-skipped
coverage

COMMANDS,
            file_get_contents($this->directory.'/commands.log'),
        );
    }

    #[DataProvider('failing_command_provider')]
    public function test_that_quality_gate_stops_at_the_first_failing_command_and_preserves_its_status(
        string $failingCommandPrefix,
        int $failingStatus,
    ): void {
        $process = new Process(
            ['bash', 'bin/quality'],
            $this->directory,
            [
                'PATH' => $this->directory.'/bin:'.(getenv('PATH') ?: ''),
                'QUALITY_GATE_COMMAND_LOG' => $this->directory.'/commands.log',
                'QUALITY_GATE_FAIL_PREFIX' => $failingCommandPrefix,
                'QUALITY_GATE_FAIL_STATUS' => (string) $failingStatus,
            ],
            null,
            20,
        );
        $process->run();

        self::assertSame($failingStatus, $process->getExitCode());
        self::assertStringEndsWith(
            $failingCommandPrefix."\n",
            file_get_contents($this->directory.'/commands.log'),
        );
    }

    #[DataProvider('coverage_replacement_provider')]
    public function test_that_quality_gate_removes_stale_clover_before_phpunit_and_enforces_only_its_replacement(
        ?string $replacement,
        int $expectedStatus,
        string $expectedCoverageOutput,
    ): void {
        mkdir($this->directory.'/var/reports/coverage', 0777, true);
        file_put_contents($this->directory.'/var/reports/coverage/clover.xml', 'stale Clover report');

        copy(dirname(__DIR__, 2).'/bin/coverage', $this->directory.'/bin/coverage');
        chmod($this->directory.'/bin/coverage', 0755);

        $environment = [
            'PATH' => $this->directory.'/bin:'.(getenv('PATH') ?: ''),
            'QUALITY_GATE_COMMAND_LOG' => $this->directory.'/commands.log',
            'QUALITY_GATE_OBSERVE_CLOVER' => '1',
            'QUALITY_GATE_REAL_PHP' => PHP_BINARY,
        ];
        if ($replacement !== null) {
            $environment['QUALITY_GATE_REPLACEMENT_CLOVER'] = $replacement;
        }

        $process = new Process(['bash', 'bin/quality'], $this->directory, $environment, null, 20);
        $process->run();

        self::assertSame($expectedStatus, $process->getExitCode());
        self::assertSame('<missing>', file_get_contents($this->directory.'/phpunit-clover-before.log'));
        self::assertSame(
            $expectedCoverageOutput,
            $expectedStatus === 0 ? $process->getOutput() : $process->getErrorOutput(),
        );
    }

    public static function failing_command_provider(): iterable
    {
        yield 'early Composer failure' => ['composer validate --strict --no-interaction', 11];
        yield 'middle PHPStan failure' => ['php vendor/bin/phpstan analyse', 22];
        yield 'first Deptrac failure' => [
            'php vendor/bin/deptrac --fail-on-uncovered --report-uncovered --report-skipped',
            31,
        ];
        yield 'second Deptrac failure' => ['php vendor/bin/deptrac debug:unassigned --no-cache', 32];
        yield 'late coverage failure' => ['coverage', 47];
    }

    public static function coverage_replacement_provider(): iterable
    {
        yield 'no replacement' => [
            null,
            1,
            "Clover report not found: var/reports/coverage/clover.xml\n",
        ];
        yield 'current exact replacement' => [
            <<<'CLOVER'
<?xml version="1.0"?>
<coverage>
    <project>
        <metrics statements="12" coveredstatements="12" />
    </project>
</coverage>
CLOVER,
            0,
            <<<'OUTPUT'
[quality] Composer validation
[quality] PHP syntax
[quality] Planning integrity
[quality] PHPCS
[quality] PHPStan
[quality] Architecture
[quality] Rector dry-run
[quality] PHPUnit
[quality] Exact coverage
Statement coverage is exact: 12/12 statements covered

OUTPUT,
        ];
    }

    private function writeCommand(string $name): void
    {
        $command = <<<'BASH'
#!/usr/bin/env bash
set -eu

command_line="$(basename "$0")"
printf '%s' "${command_line}" >> "${QUALITY_GATE_COMMAND_LOG}"
if (( $# > 0 )); then
    printf ' %s' "$@" >> "${QUALITY_GATE_COMMAND_LOG}"
    printf -v command_line '%s %s' "${command_line}" "$*"
fi
printf '\n' >> "${QUALITY_GATE_COMMAND_LOG}"

if [[ -n "${QUALITY_GATE_FAIL_PREFIX:-}" && "${command_line}" == "${QUALITY_GATE_FAIL_PREFIX}"* ]]; then
    exit "${QUALITY_GATE_FAIL_STATUS}"
fi

if [[ "${command_line}" == 'php vendor/bin/phpunit --fail-on-skipped' && -n "${QUALITY_GATE_OBSERVE_CLOVER:-}" ]]; then
    clover_report='var/reports/coverage/clover.xml'
    if [[ -e "${clover_report}" ]]; then
        printf '<present>' > phpunit-clover-before.log
    else
        printf '<missing>' > phpunit-clover-before.log
    fi
    if [[ -n "${QUALITY_GATE_REPLACEMENT_CLOVER:-}" ]]; then
        mkdir -p "$(dirname "${clover_report}")"
        printf '%s' "${QUALITY_GATE_REPLACEMENT_CLOVER}" > "${clover_report}"
    fi
fi

if [[ "${1:-}" == '-r' && -n "${QUALITY_GATE_REAL_PHP:-}" ]]; then
    exec "${QUALITY_GATE_REAL_PHP}" "$@"
fi
BASH;

        file_put_contents($this->directory.'/bin/'.$name, $command);
        chmod($this->directory.'/bin/'.$name, 0755);
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
