<?php

declare(strict_types=1);

namespace Fight\Test\Release\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
/**
 * Class ReleaseArchitectureTest
 */
final class ReleaseArchitectureTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves compatibility policy and orchestration are not owned by scripts or adapters.
     */
    public function test_that_compatibility_policy_and_orchestration_are_application_owned(): void
    {
        $root = dirname(__DIR__, 3);
        $script = file_get_contents($root.'/release/scripts/release.php');

        self::assertIsString($script);
        self::assertFileExists($root.'/release/src/Application/CompatibilityAssessmentService.php');
        self::assertFileExists($root.'/release/src/Application/PublicApiManifestAuthority.php');
        self::assertFileExists($root.'/release/src/Application/StructuralApiComparison.php');
        self::assertFileExists($root.'/release/src/Adapter/GitBaselineStructuralInventory.php');
        self::assertFileExists($root.'/release/src/Adapter/LocalCompatibilityInput.php');
        self::assertFileExists($root.'/release/src/Adapter/LocalCompatibilityWorkspace.php');
        self::assertFileExists($root.'/release/src/Adapter/PhpParserStructuralInventory.php');
        self::assertFileDoesNotExist($root.'/release/src/Adapter/LocalCompatibilityRepository.php');
        self::assertFileDoesNotExist($root.'/release/src/Application/Boundary/CompatibilityRepositoryPort.php');
        self::assertFileDoesNotExist($root.'/release/src/Adapter/PublicApiManifestAuthority.php');
        self::assertFileDoesNotExist($root.'/release/src/Adapter/StructuralApiComparison.php');
        self::assertStringNotContainsString('function release_structural_inventory', $script);
        self::assertStringNotContainsString('function release_structural_checker', $script);
        self::assertStringNotContainsString('new PharData', $script);
        self::assertStringNotContainsString('new Symfony\\Component\\Process\\Process', $script);
        self::assertStringContainsString('new CompatibilityAssessmentService(', $script);
    }

    /**
     * Proves release dependency direction and complete architecture assignment.
     */
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
            '--report-skipped'
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
            '--no-cache'
        ], $root, null, null, 20);
        $classification->run();

        self::assertSame(0, $classification->getExitCode(), $classification->getErrorOutput());
        self::assertSame("There are no unassigned tokens.\n", $classification->getOutput());
    }
}
