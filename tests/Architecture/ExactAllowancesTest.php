<?php

declare(strict_types=1);

namespace Fight\Test\Common\Architecture;

use Cron\FieldFactory;
use Fight\Common\Shared\UnassignedDependency;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class ExactAllowancesTest extends UnitTestCase
{
    public function test_that_exact_allowances_reject_forbidden_and_unassigned_dependencies(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/deptrac',
            '--config-file='.$root.'/tests/Architecture/Fixture/ExactAllowances/deptrac.php',
            '--formatter=json',
            '--fail-on-uncovered',
            '--report-skipped',
            '--no-cache',
            '--no-progress',
        ], $root);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();
        $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $process->getExitCode(), $output);
        self::assertSame(4, $report['Report']['Violations']);
        self::assertSame(0, $report['Report']['Skipped violations']);
        self::assertSame(2, $report['Report']['Uncovered']);
        self::assertSame(0, $report['Report']['Allowed']);
        self::assertStringContainsString('ApplicationDependsOnSymfonyProcess must not depend', $output);
        self::assertStringContainsString('DomainDependsOnPsr must not depend', $output);
        self::assertStringContainsString('ApplicationDependsOnStandards must not depend', $output);
        self::assertStringContainsString('StandardsDependsOnDomain must not depend', $output);

        $unassigned = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/deptrac',
            'debug:unassigned',
            '--config-file='.$root.'/tests/Architecture/Fixture/ExactAllowances/deptrac.php',
            '--no-cache',
        ], $root);
        $unassigned->run();

        $unassignedOutput = $unassigned->getOutput().$unassigned->getErrorOutput();

        self::assertSame(2, $unassigned->getExitCode(), $unassignedOutput);
        self::assertStringContainsString(FieldFactory::class, $unassignedOutput);
        self::assertStringContainsString(UnassignedDependency::class, $unassignedOutput);
    }

    public function test_that_each_layer_accepts_only_its_documented_dependencies(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/deptrac',
            '--config-file='.$root.'/tests/Architecture/Fixture/ValidAllowances/deptrac.php',
            '--formatter=json',
            '--fail-on-uncovered',
            '--report-skipped',
            '--no-cache',
            '--no-progress',
        ], $root);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();
        $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $process->getExitCode(), $output);
        self::assertSame(0, $report['Report']['Violations']);
        self::assertSame(0, $report['Report']['Skipped violations']);
        self::assertSame(0, $report['Report']['Uncovered']);
        self::assertSame(13, $report['Report']['Allowed']);
    }
}
