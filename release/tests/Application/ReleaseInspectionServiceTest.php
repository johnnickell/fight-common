<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\CompatibilityAssessment;
use Fight\Release\Application\MachineResult;
use Fight\Release\Application\ReleaseAuthorityValidator;
use Fight\Release\Application\ReleaseInspectionService;
use Fight\Release\Application\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers inward inspection policy. */
#[CoversClass(ReleaseInspectionService::class)]
#[CoversClass(ReleaseResultFactory::class)]
#[CoversClass(ReleaseAuthorityValidator::class)]
class ReleaseInspectionServiceTest extends UnitTestCase
{
    /**
     * Covers a derived recommendation without a boundary effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_derives_a_non_authoritative_increment(): void
    {
        $effects = new DeterministicReleaseBoundaryFake();
        $effects->read('/missing-release-fixture');
        $result = $this->service($effects)->inspect($this->candidate('major'), $effects);

        self::assertSame(0, $result->exitCode);
        self::assertSame('2.0.0', $result->payload['recommendation']['recommended_version']);
        self::assertFalse($result->payload['recommendation']['authoritative']);
        self::assertSame([
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'failure'],
            ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success']
        ], $result->payload['performed_effects']);
    }

    /**
     * Covers JSON object member-order equivalence at the application inspection seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_inspect_treats_permuted_compatibility_entry_members_as_equivalent(): void
    {
        $canonicalEffects = new DeterministicReleaseBoundaryFake();
        $canonical = $this->service()->inspect($this->candidate('major'), $canonicalEffects);
        $permutedCandidate = $this->candidate('major');
        $permutedCandidate['compatibility_evidence'] = array_map(
            static fn (array $entry): array => array_reverse($entry, true),
            array_reverse($permutedCandidate['compatibility_evidence'])
        );
        $permutedEffects = new DeterministicReleaseBoundaryFake();
        $permuted = $this->service()->inspect($permutedCandidate, $permutedEffects);

        self::assertSame($canonical->payload['recommendation'], $permuted->payload['recommendation']);
        self::assertSame($canonical->payload['performed_effects'], $permuted->payload['performed_effects']);
        self::assertSame([
            ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success']
        ], $permuted->payload['performed_effects']);

        foreach ($permuted->payload['recommendation']['compatibility_assessment']['categories'] as $entry) {
            self::assertSame(
                ['category', 'finding_id', 'evidence_id', 'classification'],
                array_keys($entry)
            );
        }
    }

    /**
     * Covers deterministic increments without converting SemVer identifiers to platform integers.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_increments_arbitrarily_large_semver_identifiers_as_decimal_strings(): void
    {
        $expectations = [
            'patch' => [
                '18446744073709551616.340282366920938463463374607431768211456.999999999999999999999999999999',
                '18446744073709551616.340282366920938463463374607431768211456.1000000000000000000000000000000'
            ],
            'minor' => [
                '18446744073709551616.999999999999999999999999999999.340282366920938463463374607431768211456',
                '18446744073709551616.1000000000000000000000000000000.0'
            ],
            'major' => [
                '999999999999999999999999999999.340282366920938463463374607431768211456.18446744073709551616',
                '1000000000000000000000000000000.0.0'
            ]
        ];

        foreach ($expectations as $classification => [$baseline, $recommended]) {
            $result = $this->service()->inspect(
                $this->candidate($classification, $baseline),
                new DeterministicReleaseBoundaryFake()
            );

            self::assertSame(0, $result->exitCode);
            self::assertSame($recommended, $result->payload['recommendation']['recommended_version']);
        }
    }

    /**
     * Covers rejection of non-canonical stable SemVer core forms.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_rejects_noncanonical_stable_semver_baselines(): void
    {
        $invalidBaselines = [
            '01.2.3', '1.02.3', '1.2.03', '+1.2.3', '-1.2.3',
            '1.+2.3', '1.-2.3', '1.2.+3', '1.2.-3', '1.2', '1.2.3.4',
            '1.2.3-alpha', '1.2.3+build'
        ];

        foreach ($invalidBaselines as $baseline) {
            $result = $this->service()->inspect([
                'baseline'     => ['version' => $baseline],
                'change_class' => 'fix'
            ], new DeterministicReleaseBoundaryFake());

            self::assertSame(2, $result->exitCode, $baseline);
            self::assertSame('release.inspect.baseline_invalid', $result->payload['findings'][0]['id'], $baseline);
        }
    }

    /**
     * Covers effect capability enforcement before ledger recording.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_rejects_a_forbidden_effect_before_it_is_recorded(): void
    {
        $effects = new DeterministicReleaseBoundaryFake();
        $effects->read('/missing-release-fixture');
        $result = $this->service($effects)->inspect([
            ...$this->candidate(),
            'boundary'     => ['effect_class' => 'publish_release', 'outcome' => 'success']
        ], $effects);

        self::assertSame(2, $result->exitCode);
        self::assertSame('release.capability.effect_forbidden', $result->payload['findings'][0]['id']);
        self::assertSame([
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'failure']
        ], $result->payload['performed_effects']);
    }

    /**
     * Covers malformed candidate and baseline inputs without recording an effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_rejects_malformed_candidate_and_baseline_inputs(): void
    {
        $effects = new DeterministicReleaseBoundaryFake();
        $missingCandidate = $this->service()->inspect([], $effects);
        $invalidBaseline = $this->service()->inspect([
            'baseline'     => ['version' => '1.next.0'],
            'change_class' => 'fix'
        ], $effects);

        self::assertSame(2, $missingCandidate->exitCode);
        self::assertSame('release.inspect.fixture_invalid', $missingCandidate->payload['findings'][0]['id']);
        self::assertSame('release.inspect.baseline_invalid', $invalidBaseline->payload['findings'][0]['id']);
        self::assertSame([], $missingCandidate->payload['performed_effects']);
        self::assertSame([], $invalidBaseline->payload['performed_effects']);
        self::assertSame([], $effects->effects());
    }

    /**
     * Covers category-evidence and caller-declared aggregate stops before Git effects.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_inspect_requires_complete_determinate_evidence_and_rejects_legacy_aggregates(): void
    {
        $effects = new DeterministicReleaseBoundaryFake();
        $missing = $this->candidate();
        array_pop($missing['compatibility_evidence']);
        $missingResult = $this->service()->inspect($missing, $effects);

        $indeterminate = $this->candidate();
        $indeterminate['compatibility_evidence'][0]['classification'] = 'indeterminate';
        $indeterminateResult = $this->service()->inspect($indeterminate, $effects);

        $legacy = $this->candidate();
        $legacy['change_class'] = 'fix';
        $legacyResult = $this->service()->inspect($legacy, $effects);

        $selfDeclared = $this->candidate('major');
        $selfDeclared['minimum_increment'] = 'patch';
        $selfDeclaredResult = $this->service()->inspect($selfDeclared, $effects);

        self::assertSame('release.inspect.compatibility_evidence_invalid', $missingResult->payload['findings'][0]['id']);
        self::assertSame(5, $indeterminateResult->exitCode);
        self::assertSame('evidence_indeterminate', $indeterminateResult->payload['status']);
        self::assertSame('release.inspect.compatibility_indeterminate', $indeterminateResult->payload['findings'][0]['id']);
        self::assertSame('release.inspect.compatibility_aggregate_forbidden', $legacyResult->payload['findings'][0]['id']);
        self::assertSame('release.inspect.compatibility_aggregate_forbidden', $selfDeclaredResult->payload['findings'][0]['id']);
        self::assertSame([], $effects->effects());
    }

    /**
     * Covers every established caller-declared compatibility result alias before Git inspection.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_rejects_every_caller_declared_derived_compatibility_field_before_git(): void
    {
        $aliases = [
            'change_class',
            'minimum_increment',
            'release_class',
            'minimum_release_class',
            'authorized_release_class',
            'recommended_version',
            'compatibility_aggregate',
            'compatibility_assessment',
            'recommendation',
            'classification',
            'authoritative',
            'categories',
            'rationale',
            'aggregate'
        ];

        foreach ($aliases as $alias) {
            $effects = new DeterministicReleaseBoundaryFake();
            $candidate = $this->candidate();
            $candidate[$alias] = 'caller-declared';
            $result = $this->service()->inspect($candidate, $effects);

            self::assertSame(2, $result->exitCode, $alias);
            self::assertSame('policy_blocked', $result->payload['status'], $alias);
            self::assertSame('invalid_input', $result->payload['exit_class'], $alias);
            self::assertSame(
                'release.inspect.compatibility_aggregate_forbidden',
                $result->payload['findings'][0]['id'],
                $alias
            );
            self::assertSame([], $result->payload['performed_effects'], $alias);
            self::assertSame([], $effects->effects(), $alias);
            self::assertSame(
                ['action' => 'provide_category_compatibility_evidence'],
                $result->payload['next_action'],
                $alias
            );
        }

        $effects = new DeterministicReleaseBoundaryFake();
        $foreign = $this->service()->inspect([
            ...$this->candidate(),
            'foreign_policy_input' => 'caller-declared'
        ], $effects);

        self::assertSame(2, $foreign->exitCode);
        self::assertSame('release.inspect.fixture_invalid', $foreign->payload['findings'][0]['id']);
        self::assertSame([], $foreign->payload['performed_effects']);
        self::assertSame([], $effects->effects());
        self::assertSame(['action' => 'correct_inspection_fixture'], $foreign->payload['next_action']);
    }

    /**
     * Covers malformed and unsupported deterministic boundary declarations.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_rejects_invalid_boundary_declarations_before_performing_an_effect(): void
    {
        $effects = new DeterministicReleaseBoundaryFake();
        $malformed = $this->service()->inspect([
            ...$this->candidate(),
            'boundary'     => 'filesystem.read'
        ], $effects);
        $unsupported = $this->service()->inspect([
            ...$this->candidate(),
            'boundary' => ['effect_class' => 'git.inspect_repository', 'outcome' => 'not-supported']
        ], $effects);

        self::assertSame('release.boundary.fixture_invalid', $malformed->payload['findings'][0]['id']);
        self::assertSame('release.boundary.outcome_unsupported', $unsupported->payload['findings'][0]['id']);
        self::assertSame([], $malformed->payload['performed_effects']);
        self::assertSame([], $unsupported->payload['performed_effects']);
        self::assertSame([], $effects->effects());
    }

    /**
     * Covers bootstrap capability preflight before the release ledger begins.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_preflight_rejects_boundary_controls_without_a_release_effect(): void
    {
        $service = $this->service();
        $candidate = $this->candidate();

        $empty = $service->preflight([]);
        $list = $service->preflight([[]]);
        self::assertNull($service->preflight($candidate));
        self::assertNull($service->preflight([
            ...$candidate,
            'boundary' => ['effect_class' => 'git.inspect_repository', 'outcome' => 'success']
        ]));
        self::assertNull($service->preflight([
            ...$candidate,
            'boundary' => ['effect_class' => 'git.inspect_repository', 'outcome' => 'crash']
        ]));

        $malformed = $service->preflight([
            ...$candidate,
            'boundary' => ['effect_class' => 'git.inspect_repository']
        ]);
        $forbidden = $service->preflight([
            ...$candidate,
            'boundary' => ['effect_class' => 'git.tag', 'outcome' => 'success']
        ]);
        $unsupported = $service->preflight([
            ...$candidate,
            'boundary' => ['effect_class' => 'git.inspect_repository', 'outcome' => 'already_satisfied']
        ]);
        $unknown = $service->preflight([
            ...$candidate,
            'boundary' => ['effect_class' => 'git.inspect_repository', 'outcome' => 'unknown']
        ]);

        self::assertInstanceOf(MachineResult::class, $empty);
        self::assertInstanceOf(MachineResult::class, $list);
        self::assertInstanceOf(MachineResult::class, $malformed);
        self::assertInstanceOf(MachineResult::class, $forbidden);
        self::assertInstanceOf(MachineResult::class, $unsupported);
        self::assertInstanceOf(MachineResult::class, $unknown);
        self::assertSame('release.inspect.fixture_invalid', $empty->payload['findings'][0]['id']);
        self::assertSame([], $empty->payload['performed_effects']);
        self::assertSame('release.inspect.fixture_invalid', $list->payload['findings'][0]['id']);
        self::assertSame([], $list->payload['performed_effects']);
        self::assertSame('release.boundary.fixture_invalid', $malformed->payload['findings'][0]['id']);
        self::assertSame('release.capability.effect_forbidden', $forbidden->payload['findings'][0]['id']);
        self::assertSame('release.boundary.outcome_unsupported', $unsupported->payload['findings'][0]['id']);
        self::assertSame('release.boundary.outcome_unsupported', $unknown->payload['findings'][0]['id']);
        self::assertSame([], $malformed->payload['performed_effects']);
        self::assertSame([], $forbidden->payload['performed_effects']);
    }

    /**
     * Covers every supported controlled outcome at the application ledger seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_inspect_classifies_each_supported_boundary_outcome_and_records_the_performed_effect(): void
    {
        $expectations = [
            'success'     => ['succeeded', 'success', 0, 'continue_release_planning'],
            'refusal'     => ['authority_required', 'refused', 3, 'obtain_boundary_authority'],
            'failure'     => ['policy_blocked', 'failed', 4, 'repair_boundary_failure'],
            'uncertainty' => ['evidence_indeterminate', 'uncertain', 5, 'reconcile_boundary_effect'],
            'drift'       => ['stale_plan', 'drifted', 6, 'refresh_bound_inputs']
        ];

        foreach ($expectations as $outcome => [$status, $exitClass, $exitCode, $nextAction]) {
            $effects = new DeterministicReleaseBoundaryFake();
            $candidate = $this->candidate();
            $candidate['boundary'] = ['effect_class' => 'git.inspect_repository', 'outcome' => $outcome];
            $result = $this->service()->inspect($candidate, $effects);

            if ($outcome === 'success') {
                self::assertSame(0, $result->exitCode);
                self::assertSame('1.3.0', $result->payload['recommendation']['recommended_version']);
                self::assertSame($this->resolvedInputs(), $result->payload['resolved_inputs']);
                self::assertSame([
                    'inspection_boundary_effect_completed',
                    'minimum_increment_recommendation_derived'
                ], $result->payload['verified_postconditions']);
                self::assertSame(
                    'release.inspect.minimum_increment',
                    $result->payload['findings'][0]['id']
                );
                self::assertSame(
                    ['action' => 'approve_exact_version_for_plan', 'version' => '1.3.0'],
                    $result->payload['next_action']
                );
            } else {
                self::assertSame($exitCode, $result->exitCode);
                self::assertSame($status, $result->payload['status']);
                self::assertSame($exitClass, $result->payload['exit_class']);
                self::assertSame('release.boundary.'.$outcome, $result->payload['findings'][0]['id']);
                self::assertSame(['action' => $nextAction], $result->payload['next_action']);
                self::assertSame([], $result->payload['verified_postconditions']);
            }

            $expectedEffects = [[
                'capability'   => 'git',
                'effect_class' => 'git.inspect_repository',
                'outcome'      => $outcome
            ]];

            if ($outcome === 'success') {
                $expectedEffects[] = [
                    'capability'   => 'git',
                    'effect_class' => 'git.resolve_ref',
                    'outcome'      => 'success'
                ];
            }

            self::assertSame($expectedEffects, $result->payload['performed_effects']);
        }
    }

    /**
     * Covers authority validation after a successful repository inspection.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_rejects_invalid_resolved_immutable_identities_before_claiming_success(): void
    {
        $invalidInputs = [
            ['source_commit', 'release.inspect.source_commit_invalid'],
            ['baseline.tag_object', 'release.inspect.baseline_tag_object_invalid'],
            ['baseline.commit', 'release.inspect.baseline_commit_invalid'],
            ['support_policy', 'release.inspect.support_policy_invalid']
        ];

        foreach ($invalidInputs as [$field, $findingId]) {
            $candidate = $this->candidate();
            $candidate['boundary'] = ['effect_class' => 'git.inspect_repository', 'outcome' => 'success'];

            if (str_contains($field, '.')) {
                [$parent, $child] = explode('.', $field);
                $candidate[$parent][$child] = 'invalid';
            } else {
                $candidate[$field] = ' ';
            }

            $effects = new DeterministicReleaseBoundaryFake();
            $result = $this->service()->inspect($candidate, $effects);

            self::assertSame(2, $result->exitCode, $field);
            self::assertSame($findingId, $result->payload['findings'][0]['id'], $field);
            self::assertArrayNotHasKey('recommendation', $result->payload, $field);
            self::assertArrayNotHasKey('resolved_inputs', $result->payload, $field);
            self::assertSame([], $result->payload['verified_postconditions'], $field);
            self::assertSame([], $result->payload['performed_effects'], $field);
        }
    }

    /**
     * Covers baseline validation after a successful repository inspection.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_successful_boundary_evaluation_does_not_bypass_baseline_validation(): void
    {
        $candidate = $this->candidate(baseline: '01.2.3');
        $candidate['boundary'] = ['effect_class' => 'git.inspect_repository', 'outcome' => 'success'];
        $effects = new DeterministicReleaseBoundaryFake();

        $result = $this->service()->inspect($candidate, $effects);

        self::assertSame(2, $result->exitCode);
        self::assertSame('release.inspect.baseline_invalid', $result->payload['findings'][0]['id']);
        self::assertArrayNotHasKey('recommendation', $result->payload);
        self::assertSame([], $result->payload['verified_postconditions']);
        self::assertSame([], $result->payload['performed_effects']);
    }

    /**
     * Covers canonical, historical and unusable baseline-tag authority.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_inspect_revalidates_canonical_baseline_tag_authority_before_recommending(): void
    {
        $historical = $this->candidate('patch', '1.1.0');
        $result = $this->service()->inspect($historical, new DeterministicReleaseBoundaryFake());
        self::assertSame(0, $result->exitCode);
        self::assertSame('1.1.0', $result->payload['resolved_inputs']['baseline_tag']);

        $historical['baseline']['tag_name'] = 'v1.1.0';
        $invalid = $this->service()->inspect($historical, new DeterministicReleaseBoundaryFake());
        self::assertSame('release.inspect.baseline_tag_invalid', $invalid->payload['findings'][0]['id']);

        foreach (['missing', 'ambiguous', 'duplicate_normalized', 'non_ancestor'] as $status) {
            $effects = new DeterministicReleaseBoundaryFake();
            $effects->configureBaselineTagResolution($status);
            $stopped = $this->service()->inspect($this->candidate(), $effects);

            self::assertSame(4, $stopped->exitCode, $status);
            self::assertSame('release.inspect.baseline_tag_'.$status, $stopped->payload['findings'][0]['id']);
        }

        $movingEffects = new DeterministicReleaseBoundaryFake();
        $movingEffects->configureBaselineTagResolution('resolved', 'v1.2.3', str_repeat('c', 40));
        $moving = $this->service()->inspect($this->candidate(), $movingEffects);
        self::assertSame(6, $moving->exitCode);
        self::assertSame('release.inspect.baseline_tag_moving', $moving->payload['findings'][0]['id']);

        $failed = $this->service()->inspect(
            $this->candidate(),
            new DeterministicReleaseBoundaryFake(['git.resolve_ref' => 'failure'])
        );
        self::assertSame(4, $failed->exitCode);
        self::assertSame('release.boundary.failure', $failed->payload['findings'][0]['id']);
    }

    /**
     * Covers invalid fixture strings before any inspection boundary effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_inspect_rejects_invalid_utf8_fixture_strings_before_any_effect(): void
    {
        $candidate = $this->candidate();
        $candidate['support_policy'] = "support-policy-\xFF";
        $effects = new DeterministicReleaseBoundaryFake();

        $result = $this->service($effects)->inspect($candidate, $effects);

        self::assertSame(2, $result->exitCode);
        self::assertSame('release.inspect.fixture_encoding_invalid', $result->payload['findings'][0]['id']);
        self::assertSame([], $result->payload['performed_effects']);
        self::assertCount(1, $result->payload['next_action']);
        self::assertArrayNotHasKey('recommendation', $result->payload);
    }

    private function service(?DeterministicReleaseBoundaryFake $effects = null): ReleaseInspectionService
    {
        return new ReleaseInspectionService(new ReleaseResultFactory($effects));
    }

    /** @return array<string, mixed> */
    private function candidate(string $classification = 'minor', string $baseline = '1.2.3'): array
    {
        $resolved = $this->resolvedInputs();

        return [
            'source_commit'          => $resolved['source_commit'],
            'baseline'               => [
                'version'    => $baseline,
                'tag_name'   => $baseline === '1.1.0' ? '1.1.0' : 'v'.$baseline,
                'tag_object' => $resolved['baseline_tag_object'],
                'commit'     => $resolved['baseline_commit']
            ],
            'support_policy'         => $resolved['support_policy'],
            'compatibility_evidence' => $this->compatibilityEvidence($classification)
        ];
    }

    /** @return list<array<string, string>> */
    private function compatibilityEvidence(string $classification = 'patch'): array
    {
        return array_map(
            static fn (string $category): array => [
                'category'       => $category,
                'finding_id'     => 'release.compatibility.'.$category.'.fixture',
                'evidence_id'    => 'evidence.compatibility.'.$category.'.fixture',
                'classification' => $classification
            ],
            CompatibilityAssessment::CATEGORIES
        );
    }

    /** @return array<string, string> */
    private function resolvedInputs(): array
    {
        return [
            'source_commit'       => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline_tag'        => 'v1.2.3',
            'baseline_tag_object' => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            'baseline_commit'     => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
            'support_policy'      => 'support-policy-2026-08'
        ];
    }
}
