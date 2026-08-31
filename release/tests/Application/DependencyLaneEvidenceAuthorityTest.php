<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Application\DependencyLaneEvidenceAuthority;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DependencyLaneEvidenceAuthority::class)]
/**
 * Class DependencyLaneEvidenceAuthorityTest
 */
final class DependencyLaneEvidenceAuthorityTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves complete lane receipts are accepted in canonical order.
     */
    public function test_that_validates_complete_ordered_dependency_lane_evidence(): void
    {
        $authority = new DependencyLaneEvidenceAuthority();
        $receipt = $this->receipt();

        self::assertTrue($authority->isValid($receipt));

        $receipt['lanes']['latest']['next_action'] = ['action' => 'wrong'];
        self::assertFalse($authority->isValid($receipt));
    }

    /**
     * Proves malformed, incomplete, and indeterminate lanes fail closed.
     */
    public function test_that_rejects_malformed_missing_or_indeterminate_lane_evidence(): void
    {
        $authority = new DependencyLaneEvidenceAuthority();
        $missing = $this->receipt();
        array_pop($missing['lanes']);
        $indeterminate = $this->receipt();
        $indeterminate['lanes']['yii']['status'] = 'indeterminate';

        self::assertFalse($authority->isValid(['schema_version' => 'wrong', 'status' => 'valid', 'lanes' => []]));
        self::assertFalse($authority->isValid($missing));
        self::assertFalse($authority->isValid($indeterminate));
    }

    /**
     * Proves retry actions resume only one known dependency lane.
     */
    public function test_that_retry_actions_are_limited_to_known_dependency_lanes(): void
    {
        $authority = new DependencyLaneEvidenceAuthority();

        self::assertTrue(
            $authority->isRetryAction(['action' => 'restore_locked_dependency_lane_and_retry'])
        );
        self::assertFalse(
            $authority->isRetryAction(['action' => 'restore_unknown_dependency_lane_and_retry'])
        );
        self::assertFalse($authority->isRetryAction(['action' => 'restore_locked_dependency_lane_and_retry', 'version' => '1']));
    }

    /**
     * Returns one canonical complete receipt
     *
     * @return array<string, mixed>
     */
    private function receipt(): array
    {
        $lanes = [];
        foreach (DependencyLaneEvidenceAuthority::LANES as $lane) {
            $lanes[$lane] = [
                'name'        => $lane,
                'status'      => 'passed',
                'lock_sha256' => str_repeat('a', 64),
                'resolved'    => ['johnnickell/fight-common' => 'dev-main'],
                'probes'      => ['public_api' => 'passed'],
                'next_action' => ['action' => 'review_dependency_lane_evidence']
            ];
        }

        return ['schema_version' => 'fight-common.dependency-lanes/v1', 'status' => 'valid', 'lanes' => $lanes];
    }
}
