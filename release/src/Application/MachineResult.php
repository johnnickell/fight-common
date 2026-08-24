<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\Boundary\ReleasePackageEffectSet;
use InvalidArgumentException;

/**
 * Class MachineResult
 *
 * Couples a release machine result to its process exit code.
 */
final readonly class MachineResult
{
    public const string RUNTIME_FAILURE_STATUS = 'infrastructure_unavailable';
    public const string RUNTIME_FAILURE_FINDING = 'release.runtime.bootstrap_unavailable';
    public const string RUNTIME_FAILURE_MESSAGE = 'The canonical release runtime could not be started.';
    public const string RUNTIME_FAILURE_ACTION = 'restore_release_runtime_and_retry';
    public const string RUNTIME_TERMINATION_STATUS = 'infrastructure_terminated';
    public const string RUNTIME_TERMINATION_FINDING = 'release.runtime.result_unavailable';
    public const string RUNTIME_TERMINATION_MESSAGE = 'The started runtime returned no valid authenticated result.';
    public const string RUNTIME_TERMINATION_ACTION = 'inspect_release_runtime_termination';

    // phpcs:disable Phpcs.Arrays.RequireAlignedArrayArrow
    /**
     * @var array<string, array{status: string, exit_class: string, exit_code: int, message: string, action: string}>
     */
    private const array PREPARATION_STOP_CONTRACTS = [
        'release.prepare.resume_state_missing' => [
            'status' => 'evidence_indeterminate', 'exit_class' => 'uncertain', 'exit_code' => 5,
            'message' => 'The named release run evidence is missing.',
            'action' => 'restore_named_release_run_evidence'
        ],
        'release.prepare.resume_contention' => [
            'status' => 'conflict', 'exit_class' => 'refused', 'exit_code' => 23,
            'message' => 'Another writer currently owns the named release run.',
            'action' => 'retry_named_resume_after_writer_completes'
        ],
        'release.prepare.resume_plan_drift' => [
            'status' => 'stale_plan', 'exit_class' => 'drifted', 'exit_code' => 6,
            'message' => 'The named release run is bound to a different immutable plan.',
            'action' => 'create_current_release_plan'
        ],
        'release.prepare.state_persistence_failed' => [
            'status' => 'policy_blocked', 'exit_class' => 'failed', 'exit_code' => 4,
            'message' => 'The release run could not be durably persisted.',
            'action' => 'repair_release_run_storage'
        ],
        'release.prepare.run_identity_conflict' => [
            'status' => 'conflict', 'exit_class' => 'refused', 'exit_code' => 23,
            'message' => 'The generated release run identity already exists.',
            'action' => 'retry_release_preparation_with_new_run'
        ],
        'release.prepare.state_persistence_indeterminate' => [
            'status' => 'evidence_indeterminate', 'exit_class' => 'uncertain', 'exit_code' => 5,
            'message' => 'The release run state may have been partially persisted.',
            'action' => 'reconcile_named_release_run'
        ],
        'release.prepare.artifacts_indeterminate' => [
            'status' => 'evidence_indeterminate', 'exit_class' => 'uncertain', 'exit_code' => 5,
            'message' => 'Preparation evidence or its handoff could not be verified.',
            'action' => 'reconcile_named_release_run'
        ],
        'release.prepare.baseline_resolution_refused' => [
            'status' => 'authority_required', 'exit_class' => 'refused', 'exit_code' => 3,
            'message' => 'The current baseline identity could not be resolved without additional authority.',
            'action' => 'obtain_current_baseline_authority'
        ],
        'release.prepare.baseline_resolution_failed' => [
            'status' => 'policy_blocked', 'exit_class' => 'failed', 'exit_code' => 4,
            'message' => 'The current baseline identity provider failed during revalidation.',
            'action' => 'repair_baseline_resolution_provider'
        ],
        'release.prepare.baseline_resolution_uncertain' => [
            'status' => 'evidence_indeterminate', 'exit_class' => 'uncertain', 'exit_code' => 5,
            'message' => 'The current baseline identity could not be determined conclusively.',
            'action' => 'reconcile_baseline_resolution'
        ],
        'release.prepare.baseline_resolution_drift' => [
            'status' => 'stale_plan', 'exit_class' => 'drifted', 'exit_code' => 6,
            'message' => 'The current baseline identity drifted from its immutable plan binding.',
            'action' => 'create_current_release_plan'
        ],
        'release.prepare.baseline_tag_missing' => [
            'status' => 'policy_blocked', 'exit_class' => 'failed', 'exit_code' => 4,
            'message' => 'The plan baseline tag is missing from the current repository authority.',
            'action' => 'repair_baseline_authority'
        ],
        'release.prepare.baseline_tag_ambiguous' => [
            'status' => 'policy_blocked', 'exit_class' => 'failed', 'exit_code' => 4,
            'message' => 'The plan baseline tag does not resolve to one annotated tag authority.',
            'action' => 'repair_baseline_authority'
        ],
        'release.prepare.baseline_tag_duplicate_normalized' => [
            'status' => 'policy_blocked', 'exit_class' => 'failed', 'exit_code' => 4,
            'message' => 'The plan baseline tag has duplicate normalized release authority.',
            'action' => 'repair_baseline_authority'
        ],
        'release.prepare.baseline_tag_non_ancestor' => [
            'status' => 'policy_blocked', 'exit_class' => 'failed', 'exit_code' => 4,
            'message' => 'The plan baseline commit is not an ancestor of the bound source commit.',
            'action' => 'repair_baseline_authority'
        ],
        'release.prepare.support_policy_drift' => [
            'status' => 'stale_plan', 'exit_class' => 'drifted', 'exit_code' => 6,
            'message' => 'The plan support-policy authority no longer matches current truth.',
            'action' => 'create_current_release_plan'
        ],
        'release.prepare.approval_authority_drift' => [
            'status' => 'authority_required', 'exit_class' => 'refused', 'exit_code' => 3,
            'message' => 'The plan approval authority no longer matches current truth.',
            'action' => 'obtain_current_release_approval'
        ],
        'release.prepare.evidence_authority_drift' => [
            'status' => 'stale_plan', 'exit_class' => 'drifted', 'exit_code' => 6,
            'message' => 'The plan evidence authority no longer matches current truth.',
            'action' => 'create_current_release_plan'
        ],
        'release.prepare.compatibility_authority_drift' => [
            'status' => 'stale_plan', 'exit_class' => 'drifted', 'exit_code' => 6,
            'message' => 'The plan compatibility authority no longer matches current truth.',
            'action' => 'create_current_release_plan'
        ],
        'release.prepare.plan_authority_refused' => [
            'status' => 'authority_required', 'exit_class' => 'refused', 'exit_code' => 3,
            'message' => 'The current release-plan authority refused preparation.',
            'action' => 'obtain_current_release_authority'
        ],
        'release.prepare.plan_authority_failed' => [
            'status' => 'policy_blocked', 'exit_class' => 'failed', 'exit_code' => 4,
            'message' => 'The current release-plan authority could not be revalidated.',
            'action' => 'repair_release_authority_provider'
        ],
        'release.prepare.plan_authority_uncertain' => [
            'status' => 'evidence_indeterminate', 'exit_class' => 'uncertain', 'exit_code' => 5,
            'message' => 'The current release-plan authority is uncertain.',
            'action' => 'reconcile_release_plan_authority'
        ],
        'release.prepare.resume_state_indeterminate' => [
            'status' => 'evidence_indeterminate', 'exit_class' => 'uncertain', 'exit_code' => 5,
            'message' => 'The named release run history, projection, or prepared postcondition is indeterminate.',
            'action' => 'reconcile_named_release_run'
        ]
    ];
    /** @var array<string, array{message: string, action: string, effect_classes: list<string>}> */
    private const array PREPARATION_INPUT_FAILURE_CONTRACTS = [
        'release.prepare.arguments_encoding_invalid' => [
            'message' => 'Release command options must be valid UTF-8.',
            'action' => 'provide_valid_utf8_arguments',
            'effect_classes' => []
        ],
        'release.prepare.ledger_unsupported' => [
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'message' => 'The command exposes its in-memory boundary ledger in the result and does not write ledger artifacts.',
            'action' => 'read_performed_effects',
            'effect_classes' => []
        ],
        'release.prepare.inputs_required' => [
            'message' => 'Preparation requires exactly one immutable plan option.',
            'action' => 'provide_prepare_plan',
            'effect_classes' => []
        ],
        'release.prepare.fixture_forbidden' => [
            'message' => 'Preparation fixtures are available only in the explicit direct-test runtime.',
            'action' => 'remove_prepare_fixture',
            'effect_classes' => []
        ],
        'release.prepare.fixture_invalid' => [
            'message' => 'The controlled preparation fixture is invalid.',
            'action' => 'provide_valid_prepare_fixture',
            'effect_classes' => []
        ],
        'release.prepare.authority_required' => [
            'message' => 'Normal preparation requires one current release-plan authority artifact.',
            'action' => 'provide_current_release_plan_authority',
            'effect_classes' => []
        ],
        'release.prepare.plan_forbidden' => [
            'message' => 'Preparation requires one immutable plan below the repository .runs directory.',
            'action' => 'select_immutable_release_plan',
            'effect_classes' => ['filesystem.inspect_runs_directory']
        ],
        'release.prepare.plan_unreadable' => [
            'message' => 'The immutable release plan could not be read.',
            'action' => 'select_immutable_release_plan',
            'effect_classes' => ['filesystem.read']
        ],
        'release.prepare.plan_invalid' => [
            'message' => 'The immutable release plan failed canonical identity or binding revalidation.',
            'action' => 'create_current_release_plan',
            'effect_classes' => ['filesystem.read', 'hashing.sha256']
        ],
        'release.prepare.run_identity_invalid' => [
            'message' => 'A unique release run identity could not be generated.',
            'action' => 'retry_release_preparation',
            'effect_classes' => ['filesystem.read', 'hashing.sha256']
        ]
    ];
    // phpcs:enable Phpcs.Arrays.RequireAlignedArrayArrow

    /** @var array<string, mixed> */
    public array $payload;
    public int $exitCode;

    /**
     * Constructs MachineResult
     *
     * @param array<string, mixed> $payload Machine-readable result payload.
     */
    public function __construct(array $payload, int $exitCode)
    {
        $payload['exit_code'] = $exitCode;

        if (!self::isValidPayload($payload, $exitCode)) {
            throw new InvalidArgumentException('The release machine result does not satisfy the v1 contract.');
        }

        $this->payload = $payload;
        $this->exitCode = $exitCode;
    }

    /**
     * Validates the complete stable v1 result contract
     *
     * @param array<string, mixed> $payload Machine-readable result payload.
     */
    public static function isValidPayload(array $payload, int $processExitCode): bool
    {
        if (!self::hasValidUtf8Recursively($payload)) {
            return false;
        }

        $command = $payload['command'] ?? null;
        $capability = $payload['capability'] ?? null;
        $classification = [$payload['status'] ?? null, $payload['exit_class'] ?? null];
        $releaseCommand = is_string($command) ? ReleaseCommand::tryFrom($command) : null;
        $expectedCapability = ReleaseCommand::UNSUPPORTED_CAPABILITY;

        if (is_string($command)) {
            $expectedCapability = ReleaseCommand::capabilityFor($command);
        }

        $successStatus = match ($command) {
            'prepare' => 'prepared',
            'package' => 'packaged',
            'certify' => 'certified',
            default => 'succeeded'
        };

        $expectedClassification = match ($processExitCode) {
            0 => [$successStatus, 'success'],
            2 => ['policy_blocked', 'invalid_input'],
            3 => ['authority_required', 'refused'],
            4 => $command === 'certify' ? ['certification_failed', 'failed'] : ['policy_blocked', 'failed'],
            5 => ['evidence_indeterminate', 'uncertain'],
            6 => ['stale_plan', 'drifted'],
            23 => ['conflict', 'refused'],
            70 => [self::RUNTIME_FAILURE_STATUS, 'failed'],
            71 => [self::RUNTIME_TERMINATION_STATUS, 'failed'],
            default => null,
        };

        if (
            ($payload['schema_version'] ?? null) !== 'fight-common.release-result/v1'
            || !is_string($command)
            || $command === ''
            || !is_string($capability)
            || $capability !== $expectedCapability
            || !is_int($payload['exit_code'] ?? null)
            || $payload['exit_code'] !== $processExitCode
            || $expectedClassification === null
            || $classification !== $expectedClassification
            || !self::isFindings($payload['findings'] ?? null, $classification, $processExitCode, $command)
            || !self::isStringList($payload['verified_postconditions'] ?? null)
            || !self::isPerformedEffects($payload['performed_effects'] ?? null)
            || !self::isProposedEffects($payload['proposed_effects'] ?? null)
            || !self::isNextAction($payload['next_action'] ?? null, $command)
        ) {
            return false;
        }

        $required = [
            'schema_version',
            'command',
            'capability',
            'status',
            'exit_class',
            'exit_code',
            'findings',
            'verified_postconditions',
            'performed_effects',
            'proposed_effects',
            'next_action'
        ];

        if ($processExitCode === 70 || $processExitCode === 71) {
            return array_diff(array_keys($payload), $required) === []
                && match ($processExitCode) {
                    70 => self::isRuntimeBootstrapFailure($payload),
                    71 => self::isRuntimeTermination($payload)
                };
        }

        $optional = $releaseCommand?->optionalResultFields() ?? [];

        if (array_diff(array_keys($payload), [...$required, ...$optional]) !== []) {
            return false;
        }

        return match ($releaseCommand) {
            ReleaseCommand::INSPECT => self::isInspectionFields($payload),
            ReleaseCommand::PLAN => self::isPlanFields($payload),
            ReleaseCommand::PREPARE => self::isPrepareFields($payload),
            ReleaseCommand::PACKAGE => self::isPackageFields($payload),
            ReleaseCommand::CERTIFY => self::isCertificationFields($payload),
            ReleaseCommand::COMPATIBILITY => self::isCompatibilityFields($payload),
            null => true,
        };
    }

    /**
     * Validates the sole infrastructure-owned failure admitted by the v1 result contract
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isRuntimeBootstrapFailure(array $payload): bool
    {
        return ReleaseCommand::isRuntimeCommand($payload['command'])
            && $payload['findings'] === [[
                'id'      => self::RUNTIME_FAILURE_FINDING,
                'message' => self::RUNTIME_FAILURE_MESSAGE
            ]]
            && $payload['verified_postconditions'] === []
            && $payload['performed_effects'] === []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => self::RUNTIME_FAILURE_ACTION];
    }

    /**
     * Validates the sole post-start infrastructure termination admitted by the v1 result contract
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isRuntimeTermination(array $payload): bool
    {
        return ReleaseCommand::isRuntimeCommand($payload['command'])
            && $payload['findings'] === [[
                'id'      => self::RUNTIME_TERMINATION_FINDING,
                'message' => self::RUNTIME_TERMINATION_MESSAGE
            ]]
            && $payload['verified_postconditions'] === []
            && $payload['performed_effects'] === []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => self::RUNTIME_TERMINATION_ACTION];
    }

    /**
     * Validates the non-empty finding list
     *
     * @param mixed                     $findings       Candidate finding list.
     * @param array{0: mixed, 1: mixed} $classification Result status and exit class.
     */
    private static function isFindings(
        mixed $findings,
        array $classification,
        int $exitCode,
        string $command
    ): bool {
        if (!is_array($findings) || !array_is_list($findings) || $findings === []) {
            return false;
        }

        return array_all(
            $findings,
            static function (mixed $finding) use ($classification, $exitCode, $command): bool {
                if ($command === 'compatibility' && CompatibilityFinding::isMachineFinding($finding)) {
                    return $classification === ['evidence_indeterminate', 'uncertain'] && $exitCode === 5;
                }

                if (
                    !is_array($finding)
                    || !is_string($finding['id'] ?? null)
                    || $finding['id'] === ''
                    || !is_string($finding['message'] ?? null)
                    || $finding['message'] === ''
                    || array_diff(array_keys($finding), ['id', 'message', 'outcome']) !== []
                ) {
                    return false;
                }

                if (!isset($finding['outcome'])) {
                    return true;
                }

                $outcome = null;

                if (is_string($finding['outcome'])) {
                    $outcome = ReleaseBoundaryOutcome::tryFrom($finding['outcome']);
                }

                if ($outcome === null) {
                    return false;
                }

                $expected = $outcome->classification();

                return $classification === [$expected['status'], $expected['exit_class']]
                    && $exitCode === $expected['exit_code'];
            }
        );
    }

    /**
     * Validates a list of non-empty strings
     */
    private static function isStringList(mixed $values): bool
    {
        if (!is_array($values) || !array_is_list($values)) {
            return false;
        }

        return array_all($values, static fn (mixed $value): bool => !(!is_string($value) || $value === ''));
    }

    /**
     * Validates the performed-effect ledger projection
     */
    private static function isPerformedEffects(mixed $effects): bool
    {
        if (!is_array($effects) || !array_is_list($effects)) {
            return false;
        }

        foreach ($effects as $effect) {
            if (
                !is_array($effect)
                || array_keys($effect) !== ['capability', 'effect_class', 'outcome']
                || !self::hasNonEmptyStrings($effect)
            ) {
                return false;
            }

            $releaseEffect = ReleaseEffect::tryFrom($effect['effect_class']);
            $outcome = ReleaseBoundaryOutcome::tryFrom($effect['outcome']);

            if (
                $releaseEffect === null
                || $outcome === null
                || $releaseEffect->capability() !== $effect['capability']
                || $outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
                && !$releaseEffect->allowsAlreadySatisfiedOutcome()
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates the proposed-effect projection
     */
    private static function isProposedEffects(mixed $effects): bool
    {
        if (!is_array($effects) || !array_is_list($effects)) {
            return false;
        }

        $effectClasses = [];

        foreach ($effects as $effect) {
            if (
                !is_array($effect)
                || array_keys($effect) !== ['effect_class']
                || !self::hasNonEmptyStrings($effect)
                || ReleaseEffect::tryFrom($effect['effect_class']) === null
            ) {
                return false;
            }

            $effectClasses[] = $effect['effect_class'];
        }

        $canonical = array_values(array_unique($effectClasses, SORT_STRING));
        sort($canonical, SORT_STRING);

        return $effectClasses === $canonical;
    }

    /**
     * Validates UTF-8 recursively before any payload can cross the result boundary
     */
    private static function hasValidUtf8Recursively(mixed $value): bool
    {
        if (is_string($value)) {
            return preg_match('//u', $value) === 1;
        }

        if (!is_array($value)) {
            return true;
        }

        return array_all(
            $value,
            static fn (mixed $nested, int|string $key): bool => !(
                is_string($key)
                && preg_match('//u', $key) !== 1
                || !self::hasValidUtf8Recursively($nested)
            )
        );
    }

    /**
     * Validates that every array value is one non-empty string
     *
     * @param array<array-key, mixed> $values Candidate values.
     */
    private static function hasNonEmptyStrings(array $values): bool
    {
        return array_all($values, fn($value): bool => !(!is_string($value) || $value === ''));
    }

    /**
     * Validates the singular next-action object
     */
    private static function isNextAction(mixed $nextAction, string $command): bool
    {
        if (
            !is_array($nextAction)
            || !is_string($nextAction['action'] ?? null)
            || $nextAction['action'] === ''
            || array_diff(array_keys($nextAction), ['action', 'version']) !== []
        ) {
            return false;
        }

        return !isset($nextAction['version'])
            || $command === 'inspect' && is_string($nextAction['version']) && $nextAction['version'] !== ''
            || $command === 'compatibility' && SchedulerEvidenceAuthority::isReplanAction($nextAction);
    }

    /**
     * Validates the read-only composed compatibility evidence result
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isCompatibilityFields(array $payload): bool
    {
        if (($payload['exit_code'] ?? null) !== 0) {
            $withoutEvidence = !isset($payload['evidence'])
                && $payload['verified_postconditions'] === []
                && $payload['performed_effects'] === []
                && $payload['proposed_effects'] === [];
            if (!SchedulerEvidenceAuthority::claimsIncompatibility($payload)) {
                return $withoutEvidence && !isset($payload['next_action']['version']);
            }

            return $withoutEvidence && SchedulerEvidenceAuthority::isIncompatibilityResult($payload);
        }

        $evidence = $payload['evidence'] ?? null;
        if (!is_array($evidence) || array_keys($evidence) !== ['manifest', 'structural', 'consumer']) {
            return false;
        }

        $manifest = $evidence['manifest'];
        $structural = $evidence['structural'];
        $consumer = $evidence['consumer'];
        $packageProbes = is_array($consumer) ? ($consumer['package_probes'] ?? null) : null;
        $baselineProbe = is_array($packageProbes) ? ($packageProbes['baseline'] ?? null) : null;
        $candidateProbe = is_array($packageProbes) ? ($packageProbes['candidate'] ?? null) : null;
        $baselineReceipt = is_array($baselineProbe) ? ($baselineProbe['receipt'] ?? null) : null;
        $candidateReceipt = is_array($candidateProbe) ? ($candidateProbe['receipt'] ?? null) : null;
        $baselineCandidate = is_array($baselineReceipt) ? ($baselineReceipt['candidate'] ?? null) : null;
        $candidateCandidate = is_array($candidateReceipt) ? ($candidateReceipt['candidate'] ?? null) : null;
        $baselineTree = is_array($baselineCandidate) ? ($baselineCandidate['production_tree_sha256'] ?? null) : null;
        $candidateTree = is_array($candidateCandidate) ? ($candidateCandidate['production_tree_sha256'] ?? null) : null;

        return is_array($manifest)
            && ($manifest['status'] ?? null) === 'valid'
            && is_array($manifest['baseline'] ?? null)
            && ($manifest['baseline']['version'] ?? null) === '1.1.0'
            && is_array($structural)
            && ($structural['status'] ?? null) === 'valid'
            && in_array($structural['classification'] ?? null, ['patch', 'minor', 'major'], true)
            && is_array($structural['findings'] ?? null)
            && array_is_list($structural['findings'])
            && is_array($consumer)
            && ($consumer['schema_version'] ?? null) === 'fight-common.disposable-public-consumer/v1'
            && ($consumer['status'] ?? null) === 'valid'
            && ($consumer['resolved_package']['installed_as'] ?? null) === 'copy'
            && is_string($consumer['lock']['sha256'] ?? null)
            && preg_match('/\A[0-9a-f]{64}\z/D', $consumer['lock']['sha256']) === 1
            && is_array($packageProbes)
            && array_keys($packageProbes) === ['baseline', 'candidate', 'distinct_installations']
            && ($packageProbes['distinct_installations'] ?? null) === true
            && is_array($baselineProbe)
            && ($baselineProbe['identity'] ?? null) === [
                'version'                => '1.1.0',
                'peeled_commit_oid'      => $manifest['baseline']['peeled_commit_oid'],
                'production_tree_sha256' => $baselineTree
            ]
            && ($baselineProbe['attribution'] ?? null) === 'baseline'
            && is_array($candidateProbe)
            && ($candidateProbe['identity'] ?? null) === ['production_tree_sha256' => $candidateTree]
            && ($candidateProbe['attribution'] ?? null) === 'candidate'
            && SchedulerEvidenceAuthority::isCopiedReceipt($baselineReceipt)
            && SchedulerEvidenceAuthority::isCopiedReceipt($candidateReceipt)
            && SchedulerEvidenceAuthority::receiptsAreEquivalent($baselineReceipt, $candidateReceipt)
            && $candidateReceipt === array_diff_key($consumer, ['package_probes' => true])
            && $payload['verified_postconditions'] === [
                'compatibility_manifest_authenticated',
                'structural_evidence_composed',
                'disposable_public_consumer_verified',
                'baseline_and_candidate_public_probes_verified'
            ]
            && $payload['performed_effects'] === []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => 'review_compatibility_evidence'];
    }

    /**
     * Validates optional inspection result fields
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isInspectionFields(array $payload): bool
    {
        if (isset($payload['resolved_inputs'])) {
            $resolved = $payload['resolved_inputs'];
            $authority = new ReleaseAuthorityValidator();
            $baselineVersion = is_array($resolved) ? self::baselineVersion($resolved['baseline_tag'] ?? null) : null;

            if (
                !is_array($resolved)
                || array_keys($resolved) !== [
                    'source_commit',
                    'baseline_tag',
                    'baseline_tag_object',
                    'baseline_commit',
                    'support_policy'
                ]
                || !self::hasNonEmptyStrings($resolved)
                || !$authority->isGitObjectId($resolved['source_commit'])
                || $baselineVersion === null
                || !new BaselineTagVerifier()->isCanonical($resolved['baseline_tag'], $baselineVersion)
                || !$authority->isGitObjectId($resolved['baseline_tag_object'])
                || !$authority->isGitObjectId($resolved['baseline_commit'])
                || !$authority->isSupportPolicyIdentity($resolved['support_policy'])
            ) {
                return false;
            }
        }

        if (isset($payload['recommendation'])) {
            $recommendation = $payload['recommendation'];

            if (
                !is_array($recommendation)
                || array_keys($recommendation) !== [
                    'minimum_increment',
                    'recommended_version',
                    'authoritative',
                    'compatibility_assessment'
                ]
                || !in_array($recommendation['minimum_increment'], ['patch', 'minor', 'major'], true)
                || !is_string($recommendation['recommended_version'])
                || !StableSemVer::isValid($recommendation['recommended_version'])
                || $recommendation['authoritative'] !== false
                || !self::isCompatibilityAssessment(
                    $recommendation['compatibility_assessment'],
                    $recommendation['minimum_increment']
                )
            ) {
                return false;
            }
        }

        if (($payload['exit_code'] ?? null) !== 0) {
            return !self::containsAnyPostcondition($payload, [
                'inspection_boundary_effect_completed',
                'minimum_increment_recommendation_derived'
            ]);
        }

        if (!isset($payload['resolved_inputs'], $payload['recommendation'])) {
            return false;
        }

        $resolved = $payload['resolved_inputs'];
        $recommendation = $payload['recommendation'];
        $baselineVersion = self::baselineVersion($resolved['baseline_tag']);
        assert($baselineVersion !== null);
        $expectedVersion = StableSemVer::increment($baselineVersion, $recommendation['minimum_increment']);
        $postconditions = $payload['verified_postconditions'];
        $validPostconditions = $postconditions === ['minimum_increment_recommendation_derived']
            || $postconditions === [
                'inspection_boundary_effect_completed',
                'minimum_increment_recommendation_derived'
            ];

        return $expectedVersion === $recommendation['recommended_version']
            && $validPostconditions
            && $payload['next_action'] === [
                'action'  => 'approve_exact_version_for_plan',
                'version' => $recommendation['recommended_version']
            ];
    }

    /**
     * Validates the complete category evidence and its independently derived aggregate
     */
    private static function isCompatibilityAssessment(mixed $candidate, mixed $minimumIncrement): bool
    {
        if (
            !is_array($candidate)
            || array_keys($candidate) !== ['categories', 'rationale']
            || $candidate['rationale'] !== 'maximum_required_increment_across_all_compatibility_categories'
        ) {
            return false;
        }

        $assessment = new CompatibilityAssessment()->assess($candidate['categories']);

        return $assessment['status'] === 'valid' && $assessment['minimum_increment'] === $minimumIncrement;
    }

    /**
     * Validates optional plan identity and artifact fields
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPlanFields(array $payload): bool
    {
        if (
            isset($payload['plan_id'])
            && (!is_string($payload['plan_id']) || preg_match('/\A[0-9a-f]{64}\z/D', $payload['plan_id']) !== 1)
        ) {
            return false;
        }

        if (isset($payload['artifact'])) {
            $artifact = $payload['artifact'];

            if (
                !is_array($artifact)
                || array_keys($artifact) !== ['plan_id', 'path']
                || !self::hasNonEmptyStrings($artifact)
                || !isset($payload['plan_id'])
                || $artifact['plan_id'] !== $payload['plan_id']
            ) {
                return false;
            }
        }

        if (($payload['exit_code'] ?? null) !== 0) {
            return !self::containsAnyPostcondition($payload, [
                'immutable_release_plan_persisted',
                'immutable_release_plan_already_persisted'
            ]);
        }

        if (!isset($payload['plan_id'], $payload['artifact'])) {
            return false;
        }

        $findingIds = array_column($payload['findings'], 'id');
        $created = in_array('release.plan.created', $findingIds, true);
        $alreadySatisfied = in_array('release.plan.already_satisfied', $findingIds, true);
        $expectedPostconditions = match (true) {
            $created && !$alreadySatisfied => ['immutable_release_plan_persisted'],
            $alreadySatisfied && !$created => ['immutable_release_plan_already_persisted'],
            default => null,
        };

        return $expectedPostconditions !== null
            && $payload['verified_postconditions'] === $expectedPostconditions
            && $payload['next_action'] === ['action' => 'create_release_run'];
    }

    /**
     * Validates the complete successful prepared-run projection
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPrepareFields(array $payload): bool
    {
        foreach (['plan_id', 'run_id'] as $field) {
            if (
                isset($payload[$field])
                && (!is_string($payload[$field]) || preg_match('/\A[0-9a-f]{64}\z/D', $payload[$field]) !== 1)
            ) {
                return false;
            }
        }

        if (isset($payload['run_state'])) {
            $state = $payload['run_state'];

            if (
                !is_array($state)
                || array_keys($state) !== ['history_path', 'projection_path']
                || !self::hasNonEmptyStrings($state)
            ) {
                return false;
            }
        }

        if (isset($payload['artifacts']) && !self::isPreparationArtifacts($payload['artifacts'])) {
            return false;
        }

        if (!self::hasConsistentPreparationPaths($payload)) {
            return false;
        }

        if (($payload['exit_code'] ?? null) !== 0) {
            $findingId = $payload['findings'][0]['id'] ?? null;
            $claimsEvidencePersistenceFailure = $findingId === 'release.prepare.evidence_persistence_failed'
                || $payload['next_action'] === ['action' => 'repair_release_evidence_storage'];

            if ($claimsEvidencePersistenceFailure) {
                return self::isPrepareEvidencePersistenceFailure($payload)
                    && isset($payload['plan_id'], $payload['run_id'])
                    && !isset($payload['artifacts']);
            }

            if (($payload['exit_code'] ?? null) === 2) {
                return self::isPreparationInputFailure($payload);
            }

            return self::isPreparationStop($payload);
        }

        if (!isset($payload['plan_id'], $payload['run_id'], $payload['run_state'], $payload['artifacts'])) {
            return false;
        }

        $created = $payload['findings'] === [[
            'id'      => 'release.prepare.completed',
            'message' => 'The immutable plan was revalidated and a distinct prepared run was persisted.'
        ]] && $payload['verified_postconditions'] === [
            'immutable_plan_revalidated',
            'prepared_run_projection_published'
        ];
        $resumed = $payload['findings'] === [[
            'id'      => 'release.prepare.already_satisfied',
            'message' => 'The named prepared run and every claimed postcondition were reverified.'
        ]] && $payload['verified_postconditions'] === [
            'immutable_plan_revalidated',
            'run_event_chain_revalidated',
            'prepared_run_projection_revalidated',
            'prepared_postconditions_reverified'
        ];
        $resumedCompleted = $payload['findings'] === [[
            'id'      => 'release.prepare.resumed_completed',
            'message' => 'The named release run was revalidated and preparation completed during resume.'
        ]] && $payload['verified_postconditions'] === [
            'immutable_plan_revalidated',
            'run_event_chain_revalidated',
            'prepared_run_projection_published',
            'prepared_postconditions_verified'
        ];

        return ($created || $resumed || $resumedCompleted)
            && $payload['next_action'] === ['action' => 'package_release_run']
            && self::hasSuccessfulPreparationEffects($payload, $resumed, $resumedCompleted);
    }

    /**
     * Validates one exact pre-identity preparation rejection emitted by the result factory
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPreparationInputFailure(array $payload): bool
    {
        $findingId = $payload['findings'][0]['id'] ?? null;
        $contract = is_string($findingId) ? self::PREPARATION_INPUT_FAILURE_CONTRACTS[$findingId] ?? null : null;

        if ($contract === null) {
            return false;
        }

        $allowedEffects = match ($findingId) {
            'release.prepare.plan_forbidden' => [
                'filesystem.inspect_runs_directory', 'filesystem.inspect_directory', 'filesystem.inspect_writable'
            ],
            'release.prepare.plan_unreadable' => [
                'filesystem.inspect_runs_directory', 'filesystem.inspect_directory',
                'filesystem.inspect_writable', 'filesystem.read'
            ],
            'release.prepare.plan_invalid', 'release.prepare.run_identity_invalid' => [
                'filesystem.inspect_runs_directory', 'filesystem.inspect_directory',
                'filesystem.inspect_writable', 'filesystem.read', 'hashing.sha256'
            ],
            default => []
        };
        $causalEffect = match ($findingId) {
            'release.prepare.plan_forbidden' => 'filesystem.inspect_runs_directory',
            'release.prepare.plan_unreadable' => 'filesystem.read',
            'release.prepare.plan_invalid' => self::finalPerformedOutcome(
                $payload,
                'hashing.sha256'
            ) === null ? 'filesystem.read' : 'hashing.sha256',
            'release.prepare.run_identity_invalid' => 'hashing.sha256',
            default => null
        };
        $hasCausalEffect = $payload['performed_effects'] === [];
        if ($causalEffect !== null) {
            $hasCausalEffect = self::finalPerformedOutcome($payload, $causalEffect) !== null;
        }

        return $payload['findings'] === [['id' => $findingId, 'message' => $contract['message']]]
            && $payload['verified_postconditions'] === []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => $contract['action']]
            && array_intersect(
                array_keys($payload),
                ['plan_id', 'run_id', 'run_state', 'artifacts']
            ) === []
            && self::hasOnlyPerformedEffectClasses($payload, $allowedEffects)
            && $hasCausalEffect;
    }

    /**
     * Validates one exact artifact-backed preparation stop emitted by the result factory
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPreparationStop(array $payload): bool
    {
        $findingId = $payload['findings'][0]['id'] ?? null;
        $contract = is_string($findingId) ? self::PREPARATION_STOP_CONTRACTS[$findingId] ?? null : null;

        return $contract !== null
            && ($payload['status'] ?? null) === $contract['status']
            && ($payload['exit_class'] ?? null) === $contract['exit_class']
            && ($payload['exit_code'] ?? null) === $contract['exit_code']
            && $payload['findings'] === [['id' => $findingId, 'message' => $contract['message']]]
            && $payload['verified_postconditions'] === []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => $contract['action']]
            && isset($payload['plan_id'], $payload['run_id'], $payload['artifacts'])
            && !isset($payload['run_state'])
            && self::hasOnlyPreparationEffects($payload)
            && self::hasPreparationStopEffects($payload, $findingId);
    }

    /**
     * Reports whether every recorded effect belongs to the exact admitted preparation boundary set
     *
     * @param array<string, mixed> $payload Candidate payload.
     * @param array                $effectClasses Admitted effect classes.
     *
     * @phpstan-param list<string> $effectClasses
     */
    private static function hasOnlyPerformedEffectClasses(array $payload, array $effectClasses): bool
    {
        return array_all(
            $payload['performed_effects'],
            static fn (array $effect): bool => in_array($effect['effect_class'], $effectClasses, true)
        );
    }

    /**
     * Returns the final recorded outcome for one effect class
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function finalPerformedOutcome(array $payload, string $effectClass): ?string
    {
        $outcome = null;

        foreach ($payload['performed_effects'] as $effect) {
            if ($effect['effect_class'] === $effectClass) {
                $outcome = $effect['outcome'];
            }
        }

        return $outcome;
    }

    /**
     * Returns the final ledger position for one effect class
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function finalPerformedPosition(array $payload, string $effectClass): ?int
    {
        $position = null;

        foreach ($payload['performed_effects'] as $index => $effect) {
            if ($effect['effect_class'] === $effectClass) {
                $position = $index;
            }
        }

        return $position;
    }

    /**
     * Returns the final ledger position for one exact effect-class outcome
     *
     * @param array<string, mixed> $payload Candidate payload.
     * @param string               $effectClass Exact effect class.
     * @param array                $outcomes Admitted effect outcomes.
     *
     * @phpstan-param list<string> $outcomes
     */
    private static function finalPerformedOutcomePosition(
        array $payload,
        string $effectClass,
        array $outcomes
    ): ?int {
        $position = null;

        foreach ($payload['performed_effects'] as $index => $effect) {
            if ($effect['effect_class'] === $effectClass && in_array($effect['outcome'], $outcomes, true)) {
                $position = $index;
            }
        }

        return $position;
    }

    /**
     * Reports whether the terminal observation of one boundary has an admitted causal outcome
     *
     * @param array<string, mixed> $payload Candidate payload.
     * @param string               $effectClass Exact effect class.
     * @param array                $outcomes Admitted terminal outcomes.
     *
     * @phpstan-param list<string> $outcomes
     */
    private static function hasTerminalPerformedEffect(array $payload, string $effectClass, array $outcomes): bool
    {
        $outcome = self::finalPerformedOutcome($payload, $effectClass);

        return $outcome !== null && in_array($outcome, $outcomes, true);
    }

    /**
     * Counts exact effect-class outcomes in the ordered ledger projection
     *
     * @param array<string, mixed> $payload Candidate payload.
     * @param string               $effectClass Exact effect class.
     * @param array                $outcomes Admitted effect outcomes.
     *
     * @phpstan-param list<string> $outcomes
     */
    private static function countPerformedEffects(array $payload, string $effectClass, array $outcomes): int
    {
        return count(array_filter(
            $payload['performed_effects'],
            static fn (array $effect): bool => $effect['effect_class'] === $effectClass
                && in_array($effect['outcome'], $outcomes, true)
        ));
    }

    /**
     * Reports whether the immutable plan was resolved, read, and hashed in this invocation
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function hasPreparationRevalidationEffects(array $payload): bool
    {
        return self::hasTerminalPerformedEffect($payload, 'filesystem.inspect_runs_directory', ['success'])
            && self::countPerformedEffects($payload, 'filesystem.read', ['success']) >= 1
            && self::countPerformedEffects($payload, 'hashing.sha256', ['success']) >= 1;
    }

    /**
     * Reports whether both content-addressed evidence artifacts were hashed and durably observed
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function hasPreparationArtifactEffects(array $payload): bool
    {
        $observedWrites = self::countPerformedEffects(
            $payload,
            'filesystem.write',
            ['success', 'already_satisfied']
        ) >= 2;
        $verifiedReads = self::countPerformedEffects($payload, 'filesystem.read', ['success']) >= 3;

        return self::countPerformedEffects($payload, 'hashing.sha256', ['success']) >= 3
            && ($observedWrites || $verifiedReads);
    }

    /**
     * Reports whether the ledger proves every causal claim of preparation success
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function hasSuccessfulPreparationEffects(
        array $payload,
        bool $resumed,
        bool $resumedCompleted
    ): bool {
        if (
            !self::hasPreparationRevalidationEffects($payload)
            || !self::hasTerminalPerformedEffect($payload, 'filesystem.read', ['success'])
            || !self::hasTerminalPerformedEffect($payload, 'hashing.sha256', ['success'])
            || !self::hasTerminalPerformedEffect($payload, 'git.resolve_ref', ['success'])
            || !self::hasTerminalPerformedEffect($payload, 'authorization.check', ['success'])
            || self::countPerformedEffects($payload, 'hashing.sha256', ['success']) < 3
            || !self::hasOnlyPerformedEffectClasses($payload, self::preparationEffectClasses(true, true))
        ) {
            return false;
        }

        if ($resumedCompleted) {
            return self::countPerformedEffects($payload, 'filesystem.read', ['success']) >= 3
                && self::hasTerminalPerformedEffect(
                    $payload,
                    'filesystem.write',
                    ['success', 'already_satisfied']
                );
        }

        if ($resumed) {
            return self::countPerformedEffects($payload, 'filesystem.read', ['success']) >= 3;
        }

        return self::hasPreparationArtifactEffects($payload)
            && self::hasTerminalPerformedEffect($payload, 'filesystem.write', ['success', 'already_satisfied']);
    }

    /**
     * Reports whether the ledger proves the exact causal outcome for one classified stop
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function hasPreparationStopEffects(array $payload, string $findingId): bool
    {
        $stateFinding = in_array($findingId, [
            'release.prepare.resume_state_missing',
            'release.prepare.resume_contention',
            'release.prepare.resume_plan_drift',
            'release.prepare.state_persistence_failed',
            'release.prepare.run_identity_conflict',
            'release.prepare.state_persistence_indeterminate',
            'release.prepare.resume_state_indeterminate'
        ], true);

        if (
            !self::hasPreparationRevalidationEffects($payload)
            || !self::hasPreparationArtifactEffects($payload)
        ) {
            return false;
        }

        if (
            $findingId !== 'release.prepare.artifacts_indeterminate'
            && (
                !$stateFinding
                && !self::hasTerminalPerformedEffect($payload, 'filesystem.read', ['success'])
                || !self::hasTerminalPerformedEffect($payload, 'hashing.sha256', ['success'])
            )
        ) {
            return false;
        }

        /** @phpstan-ignore match.unhandled (finding is admitted by the closed contract before this call) */
        [$effectClass, $outcomes] = match ($findingId) {
            'release.prepare.state_persistence_failed' => ['filesystem.write', ['failure']],
            'release.prepare.run_identity_conflict' => ['filesystem.write', ['refusal']],
            'release.prepare.resume_state_missing',
            'release.prepare.resume_plan_drift',
            'release.prepare.resume_state_indeterminate' => ['filesystem.read', ['uncertainty']],
            'release.prepare.resume_contention' => ['filesystem.read', ['refusal']],
            'release.prepare.state_persistence_indeterminate' => ['filesystem.write', ['uncertainty']],
            'release.prepare.artifacts_indeterminate' => [
                'filesystem.write',
                ['refusal', 'failure', 'uncertainty']
            ],
            'release.prepare.baseline_resolution_refused' => ['git.resolve_ref', ['refusal']],
            'release.prepare.baseline_resolution_failed' => ['git.resolve_ref', ['failure']],
            'release.prepare.baseline_resolution_uncertain' => ['git.resolve_ref', ['uncertainty']],
            'release.prepare.baseline_resolution_drift' => ['git.resolve_ref', ['success', 'drift']],
            'release.prepare.baseline_tag_missing',
            'release.prepare.baseline_tag_ambiguous',
            'release.prepare.baseline_tag_duplicate_normalized',
            'release.prepare.baseline_tag_non_ancestor' => [
                'git.resolve_ref', ['success', 'refusal', 'failure', 'uncertainty', 'drift']
            ],
            'release.prepare.plan_authority_refused',
            'release.prepare.plan_authority_failed',
            'release.prepare.plan_authority_uncertain' => [
                'authorization.check', ['success', 'refusal', 'failure', 'uncertainty', 'drift']
            ],
            'release.prepare.support_policy_drift',
            'release.prepare.approval_authority_drift',
            'release.prepare.evidence_authority_drift',
            'release.prepare.compatibility_authority_drift' => ['authorization.check', ['success']]
        };

        $gitIsCausal = str_starts_with($findingId, 'release.prepare.baseline_');
        $authorityIsCausal = str_contains($findingId, 'authority')
            || in_array($findingId, [
                'release.prepare.support_policy_drift',
                'release.prepare.evidence_authority_drift',
                'release.prepare.compatibility_authority_drift'
            ], true);
        $authorityPosition = self::finalPerformedPosition($payload, 'authorization.check');
        $gitPosition = self::finalPerformedPosition($payload, 'git.resolve_ref');
        $writePosition = self::finalPerformedPosition($payload, 'filesystem.write');
        if (
            $gitIsCausal
            && $authorityPosition !== null
            && (
                !self::hasTerminalPerformedEffect($payload, 'authorization.check', ['success'])
                || $gitPosition === null
                || $authorityPosition > $gitPosition
            )
        ) {
            return false;
        }

        if (
            $authorityIsCausal
            && $gitPosition !== null
            && !self::hasTerminalPerformedEffect($payload, 'git.resolve_ref', ['success'])
        ) {
            return false;
        }

        if (
            $stateFinding
            && (
                $gitPosition !== null
                && (!self::hasTerminalPerformedEffect($payload, 'git.resolve_ref', ['success'])
                    || $writePosition === null || $gitPosition > $writePosition)
                || $authorityPosition !== null
                && (!self::hasTerminalPerformedEffect($payload, 'authorization.check', ['success'])
                    || $writePosition === null || $authorityPosition > $writePosition)
            )
        ) {
            return false;
        }

        if ($findingId === 'release.prepare.artifacts_indeterminate') {
            return array_any(
                $payload['performed_effects'],
                static fn (array $effect): bool => in_array(
                    $effect['effect_class'],
                    ['filesystem.read', 'filesystem.write', 'hashing.sha256'],
                    true
                ) && in_array($effect['outcome'], $outcomes, true)
            );
        }

        if (!$stateFinding) {
            return self::hasTerminalPerformedEffect($payload, $effectClass, $outcomes);
        }

        $causalClasses = in_array($findingId, [
            'release.prepare.resume_contention',
            'release.prepare.state_persistence_indeterminate',
            'release.prepare.resume_state_indeterminate'
        ], true) ? ['filesystem.read', 'filesystem.write'] : [$effectClass];
        $causalPosition = max(array_map(
            static fn (string $causalClass): int => self::finalPerformedOutcomePosition(
                $payload,
                $causalClass,
                $outcomes
            ) ?? -1,
            $causalClasses
        ));
        $finalEvidencePosition = max(
            self::finalPerformedPosition($payload, 'filesystem.read') ?? -1,
            self::finalPerformedPosition($payload, 'filesystem.write') ?? -1,
            self::finalPerformedPosition($payload, 'hashing.sha256') ?? -1
        );

        return $causalPosition >= 0 && $causalPosition < $finalEvidencePosition;
    }

    /**
     * Rejects effects owned by later release phases from a preparation result
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function hasOnlyPreparationEffects(array $payload): bool
    {
        return self::hasOnlyPerformedEffectClasses($payload, self::preparationEffectClasses(true, true));
    }

    /**
     * Returns the closed preparation effect vocabulary for the claimed execution depth
     *
     * @return list<string>
     */
    private static function preparationEffectClasses(bool $git, bool $authority): array
    {
        $classes = [
            'filesystem.inspect_runs_directory',
            'filesystem.inspect_directory',
            'filesystem.inspect_writable',
            'filesystem.read',
            'filesystem.write',
            'hashing.sha256'
        ];

        if ($git) {
            $classes[] = 'git.resolve_ref';
        }

        if ($authority) {
            $classes[] = 'authorization.check';
        }

        return $classes;
    }

    /**
     * Validates the sole identity-bearing normal stop admitted without durable artifact references
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPrepareEvidencePersistenceFailure(array $payload): bool
    {
        $hasFailedPersistenceEffect = self::hasClosedEvidencePersistenceFailure($payload);
        $revalidatedPersistedStop = isset($payload['run_state'])
            && $payload['verified_postconditions'] === [
                'run_event_chain_revalidated',
                'stopped_run_projection_revalidated'
            ]
            && self::hasPreparationRevalidationEffects($payload);

        return ($payload['exit_code'] ?? null) === 5
            && $payload['findings'] === [[
                'id'      => 'release.prepare.evidence_persistence_failed',
                'message' => 'Preparation evidence could not be durably persisted or reverified.'
            ]]
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => 'repair_release_evidence_storage']
            && self::hasOnlyPreparationEffects($payload)
            && (
                $hasFailedPersistenceEffect
                && $payload['verified_postconditions'] === []
                && !isset($payload['run_state'])
                && self::hasPreparationRevalidationEffects($payload)
                || $revalidatedPersistedStop
            );
    }

    /**
     * Reports an evidence failure, admitting only the final state-stop persistence after that failure
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function hasClosedEvidencePersistenceFailure(array $payload): bool
    {
        $failurePosition = null;

        foreach ($payload['performed_effects'] as $position => $effect) {
            if (
                in_array($effect['effect_class'], ['filesystem.read', 'filesystem.write', 'hashing.sha256'], true)
                && in_array($effect['outcome'], ['refusal', 'failure', 'uncertainty'], true)
            ) {
                $failurePosition = $position;
            }
        }

        if ($failurePosition === null) {
            return false;
        }

        $followingEffects = array_slice($payload['performed_effects'], $failurePosition + 1);

        return in_array($followingEffects, [[], [[
            'capability'   => 'filesystem',
            'effect_class' => 'filesystem.write',
            'outcome'      => 'success'
        ]], [[
            'capability'   => 'filesystem',
            'effect_class' => 'filesystem.write',
            'outcome'      => 'already_satisfied'
        ]]], true);
    }

    /**
     * Validates content-addressed preparation artifact references
     */
    private static function isPreparationArtifacts(mixed $artifacts): bool
    {
        if (
            !is_array($artifacts)
            || array_keys($artifacts) !== ['evidence_manifest', 'phase_handoff']
        ) {
            return false;
        }


        $manifest = $artifacts['evidence_manifest'];
        $handoff = $artifacts['phase_handoff'];

        return is_array($manifest)
            && array_keys($manifest) === ['manifest_id', 'path']
            && self::hasNonEmptyStrings($manifest)
            && preg_match('/\A[0-9a-f]{64}\z/D', $manifest['manifest_id']) === 1
            && str_ends_with($manifest['path'], '/'.$manifest['manifest_id'].'.evidence-manifest.json')
            && is_array($handoff)
            && array_keys($handoff) === ['handoff_id', 'path']
            && self::hasNonEmptyStrings($handoff)
            && preg_match('/\A[0-9a-f]{64}\z/D', $handoff['handoff_id']) === 1
            && str_ends_with($handoff['path'], '/'.$handoff['handoff_id'].'.phase-handoff.json');
    }

    /**
     * Validates that all preparation paths derive from one exact output root and run identity
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function hasConsistentPreparationPaths(array $payload): bool
    {
        $runId = $payload['run_id'] ?? null;

        if (!is_string($runId)) {
            return !isset($payload['run_state'], $payload['artifacts']);
        }

        $root = null;

        if (isset($payload['run_state'])) {
            $state = $payload['run_state'];
            $historySuffix = '/runs/'.$runId.'/history.jsonl';

            if (!is_array($state) || !str_ends_with($state['history_path'], $historySuffix)) {
                return false;
            }

            $root = substr($state['history_path'], 0, -strlen($historySuffix));
            if (
                !self::isCanonicalAbsolutePath($root)
                || $state['projection_path'] !== $root.'/runs/'.$runId.'/projection.json'
            ) {
                return false;
            }
        }

        if (isset($payload['artifacts'])) {
            $manifest = $payload['artifacts']['evidence_manifest'];
            $handoff = $payload['artifacts']['phase_handoff'];
            $manifestSuffix = '/'.$manifest['manifest_id'].'.evidence-manifest.json';
            $artifactRoot = substr($manifest['path'], 0, -strlen($manifestSuffix));

            if (
                !self::isCanonicalAbsolutePath($artifactRoot)
                || $manifest['path'] !== $artifactRoot.'/'.$manifest['manifest_id'].'.evidence-manifest.json'
                || $handoff['path'] !== $artifactRoot.'/'.$handoff['handoff_id'].'.phase-handoff.json'
                || $root !== null && $artifactRoot !== $root
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reports whether one absolute path is already in canonical lexical form
     */
    private static function isCanonicalAbsolutePath(string $path): bool
    {
        return preg_match('/\A\/(?:[^\/\x00]+)(?:\/[^\/\x00]+)*\z/D', $path) === 1
            && array_all(
                explode('/', substr($path, 1)),
                static fn (string $segment): bool => !in_array($segment, ['.', '..'], true)
            );
    }

    /**
     * Reports whether the result claims any command-specific successful postcondition
     *
     * @param array<string, mixed> $payload        Candidate payload.
     * @param array<int, string>   $postconditions Successful postconditions.
     *
     * @phpstan-param list<string> $postconditions
     */
    private static function containsAnyPostcondition(array $payload, array $postconditions): bool
    {
        return array_intersect($payload['verified_postconditions'], $postconditions) !== [];
    }

    /**
     * Resolves the stable version represented by one supported baseline tag form
     */
    private static function baselineVersion(mixed $tag): ?string
    {
        if (!is_string($tag)) {
            return null;
        }

        if ($tag === '1.1.0') {
            return $tag;
        }

        if (!str_starts_with($tag, 'v')) {
            return null;
        }

        $version = substr($tag, 1);

        return StableSemVer::isValid($version) ? $version : null;
    }

    /**
     * Validates optional package result fields
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPackageFields(array $payload): bool
    {
        foreach (['plan_id', 'run_id'] as $field) {
            if (
                isset($payload[$field])
                && (!is_string($payload[$field]) || preg_match('/\A[0-9a-f]{64}\z/D', $payload[$field]) !== 1)
            ) {
                return false;
            }
        }

        if (
            isset($payload['candidate_oid'])
            && (!is_string($payload['candidate_oid'])
                || preg_match('/\A[0-9a-f]{40,64}\z/D', $payload['candidate_oid']) !== 1)
        ) {
            return false;
        }

        if (isset($payload['archive_digest'])) {
            if (
                !is_string($payload['archive_digest'])
                || preg_match('/\A[0-9a-f]{64}\z/D', $payload['archive_digest']) !== 1
            ) {
                return false;
            }
        }

        if (isset($payload['effect_set'])) {
            $effectSet = $payload['effect_set'];

            if (
                !is_array($effectSet)
                || ($effectSet['schema_version'] ?? null) !== ReleasePackageEffectSet::SCHEMA_VERSION
                || !is_string($effectSet['effect_set_id'] ?? null)
                || preg_match('/\A[0-9a-f]{64}\z/D', $effectSet['effect_set_id']) !== 1
                || !is_string($effectSet['candidate_oid'] ?? null)
                || !is_string($effectSet['version'] ?? null)
                || !is_string($effectSet['archive_name'] ?? null)
                || !is_array($effectSet['included_paths'] ?? null)
                || !array_is_list($effectSet['included_paths'])
                || !is_array($effectSet['excluded_paths'] ?? null)
                || !array_is_list($effectSet['excluded_paths'])
                || array_keys($effectSet) !== [
                    'schema_version',
                    'effect_set_id',
                    'candidate_oid',
                    'version',
                    'archive_name',
                    'included_paths',
                    'excluded_paths'
                ]
            ) {
                return false;
            }
        }

        if (($payload['exit_code'] ?? null) !== 0) {
            $findingId = $payload['findings'][0]['id'] ?? null;

            if (($payload['exit_code'] ?? null) === 2) {
                return self::isPackageInputFailure($payload);
            }

            return self::isPackageStop($payload, $findingId);
        }

        if (!isset($payload['plan_id'], $payload['run_id'], $payload['candidate_oid'], $payload['archive_digest'])) {
            return false;
        }

        $created = $payload['findings'] === [[
            'id'      => 'release.package.completed',
            'message' => 'The deterministic release archive was created and its identity was bound.'
        ]] && $payload['verified_postconditions'] === [
            'phase_handoff_revalidated',
            'archive_created_and_verified'
        ];
        $alreadySatisfied = $payload['findings'] === [[
            'id'      => 'release.package.already_satisfied',
            'message' => 'The deterministic release archive already existed and was verified.'
        ]] && $payload['verified_postconditions'] === [
            'phase_handoff_revalidated',
            'archive_already_persisted'
        ];

        return ($created || $alreadySatisfied)
            && $payload['next_action'] === ['action' => 'certify_release_package']
            && isset($payload['effect_set'])
            && self::isCertificationHandoffArtifacts($payload['artifacts'] ?? null);
    }

    /**
     * Validates the minimal package-bound certification result
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isCertificationFields(array $payload): bool
    {
        if (($payload['exit_code'] ?? null) !== 0) {
            if (($payload['exit_code'] ?? null) === 2) {
                return !isset($payload['plan_id'], $payload['run_id'], $payload['artifacts']);
            }

            $indeterminate = ($payload['exit_code'] ?? null) === 5;
            $expectedAction = 'repair_failed_certification_lane';
            if ($indeterminate) {
                $expectedAction = 'reconcile_certification_evidence';
            }

            return isset($payload['plan_id'], $payload['run_id'], $payload['run_state'], $payload['artifacts'])
                && is_string($payload['plan_id'])
                && is_string($payload['run_id'])
                && preg_match('/\A[0-9a-f]{64}\z/D', $payload['plan_id']) === 1
                && preg_match('/\A[0-9a-f]{64}\z/D', $payload['run_id']) === 1
                && self::isCertificationStopFinding($payload['findings'], $indeterminate)
                && $payload['verified_postconditions'] === [
                    'package_handoff_revalidated',
                    'certification_stop_persisted'
                ]
                && $payload['proposed_effects'] === []
                && $payload['next_action'] === ['action' => $expectedAction]
                && self::isCertificationRunState($payload['run_state'])
                && self::isCertificationStopArtifacts($payload['artifacts']);
        }

        return isset($payload['plan_id'], $payload['run_id'], $payload['run_state'])
            && is_string($payload['plan_id'])
            && is_string($payload['run_id'])
            && preg_match('/\A[0-9a-f]{64}\z/D', $payload['plan_id']) === 1
            && preg_match('/\A[0-9a-f]{64}\z/D', $payload['run_id']) === 1
            && $payload['findings'] === [[
                'id'      => 'release.certification.manifest_persisted',
                'message' => 'The verified package handoff was bound into an immutable certification manifest.'
            ]]
            && $payload['verified_postconditions'] === [
                'package_handoff_revalidated',
                'certification_manifest_persisted'
            ]
            && $payload['performed_effects'] !== []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => 'review_certification_manifest']
            && self::isCertificationRunState($payload['run_state'])
            && self::isCertificationManifestArtifacts($payload['artifacts'] ?? null);
    }

    /**
     * Validates the atomic current-state reference returned by certification
     */
    private static function isCertificationRunState(mixed $state): bool
    {
        return is_array($state)
            && array_keys($state) === [
                'status', 'history_path', 'projection_path', 'sequence', 'state', 'history_sha256',
                'projection_sha256', 'certification_artifact_id', 'prerequisite_certification_handoff_id'
            ]
            && is_string($state['history_path'] ?? null)
            && is_string($state['projection_path'] ?? null)
            && is_int($state['sequence'] ?? null)
            && $state['sequence'] > 0
            && in_array($state['state'] ?? null, ['certified', 'certification_failed', 'evidence_indeterminate'], true)
            && $state['status'] === 'created'
            && self::hasNonEmptyStrings([
                'history_sha256'                        => $state['history_sha256'] ?? null,
                'projection_sha256'                     => $state['projection_sha256'] ?? null,
                'certification_artifact_id'             => $state['certification_artifact_id'] ?? null,
                'prerequisite_certification_handoff_id' => $state['prerequisite_certification_handoff_id'] ?? null
            ])
            && array_all([
                $state['history_sha256'], $state['projection_sha256'], $state['certification_artifact_id'],
                $state['prerequisite_certification_handoff_id']
            ], static fn (string $value): bool => preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1);
    }

    /**
     * Validates the certification-handoff artifact reference
     *
     * @param mixed $artifacts
     */
    private static function isCertificationHandoffArtifacts(mixed $artifacts): bool
    {
        return is_array($artifacts)
            && array_keys($artifacts) === ['certification_handoff']
            && self::isArtifactReference(
                $artifacts['certification_handoff'],
                'handoff_id',
                '.certification-handoff.json'
            );
    }

    /**
     * Validates the certification-manifest artifact reference
     *
     * @param mixed $artifacts
     */
    private static function isCertificationManifestArtifacts(mixed $artifacts): bool
    {
        return is_array($artifacts)
            && array_keys($artifacts) === ['certification_manifest']
            && self::isArtifactReference(
                $artifacts['certification_manifest'],
                'manifest_id',
                '.certification-manifest.json'
            );
    }

    /**
     * Validates the certification-stop artifact reference
     *
     * @param mixed $artifacts
     */
    private static function isCertificationStopArtifacts(mixed $artifacts): bool
    {
        return is_array($artifacts)
            && array_keys($artifacts) === ['certification_stop']
            && self::isArtifactReference($artifacts['certification_stop'], 'stop_id', '.certification-stop.json');
    }

    /**
     * Validates one classified certification stop finding
     *
     * @param mixed $findings
     */
    private static function isCertificationStopFinding(mixed $findings, bool $indeterminate): bool
    {
        $finding = null;
        if (is_array($findings) && array_is_list($findings) && count($findings) === 1) {
            $finding = $findings[0];
        }

        $prefix = 'A required certification lane failed: ';
        $findingId = 'release.certification.lane_failed';
        if ($indeterminate) {
            $prefix = 'A required certification lane has no composed authoritative evidence: ';
            $findingId = 'release.certification.evidence_indeterminate';
        }

        return is_array($finding)
            && $finding['id'] === $findingId
            && is_string($finding['message'] ?? null)
            && str_starts_with($finding['message'], $prefix)
            && str_ends_with($finding['message'], '.');
    }

    /**
     * Validates one named certification artifact reference
     *
     * @param mixed $artifact
     */
    private static function isArtifactReference(mixed $artifact, string $idField, string $suffix): bool
    {
        return is_array($artifact)
            && array_keys($artifact) === [$idField, 'path']
            && is_string($artifact[$idField] ?? null)
            && preg_match('/\A[0-9a-f]{64}\z/D', $artifact[$idField]) === 1
            && is_string($artifact['path'] ?? null)
            && str_ends_with($artifact['path'], '/'.$artifact[$idField].$suffix);
    }

    /**
     * Validates one exact pre-identity package rejection
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPackageInputFailure(array $payload): bool
    {
        $findingId = $payload['findings'][0]['id'] ?? null;

        $expected = match ($findingId) {
            'release.package.handoff_forbidden' => [
                'release.package.handoff_forbidden',
                'Packaging requires one phase handoff below the repository .runs directory.',
                'select_immutable_phase_handoff',
                []
            ],
            'release.package.handoff_unreadable' => [
                'release.package.handoff_unreadable',
                'The phase handoff could not be read.',
                'select_immutable_phase_handoff',
                []
            ],
            'release.package.handoff_invalid' => [
                'release.package.handoff_invalid',
                'The phase handoff failed canonical identity or binding revalidation.',
                'create_current_release_plan',
                []
            ],
            'release.package.effect_set_derivation_failed' => [
                'release.package.effect_set_derivation_failed',
                'The archive effect set could not be derived from the candidate commit.',
                'repair_release_repository_storage',
                []
            ],
            'release.package.approval_unreadable' => [
                'release.package.approval_unreadable',
                'The package approval could not be read.',
                'provide_valid_package_approval',
                []
            ],
            'release.package.approval_invalid' => [
                'release.package.approval_invalid',
                'The package approval must be valid JSON.',
                'provide_valid_package_approval',
                []
            ],
            default => null
        };

        if ($expected === null) {
            return false;
        }

        $message = $payload['findings'][0]['message'] ?? null;

        return $message === $expected[1]
            && $payload['verified_postconditions'] === []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => $expected[2]]
            && array_intersect(
                array_keys($payload),
                ['plan_id', 'run_id', 'candidate_oid', 'archive_digest', 'effect_set', 'handoff']
            ) === [];
    }

    /**
     * Validates one exact artifact-backed package stop
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPackageStop(array $payload, mixed $findingId): bool
    {
        return match ($findingId) {
            'release.package.effect_set_refused' => (
                ($payload['status'] ?? null) === 'authority_required'
                && ($payload['exit_class'] ?? null) === 'refused'
                && ($payload['exit_code'] ?? null) === 3
                && $payload['findings'] === [[
                    'id'      => 'release.package.effect_set_refused',
                    'message' => 'The packaging effect set was not approved for the exact bounded local effects.'
                ]]
                && $payload['verified_postconditions'] === []
                && $payload['proposed_effects'] === []
                && $payload['next_action'] === ['action' => 'approve_exact_packaging_effects']
                && isset($payload['plan_id'], $payload['run_id'])
            ),
            'release.package.archive_creation_refused' => self::isPackageArchiveStop(
                $payload,
                'authority_required', 'refused', 3,
                'release.package.archive_creation_refused',
                'The deterministic archive creation was refused by the archive provider.',
                'obtain_archive_creation_authority'
            ),
            'release.package.archive_creation_failed' => self::isPackageArchiveStop(
                $payload,
                'policy_blocked', 'failed', 4,
                'release.package.archive_creation_failed',
                'The deterministic archive could not be created.',
                'repair_archive_creation_provider'
            ),
            'release.package.archive_creation_uncertain' => self::isPackageArchiveStop(
                $payload,
                'evidence_indeterminate', 'uncertain', 5,
                'release.package.archive_creation_uncertain',
                'The archive creation outcome could not be determined.',
                'reconcile_archive_creation'
            ),
            'release.package.archive_creation_drift' => self::isPackageArchiveStop(
                $payload,
                'stale_plan', 'drifted', 6,
                'release.package.archive_creation_drift',
                'The candidate commit identity drifted during archive creation.',
                'create_current_release_plan'
            ),
            'release.package.archive_creation_indeterminate' => self::isPackageArchiveStop(
                $payload,
                'evidence_indeterminate', 'uncertain', 5,
                'release.package.archive_creation_indeterminate',
                'The archive creation state is indeterminate.',
                'reconcile_archive_creation'
            ),
            default => false
        };
    }

    /**
     * Validates one package archive-classified stop
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isPackageArchiveStop(
        array $payload,
        string $expectedStatus,
        string $expectedExitClass,
        int $expectedExitCode,
        string $expectedFindingId,
        string $expectedMessage,
        string $expectedAction
    ): bool {
        return ($payload['status'] ?? null) === $expectedStatus
            && ($payload['exit_class'] ?? null) === $expectedExitClass
            && ($payload['exit_code'] ?? null) === $expectedExitCode
            && $payload['findings'] === [['id' => $expectedFindingId, 'message' => $expectedMessage]]
            && $payload['verified_postconditions'] === []
            && $payload['proposed_effects'] === []
            && $payload['next_action'] === ['action' => $expectedAction]
            && isset($payload['plan_id'], $payload['run_id']);
    }
}
