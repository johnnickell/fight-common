<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class CoverageGateTest extends UnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/fight-common-coverage-gate-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/src', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_that_coverage_gate_rejects_every_ignore_directive_before_validating_clover(): void
    {
        foreach (['@codeCoverageIgnore', '@codeCoverageIgnoreStart', '@codeCoverageIgnoreEnd'] as $directive) {
            file_put_contents($this->directory.'/src/Example.php', "<?php\n\n// {$directive}\n");

            $result = $this->runCoverageGate();

            self::assertSame(1, $result->getExitCode());
            self::assertSame(
                "Coverage-ignore directive found in production PHP: src/Example.php\n",
                $result->getErrorOutput(),
            );
        }

        file_put_contents($this->directory.'/src/Example.php', "<?php\n");

        $result = $this->runCoverageGate();

        self::assertSame(1, $result->getExitCode());
        self::assertSame(
            "Clover report not found: var/reports/coverage/clover.xml\n",
            $result->getErrorOutput(),
        );
    }

    public function test_that_coverage_gate_accepts_only_exact_valid_project_statement_metrics(): void
    {
        $reports = [
            'malformed XML' => [
                '<coverage><project>',
                1,
                "Clover report is malformed: var/reports/coverage/clover.xml\n",
            ],
            'missing project metrics' => [
                '<?xml version="1.0"?><coverage><project /></coverage>',
                1,
                "Clover project statement metrics are missing: var/reports/coverage/clover.xml\n",
            ],
            'missing covered statements' => [
                '<?xml version="1.0"?><coverage><project><metrics statements="12" /></project></coverage>',
                1,
                "Clover project statement metrics are missing: var/reports/coverage/clover.xml\n",
            ],
            'missing statements' => [
                '<?xml version="1.0"?><coverage><project><metrics coveredstatements="12" /></project></coverage>',
                1,
                "Clover project statement metrics are missing: var/reports/coverage/clover.xml\n",
            ],
            'non-integer statements' => [
                '<?xml version="1.0"?><coverage><project><metrics statements="twelve" coveredstatements="12" /></project></coverage>',
                1,
                "Clover project statement metrics must be non-negative integers: var/reports/coverage/clover.xml\n",
            ],
            'non-integer covered statements' => [
                '<?xml version="1.0"?><coverage><project><metrics statements="12" coveredstatements="twelve" /></project></coverage>',
                1,
                "Clover project statement metrics must be non-negative integers: var/reports/coverage/clover.xml\n",
            ],
            'negative covered statements' => [
                '<?xml version="1.0"?><coverage><project><metrics statements="12" coveredstatements="-1" /></project></coverage>',
                1,
                "Clover project statement metrics must be non-negative integers: var/reports/coverage/clover.xml\n",
            ],
            'contradictory statement metrics' => [
                '<?xml version="1.0"?><coverage><project><metrics statements="12" coveredstatements="13" /></project></coverage>',
                1,
                "Clover project statement metrics are contradictory: 13 covered exceeds 12 statements\n",
            ],
            'incomplete statement coverage' => [
                '<?xml version="1.0"?><coverage><project><metrics statements="12" coveredstatements="11" /></project></coverage>',
                1,
                "Statement coverage is incomplete: 11/12 statements covered\n",
            ],
            'exact statement coverage' => [
                '<?xml version="1.0"?><coverage><project><metrics statements="12" coveredstatements="12" /></project></coverage>',
                0,
                "Statement coverage is exact: 12/12 statements covered\n",
            ],
        ];

        mkdir($this->directory.'/var/reports/coverage', 0777, true);

        foreach ($reports as $case => [$report, $expectedExitCode, $expectedOutput]) {
            file_put_contents($this->directory.'/var/reports/coverage/clover.xml', $report);

            $result = $this->runCoverageGate();

            self::assertSame($expectedExitCode, $result->getExitCode(), $case);
            self::assertSame(
                $expectedOutput,
                $expectedExitCode === 0 ? $result->getOutput() : $result->getErrorOutput(),
                $case,
            );
        }
    }

    public function test_that_coverage_gate_fails_when_production_source_cannot_be_scanned(): void
    {
        $this->removeDirectory($this->directory.'/src');
        mkdir($this->directory.'/var/reports/coverage', 0777, true);
        file_put_contents(
            $this->directory.'/var/reports/coverage/clover.xml',
            '<?xml version="1.0"?><coverage><project><metrics statements="12" coveredstatements="12" /></project></coverage>',
        );

        $result = $this->runCoverageGate();

        self::assertSame(1, $result->getExitCode());
        self::assertSame("Unable to scan production PHP: src\n", $result->getErrorOutput());
    }

    private function runCoverageGate(): Process
    {
        $process = new Process(
            ['bash', dirname(__DIR__, 2).'/bin/coverage'],
            $this->directory,
            null,
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
