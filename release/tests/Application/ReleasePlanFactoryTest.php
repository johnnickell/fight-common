<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Application\CompatibilityAssessment;
use Fight\Release\Application\PatchExceptionAuthority;
use Fight\Release\Application\ReleaseApprovalAuthority;
use Fight\Release\Application\ReleaseAuthorityValidator;
use Fight\Release\Application\ReleasePlanFactory;
use Fight\Release\Application\ReleasePlanValidationFailure;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers immutable release-plan binding. */
#[CoversClass(ReleasePlanFactory::class)]
#[CoversClass(ReleaseAuthorityValidator::class)]
#[CoversClass(PatchExceptionAuthority::class)]
#[CoversClass(ReleaseApprovalAuthority::class)]
#[CoversClass(ReleasePlanValidationFailure::class)]
class ReleasePlanFactoryTest extends UnitTestCase
{
    /**
     * Covers one typed, stable validation reason for every malformed or missing authority input.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_validation_reports_the_exact_invalid_plan_authority(): void
    {
        $factory = new ReleasePlanFactory();

        foreach ($this->invalidCandidates() as $expected => $candidate) {
            $failure = $factory->validationFailure($candidate);
            self::assertInstanceOf(ReleasePlanValidationFailure::class, $failure, $expected);
            self::assertSame($expected, $failure->value, $expected);
            self::assertSame('release.plan.'.$expected, $failure->findingId(), $expected);
            self::assertNotSame('', $failure->message(), $expected);
            self::assertSame(
                match ($expected) {
                    'patch_exception_authorities_duplicate' => 'remove_duplicate_patch_exception_authority',
                    'patch_exception_authorities_ambiguous' => 'resolve_patch_exception_authority_ambiguity',
                    'release_approval_authority_mismatched' => 'obtain_current_release_approval',
                    default => (str_ends_with($expected, '_missing') ? 'provide_' : 'correct_')
                        .preg_replace('/_(?:missing|invalid)$/D', '', $expected)
                },
                $failure->nextAction(),
                $expected
            );
        }
    }

    /**
     * Covers rejected absent and non-approved exact versions.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_requires_every_bound_input_and_exact_version_approval(): void
    {
        $factory = new ReleasePlanFactory();
        $candidate = $this->candidate();
        unset($candidate['source_commit_oid']);

        self::assertNull($factory->create($candidate));
        self::assertNull($factory->create([...$this->candidate(), 'required_approvals' => []]));
    }

    /**
     * Covers binding only the immutable plan contract.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_returns_the_versioned_immutable_plan(): void
    {
        $plan = (new ReleasePlanFactory())->create($this->candidate());

        self::assertSame('fight-common.release-plan/v1', $plan['schema_version']);
        self::assertSame('1.3.0', $plan['approved_version']);
        self::assertArrayNotHasKey('boundary', $plan);
    }

    /**
     * Covers release approval as immutable authority over every material release decision.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_invalidates_a_stale_release_approval_when_any_binding_changes(): void
    {
        $candidate = $this->candidate();
        $candidate['required_approvals'] = ['release-approval-001'];
        $candidate['release_approval_authority'] = $this->releaseApprovalAuthority();
        $factory = new ReleasePlanFactory();

        self::assertNotNull($factory->create($candidate));

        foreach (
            [
                'approved_version'           => '1.4.0',
                'source_commit_oid'          => str_repeat('c', 40),
                'evidence_manifest_digest'   => str_repeat('b', 64),
                'compatibility_exceptions'   => ['compat-001'],
                'release_class'              => 'patch'
            ] as $field => $changed
        ) {
            $stale = $candidate;
            $stale[$field] = $changed;

            self::assertSame(
                ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISMATCHED,
                $factory->validationFailure($stale),
                $field
            );
        }

        foreach (
            [
                'baseline_tag_object_oid' => ['tag_object_oid', str_repeat('d', 40)],
                'baseline_peeled_commit_oid' => ['peeled_commit_oid', str_repeat('e', 40)]
            ] as $field => [$baselineField, $changed]
        ) {
            $stale = $candidate;
            $stale['baseline'][$baselineField] = $changed;

            self::assertSame(
                ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISMATCHED,
                $factory->validationFailure($stale),
                $field
            );
        }

        $staleTag = $candidate;
        $staleTag['baseline']['version'] = '1.1.0';
        $staleTag['baseline']['tag_name'] = '1.1.0';
        self::assertSame(
            ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISMATCHED,
            $factory->validationFailure($staleTag),
            'baseline_tag_name'
        );

        $contradictory = $candidate;
        $contradictory['release_approval_authority']['authorized_release_class'] = 'major';
        self::assertSame(
            ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISMATCHED,
            $factory->validationFailure($contradictory),
            'authorized_release_class'
        );
    }

    /**
     * Covers fail-closed typed approval completeness before version-relation policy.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_validation_rejects_every_malformed_release_approval_shape(): void
    {
        $factory = new ReleasePlanFactory();
        $candidate = $this->candidate();
        $missing = $candidate;
        unset($missing['release_approval_authority']);

        self::assertSame(
            ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISSING,
            $factory->validationFailure($missing)
        );

        $incomplete = $this->releaseApprovalAuthority();
        unset($incomplete['approval_id']);

        foreach (
            [
                [],
                $incomplete,
                $this->releaseApprovalAuthority(['approval_id' => 'Invalid']),
                $this->releaseApprovalAuthority(['compatibility_exception_ids' => 'invalid']),
                $this->releaseApprovalAuthority(['compatibility_exception_ids' => ['']]),
                $this->releaseApprovalAuthority(['patch_exception_authority_digests' => 'invalid']),
                $this->releaseApprovalAuthority(['patch_exception_authority_digests' => ['invalid']])
            ] as $approval
        ) {
            self::assertSame(
                ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_INVALID,
                $factory->validationFailure([...$candidate, 'release_approval_authority' => $approval])
            );
        }

        $sameVersion = $candidate;
        $sameVersion['approved_version'] = '1.2.3';
        $sameVersion['release_approval_authority']['approved_version'] = '1.2.3';
        self::assertNull($factory->validationFailure($sameVersion));
        self::assertSame(
            ReleasePlanValidationFailure::VERSION_RELATION_INVALID,
            $factory->versionAuthorizationFailure($sameVersion)
        );
    }

    /**
     * Covers the distinction between inspected minimum and actual baseline-relative release class.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_derives_the_authorized_release_class_from_the_exact_version(): void
    {
        $candidate = $this->candidate();
        $candidate['release_class'] = 'patch';
        $candidate['approved_version'] = '2.0.0';
        $candidate['required_approvals'] = ['release-approval-001'];
        $candidate['release_approval_authority'] = $this->releaseApprovalAuthority([
            'approved_version'         => '2.0.0',
            'minimum_release_class'    => 'patch',
            'authorized_release_class' => 'major'
        ]);

        $plan = (new ReleasePlanFactory())->create($candidate);

        self::assertSame('patch', $plan['minimum_release_class']);
        self::assertSame('major', $plan['release_class']);
    }

    /**
     * Covers deterministic normalization of semantically set-like plan authority.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_canonicalizes_each_set_like_bound_input(): void
    {
        $candidate = [
            ...$this->candidate(),
            'expected_effect_classes'  => ['hashing.sha256', 'filesystem.read'],
            'evidence_requirements'    => [
                'planning-check',
                'full-submit-gate',
                'composer.locked',
                'composer.lowest-permitted',
                'composer.latest-permitted',
                'archive.installation',
                'archive.reproducibility',
                'compatibility.evidence',
                'git.ref-verification'
            ],
            'compatibility_exceptions' => ['compat-002', 'compat-001'],
            'required_approvals'       => ['release-manager', 'release-approval-001'],
            'release_approval_authority' => $this->releaseApprovalAuthority([
                'compatibility_exception_ids' => ['compat-001', 'compat-002']
            ])
        ];

        $plan = (new ReleasePlanFactory())->create($candidate);

        self::assertSame(['filesystem.read', 'hashing.sha256'], $plan['expected_effect_classes']);
        self::assertSame([
            'archive.installation',
            'archive.reproducibility',
            'compatibility.evidence',
            'composer.latest-permitted',
            'composer.locked',
            'composer.lowest-permitted',
            'full-submit-gate',
            'git.ref-verification',
            'planning-check'
        ], $plan['evidence_requirements']);
        self::assertSame(['compat-001', 'compat-002'], $plan['compatibility_exceptions']);
        self::assertSame(['release-approval-001', 'release-manager'], $plan['required_approvals']);
    }

    /**
     * Covers strict stable SemVer validation for approved plan versions.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_rejects_noncanonical_stable_semver_versions(): void
    {
        $factory = new ReleasePlanFactory();
        $invalidVersions = [
            '01.2.3', '1.02.3', '1.2.03', '+1.2.3', '-1.2.3',
            '1.+2.3', '1.-2.3', '1.2.+3', '1.2.-3', '1.2', '1.2.3.4',
            '1.2.3-alpha', '1.2.3+build'
        ];

        foreach ($invalidVersions as $version) {
            $candidate = $this->candidate();
            $candidate['approved_version'] = $version;
            $candidate['required_approvals'] = ['exact-version:'.$version];

            self::assertNull($factory->create($candidate), $version);
        }
    }

    /**
     * Covers arbitrary-size canonical numeric identifiers without conversion.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_accepts_arbitrarily_large_canonical_stable_semver_versions(): void
    {
        $candidate = $this->candidate();
        $candidate['baseline']['version'] = '18446744073709551616.340282366920938463463374607431768211456.0';
        $candidate['baseline']['tag_name'] = 'v18446744073709551616.340282366920938463463374607431768211456.0';
        $candidate['approved_version'] = '18446744073709551616.340282366920938463463374607431768211456.1';
        $candidate['release_class'] = 'patch';
        $candidate['release_approval_authority'] = $this->releaseApprovalAuthority([
            'approved_version'          => $candidate['approved_version'],
            'baseline_tag_name'         => $candidate['baseline']['tag_name'],
            'minimum_release_class'     => 'patch',
            'authorized_release_class'  => 'patch'
        ]);

        $plan = (new ReleasePlanFactory())->create($candidate);

        self::assertSame(
            '18446744073709551616.340282366920938463463374607431768211456.1',
            $plan['approved_version']
        );
    }

    /**
     * Covers the exact canonical increment relation between baseline, class and approved version.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_accepts_the_minimum_or_any_higher_exactly_approved_version(): void
    {
        $factory = new ReleasePlanFactory();

        foreach (
            [
            ['patch', '1.2.3', '1.2.4', 'patch'],
            ['patch', '1.2.3', '1.2.5', 'patch'],
            ['minor', '1.2.3', '1.3.0', 'minor'],
            ['minor', '1.2.3', '1.4.0', 'minor'],
            ['major', '1.2.3', '2.0.0', 'major'],
            ['major', '1.2.3', '3.0.0', 'major'],
            ['major', '99999999999999999999.8.7', '100000000000000000000.0.0', 'major']
            ] as [$releaseClass, $baseline, $approved, $authorizedClass]
        ) {
            $candidate = $this->candidate();
            $candidate['release_class'] = $releaseClass;
            $candidate['baseline']['version'] = $baseline;
            $candidate['baseline']['tag_name'] = 'v'.$baseline;
            $candidate['approved_version'] = $approved;
            $candidate['release_approval_authority'] = $this->releaseApprovalAuthority([
                'approved_version'          => $approved,
                'baseline_tag_name'         => 'v'.$baseline,
                'minimum_release_class'     => $releaseClass,
                'authorized_release_class'  => $authorizedClass
            ]);

            self::assertSame($baseline, $factory->create($candidate)['baseline']['version']);
        }
    }

    /**
     * Covers patch exceptions as exclusive lower-patch authority, never normal-plan baggage.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_rejects_patch_exception_material_for_minimum_and_higher_versions(): void
    {
        $factory = new ReleasePlanFactory();
        $authority = $this->patchExceptionAuthority();
        $failure = ReleasePlanValidationFailure::PATCH_EXCEPTION_NOT_ALLOWED;

        self::assertSame(
            'Patch-exception references, records, and approval digests are allowed only for a lower patch.',
            $failure->message()
        );
        self::assertSame('remove_patch_exception_material', $failure->nextAction());
        self::assertNull($factory->versionAuthorizationFailure(['approved_version' => 'invalid']));

        foreach (['1.3.0', '1.4.0'] as $approvedVersion) {
            $candidate = $this->candidate();
            $candidate['approved_version'] = $approvedVersion;
            $candidate['compatibility_exceptions'] = [
                'patch-exception:compat-001:exact-version:1.2.4'
            ];
            $candidate['patch_exception_authorities'] = [$authority];
            $candidate['required_approvals'] = ['release-approval-001', 'release-authority-001'];
            $candidate['release_approval_authority'] = $this->releaseApprovalAuthority([
                'approved_version'                  => $approvedVersion,
                'compatibility_exception_ids'       => $candidate['compatibility_exceptions'],
                'patch_exception_authority_digests' => [$authority['authority_digest']]
            ]);

            self::assertSame(
                $failure,
                $factory->versionAuthorizationFailure($candidate),
                $approvedVersion
            );
            self::assertNull($factory->create($candidate), $approvedVersion);
        }
    }

    /**
     * Covers the exact patch exception required to authorize a lower version.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_requires_a_matching_exact_patch_exception_for_a_lower_version(): void
    {
        $factory = new ReleasePlanFactory();
        $candidate = $this->candidate();
        $candidate['release_class'] = 'major';
        $candidate['approved_version'] = '1.2.4';
        $candidate['required_approvals'] = ['exact-version:1.2.4'];

        self::assertSame(
            ReleasePlanValidationFailure::LOWER_VERSION_EXCEPTION_REQUIRED,
            $factory->versionAuthorizationFailure($candidate)
        );
        $missingExceptions = $candidate;
        unset($missingExceptions['compatibility_exceptions']);
        self::assertSame(
            ReleasePlanValidationFailure::LOWER_VERSION_EXCEPTION_REQUIRED,
            $factory->versionAuthorizationFailure($missingExceptions)
        );
        self::assertSame(
            ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED,
            $factory->versionAuthorizationFailure([
                ...$candidate,
                'compatibility_exceptions' => ['patch-exception:compat-001:exact-version:1.2.5']
            ])
        );
        $patchAuthority = $this->patchExceptionAuthority();
        $authorized = [
            ...$candidate,
            'compatibility_exceptions'    => ['patch-exception:compat-001:exact-version:1.2.4'],
            'patch_exception_authorities' => [$patchAuthority],
            'required_approvals'          => ['release-approval-001', 'release-authority-001'],
            'release_approval_authority'  => $this->releaseApprovalAuthority([
                'approved_version'                  => '1.2.4',
                'compatibility_exception_ids'       => [
                    'patch-exception:compat-001:exact-version:1.2.4'
                ],
                'patch_exception_authority_digests' => [$patchAuthority['authority_digest']],
                'minimum_release_class'             => 'major',
                'authorized_release_class'          => 'patch'
            ])
        ];

        self::assertNotNull($factory->create($authorized));

        foreach (
            [
                'exact_version'                => '1.2.5',
                'candidate_commit_oid'         => str_repeat('c', 40),
                'baseline_tag_object_oid'      => str_repeat('d', 40),
                'baseline_peeled_commit_oid'   => str_repeat('e', 40),
                'release_authority_approval'   => 'release-authority-002'
            ] as $field => $changed
        ) {
            $variant = $authorized;
            $variant['patch_exception_authorities'][0][$field] = $changed;

            self::assertSame(
                ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED,
                $factory->versionAuthorizationFailure($variant),
                $field
            );
        }

        $mismatchedAuthority = $this->patchExceptionAuthority([
            'candidate_commit_oid' => str_repeat('c', 40)
        ]);
        $mismatched = $authorized;
        $mismatched['patch_exception_authorities'] = [$mismatchedAuthority];
        $mismatched['release_approval_authority']['patch_exception_authority_digests'] = [
            $mismatchedAuthority['authority_digest']
        ];
        self::assertSame(
            ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED,
            $factory->versionAuthorizationFailure($mismatched)
        );
    }

    /**
     * Covers one-to-one lower-patch authority binding to the inspected minimum class.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_rejects_stale_or_extra_lower_patch_authority_records(): void
    {
        $factory = new ReleasePlanFactory();
        $candidate = $this->candidate();
        $candidate['release_class'] = 'major';
        $candidate['approved_version'] = '1.2.4';
        $candidate['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.4'];
        $candidate['required_approvals'] = ['release-approval-001', 'release-authority-001'];

        $staleAssessment = $this->compatibilityAssessment();
        $staleAssessment[0]['classification'] = 'minor';
        $stale = $candidate;
        $stale['patch_exception_authorities'] = [$this->patchExceptionAuthority([
            'compatibility_assessment' => $staleAssessment
        ])];

        self::assertSame(
            ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED,
            $factory->versionAuthorizationFailure($stale)
        );

        $second = $this->patchExceptionAuthority([
            'exception_id'  => 'compat-002',
            'exact_version' => '1.2.5'
        ]);
        $extra = $candidate;
        $extra['compatibility_exceptions'][] = 'patch-exception:compat-002:exact-version:1.2.5';
        $extra['patch_exception_authorities'] = [$this->patchExceptionAuthority(), $second];

        self::assertSame(
            ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED,
            $factory->versionAuthorizationFailure($extra)
        );

        $valid = $candidate;
        $valid['patch_exception_authorities'] = [$this->patchExceptionAuthority()];

        self::assertNull($factory->versionAuthorizationFailure($valid));
    }

    /**
     * Covers the closed emergency eligibility and exact inspected-finding binding.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_patch_exception_authority_requires_eligible_emergency_evidence(): void
    {
        $validator = new ReleaseAuthorityValidator();

        foreach (['security', 'imminent-data-loss', 'critical-interoperability'] as $emergencyClass) {
            self::assertInstanceOf(
                PatchExceptionAuthority::class,
                PatchExceptionAuthority::tryFrom(
                    $this->patchExceptionAuthority(['emergency_class' => $emergencyClass]),
                    $validator
                ),
                $emergencyClass
            );
        }

        foreach (
            [
                ['emergency_class' => 'urgent'],
                ['no_compatible_repair' => ['attested' => false, 'evidence_ids' => ['evidence.no-compatible-repair.analysis']]],
                ['no_compatible_repair' => 'unsupported'],
                ['no_compatible_repair' => ['attested' => true, 'evidence_ids' => []]],
                ['overridden_finding_ids' => ['release.compatibility.structural-api.break']],
                ['overridden_finding_ids' => ['release.compatibility.unknown.break']],
                ['overridden_finding_ids' => [1]],
                ['overridden_finding_ids' => ['release.compatibility.structural-api.break', 'release.compatibility.structural-api.break']]
            ] as $override
        ) {
            self::assertNull(
                PatchExceptionAuthority::tryFrom($this->patchExceptionAuthority($override), $validator)
            );
        }

    }

    /**
     * Covers canonical set normalization inside an exact patch-exception authority.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_canonicalizes_patch_exception_authority_sets(): void
    {
        $candidate = $this->candidate();
        $candidate['release_class'] = 'major';
        $candidate['approved_version'] = '1.2.4';
        $candidate['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.4'];
        $candidate['required_approvals'] = ['release-authority-001', 'release-approval-001'];
        $candidate['release_approval_authority'] = $this->releaseApprovalAuthority([
            'approved_version'            => '1.2.4',
            'compatibility_exception_ids' => ['patch-exception:compat-001:exact-version:1.2.4'],
            'minimum_release_class'       => 'major',
            'authorized_release_class'    => 'patch'
        ]);
        $patchAuthority = $this->patchExceptionAuthority([
            'overridden_finding_ids' => [
                'release.compatibility.structural-api.break',
                'release.compatibility.behavioral-fixtures.break'
            ],
            'test_evidence'           => ['compatibility.zeta-test', 'compatibility.alpha-test']
        ]);
        $candidate['patch_exception_authorities'] = [$patchAuthority];
        $candidate['release_approval_authority']['patch_exception_authority_digests'] = [
            $patchAuthority['authority_digest']
        ];

        $plan = (new ReleasePlanFactory())->create($candidate);

        self::assertSame(
            [
                'release.compatibility.behavioral-fixtures.break',
                'release.compatibility.structural-api.break'
            ],
            $plan['patch_exception_authorities'][0]['overridden_finding_ids']
        );
        self::assertSame(
            ['compatibility.alpha-test', 'compatibility.zeta-test'],
            $plan['patch_exception_authorities'][0]['test_evidence']
        );
    }

    /**
     * Covers fail-closed completeness for every material authority field.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_rejects_each_incomplete_patch_exception_authority_field(): void
    {
        $factory = new ReleasePlanFactory();

        foreach (array_keys($this->patchExceptionAuthority()) as $field) {
            $record = $this->patchExceptionAuthority();
            unset($record[$field]);

            self::assertSame(
                ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID,
                $factory->validationFailure([
                    ...$this->candidate(),
                    'patch_exception_authorities' => [$record]
                ]),
                $field
            );
        }

        foreach (
            [
                ['consumer_impact' => ''],
                ['overridden_finding_ids' => []],
                ['test_evidence' => ['Invalid Evidence']]
            ] as $override
        ) {
            self::assertSame(
                ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID,
                $factory->validationFailure([
                    ...$this->candidate(),
                    'patch_exception_authorities' => [$this->patchExceptionAuthority($override)]
                ])
            );
        }

        self::assertSame(
            ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID,
            $factory->validationFailure([
                ...$this->candidate(),
                'patch_exception_authorities' => 'invalid'
            ])
        );

        $lower = $this->candidate();
        $lower['release_class'] = 'major';
        $lower['approved_version'] = '1.2.4';
        $lower['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.4'];
        $lower['required_approvals'] = ['exact-version:1.2.4'];

        foreach (['invalid', ['invalid']] as $authorities) {
            self::assertSame(
                ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED,
                $factory->versionAuthorizationFailure([
                    ...$lower,
                    'patch_exception_authorities' => $authorities
                ])
            );
        }

        self::assertSame(
            'Every patch exception must resolve to one authority matching the plan bindings and approvals.',
            ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED->message()
        );
        self::assertSame(
            'correct_patch_exception_authority_bindings',
            ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED->nextAction()
        );
    }

    /**
     * Covers relations that cannot be authorized as a lower patch.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_version_authorization_rejects_missing_or_invalid_relations_distinctly(): void
    {
        $factory = new ReleasePlanFactory();
        $candidate = $this->candidate();

        self::assertNull($factory->versionAuthorizationFailure([]));
        self::assertNull($factory->versionAuthorizationFailure(['approved_version' => 'invalid']));
        self::assertNull($factory->versionAuthorizationFailure(['approved_version' => '1.3.0']));
        self::assertNull($factory->versionAuthorizationFailure([
            'approved_version' => '1.3.0', 'release_class' => 'feature'
        ]));
        self::assertNull($factory->versionAuthorizationFailure([
            'approved_version' => '1.3.0', 'release_class' => 'minor'
        ]));
        self::assertNull($factory->versionAuthorizationFailure([
            'approved_version' => '1.3.0', 'release_class' => 'minor', 'baseline' => ['version' => 'invalid']
        ]));
        self::assertNull($factory->versionAuthorizationFailure($candidate));
        self::assertSame(
            ReleasePlanValidationFailure::VERSION_RELATION_INVALID,
            $factory->versionAuthorizationFailure([...$candidate, 'approved_version' => '1.2.3'])
        );
        self::assertSame(
            'The approved version is neither the minimum, a higher version, nor the next patch version.',
            ReleasePlanValidationFailure::VERSION_RELATION_INVALID->message()
        );
        self::assertSame(
            'approve_valid_version_relation',
            ReleasePlanValidationFailure::VERSION_RELATION_INVALID->nextAction()
        );
        self::assertSame(
            'A lower approved patch version requires one matching complete patch-exception authority.',
            ReleasePlanValidationFailure::LOWER_VERSION_EXCEPTION_REQUIRED->message()
        );
        self::assertSame(
            'provide_complete_patch_exception_authority',
            ReleasePlanValidationFailure::LOWER_VERSION_EXCEPTION_REQUIRED->nextAction()
        );
        self::assertSame(
            ReleasePlanValidationFailure::VERSION_RELATION_INVALID,
            $factory->versionAuthorizationFailure([
                ...$candidate,
                'release_class'            => 'major',
                'approved_version'         => '1.3.0',
                'required_approvals'       => ['exact-version:1.3.0'],
                'compatibility_exceptions' => ['patch-exception:compat-001:exact-version:1.3.0']
            ])
        );
    }

    /**
     * Covers the closed release-class vocabulary and immutable Git object identities.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_rejects_unknown_release_classes_and_invalid_object_ids(): void
    {
        $factory = new ReleasePlanFactory();

        foreach (['', 'feature', 'MINOR'] as $releaseClass) {
            self::assertNull($factory->create([...$this->candidate(), 'release_class' => $releaseClass]));
        }

        foreach (['source_commit_oid', 'tag_object_oid', 'peeled_commit_oid'] as $field) {
            foreach (['', 'd34db33f', str_repeat('A', 40), str_repeat('g', 40)] as $oid) {
                $candidate = $this->candidate();

                if ($field === 'source_commit_oid') {
                    $candidate[$field] = $oid;
                } else {
                    $candidate['baseline'][$field] = $oid;
                }

                self::assertNull($factory->create($candidate), $field.':'.$oid);
            }
        }
    }

    /**
     * Covers closed effect vocabulary, evidence identifier syntax and non-empty policy identity.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_rejects_unbound_policy_effect_and_evidence_authority(): void
    {
        $factory = new ReleasePlanFactory();

        foreach (['', '   '] as $identity) {
            self::assertNull($factory->create([...$this->candidate(), 'support_policy_identity' => $identity]));
        }

        foreach ([['unknown.effect'], [''], [1], ['filesystem.write', 'filesystem.write']] as $effects) {
            self::assertNull($factory->create([...$this->candidate(), 'expected_effect_classes' => $effects]));
        }

        foreach ([
            [],
            [''],
            [' planning-check'],
            ['planning check'],
            ['Planning-check'],
            ['planning_check'],
            ['planning-check.'],
            ['planning..check'],
            [str_repeat('a', 129)],
            [1],
            ['planning-check', 'planning-check']
        ] as $evidence) {
            self::assertNull($factory->create([...$this->candidate(), 'evidence_requirements' => $evidence]));
        }
    }

    /**
     * Covers exact exception and approval identifiers without inventing their external registries.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_create_rejects_malformed_exception_and_approval_authority(): void
    {
        $factory = new ReleasePlanFactory();

        foreach (
            [
                [''], ['   '], [1], ['compat-001', 'compat-001'],
                ['patch-exception:'],
                ['patch-exception:Compat-001:exact-version:1.2.4'],
                ['patch-exception:compat-001:exact-version:01.2.4']
            ] as $exceptions
        ) {
            self::assertNull($factory->create([...$this->candidate(), 'compatibility_exceptions' => $exceptions]));
        }

        foreach ([
            [], [''], [1], ['exact-version:1.3.1'],
            ['exact-version:1.3.0', 'exact-version:1.3.1'],
            ['exact-version:1.3.0', 'release-manager', 'release-manager']
        ] as $approvals) {
            self::assertNull($factory->create([...$this->candidate(), 'required_approvals' => $approvals]));
        }

        self::assertNotNull($factory->create([
            ...$this->candidate(),
            'compatibility_exceptions' => ['compat-001'],
            'required_approvals'       => ['release-approval-001', 'release-manager'],
            'release_approval_authority' => $this->releaseApprovalAuthority([
                'compatibility_exception_ids' => ['compat-001']
            ])
        ]));
    }

    /** @return array<string, mixed> */
    private function candidate(): array
    {
        return [
            'schema_version' => 'fight-common.release-plan/v1',
            'approved_version' => '1.3.0',
            'release_class' => 'minor',
            'source_commit_oid' => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline' => [
                'version'           => '1.2.3',
                'tag_name'          => 'v1.2.3',
                'tag_object_oid'    => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                'peeled_commit_oid' => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
            ],
            'support_policy_identity' => 'support-policy-2026-08',
            'expected_effect_classes' => [],
            'evidence_requirements' => ['full-submit-gate', 'planning-check'],
            'evidence_manifest_digest' => str_repeat('a', 64),
            'compatibility_exceptions' => [],
            'patch_exception_authorities' => [],
            'required_approvals' => ['release-approval-001'],
            'release_approval_authority' => $this->releaseApprovalAuthority()
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function patchExceptionAuthority(array $overrides = []): array
    {
        $authority = [
            'exception_id'                  => 'compat-001',
            'exact_version'                 => '1.2.4',
            'candidate_commit_oid'          => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline_tag_object_oid'       => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            'baseline_peeled_commit_oid'    => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
            'emergency_class'               => 'security',
            'no_compatible_repair'          => [
                'attested'    => true,
                'evidence_ids' => ['evidence.no-compatible-repair.analysis']
            ],
            'compatibility_assessment'      => $this->compatibilityAssessment(),
            'overridden_finding_ids'        => [
                'release.compatibility.structural-api.break',
                'release.compatibility.behavioral-fixtures.break'
            ],
            'consumer_impact'               => 'One legacy consumer must update its integration.',
            'mitigation'                    => 'Publish the documented compatibility adapter.',
            'test_evidence'                 => ['compatibility.integration-test', 'compatibility.regression-test'],
            'recovery_posture'              => 'Revert the release and publish the compatible repair.',
            'evidence_manifest_digest'      => str_repeat('a', 64),
            'release_authority_approval'    => 'release-authority-001',
            ...$overrides
        ];

        $content = $authority;
        unset($content['authority_digest']);
        sort($content['overridden_finding_ids'], SORT_STRING);
        sort($content['test_evidence'], SORT_STRING);

        if (is_array($content['no_compatible_repair']['evidence_ids'] ?? null)) {
            sort($content['no_compatible_repair']['evidence_ids'], SORT_STRING);
        }

        $assessment = (new CompatibilityAssessment())->assess($content['compatibility_assessment'] ?? null);

        if ($assessment['status'] === 'valid') {
            $content['compatibility_assessment'] = $assessment['categories'];
        }

        $authority['authority_digest'] = hash('sha256', $this->canonicalJson($content));

        return $authority;
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

    /** Returns deterministic JSON for independently calculated fixture identities. */
    private function canonicalJson(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                $value = array_map($this->canonicalize(...), $value);
            } else {
                ksort($value, SORT_STRING);

                foreach ($value as $key => $entry) {
                    $value[$key] = $this->canonicalize($entry);
                }
            }
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Recursively orders object members without reordering list semantics. */
    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->canonicalize(...), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $entry) {
            $value[$key] = $this->canonicalize($entry);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function releaseApprovalAuthority(array $overrides = []): array
    {
        return [
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
            'authorized_release_class'     => 'minor',
            ...$overrides
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function invalidCandidates(): array
    {
        $candidate = $this->candidate();
        $missingSchema = $candidate;
        unset($missingSchema['schema_version']);
        $missingApproved = $candidate;
        unset($missingApproved['approved_version']);
        $missingBaseline = $candidate;
        unset($missingBaseline['baseline']);
        $missingBaselineVersion = $candidate;
        unset($missingBaselineVersion['baseline']['version']);
        $missingBaselineTagName = $candidate;
        unset($missingBaselineTagName['baseline']['tag_name']);
        $missingReleaseClass = $candidate;
        unset($missingReleaseClass['release_class']);
        $missingSource = $candidate;
        unset($missingSource['source_commit_oid']);
        $missingTagObject = $candidate;
        unset($missingTagObject['baseline']['tag_object_oid']);
        $missingPeeledCommit = $candidate;
        unset($missingPeeledCommit['baseline']['peeled_commit_oid']);
        $missingPolicy = $candidate;
        unset($missingPolicy['support_policy_identity']);
        $missingEffects = $candidate;
        unset($missingEffects['expected_effect_classes']);
        $missingEvidence = $candidate;
        unset($missingEvidence['evidence_requirements']);
        $missingEvidenceManifestDigest = $candidate;
        unset($missingEvidenceManifestDigest['evidence_manifest_digest']);
        $missingExceptions = $candidate;
        unset($missingExceptions['compatibility_exceptions']);
        $missingPatchAuthorities = $candidate;
        unset($missingPatchAuthorities['patch_exception_authorities']);
        $missingApprovals = $candidate;
        unset($missingApprovals['required_approvals']);
        $missingReleaseApproval = $candidate;
        unset($missingReleaseApproval['release_approval_authority']);
        $authority = $this->patchExceptionAuthority();
        $conflictingAuthority = $this->patchExceptionAuthority([
            'mitigation' => 'Use a different compatibility adapter.'
        ]);

        return [
            'schema_version_missing'               => $missingSchema,
            'schema_version_invalid'               => [...$candidate, 'schema_version' => 'release-plan/v2'],
            'approved_version_missing'             => $missingApproved,
            'approved_version_invalid'             => [...$candidate, 'approved_version' => '01.3.0'],
            'baseline_missing'                     => $missingBaseline,
            'baseline_invalid'                     => [...$candidate, 'baseline' => 'v1.2.3'],
            'baseline_version_missing'             => $missingBaselineVersion,
            'baseline_version_invalid'             => [...$candidate, 'baseline' => [...$candidate['baseline'], 'version' => '1.02.3']],
            'baseline_tag_name_missing'            => $missingBaselineTagName,
            'baseline_tag_name_invalid'            => [...$candidate, 'baseline' => [...$candidate['baseline'], 'tag_name' => '1.2.3']],
            'release_class_missing'                => $missingReleaseClass,
            'release_class_invalid'                => [...$candidate, 'release_class' => 'feature'],
            'source_commit_oid_missing'            => $missingSource,
            'source_commit_oid_invalid'            => [...$candidate, 'source_commit_oid' => 'd34db33f'],
            'baseline_tag_object_oid_missing'      => $missingTagObject,
            'baseline_tag_object_oid_invalid'      => [...$candidate, 'baseline' => [...$candidate['baseline'], 'tag_object_oid' => 'a11ce0a1']],
            'baseline_peeled_commit_oid_missing'   => $missingPeeledCommit,
            'baseline_peeled_commit_oid_invalid'   => [...$candidate, 'baseline' => [...$candidate['baseline'], 'peeled_commit_oid' => 'b45e1b45']],
            'support_policy_identity_missing'      => $missingPolicy,
            'support_policy_identity_invalid'      => [...$candidate, 'support_policy_identity' => '   '],
            'expected_effect_classes_missing'      => $missingEffects,
            'expected_effect_classes_invalid'      => [...$candidate, 'expected_effect_classes' => ['git.unknown']],
            'evidence_requirements_missing'        => $missingEvidence,
            'evidence_requirements_invalid'        => [...$candidate, 'evidence_requirements' => ['planning check']],
            'evidence_manifest_digest_missing'     => $missingEvidenceManifestDigest,
            'evidence_manifest_digest_invalid'     => [...$candidate, 'evidence_manifest_digest' => 'sha256:*'],
            'compatibility_exceptions_missing'     => $missingExceptions,
            'compatibility_exceptions_invalid'     => [...$candidate, 'compatibility_exceptions' => ['compat-1', 'compat-1']],
            'patch_exception_authorities_missing'  => $missingPatchAuthorities,
            'patch_exception_authorities_invalid'  => [...$candidate, 'patch_exception_authorities' => ['invalid']],
            'patch_exception_authorities_duplicate' => [
                ...$candidate,
                'patch_exception_authorities' => [$authority, $authority]
            ],
            'patch_exception_authorities_ambiguous' => [
                ...$candidate,
                'patch_exception_authorities' => [$authority, $conflictingAuthority]
            ],
            'required_approvals_missing'           => $missingApprovals,
            'required_approvals_invalid'           => [...$candidate, 'required_approvals' => ['release-manager', 'release-manager']],
            'release_approval_authority_missing'   => $missingReleaseApproval,
            'release_approval_authority_invalid'   => [...$candidate, 'release_approval_authority' => []],
            'release_approval_authority_mismatched' => [
                ...$candidate,
                'required_approvals' => ['release-approval-002']
            ]
        ];
    }
}
