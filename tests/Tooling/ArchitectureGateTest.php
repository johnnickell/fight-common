<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ArchitectureGateTest extends UnitTestCase
{
    public function test_that_ci_runs_the_repository_architecture_gate(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml');

        self::assertIsString($workflow);
        self::assertStringContainsString(
            <<<'YAML'
      - name: Enforce architecture
        run: |
          php vendor/bin/deptrac --fail-on-uncovered --report-uncovered --report-skipped
          php vendor/bin/deptrac debug:unassigned --no-cache
YAML,
            $workflow,
        );
    }

    public function test_that_documented_architecture_gates_delegate_to_executables_that_reject_unassigned_classes(): void
    {
        $root = dirname(__DIR__, 2);
        $wrapper = file_get_contents($root.'/bin/deptrac');
        $instructions = file_get_contents($root.'/CLAUDE.md');
        $qualityGate = file_get_contents($root.'/bin/quality');

        self::assertIsString($wrapper);
        self::assertIsString($instructions);
        self::assertIsString($qualityGate);
        self::assertStringContainsString(
            'fight-common php vendor/bin/deptrac debug:unassigned --no-cache',
            $wrapper,
        );
        self::assertStringContainsString(
            '`./bin/quality` is the single host-neutral ordered gate',
            $instructions,
        );
        self::assertStringContainsString(
            'php vendor/bin/deptrac --fail-on-uncovered --report-uncovered --report-skipped',
            $qualityGate,
        );
        self::assertStringContainsString(
            'php vendor/bin/deptrac debug:unassigned --no-cache',
            $qualityGate,
        );
    }
}
