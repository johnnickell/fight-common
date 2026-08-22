<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Application\Boundary\ReleaseBoundaryCrash;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class ReleaseCrashJourneyTest
 *
 * Covers deliberately abnormal release crash subprocess journeys.
 */
#[CoversNothing]
class ReleaseCrashJourneyTest extends UnitTestCase
{
    /**
     * Covers an inspect Git crash as non-JSON subprocess interruption.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_inspect_git_crash_exits_nonzero_without_a_normal_machine_result(): void
    {
        $root = dirname(__DIR__, 3);
        $process = ReleaseProcess::create([
            $root.'/bin/release',
            'inspect',
            '--fixture='.$root.'/release/fixtures/boundary-crash.json'
        ]);

        $process->run();

        self::assertNotSame(0, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        $diagnostic = $process->getOutput().$process->getErrorOutput();
        self::assertStringContainsString(ReleaseBoundaryCrash::class, $diagnostic);
        self::assertStringContainsString('git.inspect_repository', $diagnostic);
    }

    /**
     * Covers a plan artifact-write crash as interruption before persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_artifact_write_crash_exits_nonzero_without_json_or_a_persisted_artifact(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/release-plan-crash-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/release/fixtures/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $fixture = $output.'/plan-crash.json';
        file_put_contents($fixture, json_encode([
            ...$candidate,
            'boundary' => ['effect_class' => 'filesystem.write', 'outcome' => 'crash']
        ], JSON_THROW_ON_ERROR));

        try {
            $process = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$fixture,
                '--output='.$output
            ]);

            $process->run();

            self::assertNotSame(0, $process->getExitCode());
            self::assertSame('', $process->getOutput());
            $diagnostic = $process->getOutput().$process->getErrorOutput();
            self::assertStringContainsString(ReleaseBoundaryCrash::class, $diagnostic);
            self::assertStringContainsString('filesystem.write', $diagnostic);
            self::assertSame([$fixture], glob($output.'/*') ?: []);
        } finally {
            unlink($fixture);
            rmdir($output);
        }
    }
}
// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
