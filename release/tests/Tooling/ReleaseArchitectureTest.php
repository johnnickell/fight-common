<?php

declare(strict_types=1);

namespace Fight\Test\Release\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class ReleaseArchitectureTest extends UnitTestCase
{
    public function test_that_runtime_cannot_depend_on_release_while_release_adapter_direction_is_assigned(): void
    {
        $root = dirname(__DIR__, 3);
        $config = $root.'/release/fixtures/Architecture/ReleaseDirection/deptrac.php';
        $analysis = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/deptrac',
            '--config-file='.$config,
            '--no-cache',
            '--fail-on-uncovered',
            '--report-uncovered',
            '--report-skipped',
        ], $root, null, null, 20);
        $analysis->run();

        self::assertSame(1, $analysis->getExitCode(), $analysis->getOutput().$analysis->getErrorOutput());
        self::assertStringContainsString('Fight\\Common\\Application\\RuntimeConsumer', $analysis->getOutput());
        self::assertStringContainsString('Fight\\Release\\Application\\ReleaseTool', $analysis->getOutput());

        $classification = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/deptrac',
            '--config-file='.$config,
            'debug:unassigned',
            '--no-cache',
        ], $root, null, null, 20);
        $classification->run();

        self::assertSame(0, $classification->getExitCode(), $classification->getErrorOutput());
        self::assertSame("There are no unassigned tokens.\n", $classification->getOutput());
    }
}
