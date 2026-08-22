<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\BaselineTagResolutionResult;
use Fight\Release\Application\Boundary\BaselineTagResolutionStatus;
use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\ReleasePlanAuthorityPort;
use Fight\Release\Application\Boundary\ReleasePlanAuthorityStatus;
use Fight\Release\Application\Boundary\RunIdGenerator;
use Fight\Release\Application\Boundary\RunStateStore;
use Fight\Release\Application\Boundary\ScopedReleaseEffectLedger;
use Fight\Release\Application\Boundary\Sha256Digest;
use JsonException;

/**
 * Class ReleasePreparationService
 *
 * Revalidates one immutable plan and starts one distinct prepared run.
 */
final readonly class ReleasePreparationService
{
    /**
     * Constructs ReleasePreparationService
     */
    public function __construct(
        private PlanArtifactStore $artifacts,
        private RunStateStore $runs,
        private ReleasePlanAuthorityPort $authority,
        private RunIdGenerator $runIds,
        private GitPort $git,
        private HashingPort $hashing,
        private ReleaseEffectLedger $effects,
        private CanonicalJson $json,
        private ReleasePlanFactory $plans,
        private ReleaseResultFactory $results,
        private BaselineTagVerifier $baselineTags = new BaselineTagVerifier(),
        private ReleasePreparationArtifactFactory $artifactFactory = new ReleasePreparationArtifactFactory()
    ) {
    }

    /**
     * Creates one prepared run after revalidating its immutable plan
     */
    public function prepare(string $planPath, string $runsDirectory, ?string $resumeRunId = null): MachineResult
    {
        if ($this->effects instanceof ScopedReleaseEffectLedger) {
            $this->effects->beginEffectScope();
        }

        $output = dirname($planPath);
        $filename = basename($planPath);
        $resolved = $this->artifacts->resolveRunsDirectory($output, $runsDirectory);

        if (
            $resolved->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !$resolved->hasDirectory()
            || !$resolved->directory instanceof CanonicalRunsDirectory
            || !$resolved->directory->matches($output, $runsDirectory)
            || $resolved->directory->artifactPath($filename) !== $planPath
        ) {
            return $this->results->failure(
                'prepare',
                'release.prepare.plan_forbidden',
                'Preparation requires one immutable plan below the repository .runs directory.',
                'select_immutable_release_plan',
                $this->performedEffects()
            );
        }

        $artifact = $this->artifacts->readArtifact($resolved->directory, $filename);

        if ($artifact->outcome !== ReleaseBoundaryOutcome::SUCCESS || $artifact->missing || !$artifact->hasContent()) {
            return $this->results->failure(
                'prepare',
                'release.prepare.plan_unreadable',
                'The immutable release plan could not be read.',
                'select_immutable_release_plan',
                $this->performedEffects()
            );
        }

        $artifactBytes = $artifact->contents ?? '';

        if (!str_ends_with($artifactBytes, "\n") || str_ends_with($artifactBytes, "\r\n")) {
            return $this->invalidPlan($this->performedEffects());
        }

        $contents = substr($artifactBytes, 0, -1);

        try {
            $plan = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $plan = null;
        }

        if (!is_array($plan) || array_is_list($plan) || $this->json->encode($plan) !== $contents) {
            return $this->invalidPlan($this->performedEffects());
        }

        $planId = $plan['plan_id'] ?? null;

        if (
            !is_string($planId)
            || !Sha256Digest::tryFrom($planId) instanceof Sha256Digest
            || $filename !== $planId.'.json'
        ) {
            return $this->invalidPlan($this->performedEffects());
        }

        $candidate = $plan;
        unset($candidate['plan_id']);
        $candidate['release_class'] = $candidate['minimum_release_class'] ?? null;
        unset($candidate['minimum_release_class']);
        $recreated = $this->plans->create($candidate);

        if (!is_array($recreated)) {
            return $this->invalidPlan($this->performedEffects());
        }

        $hash = $this->hashing->sha256($this->json->encode($recreated));

        if ($hash->outcome !== ReleaseBoundaryOutcome::SUCCESS || $hash->value !== $planId) {
            return $this->invalidPlan($this->performedEffects());
        }

        $runId = $resumeRunId ?? $this->runIds->generate();

        if (!Sha256Digest::tryFrom($runId) instanceof Sha256Digest) {
            return $this->results->failure(
                'prepare',
                'release.prepare.run_identity_invalid',
                'A unique release run identity could not be generated.',
                'retry_release_preparation',
                $this->performedEffects()
            );
        }

        if ($resumeRunId !== null) {
            $preflight = $this->runs->resumePreparedRun($resolved->directory, $planId, $runId);

            if (!in_array($preflight['status'], ['planned', 'evidence_pending', 'verified', 'stopped'], true)) {
                return $this->resumeStateStop(
                    $preflight['status'],
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan
                );
            }
        } else {
            $state = $this->runs->createPlannedRun($resolved->directory, $planId, $runId);
            $preflight = $state;

            if ($state['status'] !== 'planned') {
                $stop = match ($state['status']) {
                    'conflict' => 'create_conflict',
                    'indeterminate' => 'state_indeterminate',
                    default => $state['status']
                };

                return $this->stopResult(
                    $stop,
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    publishState: false,
                    expectedPredecessor: $state
                );
            }
        }

        $currentAuthority = null;
        $resumeChangedState = $resumeRunId !== null && (
            $preflight['status'] === 'stopped'
            || ($preflight['projection_repaired'] ?? false) === true
        );

        if ($preflight['status'] === 'stopped') {
            if (!$this->validPersistedStopContract($preflight)) {
                return $this->resumeStateStop(
                    'state_indeterminate',
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan
                );
            }

            if (($preflight['next_action'] ?? null) === 'repair_release_evidence_storage') {
                $preflight = $this->recoverPersistedStop(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    $preflight
                );

                if ($preflight instanceof MachineResult) {
                    return $preflight;
                }
            } elseif ($this->isAuthorityStop($preflight)) {
                $currentAuthority = $this->authority->revalidatePlanAuthority($plan);

                if (!$this->authorityRepairProven($preflight, $currentAuthority)) {
                    return $this->persistedStopResult(
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan,
                        $preflight
                    );
                }

                $preflight = $this->recoverPersistedStop(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    $preflight
                );

                if ($preflight instanceof MachineResult) {
                    return $preflight;
                }
            } elseif (($preflight['next_action'] ?? null) === 'reconcile_named_release_run') {
                $preflight = $this->recoverPersistedStop(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    $preflight
                );

                if ($preflight instanceof MachineResult) {
                    return $preflight;
                }
            }
        }

        /** @var array<string, string> $baseline */
        $baseline = $plan['baseline'];
        $resolution = $this->baselineTags->verify(
            $this->git,
            $baseline['tag_name'],
            $plan['source_commit_oid'],
            $baseline['tag_object_oid'],
            $baseline['peeled_commit_oid']
        );
        $baselineStop = $this->baselineStop($resolution);

        if ($preflight['status'] === 'stopped' && $this->isBaselineStop($preflight)) {
            if (!$this->baselineRepairProven($preflight, $resolution)) {
                return $this->persistedStopResult(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    $preflight
                );
            }

            $preflight = $this->recoverPersistedStop(
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                $preflight
            );

            if ($preflight instanceof MachineResult) {
                return $preflight;
            }
        }

        if ($baselineStop !== null) {
            return $this->stopResult(
                $baselineStop,
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                expectedPredecessor: $preflight
            );
        }

        $authority = $currentAuthority ?? $this->authority->revalidatePlanAuthority($plan);

        if ($authority !== ReleasePlanAuthorityStatus::VERIFIED) {
            $authorityStop = match ($authority) {
                ReleasePlanAuthorityStatus::REFUSED => 'authority_refused',
                ReleasePlanAuthorityStatus::FAILED => 'authority_failed',
                ReleasePlanAuthorityStatus::UNCERTAIN => 'authority_uncertain',
                default => $authority->value
            };
            $stop = $this->resumeStopContract($authorityStop);
            $stopArtifacts = $this->preparationArtifacts(
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                [],
                [],
                ['finding_id' => $stop['finding_id'], 'status' => $stop['status']],
                ['action' => $stop['action']]
            );

            return $this->stopResult(
                $authorityStop,
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                $stopArtifacts,
                true,
                expectedPredecessor: $preflight
            );
        }

        if ($resumeRunId !== null) {
            $state = $this->runs->resumePreparedRun($resolved->directory, $planId, $runId);
            $packageArtifacts = null;

            if ($state['status'] === 'stopped') {
                if (!$this->validPersistedStopContract($state)) {
                    return $this->resumeStateStop(
                        'state_indeterminate',
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan
                    );
                }

                $repairProven = ($this->isBaselineStop($state) && $this->baselineRepairProven($state, $resolution))
                    || ($this->isAuthorityStop($state) && $this->authorityRepairProven($state, $authority))
                    || in_array(
                        $state['next_action'] ?? null,
                        ['repair_release_evidence_storage', 'reconcile_named_release_run'],
                        true
                    );

                if (!$repairProven) {
                    return $this->persistedStopResult(
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan,
                        $state
                    );
                }

                $state = $this->recoverPersistedStop(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    $state
                );

                if ($state instanceof MachineResult) {
                    return $state;
                }
            }

            if (!in_array($state['status'], ['planned', 'evidence_pending', 'verified'], true)) {
                return $this->resumeStateStop(
                    $state['status'],
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan
                );
            }

            $alreadyPrepared = !$resumeChangedState
                && $state['status'] === 'verified'
                && ($state['projection_repaired'] ?? false) === false;

            if ($state['status'] === 'planned') {
                $expectedPredecessor = $state;
                if (!$this->hasPredecessorToken($expectedPredecessor)) {
                    return $this->stopResult(
                        'state_indeterminate',
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan,
                        expectedPredecessor: $expectedPredecessor
                    );
                }

                $state = $this->runs->publishPreparedRun(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $expectedPredecessor['sequence'],
                    $expectedPredecessor['state']
                );

                if ($state['status'] !== 'created') {
                    return $this->stopResult(
                        $state['status'] === 'conflict' ? 'conflict' : 'state_indeterminate',
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan,
                        publishState: $state['status'] !== 'conflict',
                        expectedPredecessor: $expectedPredecessor
                    );
                }
            }

            if (
                !isset($state['history_sha256'], $state['projection_sha256'])
            ) {
                return $this->stopResult(
                    'indeterminate',
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    expectedPredecessor: $state
                );
            }

            if (
                $state['status'] === 'verified'
                && ($packageArtifacts = $this->prerequisiteArtifacts(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    $state
                )) === null
            ) {
                $stopArtifacts = $this->indeterminateArtifacts($resolved->directory, $planId, $runId, $plan);

                return $this->stopResult(
                    'indeterminate',
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    $stopArtifacts,
                    true,
                    expectedPredecessor: $state
                );
            }

            if (in_array($state['status'], ['created', 'evidence_pending'], true)) {
                $intermediateArtifacts = $this->preparationArtifacts(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $plan,
                    ['immutable_plan_revalidated', 'prepared_run_projection_published'],
                    [
                        'history_sha256'    => $state['history_sha256'],
                        'projection_sha256' => $state['projection_sha256']
                    ],
                    null,
                    ['action' => 'package_release_run']
                );

                if ($intermediateArtifacts === null) {
                    return $this->stopResult(
                        'artifact_indeterminate',
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan,
                        null,
                        true,
                        expectedPredecessor: $state
                    );
                }

                $expectedPredecessor = $state;
                if (!$this->hasPredecessorToken($expectedPredecessor)) {
                    return $this->stopResult(
                        'state_indeterminate',
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan,
                        expectedPredecessor: $expectedPredecessor
                    );
                }

                $state = $this->runs->finalizePreparedRun(
                    $resolved->directory,
                    $planId,
                    $runId,
                    $intermediateArtifacts['evidence_manifest']['manifest_id'],
                    $intermediateArtifacts['phase_handoff']['handoff_id'],
                    $expectedPredecessor['sequence'],
                    $expectedPredecessor['state']
                );

                if ($state['status'] !== 'created') {
                    return $this->stopResult(
                        $state['status'] === 'conflict' ? 'conflict' : 'state_indeterminate',
                        $resolved->directory,
                        $planId,
                        $runId,
                        $plan,
                        publishState: $state['status'] !== 'conflict',
                        expectedPredecessor: $expectedPredecessor
                    );
                }

                $packageArtifacts = $intermediateArtifacts;
            }

            /** @var array{history_path: string, projection_path: string} $verifiedState */
            $verifiedState = [
                'history_path'    => $state['history_path'],
                'projection_path' => $state['projection_path']
            ];

            if ($alreadyPrepared) {
                return $this->results->resumedPrepared(
                    $planId,
                    $runId,
                    $verifiedState,
                    $packageArtifacts,
                    $this->performedEffects()
                );
            }

            return $this->results->resumedPreparationCompleted(
                $planId,
                $runId,
                $verifiedState,
                $packageArtifacts,
                $this->performedEffects()
            );
        }

        $expectedPredecessor = $preflight;
        if (!$this->hasPredecessorToken($expectedPredecessor)) {
            return $this->stopResult(
                'state_indeterminate',
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                expectedPredecessor: $expectedPredecessor
            );
        }

        $state = $this->runs->publishPreparedRun(
            $resolved->directory,
            $planId,
            $runId,
            $expectedPredecessor['sequence'],
            $expectedPredecessor['state']
        );

        if ($state['status'] !== 'created') {
            return $this->stopResult(
                $state['status'] === 'conflict' ? 'conflict' : 'state_indeterminate',
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                publishState: $state['status'] !== 'conflict',
                expectedPredecessor: $expectedPredecessor
            );
        }

        $intermediateArtifacts = $this->preparationArtifacts(
            $resolved->directory,
            $planId,
            $runId,
            $plan,
            ['immutable_plan_revalidated', 'prepared_run_projection_published'],
            [
                'history_sha256'    => $state['history_sha256'],
                'projection_sha256' => $state['projection_sha256']
            ],
            null,
            ['action' => 'package_release_run']
        );

        if ($intermediateArtifacts === null) {
            return $this->stopResult(
                'artifact_indeterminate',
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                null,
                true,
                expectedPredecessor: $state
            );
        }

        $expectedPredecessor = $state;
        if (!$this->hasPredecessorToken($expectedPredecessor)) {
            return $this->stopResult(
                'state_indeterminate',
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                expectedPredecessor: $expectedPredecessor
            );
        }

        $state = $this->runs->finalizePreparedRun(
            $resolved->directory,
            $planId,
            $runId,
            $intermediateArtifacts['evidence_manifest']['manifest_id'],
            $intermediateArtifacts['phase_handoff']['handoff_id'],
            $expectedPredecessor['sequence'],
            $expectedPredecessor['state']
        );

        if ($state['status'] !== 'created') {
            return $this->stopResult(
                $state['status'] === 'conflict' ? 'conflict' : 'state_indeterminate',
                $resolved->directory,
                $planId,
                $runId,
                $plan,
                publishState: $state['status'] !== 'conflict',
                expectedPredecessor: $expectedPredecessor
            );
        }

        $publicState = [
            'history_path'    => $state['history_path'],
            'projection_path' => $state['projection_path']
        ];

        return $this->results->prepared(
            $planId,
            $runId,
            $publicState,
            $intermediateArtifacts,
            $this->performedEffects()
        );
    }

    /**
     * Appends recovery from one exact stop after its reported live repair was verified
     *
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     * @param array<string, mixed> $plan Revalidated immutable plan.
     * @param array<string, mixed> $state Revalidated stopped state.
     *
     * @return array<string, mixed>|MachineResult
     */
    private function recoverPersistedStop(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $plan,
        array $state
    ): array|MachineResult {
        if (!$this->validRecoverableStopReceipt($directory, $runId, $state)) {
            return $this->resumeStateStop('state_indeterminate', $directory, $planId, $runId, $plan);
        }

        /** @var array{int, string, string, string, string, string, int, string} $required */
        $required = [
            $state['sequence'] ?? null,
            $state['stop_code'] ?? null,
            $state['stop_state'] ?? null,
            $state['finding_id'] ?? null,
            $state['next_action'] ?? null,
            $state['resume_state'] ?? null,
            $state['resume_sequence'] ?? null,
            $state['resume_next_action'] ?? null
        ];

        $repairManifestId = null;
        $repairHandoffId = null;

        if (in_array($required[4], ['repair_release_evidence_storage', 'reconcile_named_release_run'], true)) {
            $probe = $this->preparationArtifacts(
                $directory,
                $planId,
                $runId,
                $plan,
                [],
                [],
                [
                    'finding_id' => $required[3],
                    'status'     => $required[2]
                ],
                ['action' => $required[4]],
                activationMode: ReleasePreparationArtifactFactory::ACTIVATION_EVIDENCE_ONLY
            );

            if ($probe === null) {
                if ($required[4] !== 'repair_release_evidence_storage') {
                    return $this->persistedStopResult($directory, $planId, $runId, $plan, $state);
                }

                return $this->results->resumedPrepareEvidencePersistenceFailure(
                    $planId,
                    $runId,
                    [
                        'history_path'    => $state['history_path'],
                        'projection_path' => $state['projection_path']
                    ],
                    $this->performedEffects()
                );
            }

            $repairManifestId = $probe['evidence_manifest']['manifest_id'];
            $repairHandoffId = $probe['phase_handoff']['handoff_id'];
        }

        $recovered = $this->runs->recoverPreparationStop(
            $directory,
            $planId,
            $runId,
            $required[0],
            $required[1],
            $required[2],
            $required[3],
            $required[4],
            $repairManifestId,
            $repairHandoffId
        );

        if ($recovered['status'] !== 'created') {
            return $this->resumeStateStop(
                $recovered['status'] === 'conflict' ? 'conflict' : 'state_indeterminate',
                $directory,
                $planId,
                $runId,
                $plan
            );
        }

        return $this->runs->resumePreparedRun($directory, $planId, $runId);
    }

    /**
     * Reports whether one stopped receipt is complete enough to authorize exact recovery
     *
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string $runId Named run identity.
     * @param array<string, mixed> $state Revalidated stopped state.
     */
    private function validRecoverableStopReceipt(
        CanonicalRunsDirectory $directory,
        string $runId,
        array $state
    ): bool {
        $sequence = $state['sequence'] ?? null;
        $resumeSequence = $state['resume_sequence'] ?? null;
        $runPath = $directory->path.'/runs/'.$runId;

        return $this->validPersistedStopContract($state)
            && is_int($sequence)
            && $sequence > 1
            && is_int($resumeSequence)
            && $resumeSequence > 0
            && $resumeSequence < $sequence
            && is_string($state['resume_state'] ?? null)
            && $state['resume_state'] !== ''
            && is_string($state['resume_next_action'] ?? null)
            && $state['resume_next_action'] !== ''
            && ($state['history_path'] ?? null) === $runPath.'/history.jsonl'
            && ($state['projection_path'] ?? null) === $runPath.'/projection.json'
            && isset($state['prerequisite_evidence_manifest_id']) === isset($state['prerequisite_phase_handoff_id'])
            && (!isset($state['prerequisite_evidence_manifest_id']) || (
                is_string($state['prerequisite_evidence_manifest_id'])
                && is_string($state['prerequisite_phase_handoff_id'])
                && Sha256Digest::tryFrom($state['prerequisite_evidence_manifest_id']) instanceof Sha256Digest
                && Sha256Digest::tryFrom($state['prerequisite_phase_handoff_id']) instanceof Sha256Digest
            ))
            && (($state['next_action'] ?? null) !== 'repair_release_evidence_storage'
                || !isset(
                    $state['prerequisite_evidence_manifest_id'],
                    $state['prerequisite_phase_handoff_id']
                ));
    }

    /**
     * Reports whether an artifactless positive stop receipt proves the exact persisted fallback
     *
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string $runId Named run identity.
     * @param array<string, mixed> $state Positive stopped receipt.
     */
    private function validArtifactlessStopReceipt(
        CanonicalRunsDirectory $directory,
        string $runId,
        array $state
    ): bool {
        $sequence = $state['sequence'] ?? null;
        $runPath = $directory->path.'/runs/'.$runId;

        if (
            !$this->validPersistedStopContract($state)
            || ($state['next_action'] ?? null) !== 'repair_release_evidence_storage'
            || !is_int($sequence)
            || $sequence < 1
            || ($state['history_path'] ?? null) !== $runPath.'/history.jsonl'
            || ($state['projection_path'] ?? null) !== $runPath.'/projection.json'
            || isset(
                $state['prerequisite_evidence_manifest_id'],
                $state['prerequisite_phase_handoff_id']
            )
        ) {
            return false;
        }

        if ($sequence === 1) {
            return !isset($state['resume_state'], $state['resume_sequence'], $state['resume_next_action']);
        }

        return is_int($state['resume_sequence'] ?? null)
            && $state['resume_sequence'] > 0
            && $state['resume_sequence'] < $sequence
            && is_string($state['resume_state'] ?? null)
            && $state['resume_state'] !== ''
            && is_string($state['resume_next_action'] ?? null)
            && $state['resume_next_action'] !== '';
    }

    /**
     * Reports whether one stopped receipt carries a recognized exact causal tuple
     *
     * @param array<string, mixed> $state Revalidated stopped state.
     */
    private function validPersistedStopContract(array $state): bool
    {
        $stop = $state['stop_code'] ?? null;

        if (!is_string($stop) || ($state['state'] ?? null) !== ($state['stop_state'] ?? null)) {
            return false;
        }

        if ($stop === 'artifact_indeterminate') {
            return in_array([
                $state['stop_state'] ?? null,
                $state['finding_id'] ?? null,
                $state['next_action'] ?? null
            ], [
                ['evidence_indeterminate', 'release.prepare.artifacts_indeterminate', 'reconcile_named_release_run'],
                [
                    'evidence_indeterminate',
                    'release.prepare.evidence_persistence_failed',
                    'repair_release_evidence_storage'
                ]
            ], true);
        }

        if (
            !in_array($stop, [
            'missing',
            'conflict',
            'failed',
            'create_conflict',
            'state_indeterminate',
            'baseline_refusal',
            'baseline_failure',
            'baseline_uncertainty',
            'baseline_drift',
            'baseline_missing',
            'baseline_ambiguous',
            'baseline_duplicate_normalized',
            'baseline_non_ancestor',
            'support_policy_drift',
            'approval_drift',
            'evidence_drift',
            'compatibility_drift',
            'authority_refused',
            'authority_failed',
            'authority_uncertain',
            'stale'
            ], true)
        ) {
            return false;
        }

        $contract = $this->resumeStopContract($stop);

        return [
            $state['stop_state'] ?? null,
            $state['finding_id'] ?? null,
            $state['next_action'] ?? null
        ] === [$contract['status'], $contract['finding_id'], $contract['action']];
    }

    /**
     * Returns immutable evidence for a named-run preflight stop without mutating that run
     *
     * @param string                 $stop      Classified named-run stop.
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string                 $planId    Immutable plan identity.
     * @param string                 $runId     Named run identity.
     * @param array<string, mixed>   $plan      Revalidated immutable plan.
     */
    private function resumeStateStop(
        string $stop,
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $plan
    ): MachineResult {
        $contract = $this->resumeStopContract($stop);
        $artifacts = $this->preparationArtifacts(
            $directory,
            $planId,
            $runId,
            $plan,
            [],
            [],
            ['finding_id' => $contract['finding_id'], 'status' => $contract['status']],
            ['action' => $contract['action']],
            activationMode: ReleasePreparationArtifactFactory::ACTIVATION_EVIDENCE_ONLY
        );

        return $this->stopResult(
            $stop,
            $directory,
            $planId,
            $runId,
            $plan,
            $artifacts,
            true,
            false
        );
    }

    /**
     * Builds one normal stop only after its canonical evidence pair is durably re-read
     *
     * @param string                 $stop      Classified preparation stop.
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string                 $planId    Immutable plan identity.
     * @param string                 $runId     Named run identity.
     * @param array<string, mixed>   $plan      Revalidated immutable plan.
     * @param array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * }|null $artifacts Previously attempted durable evidence.
     * @param boolean $artifactAttempted Whether the classified pair was already attempted.
     * @param boolean $publishState Whether a validated writable state authority may receive the stop.
     * @param array<string, mixed>|null $expectedPredecessor Exact observed predecessor receipt.
     */
    private function stopResult(
        string $stop,
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $plan,
        ?array $artifacts = null,
        bool $artifactAttempted = false,
        bool $publishState = true,
        ?array $expectedPredecessor = null
    ): MachineResult {
        $activationMode = match ($publishState) {
            true => ReleasePreparationArtifactFactory::ACTIVATION_PROJECTION_BOUND,
            false => ReleasePreparationArtifactFactory::ACTIVATION_EVIDENCE_ONLY
        };
        $expectedSequence = null;
        $expectedState = null;

        if (is_int($expectedPredecessor['sequence'] ?? null)) {
            $expectedSequence = $expectedPredecessor['sequence'];
        }

        if (is_string($expectedPredecessor['state'] ?? null)) {
            $expectedState = $expectedPredecessor['state'];
        }

        if (!$artifactAttempted) {
            $contract = $this->resumeStopContract($stop);
            $artifacts = $this->preparationArtifacts(
                $directory,
                $planId,
                $runId,
                $plan,
                [],
                [],
                ['finding_id' => $contract['finding_id'], 'status' => $contract['status']],
                ['action' => $contract['action']],
                activationMode: $activationMode
            );
        }

        if ($artifacts === null) {
            $stop = 'artifact_indeterminate';
            $contract = $this->resumeStopContract($stop);
            $artifacts = $this->preparationArtifacts(
                $directory,
                $planId,
                $runId,
                $plan,
                [],
                [],
                ['finding_id' => $contract['finding_id'], 'status' => $contract['status']],
                ['action' => $contract['action']],
                activationMode: $activationMode
            );
        }

        if ($artifacts === null) {
            if (!$publishState) {
                return $this->results->prepareEvidencePersistenceFailure(
                    $planId,
                    $runId,
                    $this->performedEffects()
                );
            }

            $receipt = $this->runs->publishPreparationStop(
                $directory,
                $planId,
                $runId,
                'artifact_indeterminate',
                'evidence_indeterminate',
                'release.prepare.evidence_persistence_failed',
                'repair_release_evidence_storage',
                null,
                null,
                $expectedSequence,
                $expectedState
            );

            if ($receipt['status'] === 'conflict') {
                return $this->resumeStateStop('conflict', $directory, $planId, $runId, $plan);
            }

            if ($receipt['status'] === 'advanced') {
                return $this->resumeStateStop('state_indeterminate', $directory, $planId, $runId, $plan);
            }

            if (
                in_array($receipt['status'], ['created', 'verified'], true)
                && $this->validArtifactlessStopReceipt($directory, $runId, $receipt)
            ) {
                return $this->results->resumedPrepareEvidencePersistenceFailure(
                    $planId,
                    $runId,
                    [
                        'history_path'    => $receipt['history_path'],
                        'projection_path' => $receipt['projection_path']
                    ],
                    $this->performedEffects()
                );
            }

            return $this->results->prepareEvidencePersistenceFailure(
                $planId,
                $runId,
                $this->performedEffects()
            );
        }

        $contract = $this->resumeStopContract($stop);
        if (!$publishState) {
            return $this->results->prepareResumeStop(
                $stop,
                $planId,
                $runId,
                $artifacts,
                $this->performedEffects()
            );
        }

        $receipt = $this->runs->publishPreparationStop(
            $directory,
            $planId,
            $runId,
            $stop,
            $contract['status'],
            $contract['finding_id'],
            $contract['action'],
            $artifacts['evidence_manifest']['manifest_id'],
            $artifacts['phase_handoff']['handoff_id'],
            $expectedSequence,
            $expectedState
        );

        if ($receipt['status'] === 'conflict') {
            return $this->resumeStateStop('conflict', $directory, $planId, $runId, $plan);
        }

        if ($receipt['status'] === 'advanced') {
            return $this->resumeStateStop('state_indeterminate', $directory, $planId, $runId, $plan);
        }

        if (!in_array($receipt['status'], ['created', 'verified'], true)) {
            return $this->results->prepareEvidencePersistenceFailure(
                $planId,
                $runId,
                $this->performedEffects()
            );
        }

        return $this->results->prepareResumeStop(
            $stop,
            $planId,
            $runId,
            $artifacts,
            $this->performedEffects()
        );
    }

    /**
     * Reports whether current baseline truth proves the exact prior repair action
     *
     * @param array<string, mixed> $state Revalidated stopped state.
     */
    private function isBaselineStop(array $state): bool
    {
        return is_string($state['stop_code'] ?? null)
            && str_starts_with($state['stop_code'], 'baseline_');
    }

    /**
     * Classifies current live baseline truth without mutating run state
     */
    private function baselineStop(BaselineTagResolutionResult $resolution): ?string
    {
        if ($resolution->outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return [
                ReleaseBoundaryOutcome::REFUSAL->value     => 'baseline_refusal',
                ReleaseBoundaryOutcome::FAILURE->value     => 'baseline_failure',
                ReleaseBoundaryOutcome::UNCERTAINTY->value => 'baseline_uncertainty',
                ReleaseBoundaryOutcome::DRIFT->value       => 'baseline_drift'
            ][$resolution->outcome->value] ?? 'baseline_uncertainty';
        }

        if ($resolution->isResolved()) {
            return null;
        }

        assert($resolution->status instanceof BaselineTagResolutionStatus);

        if ($resolution->status === BaselineTagResolutionStatus::MOVING) {
            return 'baseline_drift';
        }

        return 'baseline_'.$resolution->status->value;
    }

    /**
     * Reports whether live baseline truth proves the prior stop's exact requested repair
     *
     * @param array<string, mixed> $state Revalidated stopped state.
     */
    private function baselineRepairProven(
        array $state,
        BaselineTagResolutionResult $resolution
    ): bool {
        $action = $state['next_action'] ?? '';

        return is_string($action) && match ($action) {
            'obtain_current_baseline_authority',
            'repair_baseline_resolution_provider',
            'reconcile_baseline_resolution' => $resolution->outcome === ReleaseBoundaryOutcome::SUCCESS,
            'repair_baseline_authority',
            'create_current_release_plan' => $resolution->isResolved(),
            default => false
        };
    }

    /**
     * Reports whether a state receipt carries one exact CAS predecessor token
     *
     * @param array<string, mixed> $state State operation receipt.
     */
    private function hasPredecessorToken(array $state): bool
    {
        return is_int($state['sequence'] ?? null) && is_string($state['state'] ?? null);
    }

    /**
     * Reports whether the stopped action requires current release authority as its repair proof
     *
     * @param array<string, mixed> $state Revalidated stopped state.
     */
    private function isAuthorityStop(array $state): bool
    {
        $stop = $state['stop_code'] ?? null;

        return is_string($stop) && (
            str_starts_with($stop, 'authority_')
            || in_array($stop, [
                'support_policy_drift',
                'approval_drift',
                'evidence_drift',
                'compatibility_drift'
            ], true)
        );
    }

    /**
     * Reports whether live authority truth proves the prior stop's exact requested repair
     *
     * @param array<string, mixed> $state Revalidated stopped state.
     */
    private function authorityRepairProven(array $state, ReleasePlanAuthorityStatus $current): bool
    {
        $action = $state['next_action'] ?? '';
        $currentIsGovernedTruth = in_array($current, [
            ReleasePlanAuthorityStatus::VERIFIED,
            ReleasePlanAuthorityStatus::SUPPORT_POLICY_DRIFT,
            ReleasePlanAuthorityStatus::APPROVAL_DRIFT,
            ReleasePlanAuthorityStatus::EVIDENCE_DRIFT,
            ReleasePlanAuthorityStatus::COMPATIBILITY_DRIFT
        ], true);

        return is_string($action) && match ($action) {
            'obtain_current_release_authority',
            'repair_release_authority_provider',
            'reconcile_release_plan_authority' => $currentIsGovernedTruth,
            'create_current_release_plan' => $current === ReleasePlanAuthorityStatus::VERIFIED,
            'obtain_current_release_approval' => $currentIsGovernedTruth
                && $current !== ReleasePlanAuthorityStatus::APPROVAL_DRIFT,
            default => false
        };
    }

    /**
     * Returns one still-unrepaired persisted stop without changing its history
     *
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     * @param array<string, mixed> $plan Revalidated immutable plan.
     * @param array<string, mixed> $state Revalidated stopped state.
     */
    private function persistedStopResult(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $plan,
        array $state
    ): MachineResult {
        $stop = $state['stop_code'] ?? 'state_indeterminate';
        $stop = is_string($stop) ? $stop : 'state_indeterminate';

        $contract = $this->resumeStopContract($stop);
        $artifacts = $this->preparationArtifacts(
            $directory,
            $planId,
            $runId,
            $plan,
            [],
            [],
            ['finding_id' => $contract['finding_id'], 'status' => $contract['status']],
            ['action' => $contract['action']],
            false
        );

        return $this->stopResult(
            $stop,
            $directory,
            $planId,
            $runId,
            $plan,
            $artifacts,
            true,
            false,
            $state
        );
    }

    /**
     * Returns one published or verified canonical content-addressed preparation handoff pair
     *
     * @param CanonicalRunsDirectory     $directory      Canonical plan output authority.
     * @param string                     $planId         Immutable plan identity.
     * @param string                     $runId          Named run identity.
     * @param array<string, mixed>       $plan           Revalidated immutable plan.
     * @param array                      $postconditions Verified phase postconditions.
     * @param array<string, string>      $evidence       Verified run-state evidence.
     * @param array<string, string>|null $stopState      Classified stop, when present.
     * @param array{action: string}      $nextAction     Sole permitted next action.
     * @param boolean                    $publish        Whether publication may be attempted.
     * @param string                     $activationMode Closed activation contract.
     *
     * @return array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * }|null
     *
     * @phpstan-param list<string> $postconditions
     */
    private function preparationArtifacts(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $plan,
        array $postconditions,
        array $evidence,
        ?array $stopState,
        array $nextAction,
        bool $publish = true,
        string $activationMode = ReleasePreparationArtifactFactory::ACTIVATION_PROJECTION_BOUND
    ): ?array {
        $status = $stopState['status'] ?? 'prepared';
        $manifest = $this->artifactFactory->manifest(
            $planId,
            $runId,
            $plan,
            $status,
            $postconditions,
            $evidence,
            $stopState,
            $nextAction,
            $activationMode
        );
        $manifestBytes = $this->json->encode($manifest);
        $manifestHash = $this->hashing->sha256($manifestBytes);

        if (
            $manifestHash->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !is_string($manifestHash->value)
            || !Sha256Digest::tryFrom($manifestHash->value) instanceof Sha256Digest
        ) {
            return null;
        }

        $manifestId = $manifestHash->value;
        $manifestFilename = $manifestId.'.evidence-manifest.json';
        $manifestArtifactBytes = $this->json->encode([...$manifest, 'manifest_id' => $manifestId]).PHP_EOL;

        if (!$this->persistedArtifactMatches($directory, $manifestFilename, $manifestArtifactBytes, $publish)) {
            return null;
        }

        $handoff = $this->artifactFactory->handoff($manifest, $manifestId);
        $handoffBytes = $this->json->encode($handoff);
        $handoffHash = $this->hashing->sha256($handoffBytes);

        if (
            $handoffHash->outcome !== ReleaseBoundaryOutcome::SUCCESS
            || !is_string($handoffHash->value)
            || !Sha256Digest::tryFrom($handoffHash->value) instanceof Sha256Digest
        ) {
            return null;
        }

        $handoffId = $handoffHash->value;
        $handoffFilename = $handoffId.'.phase-handoff.json';
        $handoffArtifactBytes = $this->json->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL;

        if (!$this->persistedArtifactMatches($directory, $handoffFilename, $handoffArtifactBytes, $publish)) {
            return null;
        }

        return [
            'evidence_manifest' => [
                'manifest_id' => $manifestId,
                'path'        => $directory->artifactPath($manifestFilename)
            ],
            'phase_handoff'     => [
                'handoff_id' => $handoffId,
                'path'       => $directory->artifactPath($handoffFilename)
            ]
        ];
    }

    /**
     * Verifies exact artifact bytes after an optional immutable publication attempt
     */
    private function persistedArtifactMatches(
        CanonicalRunsDirectory $directory,
        string $filename,
        string $expectedBytes,
        bool $publish
    ): bool {
        if ($publish) {
            $write = $this->artifacts->writeArtifact($directory, $filename, $expectedBytes);

            if (
                $write->outcome !== ReleaseBoundaryOutcome::SUCCESS
                && !$write->requiresPostconditionVerification()
            ) {
                return false;
            }
        }

        $read = $this->artifacts->readArtifact($directory, $filename);

        return $read->outcome === ReleaseBoundaryOutcome::SUCCESS
            && !$read->missing
            && $read->contents === $expectedBytes;
    }

    /**
     * Checks the exact prerequisite artifact pair referenced by package-ready state
     *
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     * @param array<string, mixed> $plan Revalidated immutable plan.
     * @param array<string, mixed> $state Revalidated package-ready run state.
     *
     * @return array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * }|null
     */
    private function prerequisiteArtifacts(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $plan,
        array $state
    ): ?array {
        $hasRequiredBindings = isset(
            $state['prepared_history_sha256'],
            $state['prepared_projection_sha256'],
            $state['prerequisite_evidence_manifest_id'],
            $state['prerequisite_phase_handoff_id']
        );

        if (!$hasRequiredBindings) {
            return null;
        }

        if (
            !is_string($state['prepared_history_sha256'])
            || !is_string($state['prepared_projection_sha256'])
            || !is_string($state['prerequisite_evidence_manifest_id'])
            || !is_string($state['prerequisite_phase_handoff_id'])
        ) {
            return null;
        }

        $artifacts = $this->preparationArtifacts(
            $directory,
            $planId,
            $runId,
            $plan,
            ['immutable_plan_revalidated', 'prepared_run_projection_published'],
            [
                'history_sha256'    => $state['prepared_history_sha256'],
                'projection_sha256' => $state['prepared_projection_sha256']
            ],
            null,
            ['action' => 'package_release_run'],
            false
        );

        if (
            $artifacts === null
            || $artifacts['evidence_manifest']['manifest_id'] !== $state['prerequisite_evidence_manifest_id']
            || $artifacts['phase_handoff']['handoff_id'] !== $state['prerequisite_phase_handoff_id']
        ) {
            return null;
        }

        return $artifacts;
    }

    /**
     * Resolves the artifact binding for one governed resume stop
     *
     * @return array{status: string, finding_id: string, action: string}
     */
    private function resumeStopContract(string $stop): array
    {
        return match ($stop) {
            'missing' => [
                'status'     => 'evidence_indeterminate',
                'finding_id' => 'release.prepare.resume_state_missing',
                'action'     => 'restore_named_release_run_evidence'
            ],
            'conflict' => [
                'status'     => 'conflict',
                'finding_id' => 'release.prepare.resume_contention',
                'action'     => 'retry_named_resume_after_writer_completes'
            ],
            'failed' => [
                'status'     => 'policy_blocked',
                'finding_id' => 'release.prepare.state_persistence_failed',
                'action'     => 'repair_release_run_storage'
            ],
            'create_conflict' => [
                'status'     => 'conflict',
                'finding_id' => 'release.prepare.run_identity_conflict',
                'action'     => 'retry_release_preparation_with_new_run'
            ],
            'state_indeterminate' => [
                'status'     => 'evidence_indeterminate',
                'finding_id' => 'release.prepare.state_persistence_indeterminate',
                'action'     => 'reconcile_named_release_run'
            ],
            'artifact_indeterminate' => [
                'status'     => 'evidence_indeterminate',
                'finding_id' => 'release.prepare.artifacts_indeterminate',
                'action'     => 'reconcile_named_release_run'
            ],
            'baseline_refusal' => [
                'status'     => 'authority_required',
                'finding_id' => 'release.prepare.baseline_resolution_refused',
                'action'     => 'obtain_current_baseline_authority'
            ],
            'baseline_failure' => [
                'status'     => 'policy_blocked',
                'finding_id' => 'release.prepare.baseline_resolution_failed',
                'action'     => 'repair_baseline_resolution_provider'
            ],
            'baseline_uncertainty' => [
                'status'     => 'evidence_indeterminate',
                'finding_id' => 'release.prepare.baseline_resolution_uncertain',
                'action'     => 'reconcile_baseline_resolution'
            ],
            'baseline_drift' => [
                'status'     => 'stale_plan',
                'finding_id' => 'release.prepare.baseline_resolution_drift',
                'action'     => 'create_current_release_plan'
            ],
            'baseline_missing' => [
                'status'     => 'policy_blocked',
                'finding_id' => 'release.prepare.baseline_tag_missing',
                'action'     => 'repair_baseline_authority'
            ],
            'baseline_ambiguous' => [
                'status'     => 'policy_blocked',
                'finding_id' => 'release.prepare.baseline_tag_ambiguous',
                'action'     => 'repair_baseline_authority'
            ],
            'baseline_duplicate_normalized' => [
                'status'     => 'policy_blocked',
                'finding_id' => 'release.prepare.baseline_tag_duplicate_normalized',
                'action'     => 'repair_baseline_authority'
            ],
            'baseline_non_ancestor' => [
                'status'     => 'policy_blocked',
                'finding_id' => 'release.prepare.baseline_tag_non_ancestor',
                'action'     => 'repair_baseline_authority'
            ],
            'support_policy_drift' => [
                'status'     => 'stale_plan',
                'finding_id' => 'release.prepare.support_policy_drift',
                'action'     => 'create_current_release_plan'
            ],
            'approval_drift' => [
                'status'     => 'authority_required',
                'finding_id' => 'release.prepare.approval_authority_drift',
                'action'     => 'obtain_current_release_approval'
            ],
            'evidence_drift' => [
                'status'     => 'stale_plan',
                'finding_id' => 'release.prepare.evidence_authority_drift',
                'action'     => 'create_current_release_plan'
            ],
            'compatibility_drift' => [
                'status'     => 'stale_plan',
                'finding_id' => 'release.prepare.compatibility_authority_drift',
                'action'     => 'create_current_release_plan'
            ],
            'authority_refused' => [
                'status'     => 'authority_required',
                'finding_id' => 'release.prepare.plan_authority_refused',
                'action'     => 'obtain_current_release_authority'
            ],
            'authority_failed' => [
                'status'     => 'policy_blocked',
                'finding_id' => 'release.prepare.plan_authority_failed',
                'action'     => 'repair_release_authority_provider'
            ],
            'authority_uncertain' => [
                'status'     => 'evidence_indeterminate',
                'finding_id' => 'release.prepare.plan_authority_uncertain',
                'action'     => 'reconcile_release_plan_authority'
            ],
            'stale' => [
                'status'     => 'stale_plan',
                'finding_id' => 'release.prepare.resume_plan_drift',
                'action'     => 'create_current_release_plan'
            ],
            default => [
                'status'     => 'evidence_indeterminate',
                'finding_id' => 'release.prepare.resume_state_indeterminate',
                'action'     => 'reconcile_named_release_run'
            ]
        };
    }

    /**
     * Returns the canonical stop pair when expected prepared artifacts cannot be reverified
     *
     * @param CanonicalRunsDirectory $directory Canonical plan output authority.
     * @param string                 $planId     Immutable plan identity.
     * @param string                 $runId      Named run identity.
     * @param array<string, mixed> $plan Revalidated immutable plan.
     *
     * @return array{
     *     evidence_manifest: array{manifest_id: string, path: string},
     *     phase_handoff: array{handoff_id: string, path: string}
     * }|null
     */
    private function indeterminateArtifacts(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $plan
    ): ?array {
        return $this->preparationArtifacts(
            $directory,
            $planId,
            $runId,
            $plan,
            [],
            [],
            [
                'finding_id' => 'release.prepare.resume_state_indeterminate',
                'status'     => 'evidence_indeterminate'
            ],
            ['action' => 'reconcile_named_release_run']
        );
    }

    /**
     * Builds the stable rejection for an artifact that is not its claimed immutable plan
     *
     * @param array $effects Ordered boundary effect projection.
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $effects
     */
    private function invalidPlan(array $effects): MachineResult
    {
        return $this->results->failure(
            'prepare',
            'release.prepare.plan_invalid',
            'The immutable release plan failed canonical identity or binding revalidation.',
            'create_current_release_plan',
            $effects
        );
    }

    /**
     * Returns only effects belonging to the current public service invocation
     *
     * @return list<array{capability: string, effect_class: string, outcome: string}>
     */
    private function performedEffects(): array
    {
        return $this->effects->effects();
    }
}
