<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Application\ReleasePreparationArtifactFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
/**
 * Class ReleasePreparationArtifactFactoryTest
 *
 * Covers canonical preparation evidence and handoff contracts.
 */
#[CoversClass(ReleasePreparationArtifactFactory::class)]
final class ReleasePreparationArtifactFactoryTest extends UnitTestCase
{
    /**
     * Covers known canonical fixture bytes and identities without using the production hashing boundary.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_prepared_artifacts_bind_the_complete_phase_authority(): void
    {
        $plan = [
            'approved_version'            => '1.3.0',
            'source_commit_oid'           => str_repeat('c', 40),
            'baseline'                    => [
                'version'           => '1.2.3',
                'tag_name'          => 'v1.2.3',
                'tag_object_oid'    => str_repeat('a', 40),
                'peeled_commit_oid' => str_repeat('b', 40)
            ],
            'support_policy_identity'     => 'support-policy-2026-08',
            'expected_effect_classes'     => ['filesystem.write'],
            'evidence_requirements'       => ['full-submit-gate'],
            'evidence_manifest_digest'    => str_repeat('6', 64),
            'compatibility_exceptions'    => ['compatibility-001'],
            'patch_exception_authorities' => [['authority_id' => 'patch-001']],
            'minimum_release_class'       => 'minor',
            'release_class'               => 'minor',
            'required_approvals'          => ['release-approval-001'],
            'release_approval_authority'  => [
                'approval_id' => 'release-approval-001'
            ]
        ];
        $factory = new ReleasePreparationArtifactFactory();
        $manifest = $factory->manifest(
            str_repeat('1', 64),
            str_repeat('2', 64),
            $plan,
            'prepared',
            ['immutable_plan_revalidated', 'prepared_run_projection_published'],
            [
                'history_sha256'    => str_repeat('3', 64),
                'projection_sha256' => str_repeat('4', 64)
            ],
            null,
            ['action' => 'package_release_run']
        );
        self::assertSame([
            'approved_version'            => '1.3.0',
            'baseline'                    => [
                'version'           => '1.2.3',
                'peeled_commit_oid' => str_repeat('b', 40),
                'tag_name'          => 'v1.2.3',
                'tag_object_oid'    => str_repeat('a', 40)
            ],
            'compatibility_exceptions'    => ['compatibility-001'],
            'evidence_manifest_digest'    => str_repeat('6', 64),
            'evidence_requirements'       => ['full-submit-gate'],
            'expected_effect_classes'     => ['filesystem.write'],
            'minimum_release_class'       => 'minor',
            'patch_exception_authorities' => [['authority_id' => 'patch-001']],
            'release_class'               => 'minor',
            'source_commit_oid'           => str_repeat('c', 40),
            'support_policy_identity'     => 'support-policy-2026-08'
        ], $manifest['bindings']);

        $handoff = $factory->handoff($manifest, str_repeat('5', 64));
        self::assertSame(
            [...$manifest['bindings'], 'evidence_manifest_id' => str_repeat('5', 64)],
            $handoff['bindings']
        );
        self::assertSame('prepared', $handoff['status']);
        self::assertSame([
            'mode'                              => 'projection_bound',
            'projection_must_bind_artifact_ids' => true,
            'required_projection_state'         => 'prepared'
        ], $handoff['activation']);
        self::assertNull($handoff['stop_state']);
        self::assertSame(['action' => 'package_release_run'], $handoff['next_action']);
        self::assertSame(
            [
                'history_sha256'    => str_repeat('3', 64),
                'postconditions'    => [
                    'immutable_plan_revalidated',
                    'prepared_run_projection_published'
                ],
                'projection_sha256' => str_repeat('4', 64)
            ],
            $handoff['verified_evidence']
        );

        $stopManifest = $factory->manifest(
            str_repeat('1', 64),
            str_repeat('2', 64),
            $plan,
            'stale_plan',
            [],
            [],
            [
                'finding_id' => 'release.prepare.baseline_resolution_drift',
                'status'     => 'stale_plan'
            ],
            ['action' => 'create_current_release_plan']
        );
        self::assertSame([
            'mode'                              => 'projection_bound',
            'projection_must_bind_artifact_ids' => true,
            'required_projection_state'         => 'stale_plan'
        ], $factory->handoff($stopManifest, str_repeat('7', 64))['activation']);

        $evidenceOnly = $factory->manifest(
            str_repeat('1', 64),
            str_repeat('2', 64),
            $plan,
            'evidence_indeterminate',
            [],
            [],
            [
                'finding_id' => 'release.prepare.resume_state_missing',
                'status'     => 'evidence_indeterminate'
            ],
            ['action' => 'restore_named_release_run_evidence'],
            ReleasePreparationArtifactFactory::ACTIVATION_EVIDENCE_ONLY
        );
        self::assertSame([
            'mode'                              => 'evidence_only',
            'projection_must_bind_artifact_ids' => false
        ], $evidenceOnly['activation']);

        $this->expectException(InvalidArgumentException::class);
        $factory->manifest(
            str_repeat('1', 64),
            str_repeat('2', 64),
            $plan,
            'evidence_indeterminate',
            [],
            [],
            null,
            ['action' => 'repair'],
            'unknown'
        );
    }
}
