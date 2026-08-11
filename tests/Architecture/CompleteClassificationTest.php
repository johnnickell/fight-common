<?php

declare(strict_types=1);

namespace Fight\Test\Common\Architecture;

use Fight\Common\Shared\IsolatedClass;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class CompleteClassificationTest extends UnitTestCase
{
    public function test_that_an_isolated_fight_common_class_is_rejected_as_unassigned(): void
    {
        $root = dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/deptrac',
            'debug:unassigned',
            '--config-file='.$root.'/tests/Architecture/Fixture/UnassignedClass/deptrac.php',
            '--no-cache',
        ], $root);
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();

        self::assertSame(2, $process->getExitCode(), $output);
        self::assertStringContainsString(IsolatedClass::class, $output);
    }
}
