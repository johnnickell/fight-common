<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class SchedulerEvidenceAuthority
 *
 * Owns the closed Scheduler compatibility evidence and major-replan contracts.
 */
final readonly class SchedulerEvidenceAuthority
{
    /**
     * Returns the stable finding set for one public Scheduler probe
     *
     * @return list<array{finding_id: string, evidence_id: string, attribution: string, status: string}>
     */
    public static function findings(bool $portableRunner): array
    {
        $findings = [...self::publicApiFindings(), [
            'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-construction-passed',
            'evidence_id' => 'fight-common.behavior.scheduler-legacy-construction',
            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
            'status'      => 'passed'
        ], [
            'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-command-passed',
            'evidence_id' => 'fight-common.behavior.scheduler-legacy-command',
            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
            'status'      => 'passed'
        ]];

        if ($portableRunner) {
            $findings[] = [
                'finding_id'  => 'release.compatibility.consumer.scheduler-portable-runner-passed',
                'evidence_id' => 'fight-common.behavior.scheduler-portable-runner',
                'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                'status'      => 'passed'
            ];
        }

        return $findings;
    }

    /**
     * Returns the exact stable finding emitted by the representative public-API probe
     *
     * @return list<array{finding_id: string, evidence_id: string, attribution: string, status: string}>
     */
    public static function publicApiFindings(): array
    {
        return [[
            'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
            'evidence_id' => 'fight-common.consumer.public-api-representative',
            'attribution' => 'release/fixtures/PublicApiConsumer/public-api-probe.php',
            'status'      => 'passed'
        ]];
    }

    /**
     * Authenticates the representative public-API probe before Scheduler execution
     */
    public static function isPublicApiProbeReceipt(mixed $receipt): bool
    {
        if (!is_array($receipt)) {
            return false;
        }

        $observations = $receipt['observations'] ?? null;

        return ($receipt['schema_version'] ?? null) === 'fight-common.public-api-representative-probe/v1'
            && ($receipt['findings'] ?? null) === self::publicApiFindings()
            && is_array($observations)
            && array_keys($observations) === ['uuid', 'meta', 'collection', 'runtime_deprecations']
            && $observations['uuid'] === '00000000-0000-0000-0000-000000000000'
            && $observations['meta'] === ['consumer' => 'disposable']
            && $observations['collection'] === ['alpha', 'beta']
            && self::runtimeDeprecationsAreNormalized($observations['runtime_deprecations']);
    }

    /**
     * Authenticates raw Scheduler-probe evidence before aggregate receipt composition
     */
    public static function isSchedulerProbeReceipt(mixed $receipt): bool
    {
        if (!is_array($receipt)) {
            return false;
        }

        $observations = $receipt['observations'] ?? null;
        $scheduler = is_array($observations) ? ($observations['scheduler'] ?? null) : null;
        $portable = is_array($scheduler) && array_key_exists('portable_process_runner', $scheduler);

        return ($receipt['schema_version'] ?? null) === 'fight-common.scheduler-probe/v1'
            && ($receipt['findings'] ?? null) === self::schedulerFindings($portable)
            && is_array($observations)
            && array_keys($observations) === ['runtime_deprecations', 'scheduler']
            && self::runtimeDeprecationsAreNormalized($observations['runtime_deprecations'])
            && is_array($scheduler)
            && self::isSchedulerObservationEnvelope($scheduler);
    }

    /**
     * Returns the exact published Scheduler 1.1.0 non-zero command behavior
     *
     * @return array<string, mixed>
     */
    public static function nonZeroFailureObservation(): array
    {
        $log = [
            'level'   => 'error',
            'message' => 'Command exited with non-zero status 1',
            'context' => [
                'keys'      => ['exception'],
                'exception' => [
                    'class'   => 'Fight\\Common\\Application\\Scheduler\\Exception\\SchedulerException',
                    'message' => 'Command exited with non-zero status 1',
                    'code'    => 0
                ]
            ]
        ];
        $notification = [
            'subject' => '[Scheduler] Job "consumer-failing-command" failed',
            'from'    => [['address' => 'scheduler@example.com', 'name' => null]],
            'to'      => [['address' => 'operator@example.com', 'name' => null]],
            'content' => [
                'environment'  => 'Environment: consumer',
                'error'        => 'Error: Command exited with non-zero status 1',
                'code'         => 'Code: 0',
                'content_type' => 'text/plain',
                'charset'      => 'utf-8'
            ]
        ];

        return [
            'attempts'                       => 2,
            'reported_exit_codes'            => [1, 1],
            'logs'                           => [$log, $log],
            'notification_count'             => 2,
            'notifications'                  => [$notification, $notification],
            'lock_reacquired_after_attempts' => [true, true]
        ];
    }

    /**
     * Returns the exact candidate-only portable-runner observation
     *
     * @return array{commands: list<string>, output: string}
     */
    public static function portableObservation(): array
    {
        return [
            'commands' => ['portable-command'],
            'output'   => "scheduler portable command\n"
        ];
    }

    /**
     * Authenticates one exact copied-package Scheduler receipt
     */
    public static function isCopiedReceipt(mixed $receipt): bool
    {
        if (!self::hasAuthenticatedCopiedReceiptEnvelope($receipt)) {
            return false;
        }

        $scheduler = $receipt['probe']['observations']['scheduler'];
        $portable = isset($scheduler['portable_process_runner']);

        return ($receipt['probe']['observations']['runtime_deprecations'] ?? null) === []
            && $scheduler === [
            ...self::legacyObservation(),
            ...($portable ? ['portable_process_runner' => self::portableObservation()] : [])
        ]
            && ($receipt['probe']['observations']['jsend'] ?? null) === JSendEvidenceAuthority::observation($portable);
    }

    /**
     * Authenticates the exact legacy-only receipt required for the canonical baseline role
     */
    public static function isCanonicalBaselineReceipt(mixed $receipt): bool
    {
        return self::isCopiedReceipt($receipt)
            && !isset($receipt['probe']['observations']['scheduler']['portable_process_runner']);
    }

    /**
     * Verifies exact baseline/candidate legacy equivalence plus the candidate portable observation
     */
    public static function receiptsAreEquivalent(mixed $baseline, mixed $candidate): bool
    {
        if (!self::isCopiedReceipt($baseline) || !self::isCopiedReceipt($candidate)) {
            return false;
        }

        $baselineScheduler = $baseline['probe']['observations']['scheduler'];
        $candidateScheduler = $candidate['probe']['observations']['scheduler'];
        $portableObservation = $candidateScheduler['portable_process_runner'] ?? null;
        unset($candidateScheduler['portable_process_runner']);

        return !isset($baselineScheduler['portable_process_runner'])
            && $candidateScheduler === $baselineScheduler
            && $portableObservation === self::portableObservation()
            && $baseline['probe']['observations']['jsend'] === JSendEvidenceAuthority::observation(false)
            && $candidate['probe']['observations']['jsend'] === JSendEvidenceAuthority::observation(true);
    }

    /**
     * Classifies authenticated candidate divergence from the exact legacy Scheduler behavior
     *
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $candidate
     */
    public static function candidateIsProvenIncompatible(array $baseline, array $candidate): bool
    {
        if (
            !self::isCanonicalBaselineReceipt($baseline)
            || !self::hasAuthenticatedCopiedReceiptEnvelope($candidate)
        ) {
            return false;
        }

        $baselineScheduler = $baseline['probe']['observations']['scheduler'];
        $candidateScheduler = $candidate['probe']['observations']['scheduler'];
        $candidateHasPortableRunner = array_key_exists('portable_process_runner', $candidateScheduler);
        $portableObservation = $candidateScheduler['portable_process_runner'] ?? null;
        unset($candidateScheduler['portable_process_runner']);

        if (!$candidateHasPortableRunner) {
            return true;
        }

        return self::isPortableObservationEnvelope($portableObservation)
            && (
                $candidateScheduler !== $baselineScheduler
                || ($candidate['probe']['observations']['runtime_deprecations'] ?? null) !== []
                || $portableObservation !== self::portableObservation()
            );
    }

    /**
     * Returns the exact machine result for proven Scheduler 1.x incompatibility
     *
     * @return array<string, mixed>
     */
    public static function incompatibilityResult(): array
    {
        return [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'compatibility',
            'capability'              => 'compatibility_assessment',
            'status'                  => 'policy_blocked',
            'exit_class'              => 'failed',
            'exit_code'               => 4,
            'findings'                => [[
                'id'      => 'release.compatibility.consumer.scheduler-1x-incompatible',
                'message' => 'The candidate cannot reproduce the published Scheduler 1.1.0 behavior.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => [
                'action'  => 'replan_scheduler_compatibility',
                'version' => '2.0.0'
            ]
        ];
    }

    /**
     * Validates the exact Scheduler incompatibility machine result
     *
     * @param array<string, mixed> $payload
     */
    public static function isIncompatibilityResult(array $payload): bool
    {
        return $payload === self::incompatibilityResult();
    }

    /**
     * Reports whether a payload claims the governed Scheduler incompatibility finding
     *
     * @param array<string, mixed> $payload
     */
    public static function claimsIncompatibility(array $payload): bool
    {
        return ($payload['findings'][0]['id'] ?? null) === self::incompatibilityResult()['findings'][0]['id'];
    }

    /**
     * Validates the exact Scheduler 2.0.0 replan action
     */
    public static function isReplanAction(mixed $action): bool
    {
        return $action === [
            'action'  => 'replan_scheduler_compatibility',
            'version' => '2.0.0'
        ];
    }

    /**
     * Reports whether candidate portable-runner evidence has an authenticated parseable shape
     */
    private static function isPortableObservationEnvelope(mixed $observation): bool
    {
        return is_array($observation)
            && array_keys($observation) === ['commands', 'output']
            && is_array($observation['commands'])
            && array_is_list($observation['commands'])
            && array_filter($observation['commands'], is_string(...)) === $observation['commands']
            && is_string($observation['output']);
    }

    /**
     * Returns the exact stable finding set emitted by the raw Scheduler probe
     *
     * @return list<array{finding_id: string, evidence_id: string, attribution: string, status: string}>
     */
    private static function schedulerFindings(bool $portableRunner): array
    {
        return array_slice(self::findings($portableRunner), 1);
    }

    /**
     * Authenticates the copied-package receipt envelope and its stable finding set
     */
    private static function hasAuthenticatedCopiedReceiptEnvelope(mixed $receipt): bool
    {
        $candidateTree = is_array($receipt) ? ($receipt['candidate']['production_tree_sha256'] ?? null) : null;
        $installedTree = is_array($receipt) ? ($receipt['resolved_package']['production_tree_sha256'] ?? null) : null;
        $scheduler = is_array($receipt) ? ($receipt['probe']['observations']['scheduler'] ?? null) : null;
        $observations = is_array($receipt) ? ($receipt['probe']['observations'] ?? null) : null;

        return is_array($receipt)
            && ($receipt['schema_version'] ?? null) === 'fight-common.disposable-public-consumer/v1'
            && ($receipt['status'] ?? null) === 'valid'
            && ($receipt['resolved_package']['installed_as'] ?? null) === 'copy'
            && is_string($candidateTree)
            && preg_match('/\A[0-9a-f]{64}\z/D', $candidateTree) === 1
            && $candidateTree === $installedTree
            && is_array($observations)
            && array_keys($observations) === [
                'uuid', 'meta', 'collection', 'runtime_deprecations', 'scheduler', 'jsend'
            ]
            && $observations['uuid'] === '00000000-0000-0000-0000-000000000000'
            && $observations['meta'] === ['consumer' => 'disposable']
            && $observations['collection'] === ['alpha', 'beta']
            && self::runtimeDeprecationsAreNormalized($observations['runtime_deprecations'])
            && is_array($scheduler)
            && self::isSchedulerObservationEnvelope($scheduler)
            && ($receipt['findings'] ?? null) === [
                ...self::findings(isset($scheduler['portable_process_runner'])),
                ...JSendEvidenceAuthority::findings(isset($scheduler['portable_process_runner']))
            ]
            && self::jsendObservationHasAuthenticatedShape(
                $observations['jsend'] ?? null,
                isset($scheduler['portable_process_runner'])
            )
            && is_string($receipt['lock']['sha256'] ?? null)
            && preg_match('/\A[0-9a-f]{64}\z/D', $receipt['lock']['sha256']) === 1
            && is_string($receipt['probe']['sha256'] ?? null)
            && preg_match('/\A[0-9a-f]{64}\z/D', $receipt['probe']['sha256']) === 1;
    }

    /**
     * Validates the Scheduler observation structure while permitting behavioral divergence
     *
     * @param array<string, mixed> $scheduler
     */
    private static function isSchedulerObservationEnvelope(array $scheduler): bool
    {
        $portable = $scheduler['portable_process_runner'] ?? null;
        $hasPortable = array_key_exists('portable_process_runner', $scheduler);
        unset($scheduler['portable_process_runner']);

        $expected = self::legacyObservation();
        if (!array_key_exists('non_zero_failure', $scheduler)) {
            unset($expected['non_zero_failure']);
        }

        return self::hasSameShape($expected, $scheduler)
            && (!$hasPortable || self::isPortableObservationEnvelope($portable));
    }

    /**
     * Validates recursively identical keys and scalar types without asserting behavioral values
     */
    private static function hasSameShape(mixed $expected, mixed $actual): bool
    {
        if (!is_array($expected)) {
            return get_debug_type($actual) === get_debug_type($expected);
        }

        if (!is_array($actual) || array_keys($actual) !== array_keys($expected)) {
            return false;
        }

        return array_all($expected, fn ($value, $key): bool => self::hasSameShape($value, $actual[$key]));
    }

    /**
     * Validates deterministic runtime-deprecation evidence without file or stack instability
     */
    private static function runtimeDeprecationsAreNormalized(mixed $deprecations): bool
    {
        if (!is_array($deprecations) || !array_is_list($deprecations)) {
            return false;
        }

        return array_all(
            $deprecations,
            static fn (mixed $deprecation): bool => is_array($deprecation)
                && array_keys($deprecation) === ['severity', 'message']
                && in_array($deprecation['severity'], ['E_DEPRECATED', 'E_USER_DEPRECATED'], true)
                && is_string($deprecation['message'])
        );
    }

    /**
     * Validates exact JSend evidence while allowing normalized deprecations to be composed separately
     */
    private static function jsendObservationHasAuthenticatedShape(mixed $observation, bool $typed): bool
    {
        if (!is_array($observation)) {
            return false;
        }

        $runtimeDeprecations = $observation['legacy']['runtime_deprecations'] ?? null;
        $expected = JSendEvidenceAuthority::observation($typed);
        $expected['legacy']['runtime_deprecations'] = $runtimeDeprecations;

        return self::runtimeDeprecationsAreNormalized($runtimeDeprecations)
            && $observation === $expected;
    }

    /**
     * Returns the exact published Scheduler 1.1.0 legacy observation
     *
     * @return array<string, mixed>
     */
    private static function legacyObservation(): array
    {
        return [
            'construction_styles'      => ['two_argument', 'positional_optional', 'named_arguments'],
            'callable_output'          => "scheduler callable\n",
            'command_output'           => "scheduler command\nscheduler command\n",
            'default_process_commands' => ['default-command'],
            'factory_process_commands' => ['factory-command', 'false', 'false'],
            'non_zero_failure'         => self::nonZeroFailureObservation()
        ];
    }
}
