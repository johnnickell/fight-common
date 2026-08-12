<?php

declare(strict_types=1);

namespace Fight\Test\Common\Architecture;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class RuntimeDirectionTest extends UnitTestCase
{
    public function test_that_outward_runtime_dependencies_are_rejected(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/deptrac',
            '--config-file='.$root.'/tests/Architecture/Fixture/RuntimeDirection/deptrac.php',
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
        self::assertSame(3, $report['Report']['Violations']);
        self::assertSame(0, $report['Report']['Skipped violations']);
        self::assertSame(0, $report['Report']['Uncovered']);
        self::assertStringContainsString('DomainDependsOnApplication must not depend', $output);
        self::assertStringContainsString('DomainDependsOnAdapter must not depend', $output);
        self::assertStringContainsString('ApplicationDependsOnAdapter must not depend', $output);
    }
}
