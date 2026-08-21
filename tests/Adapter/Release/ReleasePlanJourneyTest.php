<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release;

use Fight\Common\Application\Release\CanonicalJson;
use Fight\Common\Application\Release\CompatibilityAssessment;
use Fight\Common\Application\Release\ReleasePlanValidationFailure;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Covers immutable release plan persistence journeys. */
#[CoversNothing]
class ReleasePlanJourneyTest extends UnitTestCase
{
    /**
     * Covers canonical artifact persistence for an approved exact version.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_persists_a_canonical_immutable_artifact_for_an_approved_exact_version(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);

        try {
            $result = $this->plan($root, $root.'/tests/Fixture/Release/plan-candidate.json', $output);
            $idempotent = $this->plan($root, $root.'/tests/Fixture/Release/plan-candidate.json', $output);
            $equivalent = $this->plan($root, $root.'/tests/Fixture/Release/plan-candidate-equivalent.json', $output);
            $changed = $this->plan($root, $root.'/tests/Fixture/Release/plan-candidate-changed.json', $output);

            self::assertSame('fight-common.release-result/v1', $result['schema_version']);
            self::assertSame('plan', $result['command']);
            self::assertSame('release_planning', $result['capability']);
            self::assertSame('succeeded', $result['status']);
            self::assertSame('success', $result['exit_class']);
            self::assertSame([], $result['proposed_effects']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success']
            ], $result['performed_effects']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success']
            ], $equivalent['performed_effects']);
            self::assertSame([
                'immutable_release_plan_persisted'
            ], $result['verified_postconditions']);
            self::assertSame(['action' => 'create_release_run'], $result['next_action']);
            self::assertSame('release.plan.created', $result['findings'][0]['id']);
            self::assertSame('succeeded', $idempotent['status']);
            self::assertSame('success', $idempotent['exit_class']);
            self::assertSame('release.plan.already_satisfied', $idempotent['findings'][0]['id']);
            self::assertSame(
                'The immutable release plan already existed and was canonically verified.',
                $idempotent['findings'][0]['message']
            );
            self::assertSame(
                ['immutable_release_plan_already_persisted'],
                $idempotent['verified_postconditions']
            );
            self::assertSame($result['plan_id'], $idempotent['plan_id']);
            self::assertSame($result['artifact'], $idempotent['artifact']);
            self::assertSame(['action' => 'create_release_run'], $idempotent['next_action']);
            self::assertCount(1, $idempotent['next_action']);
            self::assertNotContains(
                'filesystem.write',
                array_column($idempotent['performed_effects'], 'effect_class')
            );
            self::assertSame($result['plan_id'], $equivalent['plan_id']);
            self::assertNotSame($result['plan_id'], $changed['plan_id']);

            $artifact = $result['artifact'];
            self::assertSame($result['plan_id'], $artifact['plan_id']);
            self::assertFileExists($artifact['path']);
            self::assertSame([
                'approved_version'         => '1.3.0',
                'baseline'                 => [
                    'peeled_commit_oid' => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
                    'tag_name'          => 'v1.2.3',
                    'tag_object_oid'    => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                    'version'           => '1.2.3'
                ],
                'compatibility_exceptions' => [],
                'evidence_manifest_digest' => str_repeat('a', 64),
                'evidence_requirements'    => ['full-submit-gate', 'planning-check'],
                'expected_effect_classes'  => [],
                'minimum_release_class'    => 'minor',
                'patch_exception_authorities' => [],
                'plan_id'                  => $result['plan_id'],
                'release_approval_authority' => [
                    'approval_id'                  => 'release-approval-001',
                    'approved_version'             => '1.3.0',
                    'authorized_release_class'     => 'minor',
                    'baseline_peeled_commit_oid'   => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
                    'baseline_tag_name'            => 'v1.2.3',
                    'baseline_tag_object_oid'      => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                    'candidate_commit_oid'         => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
                    'compatibility_exception_ids' => [],
                    'evidence_manifest_digest'     => str_repeat('a', 64),
                    'minimum_release_class'        => 'minor',
                    'patch_exception_authority_digests' => []
                ],
                'release_class'            => 'minor',
                'required_approvals'       => ['release-approval-001'],
                'schema_version'           => 'fight-common.release-plan/v1',
                'source_commit_oid'        => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
                'support_policy_identity'  => 'support-policy-2026-08'
            ], json_decode((string) file_get_contents($artifact['path']), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers canonical set semantics through the direct host command.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_canonicalizes_every_set_like_input_at_the_direct_host_seam(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-sets-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate = [
            ...$candidate,
            'expected_effect_classes'  => ['filesystem.read', 'hashing.sha256'],
            'evidence_requirements'    => [
                'full-submit-gate',
                'planning-check',
                'composer.locked',
                'archive.installation',
                'archive.reproducibility',
                'compatibility.evidence',
                'git.ref-verification'
            ],
            'compatibility_exceptions' => ['compat-001', 'compat-002'],
            'required_approvals'       => ['release-approval-001', 'release-manager']
        ];
        $candidate = $this->authorizeCandidate($candidate, 'minor');
        $permuted = [
            ...$candidate,
            'expected_effect_classes'  => array_reverse($candidate['expected_effect_classes']),
            'evidence_requirements'    => array_reverse($candidate['evidence_requirements']),
            'compatibility_exceptions' => array_reverse($candidate['compatibility_exceptions']),
            'required_approvals'       => array_reverse($candidate['required_approvals'])
        ];
        $permuted['release_approval_authority']['compatibility_exception_ids'] = array_reverse(
            $candidate['release_approval_authority']['compatibility_exception_ids']
        );
        $changed = [...$candidate, 'required_approvals' => [
            ...$candidate['required_approvals'],
            'security-review'
        ]];
        $fixtures = [];

        try {
            foreach (['candidate' => $candidate, 'permuted' => $permuted, 'changed' => $changed] as $name => $data) {
                $fixtures[$name] = $output.'/'.$name.'.json';
                file_put_contents($fixtures[$name], json_encode($data, JSON_THROW_ON_ERROR));
            }

            $first = $this->plan($root, $fixtures['candidate'], $output);
            $equivalent = $this->plan($root, $fixtures['permuted'], $output);
            $materiallyChanged = $this->plan($root, $fixtures['changed'], $output);

            self::assertSame($first['plan_id'], $equivalent['plan_id']);
            self::assertNotSame($first['plan_id'], $materiallyChanged['plan_id']);
            self::assertSame([
                ['effect_class' => 'filesystem.read'],
                ['effect_class' => 'hashing.sha256']
            ], $first['proposed_effects']);
            self::assertSame($first['proposed_effects'], $equivalent['proposed_effects']);
            self::assertNotSame($first['performed_effects'], $first['proposed_effects']);
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers strict approved stable SemVer validation at the public command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_noncanonical_approved_semver_versions(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-semver-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            foreach (['01.2.3', '1.02.3', '1.2.03', '-1.2.3', '1.2', '1.2.3.4'] as $index => $version) {
                $fixture = $output.'/invalid-'.$index.'.json';
                file_put_contents($fixture, json_encode([
                    ...$candidate,
                    'approved_version'   => $version,
                    'required_approvals' => ['exact-version:'.$version]
                ], JSON_THROW_ON_ERROR));
                $process = ReleaseProcess::create([
                    $root.'/bin/release',
                    'plan',
                    '--fixture='.$fixture,
                    '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $version);
                self::assertSame('release.plan.approved_version_invalid', $result['findings'][0]['id'], $version);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers invalid authority inputs stopping before plan hashing or artifact persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_invalid_authority_before_hashing_or_writing(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-authority-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        try {
            foreach ($this->invalidCandidates($candidate) as ['reason' => $reason, 'candidate' => $invalid]) {
                $fixture = $this->materializeFixture($output, 'invalid-authority-'.$reason->value, $invalid);
                $process = ReleaseProcess::create([
                    $root.'/bin/release',
                    'plan',
                    '--fixture='.$fixture,
                    '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $reason->value);
                self::assertSame('policy_blocked', $result['status'], $reason->value);
                self::assertSame('invalid_input', $result['exit_class'], $reason->value);
                self::assertSame($reason->findingId(), $result['findings'][0]['id'], $reason->value);
                self::assertSame($reason->message(), $result['findings'][0]['message'], $reason->value);
                self::assertSame(['action' => $reason->nextAction()], $result['next_action'], $reason->value);
                self::assertArrayNotHasKey('plan_id', $result, $reason->value);
                self::assertArrayNotHasKey('artifact', $result, $reason->value);
                self::assertSame([], $result['performed_effects'], $reason->value);
                self::assertSame([], array_values(array_filter(
                    glob($output.'/*.json') ?: [],
                    static fn (string $path): bool => $path !== $fixture
                )), $reason->value);
                unlink($fixture);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers minimum, higher and exact lower-patch authorization at the public command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_enforces_adr_0012_version_authorization(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-relation-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            foreach (
                [
                ['patch', '1.2.3', '1.2.4', [], 'patch'],
                ['patch', '1.2.3', '1.2.5', [], 'patch'],
                ['minor', '1.2.3', '1.3.0', [], 'minor'],
                ['minor', '1.2.3', '1.4.0', [], 'minor'],
                ['patch', '1.2.3', '2.0.0', [], 'major'],
                ['major', '1.2.3', '3.0.0', [], 'major'],
                ['major', '1.2.3', '1.2.4', ['patch-exception:compat-001:exact-version:1.2.4'], 'patch'],
                [
                    'major',
                    '99999999999999999999.8.7',
                    '100000000000000000001.0.0',
                    [],
                    'major'
                ]
                ] as $index => [$releaseClass, $baseline, $approved, $exceptions, $authorizedClass]
            ) {
                $valid = $candidate;
                $valid['release_class'] = $releaseClass;
                $valid['baseline']['version'] = $baseline;
                $valid['baseline']['tag_name'] = 'v'.$baseline;
                $valid['approved_version'] = $approved;
                $valid['compatibility_exceptions'] = $exceptions;
                $valid['patch_exception_authorities'] = $exceptions === []
                    ? []
                    : [$this->patchExceptionAuthority()];
                $valid['required_approvals'] = $exceptions === []
                    ? ['release-approval-001']
                    : ['release-approval-001', 'release-authority-001'];
                $valid = $this->authorizeCandidate($valid, $authorizedClass);
                $result = $this->plan(
                    $root,
                    $this->materializeFixture($output, 'valid-relation-'.$index, $valid),
                    $output
                );

                self::assertSame('succeeded', $result['status'], $releaseClass);
                $artifact = json_decode(
                    (string) file_get_contents($result['artifact']['path']),
                    true,
                    flags: JSON_THROW_ON_ERROR
                );
                self::assertSame($releaseClass, $artifact['minimum_release_class'], $approved);
                self::assertSame($authorizedClass, $artifact['release_class'], $approved);
            }

            foreach (
                [
                [
                    'patch', '1.2.3', '1.2.3', [],
                    'release.plan.version_relation_invalid', 'approve_valid_version_relation'
                ],
                [
                    'major', '1.2.3', '1.2.4', [],
                    'release.plan.lower_version_exception_required',
                    'provide_complete_patch_exception_authority'
                ],
                [
                    'major', '1.2.3', '1.2.4',
                    ['patch-exception:compat-001:exact-version:1.2.5'],
                    'release.plan.patch_exception_authority_mismatched',
                    'correct_patch_exception_authority_bindings'
                ],
                [
                    'major', '1.2.3', '1.3.0',
                    ['patch-exception:compat-001:exact-version:1.3.0'],
                    'release.plan.version_relation_invalid', 'approve_valid_version_relation'
                ]
                ] as $index => [$releaseClass, $baseline, $approved, $exceptions, $finding, $action]
            ) {
                $invalid = $candidate;
                $invalid['release_class'] = $releaseClass;
                $invalid['baseline']['version'] = $baseline;
                $invalid['baseline']['tag_name'] = 'v'.$baseline;
                $invalid['approved_version'] = $approved;
                $invalid['compatibility_exceptions'] = $exceptions;
                $invalid = $this->authorizeCandidate(
                    $invalid,
                    $approved === '1.2.3' ? 'patch' : ($approved === '1.2.4' ? 'patch' : 'minor')
                );
                $fixture = $this->materializeFixture($output, 'invalid-relation-'.$index, $invalid);
                $before = glob($output.'/*.json') ?: [];
                $process = ReleaseProcess::create([
                    $root.'/bin/release', 'plan', '--fixture='.$fixture, '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $releaseClass.':'.$approved);
                self::assertSame($finding, $result['findings'][0]['id']);
                self::assertSame(['action' => $action], $result['next_action']);
                self::assertSame([], $result['performed_effects']);
                self::assertSame($before, glob($output.'/*.json') ?: []);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers lower-patch exception material being forbidden on normal plans at the executable seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_patch_exception_material_for_minimum_and_higher_versions(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-normal-exception-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $authority = $this->patchExceptionAuthority();

        try {
            foreach (['1.3.0', '1.4.0'] as $index => $approvedVersion) {
                $invalid = $candidate;
                $invalid['approved_version'] = $approvedVersion;
                $invalid['compatibility_exceptions'] = [
                    'patch-exception:compat-001:exact-version:1.2.4'
                ];
                $invalid['patch_exception_authorities'] = [$authority];
                $invalid['required_approvals'] = ['release-approval-001', 'release-authority-001'];
                $invalid = $this->authorizeCandidate($invalid, 'minor');
                $fixture = $this->materializeFixture($output, 'normal-exception-'.$index, $invalid);
                $before = glob($output.'/*.json') ?: [];
                $process = ReleaseProcess::create([
                    $root.'/bin/release', 'plan', '--fixture='.$fixture, '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $approvedVersion);
                self::assertSame(
                    'release.plan.patch_exception_not_allowed',
                    $result['findings'][0]['id'],
                    $approvedVersion
                );
                self::assertSame(
                    ['action' => 'remove_patch_exception_material'],
                    $result['next_action'],
                    $approvedVersion
                );
                self::assertSame([], $result['performed_effects'], $approvedVersion);
                self::assertSame($before, glob($output.'/*.json') ?: [], $approvedVersion);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers emergency eligibility and inspected-finding resolution at the public command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_requires_eligible_patch_exception_evidence_before_boundary_effects(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-patch-eligibility-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate['release_class'] = 'major';
        $candidate['approved_version'] = '1.2.4';
        $candidate['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.4'];
        $candidate['required_approvals'] = ['release-authority-001'];

        try {
            foreach (['security', 'imminent-data-loss', 'critical-interoperability'] as $index => $class) {
                $authority = $this->patchExceptionAuthority(['emergency_class' => $class]);
                $valid = $candidate;
                $valid['patch_exception_authorities'] = [$authority];
                $valid = $this->authorizeCandidate($valid, 'patch');
                $result = $this->plan(
                    $root,
                    $this->materializeFixture($output, 'eligible-'.$index, $valid),
                    $output
                );

                self::assertSame('succeeded', $result['status'], $class);
            }

            $staleAssessment = $this->compatibilityAssessment();
            $staleAssessment[0]['finding_id'] = 'release.compatibility.structural-api.current-break';
            $invalidOverrides = [
                'unknown-class' => ['emergency_class' => 'urgent'],
                'false-attestation' => [
                    'no_compatible_repair' => [
                        'attested'     => false,
                        'evidence_ids' => ['evidence.no-compatible-repair.analysis']
                    ]
                ],
                'missing-attestation-evidence' => [
                    'no_compatible_repair' => ['attested' => true, 'evidence_ids' => []]
                ],
                'unrelated-finding' => [
                    'overridden_finding_ids' => ['release.compatibility.static-analysis.unrelated']
                ],
                'stale-finding' => ['compatibility_assessment' => $staleAssessment],
                'missing-actual-finding' => [
                    'overridden_finding_ids' => ['release.compatibility.structural-api.break']
                ],
                'duplicate-finding' => [
                    'overridden_finding_ids' => [
                        'release.compatibility.structural-api.break',
                        'release.compatibility.structural-api.break'
                    ]
                ]
            ];

            foreach ($invalidOverrides as $name => $override) {
                $invalid = $candidate;
                $invalid['patch_exception_authorities'] = [$this->patchExceptionAuthority($override)];
                $invalid = $this->authorizeCandidate($invalid, 'patch');
                $fixture = $this->materializeFixture($output, 'invalid-'.$name, $invalid);
                $before = glob($output.'/*.json') ?: [];
                $process = ReleaseProcess::create([
                    $root.'/bin/release', 'plan', '--fixture='.$fixture, '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $name);
                self::assertSame('release.plan.patch_exception_authorities_invalid', $result['findings'][0]['id']);
                self::assertSame(['action' => 'correct_patch_exception_authorities'], $result['next_action']);
                self::assertSame([], $result['performed_effects']);
                self::assertSame($before, glob($output.'/*.json') ?: []);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers lower-patch cross-record binding at the executable command seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_stale_or_surplus_lower_patch_authorities_before_writing(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-patch-binding-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate['release_class'] = 'major';
        $candidate['approved_version'] = '1.2.4';
        $candidate['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.4'];
        $candidate['required_approvals'] = ['release-authority-001'];

        $staleAssessment = $this->compatibilityAssessment();
        $staleAssessment[0]['classification'] = 'minor';
        $stale = $candidate;
        $stale['patch_exception_authorities'] = [$this->patchExceptionAuthority([
            'compatibility_assessment' => $staleAssessment
        ])];

        $extra = $candidate;
        $extra['compatibility_exceptions'][] = 'patch-exception:compat-002:exact-version:1.2.5';
        $extra['patch_exception_authorities'] = [
            $this->patchExceptionAuthority(),
            $this->patchExceptionAuthority(['exception_id' => 'compat-002', 'exact_version' => '1.2.5'])
        ];

        try {
            foreach (['stale-class' => $stale, 'surplus-record' => $extra] as $name => $attempt) {
                $attempt = $this->authorizeCandidate($attempt, 'patch');
                $fixture = $this->materializeFixture($output, $name, $attempt);
                $before = glob($output.'/*') ?: [];
                $process = ReleaseProcess::create([
                    $root.'/bin/release', 'plan', '--fixture='.$fixture, '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $name);
                self::assertSame(
                    'release.plan.patch_exception_authority_mismatched',
                    $result['findings'][0]['id'],
                    $name
                );
                self::assertSame(
                    ['action' => 'correct_patch_exception_authority_bindings'],
                    $result['next_action'],
                    $name
                );
                self::assertSame([], $result['performed_effects'], $name);
                self::assertSame($before, glob($output.'/*') ?: [], $name);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers plan identities bound to all material plan inputs.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_identity_binds_every_material_plan_input(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            $base = $this->plan(
                $root,
                $this->materializeFixture($output, 'canonical-base', $candidate),
                $output
            );

            foreach ($this->canonicalizationVariants($candidate) as $input => $variant) {
                $result = $this->plan(
                    $root,
                    $this->materializeFixture($output, 'canonical-'.$input, $variant),
                    $output
                );

                self::assertNotSame($base['plan_id'], $result['plan_id'], $input.' must bind the plan identity.');
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers stale typed approval rejection at the direct command seam before Git, hashing, or persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_a_stale_release_approval_before_any_boundary_effect(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-stale-approval-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate['source_commit_oid'] = str_repeat('c', 40);
        $fixture = $this->materializeFixture($output, 'stale-release-approval', $candidate);

        try {
            $process = ReleaseProcess::create([
                $root.'/bin/release', 'plan', '--fixture='.$fixture, '--output='.$output
            ]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $process->getExitCode());
            self::assertSame('release.plan.release_approval_authority_mismatched', $result['findings'][0]['id']);
            self::assertSame(['action' => 'obtain_current_release_approval'], $result['next_action']);
            self::assertSame([], $result['performed_effects']);
            self::assertSame([$fixture], glob($output.'/*.json') ?: []);
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers complete patch-exception authority identity and nested set canonicalization at the public seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_patch_exception_authority_is_canonical_and_fully_bound_into_plan_identity(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-patch-authority-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate['release_class'] = 'major';
        $candidate['approved_version'] = '1.2.4';
        $candidate['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.4'];
        $candidate['patch_exception_authorities'] = [$this->patchExceptionAuthority()];
        $candidate['required_approvals'] = ['exact-version:1.2.4', 'release-authority-001'];
        $candidate = $this->authorizeCandidate($candidate, 'patch');

        try {
            $base = $this->plan(
                $root,
                $this->materializeFixture($output, 'patch-authority-base', $candidate),
                $output
            );
            $equivalent = $candidate;
            $equivalent['patch_exception_authorities'][0]['overridden_finding_ids'] = [
                'release.compatibility.structural-api.break',
                'release.compatibility.behavioral-fixtures.break'
            ];
            $equivalent['patch_exception_authorities'][0]['test_evidence'] = [
                'compatibility.regression-test',
                'compatibility.integration-test'
            ];
            $equivalentResult = $this->plan(
                $root,
                $this->materializeFixture($output, 'patch-authority-equivalent', $equivalent),
                $output
            );

            self::assertSame($base['plan_id'], $equivalentResult['plan_id']);

            foreach ($this->patchAuthorityVariants($candidate) as $field => $variant) {
                $result = $this->plan(
                    $root,
                    $this->materializeFixture($output, 'patch-authority-'.$field, $variant),
                    $output
                );

                self::assertNotSame($base['plan_id'], $result['plan_id'], $field);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers output directories refused before any plan artifact write.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_refuses_an_output_directory_outside_repository_runs_before_writing(): void
    {
        $root = dirname(__DIR__, 3);
        $output = sys_get_temp_dir().'/fight-common-release-plan-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);

        try {
            $process = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/tests/Fixture/Release/plan-candidate.json',
                '--output='.$output
            ]);

            $process->run();

            self::assertSame(2, $process->getExitCode());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('fight-common.release-result/v1', $result['schema_version']);
            self::assertSame('plan', $result['command']);
            self::assertSame('release_planning', $result['capability']);
            self::assertSame('policy_blocked', $result['status']);
            self::assertSame('invalid_input', $result['exit_class']);
            self::assertSame('release.plan.output_forbidden', $result['findings'][0]['id']);
            self::assertSame([], $result['verified_postconditions']);
            self::assertSame([], $result['proposed_effects']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success']
            ], $result['performed_effects']);
            self::assertSame(['action' => 'select_runs_output'], $result['next_action']);
            self::assertSame([], glob($output.'/*') ?: []);
        } finally {
            rmdir($output);
        }
    }

    /**
     * Covers the literal repository .runs root refusing an escaping symlink at the public seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_a_symlinked_repository_runs_root_before_hashing_or_writing(): void
    {
        $root = dirname(__DIR__, 3);
        $runs = $root.'/.runs';
        $held = $root.'/.runs-held-'.bin2hex(random_bytes(8));
        $outside = sys_get_temp_dir().'/fight-common-release-outside-runs-'.bin2hex(random_bytes(8));
        rename($runs, $held);
        mkdir($outside);
        symlink($outside, $runs);

        try {
            $process = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/tests/Fixture/Release/plan-candidate.json',
                '--output='.$runs
            ]);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $process->getExitCode());
            self::assertSame('release.plan.output_forbidden', $result['findings'][0]['id']);
            self::assertSame([], glob($outside.'/*') ?: []);
            self::assertNotContains('hashing.sha256', array_column($result['performed_effects'], 'effect_class'));
            self::assertNotContains('filesystem.write', array_column($result['performed_effects'], 'effect_class'));
        } finally {
            unlink($runs);
            rename($held, $runs);
            rmdir($outside);
        }
    }

    /**
     * Covers plan input failures with an Application-owned empty or read-only ledger.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_input_failures_report_only_effects_already_performed(): void
    {
        $root = dirname(__DIR__, 3);
        $missingInputs = ReleaseProcess::create([$root.'/bin/release', 'plan']);
        $missingInputs->run();
        $missingResult = json_decode($missingInputs->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $unreadableFixture = ReleaseProcess::create([
            $root.'/bin/release',
            'plan',
            '--fixture='.$root.'/.runs/fixture-does-not-exist.json',
            '--output='.$root.'/.runs'
        ]);
        $unreadableFixture->run();
        $unreadableResult = json_decode($unreadableFixture->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(2, $missingInputs->getExitCode());
        self::assertSame('release.plan.inputs_required', $missingResult['findings'][0]['id']);
        self::assertSame([], $missingResult['performed_effects']);
        self::assertSame([], $missingResult['proposed_effects']);
        self::assertSame(2, $unreadableFixture->getExitCode());
        self::assertSame('release.plan.fixture_unreadable', $unreadableResult['findings'][0]['id']);
        self::assertSame([], $unreadableResult['performed_effects']);
        self::assertSame([], $unreadableResult['proposed_effects']);
    }

    /**
     * Covers the planning capability firewall before bootstrap transport enters the release ledger.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_forbidden_and_malformed_effect_controls_before_any_effect(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-firewall-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $fixtures = [
            'forbidden' => [
                [...$candidate, 'boundary' => ['effect_class' => 'github.release', 'outcome' => 'success']],
                'release.capability.effect_forbidden'
            ],
            'malformed' => [
                [...$candidate, 'boundary' => ['effect_class' => 'filesystem.write']],
                'release.boundary.fixture_invalid'
            ]
        ];

        try {
            $malformedJson = $output.'/malformed-json.json';
            file_put_contents($malformedJson, '{');
            $malformedProcess = ReleaseProcess::create([
                $root.'/bin/release', 'plan', '--fixture='.$malformedJson, '--output='.$output
            ]);
            $malformedProcess->run();
            $malformedResult = json_decode($malformedProcess->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(2, $malformedProcess->getExitCode());
            self::assertSame('release.plan.fixture_invalid', $malformedResult['findings'][0]['id']);
            self::assertSame([], $malformedResult['performed_effects']);
            self::assertArrayNotHasKey('artifact', $malformedResult);
            unlink($malformedJson);

            foreach ($fixtures as $name => [$controlledCandidate, $findingId]) {
                $fixture = $this->materializeFixture($output, $name, $controlledCandidate);
                $process = ReleaseProcess::create([
                    $root.'/bin/release', 'plan', '--fixture='.$fixture, '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $process->getExitCode(), $name);
                self::assertSame($findingId, $result['findings'][0]['id'], $name);
                self::assertSame([], $result['performed_effects'], $name);
                self::assertArrayNotHasKey('artifact', $result, $name);
                self::assertSame([$fixture], glob($output.'/*') ?: [], $name);
                unlink($fixture);
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers all deterministic write outcomes without false artifact persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_preserves_each_fake_filesystem_write_outcome(): void
    {
        $root = dirname(__DIR__, 3);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $classifications = [
            'success'           => ['succeeded', 'success', 0, 'release.plan.created', 'create_release_run'],
            'refusal'           => ['authority_required', 'refused', 3, 'release.boundary.refusal', 'obtain_boundary_authority'],
            'failure'           => ['policy_blocked', 'failed', 4, 'release.boundary.failure', 'repair_boundary_failure'],
            'uncertainty'       => ['evidence_indeterminate', 'uncertain', 5, 'release.boundary.uncertainty', 'reconcile_boundary_effect'],
            'drift'             => ['stale_plan', 'drifted', 6, 'release.boundary.drift', 'refresh_bound_inputs']
        ];

        foreach ($classifications as $outcome => [$status, $exitClass, $exitCode, $finding, $nextAction]) {
            $output = $root.'/.runs/fight-common-release-plan-'.$outcome.'-'.bin2hex(random_bytes(8));
            mkdir($output, 0777, true);
            $fixture = $output.'/write-'.$outcome.'.json';
            file_put_contents($fixture, json_encode([
                ...$candidate,
                'boundary' => ['effect_class' => 'filesystem.write', 'outcome' => $outcome]
            ], JSON_THROW_ON_ERROR));

            try {
                $process = ReleaseProcess::create([
                    $root.'/bin/release',
                    'plan',
                    '--fixture='.$fixture,
                    '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame($exitCode, $process->getExitCode());
                self::assertSame($status, $result['status']);
                self::assertSame($exitClass, $result['exit_class']);
                self::assertSame($finding, $result['findings'][0]['id']);
                self::assertSame(['action' => $nextAction], $result['next_action']);
                self::assertSame(
                    $outcome === 'success' ? ['immutable_release_plan_persisted'] : [],
                    $result['verified_postconditions']
                );
                $expectedEffects = [
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                    ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                    ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => $outcome]
                ];

                if ($outcome === 'success') {
                    $expectedEffects[] = ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'];
                    $expectedEffects[] = ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'];
                }

                self::assertSame($expectedEffects, $result['performed_effects']);
                self::assertCount($outcome === 'success' ? 1 : 0, array_values(array_filter(
                    glob($output.'/*') ?: [],
                    static fn (string $path): bool => $path !== $fixture
                )));
            } finally {
                foreach (glob($output.'/*') ?: [] as $artifact) {
                    unlink($artifact);
                }

                rmdir($output);
            }
        }
    }

    /**
     * Covers artifacts that conflict with an existing plan identity.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_refuses_a_corrupt_artifact_with_the_same_plan_identity(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);

        try {
            $initial = $this->plan($root, $root.'/tests/Fixture/Release/plan-candidate.json', $output);
            file_put_contents($initial['artifact']['path'], '{"plan_id":"forged"}\n');

            $process = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/tests/Fixture/Release/plan-candidate.json',
                '--output='.$output
            ]);

            $process->run();

            self::assertSame(4, $process->getExitCode());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('policy_blocked', $result['status']);
            self::assertSame('failed', $result['exit_class']);
            self::assertSame('release.plan.artifact_conflict', $result['findings'][0]['id']);
            self::assertSame([], $result['verified_postconditions']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success']
            ], $result['performed_effects']);
            self::assertSame(['action' => 'resolve_plan_artifact_conflict'], $result['next_action']);
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers baseline-tag authority stopping direct planning before hashing.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_stops_before_hashing_for_unusable_baseline_tags_at_the_public_seam(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-plan-tags-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            $historical = $candidate;
            $historical['baseline']['version'] = '1.1.0';
            $historical['baseline']['tag_name'] = '1.1.0';
            $historical['approved_version'] = '1.2.0';
            $historical['required_approvals'] = ['release-approval-001'];
            $historical = $this->authorizeCandidate($historical, 'minor');
            $fixture = $output.'/historical.json';
            file_put_contents($fixture, json_encode($historical, JSON_THROW_ON_ERROR));
            $historicalResult = $this->plan($root, $fixture, $output);
            self::assertSame('succeeded', $historicalResult['status']);
            self::assertSame(
                '1.1.0',
                json_decode(
                    (string) file_get_contents($historicalResult['artifact']['path']),
                    true,
                    flags: JSON_THROW_ON_ERROR
                )['baseline']['tag_name']
            );

            foreach (['missing', 'ambiguous', 'duplicate_normalized', 'non_ancestor'] as $status) {
                $fixture = $output.'/'.$status.'.json';
                file_put_contents($fixture, json_encode([
                    ...$candidate,
                    'git_resolution' => ['status' => $status]
                ], JSON_THROW_ON_ERROR));
                $process = ReleaseProcess::create([
                    $root.'/bin/release', 'plan', '--fixture='.$fixture, '--output='.$output
                ]);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(4, $process->getExitCode(), $status);
                self::assertSame('release.plan.baseline_tag_'.$status, $result['findings'][0]['id']);
                self::assertNotContains('hashing.sha256', array_column($result['performed_effects'], 'effect_class'));
            }
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /** @return array<string, mixed> */
    private function plan(string $root, string $fixture, string $output): array
    {
        $process = ReleaseProcess::create([
            $root.'/bin/release',
            'plan',
            '--fixture='.$fixture,
            '--output='.$output
        ]);

        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param string               $output
     * @param string               $name
     * @param array<string, mixed> $candidate
     */
    private function materializeFixture(string $output, string $name, array $candidate): string
    {
        $baseline = $candidate['baseline'] ?? null;

        if (
            is_array($baseline)
            && is_string($baseline['tag_name'] ?? null)
            && is_string($baseline['tag_object_oid'] ?? null)
            && is_string($baseline['peeled_commit_oid'] ?? null)
        ) {
            $candidate['git_resolution'] = [
                'status'             => 'resolved',
                'tag_name'           => $baseline['tag_name'],
                'tag_object_oid'     => $baseline['tag_object_oid'],
                'peeled_commit_oid'  => $baseline['peeled_commit_oid']
            ];
        }

        $fixture = $output.'/'.$name.'.json';
        file_put_contents($fixture, json_encode($candidate, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $fixture;
    }

    /**
     * @param array<string, mixed> $candidate
     *
     * @return list<array{reason: ReleasePlanValidationFailure, candidate: array<string, mixed>}>
     */
    private function invalidCandidates(array $candidate): array
    {
        $variants = [];
        $authority = $this->patchExceptionAuthority();
        $incompleteAuthority = $authority;
        unset($incompleteAuthority['recovery_posture']);
        $wildcardAuthority = $authority;
        $wildcardAuthority['candidate_commit_oid'] = '*';
        $conflictingAuthority = $this->patchExceptionAuthority([
            'mitigation' => 'Use a different compatibility adapter.'
        ]);
        $mismatchedAuthority = $this->patchExceptionAuthority([
            'candidate_commit_oid' => str_repeat('c', 40)
        ]);
        $mismatchedCandidate = [
            ...$candidate,
            'release_class'                 => 'major',
            'approved_version'              => '1.2.4',
            'compatibility_exceptions'      => ['patch-exception:compat-001:exact-version:1.2.4'],
            'patch_exception_authorities'   => [$mismatchedAuthority],
            'required_approvals'            => ['release-approval-001', 'release-authority-001']
        ];
        $mismatchedCandidate = $this->authorizeCandidate($mismatchedCandidate, 'patch');
        $missingFields = [
            [ReleasePlanValidationFailure::SCHEMA_VERSION_MISSING, ['schema_version']],
            [ReleasePlanValidationFailure::APPROVED_VERSION_MISSING, ['approved_version']],
            [ReleasePlanValidationFailure::BASELINE_MISSING, ['baseline']],
            [ReleasePlanValidationFailure::BASELINE_VERSION_MISSING, ['baseline', 'version']],
            [ReleasePlanValidationFailure::BASELINE_TAG_NAME_MISSING, ['baseline', 'tag_name']],
            [ReleasePlanValidationFailure::RELEASE_CLASS_MISSING, ['release_class']],
            [ReleasePlanValidationFailure::SOURCE_COMMIT_OID_MISSING, ['source_commit_oid']],
            [ReleasePlanValidationFailure::BASELINE_TAG_OBJECT_OID_MISSING, ['baseline', 'tag_object_oid']],
            [ReleasePlanValidationFailure::BASELINE_PEELED_COMMIT_OID_MISSING, ['baseline', 'peeled_commit_oid']],
            [ReleasePlanValidationFailure::SUPPORT_POLICY_IDENTITY_MISSING, ['support_policy_identity']],
            [ReleasePlanValidationFailure::EXPECTED_EFFECT_CLASSES_MISSING, ['expected_effect_classes']],
            [ReleasePlanValidationFailure::EVIDENCE_REQUIREMENTS_MISSING, ['evidence_requirements']],
            [ReleasePlanValidationFailure::EVIDENCE_MANIFEST_DIGEST_MISSING, ['evidence_manifest_digest']],
            [ReleasePlanValidationFailure::COMPATIBILITY_EXCEPTIONS_MISSING, ['compatibility_exceptions']],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_MISSING, ['patch_exception_authorities']],
            [ReleasePlanValidationFailure::REQUIRED_APPROVALS_MISSING, ['required_approvals']],
            [ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISSING, ['release_approval_authority']]
        ];

        foreach ($missingFields as [$reason, $path]) {
            $invalid = $candidate;

            if (count($path) === 1) {
                unset($invalid[$path[0]]);
            } else {
                unset($invalid[$path[0]][$path[1]]);
            }

            $variants[] = ['reason' => $reason, 'candidate' => $invalid];
        }

        foreach ([
            [ReleasePlanValidationFailure::SCHEMA_VERSION_INVALID, [...$candidate, 'schema_version' => 'release-plan/v2']],
            [ReleasePlanValidationFailure::APPROVED_VERSION_INVALID, [...$candidate, 'approved_version' => '01.3.0']],
            [ReleasePlanValidationFailure::BASELINE_INVALID, [...$candidate, 'baseline' => 'v1.2.3']],
            [ReleasePlanValidationFailure::BASELINE_VERSION_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'version' => '1.02.3']]],
            [ReleasePlanValidationFailure::BASELINE_TAG_NAME_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'tag_name' => '1.2.3']]],
            [ReleasePlanValidationFailure::RELEASE_CLASS_INVALID, [...$candidate, 'release_class' => 'feature']],
            [ReleasePlanValidationFailure::SOURCE_COMMIT_OID_INVALID, [...$candidate, 'source_commit_oid' => 'd34db33f']],
            [ReleasePlanValidationFailure::BASELINE_TAG_OBJECT_OID_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'tag_object_oid' => 'a11ce0a1']]],
            [ReleasePlanValidationFailure::BASELINE_PEELED_COMMIT_OID_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'peeled_commit_oid' => 'b45e1b45']]],
            [ReleasePlanValidationFailure::SUPPORT_POLICY_IDENTITY_INVALID, [...$candidate, 'support_policy_identity' => '   ']],
            [ReleasePlanValidationFailure::EXPECTED_EFFECT_CLASSES_INVALID, [...$candidate, 'expected_effect_classes' => ['git.unknown']]],
            [ReleasePlanValidationFailure::EVIDENCE_REQUIREMENTS_INVALID, [...$candidate, 'evidence_requirements' => ['planning check']]],
            [ReleasePlanValidationFailure::EVIDENCE_MANIFEST_DIGEST_INVALID, [...$candidate, 'evidence_manifest_digest' => 'sha256:*']],
            [ReleasePlanValidationFailure::COMPATIBILITY_EXCEPTIONS_INVALID, [...$candidate, 'compatibility_exceptions' => ['compat-1', 'compat-1']]],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID, [...$candidate, 'patch_exception_authorities' => ['invalid']]],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID, [...$candidate, 'patch_exception_authorities' => [$incompleteAuthority]]],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID, [...$candidate, 'patch_exception_authorities' => [$wildcardAuthority]]],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_DUPLICATE, [...$candidate, 'patch_exception_authorities' => [$authority, $authority]]],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_AMBIGUOUS, [...$candidate, 'patch_exception_authorities' => [$authority, $conflictingAuthority]]],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED, $mismatchedCandidate],
            [ReleasePlanValidationFailure::REQUIRED_APPROVALS_INVALID, [...$candidate, 'required_approvals' => ['release-manager', 'release-manager']]],
            [ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_INVALID, [...$candidate, 'release_approval_authority' => []]],
            [
                ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISMATCHED,
                [...$candidate, 'required_approvals' => ['release-approval-002']]
            ]
        ] as [$reason, $invalid]) {
            $variants[] = ['reason' => $reason, 'candidate' => $invalid];
        }

        return $variants;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function patchExceptionAuthority(array $overrides = []): array
    {
        return $this->withPatchAuthorityDigest([
            'exception_id'                => 'compat-001',
            'exact_version'               => '1.2.4',
            'candidate_commit_oid'        => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline_tag_object_oid'     => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            'baseline_peeled_commit_oid'  => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
            'emergency_class'             => 'security',
            'no_compatible_repair'        => [
                'attested'     => true,
                'evidence_ids' => ['evidence.no-compatible-repair.analysis']
            ],
            'compatibility_assessment'    => $this->compatibilityAssessment(),
            'overridden_finding_ids'      => [
                'release.compatibility.structural-api.break',
                'release.compatibility.behavioral-fixtures.break'
            ],
            'consumer_impact'             => 'One legacy consumer requires coordinated migration.',
            'mitigation'                  => 'Publish the documented compatibility adapter.',
            'test_evidence'               => [
                'compatibility.integration-test',
                'compatibility.regression-test'
            ],
            'recovery_posture'            => 'Revert the release and publish a compatible repair.',
            'evidence_manifest_digest'    => str_repeat('a', 64),
            'release_authority_approval'  => 'release-authority-001',
            ...$overrides
        ]);
    }

    /** @return list<array<string, string>> */
    private function compatibilityAssessment(): array
    {
        return array_map(
            static fn (string $category): array => [
                'category'       => $category,
                'finding_id'     => 'release.compatibility.'.$category.'.break',
                'evidence_id'    => 'evidence.compatibility.'.$category.'.inspection',
                'classification' => match ($category) {
                    'structural-api'      => 'major',
                    'behavioral-fixtures' => 'minor',
                    default              => 'patch'
                }
            ],
            CompatibilityAssessment::CATEGORIES
        );
    }

    /**
     * @param array<string, mixed> $authority
     *
     * @return array<string, mixed>
     */
    private function withPatchAuthorityDigest(array $authority): array
    {
        unset($authority['authority_digest']);
        sort($authority['overridden_finding_ids'], SORT_STRING);
        sort($authority['test_evidence'], SORT_STRING);
        sort($authority['no_compatible_repair']['evidence_ids'], SORT_STRING);
        $assessment = (new CompatibilityAssessment())->assess($authority['compatibility_assessment']);
        $authority['compatibility_assessment'] = $assessment['categories'];
        $authority['authority_digest'] = hash('sha256', (new CanonicalJson())->encode($authority));

        return $authority;
    }

    /**
     * @param array<string, mixed> $candidate Complete lower-version candidate.
     *
     * @return array<string, array<string, mixed>>
     */
    private function patchAuthorityVariants(array $candidate): array
    {
        $variants = [];
        $recordChanges = [
            'emergency_class'           => 'imminent-data-loss',
            'no_compatible_repair'      => [
                'attested'     => true,
                'evidence_ids' => ['evidence.no-compatible-repair.alternate-analysis']
            ],
            'compatibility_assessment'  => array_map(
                static fn (array $entry): array => $entry['category'] === 'structural-api'
                    ? [...$entry, 'evidence_id' => 'evidence.compatibility.structural-api.alternate-inspection']
                    : $entry,
                $this->compatibilityAssessment()
            ),
            'consumer_impact'           => 'Two legacy consumers require coordinated migration.',
            'mitigation'                => 'Publish and support the compatibility adapter for one maintenance line.',
            'test_evidence'             => ['compatibility.integration-test', 'compatibility.security-test'],
            'recovery_posture'          => 'Withdraw the release tag and publish the compatible repair.',
            'evidence_manifest_digest'  => str_repeat('f', 64)
        ];

        foreach ($recordChanges as $field => $value) {
            $variant = $candidate;
            $variant['patch_exception_authorities'][0][$field] = $value;

            if ($field === 'evidence_manifest_digest') {
                $variant['evidence_manifest_digest'] = $value;
            }

            $variant['patch_exception_authorities'][0] = $this->withPatchAuthorityDigest(
                $variant['patch_exception_authorities'][0]
            );

            $variants[$field] = $variant;
        }

        $exceptionId = $candidate;
        $exceptionId['compatibility_exceptions'] = ['patch-exception:compat-002:exact-version:1.2.4'];
        $exceptionId['patch_exception_authorities'][0]['exception_id'] = 'compat-002';
        $exceptionId['patch_exception_authorities'][0] = $this->withPatchAuthorityDigest(
            $exceptionId['patch_exception_authorities'][0]
        );
        $variants['exception_id'] = $exceptionId;

        $exactVersion = $candidate;
        $exactVersion['baseline']['version'] = '1.2.4';
        $exactVersion['baseline']['tag_name'] = 'v1.2.4';
        $exactVersion['approved_version'] = '1.2.5';
        $exactVersion['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.5'];
        $exactVersion['patch_exception_authorities'][0]['exact_version'] = '1.2.5';
        $exactVersion['patch_exception_authorities'][0] = $this->withPatchAuthorityDigest(
            $exactVersion['patch_exception_authorities'][0]
        );
        $exactVersion['required_approvals'] = ['exact-version:1.2.5', 'release-authority-001'];
        $variants['exact_version'] = $exactVersion;

        foreach (
            [
                'candidate_commit_oid'        => ['source_commit_oid', str_repeat('c', 40)],
                'baseline_tag_object_oid'     => ['tag_object_oid', str_repeat('d', 40)],
                'baseline_peeled_commit_oid'  => ['peeled_commit_oid', str_repeat('e', 40)]
            ] as $recordField => [$planField, $value]
        ) {
            $variant = $candidate;
            $variant['patch_exception_authorities'][0][$recordField] = $value;

            if ($recordField === 'candidate_commit_oid') {
                $variant[$planField] = $value;
            } else {
                $variant['baseline'][$planField] = $value;
            }

            $variant['patch_exception_authorities'][0] = $this->withPatchAuthorityDigest(
                $variant['patch_exception_authorities'][0]
            );

            $variants[$recordField] = $variant;
        }

        $approval = $candidate;
        $approval['patch_exception_authorities'][0]['release_authority_approval'] = 'release-authority-002';
        $approval['patch_exception_authorities'][0] = $this->withPatchAuthorityDigest(
            $approval['patch_exception_authorities'][0]
        );
        $approval['required_approvals'] = ['exact-version:1.2.4', 'release-authority-002'];
        $variants['release_authority_approval'] = $approval;

        foreach ($variants as $name => $variant) {
            $variants[$name] = $this->authorizeCandidate($variant, 'patch');
        }

        return $variants;
    }

    /**
     * @param array<string, mixed> $candidate
     *
     * @return array<string, mixed>
     */
    private function authorizeCandidate(array $candidate, string $authorizedReleaseClass): array
    {
        /** @var array<string, mixed> $baseline */
        $baseline = $candidate['baseline'];
        /** @var list<string> $exceptions */
        $exceptions = $candidate['compatibility_exceptions'];
        $patchAuthorityDigests = array_values(array_map(
            static fn (array $authority): string => $authority['authority_digest'],
            $candidate['patch_exception_authorities'] ?? []
        ));
        $approvals = array_values(array_filter(
            $candidate['required_approvals'] ?? [],
            static fn (mixed $approval): bool => is_string($approval)
                && !str_starts_with($approval, 'exact-version:')
                && $approval !== 'release-approval-001'
        ));
        $candidate['required_approvals'] = ['release-approval-001', ...$approvals];
        $candidate['release_approval_authority'] = [
            'approval_id'                  => 'release-approval-001',
            'approved_version'             => $candidate['approved_version'],
            'candidate_commit_oid'         => $candidate['source_commit_oid'],
            'baseline_tag_name'            => $baseline['tag_name'],
            'baseline_tag_object_oid'      => $baseline['tag_object_oid'],
            'baseline_peeled_commit_oid'   => $baseline['peeled_commit_oid'],
            'evidence_manifest_digest'     => $candidate['evidence_manifest_digest'],
            'compatibility_exception_ids' => $exceptions,
            'patch_exception_authority_digests' => $patchAuthorityDigests,
            'minimum_release_class'        => $candidate['release_class'],
            'authorized_release_class'     => $authorizedReleaseClass
        ];

        return $candidate;
    }

    private function canonicalizationVariants(array $candidate): array
    {
        $approvedVersion = $candidate;
        $approvedVersion['baseline']['version'] = '1.3.0';
        $approvedVersion['baseline']['tag_name'] = 'v1.3.0';
        $approvedVersion['approved_version'] = '1.4.0';
        $approvedVersion = $this->authorizeCandidate($approvedVersion, 'minor');

        $releaseClass = $candidate;
        $releaseClass['release_class'] = 'patch';
        $releaseClass['approved_version'] = '1.2.4';
        $releaseClass = $this->authorizeCandidate($releaseClass, 'patch');

        $sourceCommit = $candidate;
        $sourceCommit['source_commit_oid'] = 'e55ce55fe55ce55fe55ce55fe55ce55fe55ce55f';
        $sourceCommit = $this->authorizeCandidate($sourceCommit, 'minor');

        $baselineTag = $candidate;
        $baselineTag['baseline']['tag_object_oid'] = 'c22ce2c22ce2c22ce2c22ce2c22ce2c22ce2c22c';
        $baselineTag = $this->authorizeCandidate($baselineTag, 'minor');

        $baselineCommit = $candidate;
        $baselineCommit['baseline']['peeled_commit_oid'] = 'f66cf66cf66cf66cf66cf66cf66cf66cf66cf66c';
        $baselineCommit = $this->authorizeCandidate($baselineCommit, 'minor');

        $supportPolicy = $candidate;
        $supportPolicy['support_policy_identity'] = 'support-policy-2026-09';

        $expectedEffects = $candidate;
        $expectedEffects['expected_effect_classes'] = ['filesystem.write'];

        $evidenceRequirements = $candidate;
        $evidenceRequirements['evidence_requirements'] = ['full-submit-gate'];

        $evidenceManifest = $candidate;
        $evidenceManifest['evidence_manifest_digest'] = str_repeat('b', 64);
        $evidenceManifest = $this->authorizeCandidate($evidenceManifest, 'minor');

        $compatibilityExceptions = $candidate;
        $compatibilityExceptions['compatibility_exceptions'] = ['legacy-client-v1'];
        $compatibilityExceptions = $this->authorizeCandidate($compatibilityExceptions, 'minor');

        $requiredApprovals = $candidate;
        $requiredApprovals['required_approvals'] = [
            'release-approval-001',
            'release-manager'
        ];
        $requiredApprovals = $this->authorizeCandidate($requiredApprovals, 'minor');

        $releaseApproval = $candidate;
        $releaseApproval['required_approvals'] = ['release-approval-002'];
        $releaseApproval['release_approval_authority']['approval_id'] = 'release-approval-002';

        return [
            'approved_version_and_baseline_version' => $approvedVersion,
            'release_class'              => $releaseClass,
            'source_commit_oid'          => $sourceCommit,
            'baseline_tag_object_oid'    => $baselineTag,
            'baseline_peeled_commit_oid' => $baselineCommit,
            'support_policy_identity'    => $supportPolicy,
            'expected_effect_classes'    => $expectedEffects,
            'evidence_requirements'      => $evidenceRequirements,
            'evidence_manifest_digest'  => $evidenceManifest,
            'compatibility_exceptions'   => $compatibilityExceptions,
            'required_approvals'         => $requiredApprovals,
            'release_approval_authority' => $releaseApproval
        ];
    }
}
