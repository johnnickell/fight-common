<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Interface RunStateStore
 *
 * Persists append-only release-run history and its atomically visible projection.
 */
interface RunStateStore
{
    /**
     * Creates one distinct run at its prior valid planned state
     *
     * @return array{
     *     status: string,
     *     history_path?: string,
     *     projection_path?: string,
     *     sequence?: int,
     *     state?: string,
     *     prepared_history_sha256?: string,
     *     prepared_projection_sha256?: string
     * }
     */
    public function createPlannedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId
    ): array;

    /**
     * Advances one planned run to prepared state before its required handoff artifacts are created
     *
     * @return array{
     *     status: string,
     *     history_path?: string,
     *     projection_path?: string,
     *     sequence?: int,
     *     state?: string,
     *     history_sha256?: string,
     *     projection_sha256?: string,
     *     prepared_history_sha256?: string,
     *     prepared_projection_sha256?: string,
     *     prerequisite_evidence_manifest_id?: string,
     *     prerequisite_phase_handoff_id?: string
     * }
     */
    public function publishPreparedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        int $expectedSequence,
        string $expectedState
    ): array;

    /**
     * Creates package-ready prepared state after its evidence artifacts are verified
     *
     * @return array{
     *     status: string,
     *     history_path?: string,
     *     projection_path?: string,
     *     sequence?: int,
     *     state?: string,
     *     history_sha256?: string,
     *     projection_sha256?: string,
     *     prepared_history_sha256?: string,
     *     prepared_projection_sha256?: string,
     *     prerequisite_evidence_manifest_id?: string,
     *     prerequisite_phase_handoff_id?: string
     * }
     */
    public function finalizePreparedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        string $manifestId,
        string $handoffId,
        int $expectedSequence,
        string $expectedState
    ): array;

    /**
     * Appends one classified preparation stop and atomically publishes its current projection
     *
     * A caller cannot safely append when another writer owns the lease or when existing state is
     * unbound, pre-binding, or corrupt; it retains that fail-closed stop in immutable evidence only.
     *
     * The nullable predecessor pair is permitted only when no run state exists yet. Otherwise both values
     * compare the caller's exact observation with current state while the single-writer lease is held.
     *
     * @return array{status: string, history_path?: string, projection_path?: string, sequence?: int, state?: string}
     */
    public function publishPreparationStop(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        string $stopCode,
        string $stopState,
        string $findingId,
        string $nextAction,
        ?string $manifestId,
        ?string $handoffId,
        ?int $expectedSequence,
        ?string $expectedState
    ): array;

    /**
     * Creates one distinct run already advanced through preparation
     *
     * @return array{
     *     status: string,
     *     history_path?: string,
     *     projection_path?: string,
     *     sequence?: int,
     *     state?: string,
     *     history_sha256?: string,
     *     projection_sha256?: string,
     *     prepared_history_sha256?: string,
     *     prepared_projection_sha256?: string,
     *     prerequisite_evidence_manifest_id?: string,
     *     prerequisite_phase_handoff_id?: string
     * }
     */
    public function createPreparedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId
    ): array;

    /**
     * Validates one named prepared run without advancing or rewriting it
     *
     * @return array{
     *     status: string,
     *     history_path?: string,
     *     projection_path?: string,
     *     sequence?: int,
     *     state?: string,
     *     projection_repaired?: bool,
     *     history_sha256?: string,
     *     projection_sha256?: string,
     *     prepared_history_sha256?: string,
     *     prepared_projection_sha256?: string,
     *     prerequisite_evidence_manifest_id?: string,
     *     prerequisite_phase_handoff_id?: string,
     *     stop_code?: string,
     *     stop_state?: string,
     *     finding_id?: string,
     *     next_action?: string,
     *     resume_state?: string,
     *     resume_sequence?: int,
     *     resume_next_action?: string
     * }
     */
    public function resumePreparedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId
    ): array;

    /**
     * Appends recovery from one exact persisted stop to its recorded predecessor
     *
     * @return array{
     *     status: string,
     *     history_path?: string,
     *     projection_path?: string,
     *     sequence?: int,
     *     state?: string,
     *     next_action?: string
     * }
     */
    public function recoverPreparationStop(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        int $stopSequence,
        string $stopCode,
        string $stopState,
        string $findingId,
        string $nextAction,
        ?string $repairManifestId,
        ?string $repairHandoffId
    ): array;
}
