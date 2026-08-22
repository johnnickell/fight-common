<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\BaselineTagResolutionResult;
use Fight\Release\Application\Boundary\BaselineTagResolutionStatus;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;

/**
 * Class ReleaseInspectionService
 *
 * Applies read-only release-inspection policy and its controlled boundary effect.
 */
final readonly class ReleaseInspectionService
{
    /** @var list<string> */
    private const array CANDIDATE_FIELDS = [
        'source_commit',
        'baseline',
        'support_policy',
        'compatibility_evidence',
        'boundary',
        'git_resolution'
    ];
    /** @var list<string> */
    private const array DERIVED_COMPATIBILITY_FIELDS = [
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

    /**
     * Constructs ReleaseInspectionService
     */
    public function __construct(
        private ReleaseResultFactory $results,
        private ReleaseAuthorityValidator $authority = new ReleaseAuthorityValidator(),
        private BaselineTagVerifier $baselineTags = new BaselineTagVerifier(),
        private Utf8Validator $utf8 = new Utf8Validator(),
        private CompatibilityAssessment $compatibility = new CompatibilityAssessment()
    ) {
    }

    /**
     * Rejects malformed or forbidden boundary controls before the release ledger begins
     *
     * @param array<string, mixed> $candidate Inspection candidate snapshot.
     */
    public function preflight(array $candidate): ?MachineResult
    {
        $candidateStop = $this->candidateFailure($candidate, []);

        if ($candidateStop instanceof MachineResult) {
            return $candidateStop;
        }

        $boundary = $candidate['boundary'] ?? null;

        if ($boundary === null) {
            return null;
        }

        if (
            !is_array($boundary)
            || !is_string($boundary['effect_class'] ?? null)
            || !is_string($boundary['outcome'] ?? null)
        ) {
            return $this->results->failure(
                'inspect',
                'release.boundary.fixture_invalid',
                'The boundary fixture does not declare one controlled effect and ledger.',
                'correct_boundary_fixture',
                []
            );
        }

        if ($boundary['effect_class'] !== 'git.inspect_repository') {
            return $this->results->failure(
                'inspect',
                'release.capability.effect_forbidden',
                'Inspection cannot perform the requested effect class.',
                'select_permitted_capability',
                []
            );
        }

        $outcome = ReleaseBoundaryOutcome::tryFrom($boundary['outcome']);

        if (
            $boundary['outcome'] !== 'crash'
            && ($outcome === null || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED)
        ) {
            return $this->results->failure(
                'inspect',
                'release.boundary.outcome_unsupported',
                'The boundary fixture does not declare a supported deterministic outcome.',
                'correct_boundary_fixture',
                []
            );
        }

        return null;
    }

    /**
     * Evaluates a candidate without authorizing a release effect
     *
     * @param array<string, mixed> $candidate Inspection candidate.
     */
    public function inspect(array $candidate, GitPort&ReleaseEffectLedger $effects): MachineResult
    {
        $candidateStop = $this->candidateFailure($candidate, $effects->effects());

        if ($candidateStop instanceof MachineResult) {
            return $candidateStop;
        }

        /** @var array<string, mixed> $baselineCandidate */
        $baselineCandidate = $candidate['baseline'];
        /** @var string $baseline */
        $baseline = $candidate['baseline']['version'];

        $resolvedInputs = [
            'source_commit'       => $candidate['source_commit'] ?? null,
            'baseline_tag'        => $baselineCandidate['tag_name'] ?? null,
            'baseline_tag_object' => $baselineCandidate['tag_object'] ?? null,
            'baseline_commit'     => $baselineCandidate['commit'] ?? null,
            'support_policy'      => $candidate['support_policy'] ?? null
        ];
        /** @var array<string, string> $resolvedInputs */

        $boundary = $candidate['boundary'] ?? null;
        $boundaryCompleted = false;

        if ($boundary !== null) {
            $boundaryStop = $this->inspectBoundary($boundary, $effects);

            if ($boundaryStop instanceof MachineResult) {
                return $boundaryStop;
            }

            $boundaryCompleted = true;
        }

        $resolution = $this->baselineTags->verify(
            $effects,
            $resolvedInputs['baseline_tag'],
            $resolvedInputs['source_commit'],
            $resolvedInputs['baseline_tag_object'],
            $resolvedInputs['baseline_commit']
        );

        if (!$resolution->isResolved()) {
            return $this->baselineResolutionStop($resolution, $effects);
        }

        $assessment = $this->compatibility->assess($candidate['compatibility_evidence']);
        $minimumIncrement = $assessment['minimum_increment'];
        assert(is_string($minimumIncrement));
        $recommended = StableSemVer::increment($baseline, $minimumIncrement);
        assert($recommended !== null);

        $message = 'The candidate requires the maximum minimum SemVer increment independently derived';
        $message .= ' across every composed compatibility category.';
        $verifiedPostconditions = [];

        if ($boundaryCompleted) {
            $verifiedPostconditions[] = 'inspection_boundary_effect_completed';
        }

        $verifiedPostconditions[] = 'minimum_increment_recommendation_derived';

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'inspect',
            'capability'              => 'release_inspection',
            'status'                  => 'succeeded',
            'exit_class'              => 'success',
            'resolved_inputs'         => $resolvedInputs,
            'recommendation'          => [
                'minimum_increment'        => $minimumIncrement,
                'recommended_version'      => $recommended,
                'authoritative'            => false,
                'compatibility_assessment' => [
                    'categories' => $assessment['categories'],
                    'rationale'  => 'maximum_required_increment_across_all_compatibility_categories'
                ]
            ],
            'findings'                => [[
                'id'      => 'release.inspect.minimum_increment',
                'message' => $message
            ]],
            'verified_postconditions' => $verifiedPostconditions,
            'performed_effects'       => $effects->effects(),
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'approve_exact_version_for_plan', 'version' => $recommended]
        ], 0);
    }

    /**
     * Validates all inspection candidate fields before any release boundary effect
     *
     * @param array<string, mixed>                                $candidate Candidate inspection input.
     * @param list<array{capability: string, effect_class: string, outcome: string}> $effects Existing ledger.
     */
    private function candidateFailure(array $candidate, array $effects): ?MachineResult
    {
        if (!$this->utf8->isValid($candidate)) {
            return $this->results->failure(
                'inspect',
                'release.inspect.fixture_encoding_invalid',
                'The inspection fixture must contain only valid UTF-8 strings.',
                'provide_valid_utf8_inspection_fixture',
                $effects
            );
        }

        $baselineCandidate = $candidate['baseline'] ?? null;

        if (
            !is_array($baselineCandidate)
            || !array_key_exists('version', $baselineCandidate)
        ) {
            return $this->results->failure(
                'inspect',
                'release.inspect.fixture_invalid',
                'The inspection fixture does not declare a supported candidate.',
                'correct_inspection_fixture',
                $effects
            );
        }

        $baseline = $baselineCandidate['version'];

        if (!is_string($baseline) || !StableSemVer::isValid($baseline)) {
            return $this->results->failure(
                'inspect',
                'release.inspect.baseline_invalid',
                'The baseline version is not valid SemVer.',
                'correct_inspection_fixture',
                $effects
            );
        }

        if (array_intersect(array_keys($candidate), self::DERIVED_COMPATIBILITY_FIELDS) !== []) {
            return $this->results->failure(
                'inspect',
                'release.inspect.compatibility_aggregate_forbidden',
                'Inspection rejects caller-declared aggregate compatibility classifications.',
                'provide_category_compatibility_evidence',
                $effects
            );
        }

        if (array_diff(array_keys($candidate), self::CANDIDATE_FIELDS) !== []) {
            return $this->results->failure(
                'inspect',
                'release.inspect.fixture_invalid',
                'The inspection fixture contains unsupported candidate fields.',
                'correct_inspection_fixture',
                $effects
            );
        }

        $assessment = $this->compatibility->assess($candidate['compatibility_evidence'] ?? null);

        if ($assessment['status'] === 'invalid') {
            return $this->results->failure(
                'inspect',
                'release.inspect.compatibility_evidence_invalid',
                'Inspection requires one unique, category-scoped record for every compatibility category.',
                'provide_complete_compatibility_evidence',
                $effects
            );
        }

        if ($assessment['status'] === 'indeterminate') {
            return new MachineResult([
                'schema_version'          => 'fight-common.release-result/v1',
                'command'                 => 'inspect',
                'capability'              => 'release_inspection',
                'status'                  => 'evidence_indeterminate',
                'exit_class'              => 'uncertain',
                'findings'                => [[
                    'id'      => 'release.inspect.compatibility_indeterminate',
                    'message' => 'An indeterminate compatibility category blocks a SemVer recommendation.'
                ]],
                'verified_postconditions' => [],
                'performed_effects'       => $effects,
                'proposed_effects'        => [],
                'next_action'             => ['action' => 'resolve_compatibility_evidence']
            ], 5);
        }

        $resolvedInputs = [
            'source_commit'       => $candidate['source_commit'] ?? null,
            'baseline_tag'        => $baselineCandidate['tag_name'] ?? null,
            'baseline_tag_object' => $baselineCandidate['tag_object'] ?? null,
            'baseline_commit'     => $baselineCandidate['commit'] ?? null,
            'support_policy'      => $candidate['support_policy'] ?? null
        ];

        foreach (
            [
                'source_commit'       => 'release.inspect.source_commit_invalid',
                'baseline_tag_object' => 'release.inspect.baseline_tag_object_invalid',
                'baseline_commit'     => 'release.inspect.baseline_commit_invalid'
            ] as $field => $findingId
        ) {
            if (!$this->authority->isGitObjectId($resolvedInputs[$field])) {
                return $this->results->failure(
                    'inspect',
                    $findingId,
                    'Inspection requires exact lowercase 40-character Git object IDs.',
                    'correct_inspection_fixture',
                    $effects
                );
            }
        }

        if (!$this->authority->isSupportPolicyIdentity($resolvedInputs['support_policy'])) {
            return $this->results->failure(
                'inspect',
                'release.inspect.support_policy_invalid',
                'Inspection requires one non-empty support-policy identity.',
                'correct_inspection_fixture',
                $effects
            );
        }

        if (
            !is_string($resolvedInputs['baseline_tag'])
            || !$this->baselineTags->isCanonical($resolvedInputs['baseline_tag'], $baseline)
        ) {
            return $this->results->failure(
                'inspect',
                'release.inspect.baseline_tag_invalid',
                'Inspection requires the canonical baseline tag for the declared version.',
                'correct_inspection_fixture',
                $effects
            );
        }

        return null;
    }

    /**
     * Converts one governed baseline-resolution stop into the inspection result contract
     */
    private function baselineResolutionStop(
        BaselineTagResolutionResult $resolution,
        ReleaseEffectLedger $effects
    ): MachineResult {
        if ($resolution->outcome->value !== 'success') {
            $classification = $resolution->outcome->classification();

            return new MachineResult([
                'schema_version'          => 'fight-common.release-result/v1',
                'command'                 => 'inspect',
                'capability'              => 'release_inspection',
                'status'                  => $classification['status'],
                'exit_class'              => $classification['exit_class'],
                'findings'                => [[
                    'id'      => 'release.boundary.'.$resolution->outcome->value,
                    'outcome' => $resolution->outcome->value,
                    'message' => 'The baseline tag could not be resolved through the Git boundary.'
                ]],
                'verified_postconditions' => [],
                'performed_effects'       => $effects->effects(),
                'proposed_effects'        => [],
                'next_action'             => ['action' => $classification['next_action']]
            ], $classification['exit_code']);
        }

        $status = $resolution->status ?? BaselineTagResolutionStatus::AMBIGUOUS;
        $moving = $status === BaselineTagResolutionStatus::MOVING;

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'inspect',
            'capability'              => 'release_inspection',
            'status'                  => $moving ? 'stale_plan' : 'policy_blocked',
            'exit_class'              => $moving ? 'drifted' : 'failed',
            'findings'                => [[
                'id'      => 'release.inspect.baseline_tag_'.$status->value,
                'message' => 'The canonical baseline tag did not resolve to one stable ancestral identity.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $effects->effects(),
            'proposed_effects'        => [],
            'next_action'             => ['action' => $moving ? 'refresh_bound_inputs' : 'repair_baseline_authority']
        ], $moving ? 6 : 4);
    }

    /**
     * Applies the one permitted inspection boundary effect
     *
     * @param mixed $boundary Boundary fixture input.
     */
    private function inspectBoundary(mixed $boundary, GitPort&ReleaseEffectLedger $effects): ?MachineResult
    {
        if (
            !is_array($boundary)
            || !is_string($boundary['effect_class'] ?? null)
            || !is_string($boundary['outcome'] ?? null)
        ) {
            return $this->results->failure(
                'inspect',
                'release.boundary.fixture_invalid',
                'The boundary fixture does not declare one controlled effect and ledger.',
                'correct_boundary_fixture',
                $effects->effects()
            );
        }

        if ($boundary['effect_class'] !== 'git.inspect_repository') {
            return $this->results->failure(
                'inspect',
                'release.capability.effect_forbidden',
                'Inspection cannot perform the requested effect class.',
                'select_permitted_capability',
                $effects->effects()
            );
        }

        if (!$effects->configureOutcome($boundary['effect_class'], $boundary['outcome'])) {
            return $this->results->failure(
                'inspect',
                'release.boundary.outcome_unsupported',
                'The boundary fixture does not declare a supported deterministic outcome.',
                'correct_boundary_fixture',
                $effects->effects()
            );
        }

        $outcome = $effects->inspectRepository()->outcome;

        if ($outcome->value === 'success') {
            return null;
        }

        $classification = $outcome->classification();

        return new MachineResult([
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'inspect',
            'capability'              => 'release_inspection',
            'status'                  => $classification['status'],
            'exit_class'              => $classification['exit_class'],
            'findings'                => [[
                'id'      => 'release.boundary.'.$outcome->value,
                'outcome' => $outcome->value,
                'message' => 'The deterministic boundary fixture classified its configured outcome.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => $effects->effects(),
            'proposed_effects'        => [],
            'next_action'             => ['action' => $classification['next_action']]
        ], $classification['exit_code']);
    }
}
