<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Adapter\DependencyLaneVerifier;
use Fight\Release\Application\DependencyLaneEvidenceAuthority;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DependencyLaneVerifierTest
 */
#[CoversClass(DependencyLaneVerifier::class)]
final class DependencyLaneVerifierTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves every real dependency mode and selected framework stack resolves independently.
     */
    public function test_that_dependency_lanes_resolve_and_isolate_each_selected_stack(): void
    {
        $workspace = sys_get_temp_dir().'/fight-common-dependency-lanes-'.bin2hex(random_bytes(8));
        mkdir($workspace, 0700, true);

        try {
            $receipt = new DependencyLaneVerifier()->verify(dirname(__DIR__, 3), $workspace);

            self::assertTrue((new DependencyLaneEvidenceAuthority())->isValid($receipt));
            self::assertSame(DependencyLaneEvidenceAuthority::LANES, array_keys($receipt['lanes']));
            foreach ($receipt['lanes'] as $lane) {
                self::assertSame('passed', $lane['status']);
                self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $lane['lock_sha256']);
            }
        } finally {
            new Filesystem()->remove($workspace);
        }
    }

    /**
     * Proves a resolver failure remains attributed to one resumable lane.
     */
    public function test_that_a_failed_lane_returns_one_attributed_retry_action(): void
    {
        $workspace = sys_get_temp_dir().'/fight-common-dependency-lane-failure-'.bin2hex(random_bytes(8));
        mkdir($workspace, 0700, true);

        try {
            $receipt = new DependencyLaneVerifier()->verify($workspace.'/missing', $workspace);

            self::assertSame('invalid', $receipt['status']);
            self::assertSame('locked', $receipt['failure']['lane']);
            self::assertSame(
                ['action' => 'restore_locked_dependency_lane_and_retry'],
                $receipt['failure']['next_action']
            );
        } finally {
            new Filesystem()->remove($workspace);
        }
    }
}
