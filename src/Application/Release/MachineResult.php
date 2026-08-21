<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\Boundary\ReleaseEffect;
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
        $expectedCapability = match ($command) {
            'inspect' => 'release_inspection',
            'plan' => 'release_planning',
            default => 'unsupported_command',
        };
        $expectedClassification = match ($processExitCode) {
            0 => ['succeeded', 'success'],
            2 => ['policy_blocked', 'invalid_input'],
            3 => ['authority_required', 'refused'],
            4 => ['policy_blocked', 'failed'],
            5 => ['evidence_indeterminate', 'uncertain'],
            6 => ['stale_plan', 'drifted'],
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
            || !self::isFindings($payload['findings'] ?? null, $classification, $processExitCode)
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

        $optional = match ($command) {
            'inspect' => ['resolved_inputs', 'recommendation'],
            'plan' => ['plan_id', 'artifact'],
            default => [],
        };

        if (array_diff(array_keys($payload), [...$required, ...$optional]) !== []) {
            return false;
        }

        return match ($command) {
            'inspect' => self::isInspectionFields($payload),
            'plan' => self::isPlanFields($payload),
            default => true,
        };
    }

    /**
     * Validates the sole infrastructure-owned failure admitted by the v1 result contract
     *
     * @param array<string, mixed> $payload Candidate payload.
     */
    private static function isRuntimeBootstrapFailure(array $payload): bool
    {
        return in_array($payload['command'], ['inspect', 'plan', 'unknown'], true)
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
        return in_array($payload['command'], ['inspect', 'plan', 'unknown'], true)
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
    private static function isFindings(mixed $findings, array $classification, int $exitCode): bool
    {
        if (!is_array($findings) || !array_is_list($findings) || $findings === []) {
            return false;
        }

        return array_all(
            $findings,
            static function (mixed $finding) use ($classification, $exitCode): bool {
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
            || ($command === 'inspect' && is_string($nextAction['version']) && $nextAction['version'] !== '');
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
}
