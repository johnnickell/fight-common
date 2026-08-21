<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release;

use Fight\Common\Adapter\Release\Fake\DeterministicReleaseBoundaryFake;
use Fight\Common\Application\Release\Boundary\CanonicalRunsDirectory;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryCrash;
use Fight\Common\Application\Release\CanonicalJson;
use Fight\Common\Application\Release\CompatibilityAssessment;
use Fight\Common\Application\Release\ReleaseInspectionService;
use Fight\Common\Application\Release\ReleasePlanFactory;
use Fight\Common\Application\Release\ReleasePlanService;
use Fight\Common\Application\Release\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class ReleaseBoundaryCrashTest
 *
 * Covers abrupt deterministic boundary interruption at the Application seam.
 */
#[CoversClass(ReleaseBoundaryCrash::class)]
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
#[CoversClass(ReleaseInspectionService::class)]
#[CoversClass(ReleasePlanService::class)]
class ReleaseBoundaryCrashTest extends UnitTestCase
{
    /**
     * Covers every exact deterministic effect as a controllable abrupt interruption.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_each_configured_crash_records_only_its_attempt_and_throws_the_typed_interruption(): void
    {
        $directory = sys_get_temp_dir().'/release-boundary-crashes-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory.'/artifact.json';
        file_put_contents($path, 'fixture');
        $operations = [
            'filesystem.read'                   => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->read($path),
            'filesystem.write'                  => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->writeArtifact(
                    new CanonicalRunsDirectory($directory, $directory),
                    'written.json',
                    'contents'
                ),
            'filesystem.inspect_directory'      => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->isDirectory($directory),
            'filesystem.inspect_writable'       => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->isWritable($directory),
            'filesystem.inspect_exists'         => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->exists($path),
            'filesystem.inspect_runs_directory' => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->resolveRunsDirectory($directory, $directory),
            'git.inspect_repository'            => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->inspectRepository(),
            'git.resolve_ref'                   => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->resolveBaselineTag('v1.2.3', str_repeat('d', 40)),
            'hashing.sha256'                    => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->sha256('contents'),
            'clock.now'                         => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->now(),
            'signing.verify'                    => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->verify(),
            'authorization.check'               => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->check(),
            'github.release'                    => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->release(),
            'packagist.publish'                 => static fn (DeterministicReleaseBoundaryFake $fake): mixed =>
                $fake->publish()
        ];

        try {
            foreach ($operations as $effectClass => $operation) {
                $fake = new DeterministicReleaseBoundaryFake([$effectClass => 'crash']);

                try {
                    $operation($fake);
                    self::fail($effectClass.' returned ordinary data instead of crashing.');
                } catch (ReleaseBoundaryCrash $crash) {
                    self::assertSame($effectClass, $crash->effectClass);
                    self::assertSame(
                        'Deterministic release boundary crashed at '.$effectClass.'.',
                        $crash->getMessage()
                    );
                }

                self::assertSame($effectClass, $fake->effects()[0]['effect_class']);
                self::assertSame('crash', $fake->effects()[0]['outcome']);
                self::assertCount(1, $fake->effects());
            }

            self::assertFileDoesNotExist($directory.'/written.json');
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    /**
     * Covers an inspect Git crash escaping Application without a normal machine result.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_inspect_git_crash_interrupts_before_a_machine_result_or_later_effect(): void
    {
        $effects = new DeterministicReleaseBoundaryFake();

        try {
            (new ReleaseInspectionService(new ReleaseResultFactory()))->inspect([
                'source_commit'          => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
                'baseline'               => [
                    'version'    => '1.2.3',
                    'tag_name'   => 'v1.2.3',
                    'tag_object' => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                    'commit'     => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
                ],
                'support_policy'         => 'support-policy-2026-08',
                'compatibility_evidence' => array_map(
                    static fn (string $category): array => [
                        'category'       => $category,
                        'finding_id'     => 'release.compatibility.'.$category.'.fixture',
                        'evidence_id'    => 'evidence.compatibility.'.$category.'.fixture',
                        'classification' => 'minor'
                    ],
                    CompatibilityAssessment::CATEGORIES
                ),
                'boundary'               => ['effect_class' => 'git.inspect_repository', 'outcome' => 'crash']
            ], $effects);
            self::fail('Inspection returned a normal machine result after the configured crash.');
        } catch (ReleaseBoundaryCrash $crash) {
            self::assertSame('git.inspect_repository', $crash->effectClass);
        }

        self::assertSame([[
            'capability'   => 'git',
            'effect_class' => 'git.inspect_repository',
            'outcome'      => 'crash'
        ]], $effects->effects());
    }

    /**
     * Covers a plan artifact-write crash leaving no artifact or post-write effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_artifact_write_crash_interrupts_before_artifact_or_postcondition_verification(): void
    {
        $root = sys_get_temp_dir().'/release-plan-crash-'.bin2hex(random_bytes(8));
        mkdir($root);
        $ports = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePlanService(
            $ports,
            $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            new ReleaseResultFactory()
        );

        try {
            try {
                $service->plan([
                    ...$this->candidate(),
                    'boundary' => ['effect_class' => 'filesystem.write', 'outcome' => 'crash']
                ], $root, $root);
                self::fail('Plan creation returned a normal machine result after the configured crash.');
            } catch (ReleaseBoundaryCrash $crash) {
                self::assertSame('filesystem.write', $crash->effectClass);
            }

            self::assertSame('filesystem.write', $ports->effects()[6]['effect_class']);
            self::assertSame('crash', $ports->effects()[6]['outcome']);
            self::assertCount(7, $ports->effects());
            self::assertSame([], glob($root.'/*') ?: []);
        } finally {
            rmdir($root);
        }
    }

    /** @return array<string, mixed> */
    private function candidate(): array
    {
        return [
            'schema_version'           => 'fight-common.release-plan/v1',
            'approved_version'         => '1.3.0',
            'release_class'            => 'minor',
            'source_commit_oid'        => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline'                 => [
                'version'           => '1.2.3',
                'tag_name'          => 'v1.2.3',
                'tag_object_oid'    => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                'peeled_commit_oid' => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
            ],
            'support_policy_identity'  => 'support-policy-2026-08',
            'expected_effect_classes'  => [],
            'evidence_requirements'    => ['full-submit-gate', 'planning-check'],
            'evidence_manifest_digest' => str_repeat('a', 64),
            'compatibility_exceptions' => [],
            'patch_exception_authorities' => [],
            'required_approvals'       => ['release-approval-001'],
            'release_approval_authority' => [
                'approval_id'                  => 'release-approval-001',
                'approved_version'             => '1.3.0',
                'candidate_commit_oid'         => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
                'baseline_tag_name'            => 'v1.2.3',
                'baseline_tag_object_oid'      => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                'baseline_peeled_commit_oid'   => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
                'evidence_manifest_digest'     => str_repeat('a', 64),
                'compatibility_exception_ids' => [],
                'patch_exception_authority_digests' => [],
                'minimum_release_class'        => 'minor',
                'authorized_release_class'     => 'minor'
            ]
        ];
    }
}
// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
