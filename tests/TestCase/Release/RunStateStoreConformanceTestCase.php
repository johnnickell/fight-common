<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Release;

use Fight\Common\Application\Release\Boundary\CanonicalRunsDirectory;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryCrash;
use Fight\Common\Application\Release\Boundary\ReleaseEffectLedger;
use Fight\Common\Application\Release\Boundary\RunStateStore;
use Fight\Common\Application\Release\CanonicalJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use Symfony\Component\Filesystem\Filesystem;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
/**
 * Class RunStateStoreConformanceTestCase
 *
 * Reusable contract for append-ordered run history and atomic current-state publication.
 */
abstract class RunStateStoreConformanceTestCase extends UnitTestCase
{
    private string $root;

    /**
     * Asserts the planned-to-prepared history and its derived projection
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     * @phpcsSuppress Generic.Files.LineLength.TooLong
     */
    public function test_that_a_distinct_prepared_run_has_ordered_history_and_one_atomic_projection(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);

        $state = $store->createPreparedRun($directory, $planId, $runId);

        self::assertSame([
            'status'            => 'created',
            'history_path'      => $directory->path.'/runs/'.$runId.'/history.jsonl',
            'projection_path'   => $directory->path.'/runs/'.$runId.'/projection.json',
            'sequence'          => 2,
            'state'             => 'prepared',
            'history_sha256'    => hash('sha256', (string) file_get_contents($directory->path.'/runs/'.$runId.'/history.jsonl')),
            'projection_sha256' => hash('sha256', (string) file_get_contents($directory->path.'/runs/'.$runId.'/projection.json'))
        ], $state);
        self::assertSame(
            '{"from":null,"next_action":{"action":"prepare_release_run"},"operation":"create_release_run","plan_id":"'.$planId.'","run_id":"'.$runId.'","sequence":1,"state":"planned"}'."\n".'{"from":"planned","next_action":{"action":"finalize_release_preparation_evidence"},"operation":"prepare_release_run","plan_id":"'.$planId.'","run_id":"'.$runId.'","sequence":2,"state":"prepared"}'."\n",
            file_get_contents($state['history_path'])
        );
        self::assertSame(
            '{"next_action":{"action":"finalize_release_preparation_evidence"},"plan_id":"'.$planId.'","run_id":"'.$runId.'","schema_version":"fight-common.release-run-state/v1","sequence":2,"state":"prepared"}'."\n",
            file_get_contents($state['projection_path'])
        );
        self::assertSame([
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
        ], $store->effects());
    }

    /**
     * Asserts an existing run is not replaced or mutated by another create attempt
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_creating_an_existing_run_fails_closed_and_preserves_its_projection(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $created = $store->createPreparedRun($directory, $planId, $runId);
        $projection = file_get_contents($created['projection_path']);

        self::assertSame(['status' => 'conflict'], $store->createPreparedRun($directory, $planId, $runId));
        self::assertSame($projection, file_get_contents($created['projection_path']));
        self::assertSame('refusal', $store->effects()[2]['outcome']);
    }

    /**
     * Asserts interruption after append preserves the prior valid projection for reconciliation
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_interruption_after_append_preserves_the_prior_projection_and_reentry_reconciles(): void
    {
        $store = $this->createRunStateStore(true);
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);

        try {
            $store->createPreparedRun($directory, $planId, $runId);
            self::fail('The configured interruption did not stop projection publication.');
        } catch (ReleaseBoundaryCrash $releaseBoundaryCrash) {
            self::assertSame('filesystem.write', $releaseBoundaryCrash->effectClass);
        }

        $runPath = $directory->path.'/runs/'.$runId;
        self::assertSame(
            '{"from":null,"next_action":{"action":"prepare_release_run"},"operation":"create_release_run","plan_id":"'.$planId.'","run_id":"'.$runId.'","sequence":1,"state":"planned"}'."\n".'{"from":"planned","next_action":{"action":"finalize_release_preparation_evidence"},"operation":"prepare_release_run","plan_id":"'.$planId.'","run_id":"'.$runId.'","sequence":2,"state":"prepared"}'."\n",
            file_get_contents($runPath.'/history.jsonl')
        );
        self::assertSame(
            '{"next_action":{"action":"prepare_release_run"},"plan_id":"'.$planId.'","run_id":"'.$runId.'","schema_version":"fight-common.release-run-state/v1","sequence":1,"state":"planned"}'."\n",
            file_get_contents($runPath.'/projection.json')
        );
        $historyBeforeContention = file_get_contents($runPath.'/history.jsonl');
        $projectionBeforeContention = file_get_contents($runPath.'/projection.json');
        $contendingResult = $this->whileRunWriterOwnsLease(
            $directory,
            $runId,
            static fn (): array => $store->createPreparedRun($directory, $planId, $runId)
        );

        self::assertSame(['status' => 'conflict'], $contendingResult);
        self::assertSame($historyBeforeContention, file_get_contents($runPath.'/history.jsonl'));
        self::assertSame($projectionBeforeContention, file_get_contents($runPath.'/projection.json'));
        self::assertSame([
            'status'              => 'evidence_pending',
            'history_path'        => $runPath.'/history.jsonl',
            'projection_path'     => $runPath.'/projection.json',
            'sequence'            => 2,
            'state'               => 'prepared',
            'projection_repaired' => true,
            'history_sha256'      => hash('sha256', (string) file_get_contents($runPath.'/history.jsonl')),
            'projection_sha256'   => hash('sha256', '{"next_action":{"action":"finalize_release_preparation_evidence"},"plan_id":"'.$planId.'","run_id":"'.$runId.'","schema_version":"fight-common.release-run-state/v1","sequence":2,"state":"prepared"}'."\n")
        ], $store->resumePreparedRun($directory, $planId, $runId));
        self::assertSame(
            '{"next_action":{"action":"finalize_release_preparation_evidence"},"plan_id":"'.$planId.'","run_id":"'.$runId.'","schema_version":"fight-common.release-run-state/v1","sequence":2,"state":"prepared"}'."\n",
            file_get_contents($runPath.'/projection.json')
        );
    }

    /**
     * Asserts a short append restores the last complete transition boundary
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_short_append_leaves_no_partial_transition_tail(): void
    {
        $store = $this->createRunStateStore(shortAppend: true);
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);

        self::assertSame(
            ['status' => 'indeterminate'],
            $store->createPreparedRun($directory, $planId, $runId)
        );

        $runPath = $directory->path.'/runs/'.$runId;
        self::assertSame(
            '{"from":null,"next_action":{"action":"prepare_release_run"},"operation":"create_release_run","plan_id":"'.$planId.'","run_id":"'.$runId.'","sequence":1,"state":"planned"}'."\n",
            file_get_contents($runPath.'/history.jsonl')
        );
        self::assertSame(
            '{"next_action":{"action":"prepare_release_run"},"plan_id":"'.$planId.'","run_id":"'.$runId.'","schema_version":"fight-common.release-run-state/v1","sequence":1,"state":"planned"}'."\n",
            file_get_contents($runPath.'/projection.json')
        );
    }

    /**
     * Asserts planned state is visible before single-writer prepared publication
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_planned_run_precedes_single_writer_prepared_publication(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);

        self::assertSame('planned', $store->createPlannedRun($directory, $planId, $runId)['status']);
        self::assertSame('planned', $store->resumePreparedRun($directory, $planId, $runId)['status']);

        $contended = $this->whileRunWriterOwnsLease(
            $directory,
            $runId,
            static fn (): array => $store->publishPreparedRun($directory, $planId, $runId, 1, 'planned')
        );
        self::assertSame(['status' => 'conflict'], $contended);
        self::assertSame('created', $store->publishPreparedRun($directory, $planId, $runId, 1, 'planned')['status']);
    }

    /**
     * Asserts exact stop recovery appends history and restores its recorded predecessor
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_repaired_stop_recovers_append_only_to_its_exact_predecessor(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('b', 64);
        self::assertSame('planned', $store->createPlannedRun($directory, $planId, $runId)['status']);
        [$manifestId, $handoffId] = $this->writeStopArtifacts(
            $directory,
            $planId,
            $runId,
            'policy_blocked',
            'release.prepare.baseline_tag_missing',
            'repair_baseline_authority'
        );
        self::assertSame('created', $store->publishPreparationStop(
            $directory,
            $planId,
            $runId,
            'baseline_missing',
            'policy_blocked',
            'release.prepare.baseline_tag_missing',
            'repair_baseline_authority',
            $manifestId,
            $handoffId,
            1,
            'planned'
        )['status']);
        $runPath = $directory->path.'/runs/'.$runId;
        $stoppedHistory = (string) file_get_contents($runPath.'/history.jsonl');
        $stopped = $store->resumePreparedRun($directory, $planId, $runId);
        self::assertSame('stopped', $stopped['status']);
        self::assertSame(2, $stopped['sequence']);
        self::assertSame('planned', $stopped['resume_state']);
        self::assertSame('prepare_release_run', $stopped['resume_next_action']);

        self::assertSame(['status' => 'indeterminate'], $store->recoverPreparationStop(
            $directory,
            $planId,
            $runId,
            2,
            'baseline_missing',
            'policy_blocked',
            'release.prepare.other',
            'repair_baseline_authority',
            null,
            null
        ));
        self::assertSame($stoppedHistory, file_get_contents($runPath.'/history.jsonl'));

        $recovered = $store->recoverPreparationStop(
            $directory,
            $planId,
            $runId,
            2,
            'baseline_missing',
            'policy_blocked',
            'release.prepare.baseline_tag_missing',
            'repair_baseline_authority',
            null,
            null
        );
        self::assertSame('created', $recovered['status']);
        self::assertStringStartsWith($stoppedHistory, (string) file_get_contents($runPath.'/history.jsonl'));
        self::assertSame('planned', $store->resumePreparedRun($directory, $planId, $runId)['status']);
        self::assertSame('created', $store->publishPreparedRun($directory, $planId, $runId, 3, 'planned')['status']);
        self::assertSame('evidence_pending', $store->resumePreparedRun($directory, $planId, $runId)['status']);
    }

    /**
     * Asserts projection publication remains indeterminate until its containing directory is durably synced
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_projection_directory_sync_failure_requires_governed_reconciliation(): void
    {
        $store = $this->createRunStateStore(failPreparedProjectionDirectorySync: true);
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);

        self::assertSame('planned', $store->createPlannedRun($directory, $planId, $runId)['status']);
        self::assertSame(
            ['status' => 'indeterminate'],
            $store->publishPreparedRun($directory, $planId, $runId, 1, 'planned')
        );

        $runPath = $directory->path.'/runs/'.$runId;
        self::assertSame(
            '{"next_action":{"action":"finalize_release_preparation_evidence"},"plan_id":"'.$planId.'","run_id":"'.$runId.'","schema_version":"fight-common.release-run-state/v1","sequence":2,"state":"prepared"}'."\n",
            file_get_contents($runPath.'/projection.json')
        );
        self::assertSame('evidence_pending', $store->resumePreparedRun($directory, $planId, $runId)['status']);
    }

    /**
     * Asserts finalization interruption repairs the projection from the complete ordered history
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_interruption_after_finalized_append_reconciles_the_package_ready_projection(): void
    {
        $store = $this->createRunStateStore(interruptFinalizedProjection: true);
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('b', 64);
        self::assertSame('created', $store->createPreparedRun($directory, $planId, $runId)['status']);
        [$manifestId, $handoffId] = $this->writePreparationArtifacts($directory, $planId, $runId);

        try {
            $store->finalizePreparedRun(
                $directory,
                $planId,
                $runId,
                $manifestId,
                $handoffId,
                2,
                'prepared'
            );
            self::fail('The configured finalization interruption was not raised.');
        } catch (ReleaseBoundaryCrash $releaseBoundaryCrash) {
            self::assertSame('filesystem.write', $releaseBoundaryCrash->effectClass);
        }

        $runPath = $directory->path.'/runs/'.$runId;
        self::assertCount(3, file($runPath.'/history.jsonl', FILE_IGNORE_NEW_LINES));
        self::assertSame(2, json_decode(
            (string) file_get_contents($runPath.'/projection.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        )['sequence']);

        $resumed = $store->resumePreparedRun($directory, $planId, $runId);
        self::assertSame('verified', $resumed['status']);
        self::assertSame($manifestId, $resumed['prerequisite_evidence_manifest_id']);
        self::assertSame($handoffId, $resumed['prerequisite_phase_handoff_id']);
        self::assertSame(3, json_decode(
            (string) file_get_contents($runPath.'/projection.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        )['sequence']);
    }

    /**
     * Asserts finalized crash repair refuses a missing bound artifact
     */
    public function test_that_finalized_crash_repair_revalidates_bound_artifacts_before_projection(): void
    {
        $store = $this->createRunStateStore(interruptFinalizedProjection: true);
        $directory = new CanonicalRunsDirectory($this->root.'/finalized-repair-artifacts', $this->root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('d', 64);
        self::assertSame('created', $store->createPreparedRun($directory, $planId, $runId)['status']);
        [$manifestId, $handoffId] = $this->writePreparationArtifacts($directory, $planId, $runId);

        try {
            $store->finalizePreparedRun($directory, $planId, $runId, $manifestId, $handoffId, 2, 'prepared');
        } catch (ReleaseBoundaryCrash) {
        }

        unlink($directory->artifactPath($manifestId.'.evidence-manifest.json'));

        self::assertSame(['status' => 'indeterminate'], $store->resumePreparedRun($directory, $planId, $runId));
        self::assertSame(2, json_decode((string) file_get_contents(
            $directory->path.'/runs/'.$runId.'/projection.json'
        ), true, flags: JSON_THROW_ON_ERROR)['sequence']);
    }

    /**
     * Asserts classified stops are append-only current truth with one identical action
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_classified_preparation_stop_is_durable_and_idempotent(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/stop-attempt', $this->root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('b', 64);
        self::assertSame('created', $store->createPreparedRun($directory, $planId, $runId)['status']);
        [$manifestId, $handoffId] = $this->writeStopArtifacts(
            $directory,
            $planId,
            $runId,
            'evidence_indeterminate',
            'release.prepare.artifacts_indeterminate',
            'reconcile_named_release_run'
        );

        $stopped = $store->publishPreparationStop(
            $directory,
            $planId,
            $runId,
            'artifact_indeterminate',
            'evidence_indeterminate',
            'release.prepare.artifacts_indeterminate',
            'reconcile_named_release_run',
            $manifestId,
            $handoffId,
            2,
            'prepared'
        );

        self::assertSame('created', $stopped['status']);
        $history = file($stopped['history_path'], FILE_IGNORE_NEW_LINES);
        self::assertIsArray($history);
        self::assertCount(3, $history);
        $transition = json_decode($history[2], true, flags: JSON_THROW_ON_ERROR);
        $projection = json_decode(
            (string) file_get_contents($stopped['projection_path']),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        self::assertSame('evidence_indeterminate', $transition['state']);
        self::assertSame('artifact_indeterminate', $transition['stop_code']);
        self::assertSame($transition['state'], $projection['state']);
        self::assertSame(
            ['action' => 'reconcile_named_release_run'],
            $transition['next_action']
        );
        self::assertSame($transition['next_action'], $projection['next_action']);
        self::assertSame($manifestId, $projection['evidence_manifest_id']);
        self::assertSame($handoffId, $projection['phase_handoff_id']);
        self::assertSame(
            'verified',
            $store->publishPreparationStop(
                $directory,
                $planId,
                $runId,
                'artifact_indeterminate',
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run',
                $manifestId,
                $handoffId,
                2,
                'prepared'
            )['status']
        );
        $resumed = $store->resumePreparedRun($directory, $planId, $runId);
        self::assertSame('stopped', $resumed['status']);
        self::assertSame('artifact_indeterminate', $resumed['stop_code']);
        self::assertSame('evidence_indeterminate', $resumed['stop_state']);
        self::assertSame('release.prepare.artifacts_indeterminate', $resumed['finding_id']);
        self::assertSame('reconcile_named_release_run', $resumed['next_action']);
    }

    /**
     * Asserts a losing resume cannot append its stop over a winner's later state
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_stop_publication_compares_the_exact_observed_predecessor_under_the_writer_lease(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/cas-attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $observed = $store->createPlannedRun($directory, $planId, $runId);
        self::assertSame(1, $observed['sequence']);
        self::assertSame('planned', $observed['state']);

        self::assertSame('created', $store->publishPreparedRun($directory, $planId, $runId, 1, 'planned')['status']);
        $runPath = $directory->path.'/runs/'.$runId;
        $winnerHistory = (string) file_get_contents($runPath.'/history.jsonl');
        $winnerProjection = (string) file_get_contents($runPath.'/projection.json');

        $loser = $store->publishPreparationStop(
            $directory,
            $planId,
            $runId,
            'state_indeterminate',
            'evidence_indeterminate',
            'release.prepare.state_persistence_indeterminate',
            'reconcile_named_release_run',
            str_repeat('3', 64),
            str_repeat('4', 64),
            $observed['sequence'],
            $observed['state']
        );

        self::assertSame(['status' => 'advanced'], $loser);
        self::assertSame($winnerHistory, file_get_contents($runPath.'/history.jsonl'));
        self::assertSame($winnerProjection, file_get_contents($runPath.'/projection.json'));
    }

    /**
     * Asserts stop recovery cannot create an ABA predecessor for stale mutation receipts
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_stop_recovery_does_not_let_stale_prepare_or_finalize_receipts_advance(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/aba-attempt', $this->root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);

        foreach (['publish', 'finalize'] as $operation) {
            $runId = hash('sha256', 'aba-'.$operation);
            $prepared = $store->createPreparedRun($directory, $planId, $runId);
            self::assertSame(2, $prepared['sequence']);
            [$manifestId, $handoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $runId,
                'policy_blocked',
                'release.prepare.baseline_tag_missing',
                'repair_baseline_authority'
            );
            self::assertSame('created', $store->publishPreparationStop(
                $directory,
                $planId,
                $runId,
                'baseline_missing',
                'policy_blocked',
                'release.prepare.baseline_tag_missing',
                'repair_baseline_authority',
                $manifestId,
                $handoffId,
                2,
                'prepared'
            )['status']);
            self::assertSame('created', $store->recoverPreparationStop(
                $directory,
                $planId,
                $runId,
                3,
                'baseline_missing',
                'policy_blocked',
                'release.prepare.baseline_tag_missing',
                'repair_baseline_authority',
                null,
                null
            )['status']);

            $receipt = $operation === 'publish' ? $store->publishPreparedRun(
                $directory,
                $planId,
                $runId,
                2,
                'prepared'
            ) : $store->finalizePreparedRun(
                $directory,
                $planId,
                $runId,
                str_repeat('3', 64),
                str_repeat('4', 64),
                2,
                'prepared'
            );
            self::assertSame(['status' => 'advanced'], $receipt, $operation);
            self::assertSame(4, $store->resumePreparedRun($directory, $planId, $runId)['sequence']);
        }
    }

    /**
     * Asserts package-ready state cannot bind unverified content identities
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_finalization_rejects_arbitrary_valid_digest_identifiers_without_artifacts(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/artifact-truth-attempt', $this->root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        self::assertSame('created', $store->createPreparedRun($directory, $planId, $runId)['status']);

        self::assertSame(
            ['status' => 'indeterminate'],
            $store->finalizePreparedRun(
                $directory,
                $planId,
                $runId,
                str_repeat('1', 64),
                str_repeat('2', 64),
                2,
                'prepared'
            )
        );
        self::assertSame(2, $store->resumePreparedRun($directory, $planId, $runId)['sequence']);
    }

    /**
     * Asserts a classified stop can establish the first durable state for a named attempt
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_precondition_stop_creates_one_stopped_run_without_a_fabricated_planned_state(): void
    {
        $store = $this->createRunStateStore();
        $directory = new CanonicalRunsDirectory($this->root.'/early-stop-attempt', $this->root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('c', 64);
        [$manifestId, $handoffId] = $this->writeStopArtifacts(
            $directory,
            $planId,
            $runId,
            'stale_plan',
            'release.prepare.baseline_resolution_drift',
            'create_current_release_plan'
        );

        $stopped = $store->publishPreparationStop(
            $directory,
            $planId,
            $runId,
            'baseline_drift',
            'stale_plan',
            'release.prepare.baseline_resolution_drift',
            'create_current_release_plan',
            $manifestId,
            $handoffId,
            null,
            null
        );

        self::assertSame('created', $stopped['status']);
        $history = file($stopped['history_path'], FILE_IGNORE_NEW_LINES);
        self::assertIsArray($history);
        self::assertCount(1, $history);
        $transition = json_decode($history[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertNull($transition['from']);
        self::assertSame(1, $transition['sequence']);
        self::assertSame('baseline_drift', $transition['stop_code']);
        self::assertArrayNotHasKey('resume_state', $transition);
        self::assertSame('stopped', $store->resumePreparedRun($directory, $planId, $runId)['status']);
    }

    /**
     * Asserts named resume durably completes parent-directory publication after an uncertain create
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_parent_directory_sync_failures_before_binding_are_not_inferred_by_named_resume(): void
    {
        foreach (['runs_parent_directory_sync', 'run_parent_directory_sync'] as $failurePoint) {
            $store = $this->createRunStateStore(directorySyncFailure: $failurePoint);
            $directory = new CanonicalRunsDirectory($this->root.'/attempt-'.$failurePoint, $this->root);
            mkdir($directory->path);
            $planId = str_repeat('a', 64);
            $runId = hash('sha256', $failurePoint);

            self::assertSame(
                ['status' => 'indeterminate'],
                $store->createPlannedRun($directory, $planId, $runId),
                $failurePoint
            );
            self::assertSame('indeterminate', $store->resumePreparedRun($directory, $planId, $runId)['status']);
            self::assertFileDoesNotExist($directory->path.'/runs/'.$runId.'/history.jsonl');
            self::assertFileDoesNotExist($directory->path.'/runs/'.$runId.'/projection.json');
        }
    }

    /**
     * Asserts a lexical output symlink cannot redirect initial run-state publication
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_symlinked_output_authority_cannot_receive_run_state(): void
    {
        $external = sys_get_temp_dir().'/fight-common-run-state-external-'.bin2hex(random_bytes(8));
        mkdir($external);
        symlink($external, $this->root.'/attempt');
        $directory = new CanonicalRunsDirectory($this->root.'/attempt', $this->root);

        try {
            $store = $this->createRunStateStore();
            self::assertSame(
                ['status' => 'failed'],
                $store->createPlannedRun(
                    $directory,
                    str_repeat('a', 64),
                    str_repeat('b', 64)
                )
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                $store->publishPreparedRun($directory, str_repeat('a', 64), str_repeat('b', 64), 1, 'planned')
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                $store->resumePreparedRun($directory, str_repeat('a', 64), str_repeat('b', 64))
            );
            self::assertSame([], array_values(array_diff(scandir($external), ['.', '..'])));
        } finally {
            new Filesystem()->remove($external);
        }
    }

    /**
     * Asserts persisted bindings reject replacement of their output or runs-root authority
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_replaced_output_and_root_authorities_cannot_resume_a_named_run(): void
    {
        foreach (['output', 'root'] as $replacement) {
            $root = sys_get_temp_dir().'/fight-common-run-authority-'.$replacement.'-'.bin2hex(random_bytes(8));
            mkdir($root);
            mkdir($root.'/attempt');
            $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
            $store = $this->createRunStateStore();
            $planId = str_repeat('a', 64);
            $runId = hash('sha256', $replacement);
            $planned = $store->createPlannedRun($directory, $planId, $runId);
            self::assertSame('planned', $planned['status']);
            $history = file_get_contents($planned['history_path']);

            try {
                if ($replacement === 'output') {
                    rename($root.'/attempt', $root.'/displaced');
                    mkdir($root.'/attempt');
                    rename($root.'/displaced/runs', $root.'/attempt/runs');
                } else {
                    rename($root, $root.'-displaced');
                    mkdir($root);
                    rename($root.'-displaced/attempt', $root.'/attempt');
                }

                self::assertSame(
                    ['status' => 'indeterminate'],
                    $this->createRunStateStore()->resumePreparedRun($directory, $planId, $runId),
                    $replacement
                );
                self::assertSame($history, file_get_contents($planned['history_path']), $replacement);
            } finally {
                new Filesystem()->remove([$root, $root.'-displaced']);
            }
        }
    }

    /**
     * Creates the run-state adapter under test
     */
    abstract protected function createRunStateStore(
        bool $interruptAfterAppend = false,
        bool $shortAppend = false,
        bool $failPreparedProjectionDirectorySync = false,
        bool $interruptFinalizedProjection = false,
        ?string $directorySyncFailure = null
    ): RunStateStore&ReleaseEffectLedger;

    /**
     * Writes one exact paired preparation artifact set
     *
     * @return array{string, string}
     */
    protected function writePreparationArtifacts(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId
    ): array {
        $json = new CanonicalJson();
        $plan = json_decode(
            (string) file_get_contents($directory->artifactPath($planId.'.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $runPath = $directory->path.'/runs/'.$runId;
        $manifest = [
            'activation'        => [
                'mode'                              => 'projection_bound',
                'projection_must_bind_artifact_ids' => true,
                'required_projection_state'         => 'prepared'
            ],
            'approvals'         => [
                'release'  => $plan['release_approval_authority'],
                'required' => $plan['required_approvals']
            ],
            'bindings'          => $this->artifactBindings($plan),
            'next_action'       => ['action' => 'package_release_run'],
            'phase'             => 'preparation',
            'plan_id'           => $planId,
            'run_id'            => $runId,
            'schema_version'    => 'fight-common.release-evidence-manifest/v1',
            'status'            => 'prepared',
            'stop_state'        => null,
            'verified_evidence' => [
                'history_sha256'    => hash('sha256', (string) file_get_contents($runPath.'/history.jsonl')),
                'postconditions'    => ['immutable_plan_revalidated', 'prepared_run_projection_published'],
                'projection_sha256' => hash('sha256', (string) file_get_contents($runPath.'/projection.json'))
            ]
        ];
        $manifestId = hash('sha256', $json->encode($manifest));
        $handoff = [...$manifest, 'bindings' => [...$manifest['bindings'], 'evidence_manifest_id' => $manifestId], 'schema_version' => 'fight-common.release-phase-handoff/v1'];
        $handoffId = hash('sha256', $json->encode($handoff));
        self::assertNotFalse(file_put_contents(
            $directory->artifactPath($manifestId.'.evidence-manifest.json'),
            $json->encode([...$manifest, 'manifest_id' => $manifestId])."\n"
        ));
        self::assertNotFalse(file_put_contents(
            $directory->artifactPath($handoffId.'.phase-handoff.json'),
            $json->encode([...$handoff, 'handoff_id' => $handoffId])."\n"
        ));

        return [$manifestId, $handoffId];
    }

    /**
     * Writes one exact immutable plan and returns its content identity
     */
    protected function writeReleasePlan(CanonicalRunsDirectory $directory): string
    {
        $json = new CanonicalJson();
        $plan = [
            'approved_version'            => '1.2.3',
            'baseline'                    => [
                'peeled_commit_oid' => str_repeat('b', 40),
                'tag_name'          => 'v1.2.2',
                'tag_object_oid'    => str_repeat('a', 40),
                'version'           => '1.2.2'
            ],
            'compatibility_exceptions'    => [],
            'evidence_manifest_digest'    => str_repeat('d', 64),
            'evidence_requirements'       => [],
            'expected_effect_classes'     => [],
            'minimum_release_class'       => 'patch',
            'patch_exception_authorities' => [],
            'release_approval_authority'  => 'release-owner',
            'release_class'               => 'patch',
            'required_approvals'          => [],
            'schema_version'              => 'fight-common.release-plan/v1',
            'source_commit_oid'           => str_repeat('c', 40),
            'support_policy_identity'     => 'supported'
        ];
        $planId = hash('sha256', $json->encode($plan));
        self::assertNotFalse(file_put_contents(
            $directory->artifactPath($planId.'.json'),
            $json->encode([...$plan, 'plan_id' => $planId])."\n"
        ));

        return $planId;
    }

    /**
     * Writes one exact stop artifact pair
     *
     * @return array{string, string}
     */
    protected function writeStopArtifacts(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        string $status,
        string $findingId,
        string $nextAction,
        string $activationMode = 'projection_bound'
    ): array {
        $json = new CanonicalJson();
        $plan = json_decode(
            (string) file_get_contents($directory->artifactPath($planId.'.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $activation = [
            'mode'                              => $activationMode,
            'projection_must_bind_artifact_ids' => $activationMode === 'projection_bound'
        ];
        if ($activationMode === 'projection_bound') {
            $activation['required_projection_state'] = $status;
        }

        $manifest = [
            'activation'        => $activation,
            'approvals'         => [
                'release'  => $plan['release_approval_authority'],
                'required' => $plan['required_approvals']
            ],
            'bindings'          => $this->artifactBindings($plan),
            'next_action'       => ['action' => $nextAction],
            'phase'             => 'preparation',
            'plan_id'           => $planId,
            'run_id'            => $runId,
            'schema_version'    => 'fight-common.release-evidence-manifest/v1',
            'status'            => $status,
            'stop_state'        => ['finding_id' => $findingId, 'status' => $status],
            'verified_evidence' => ['postconditions' => []]
        ];
        $manifestId = hash('sha256', $json->encode($manifest));
        $handoff = [
            ...$manifest,
            'bindings'       => [...$manifest['bindings'], 'evidence_manifest_id' => $manifestId],
            'schema_version' => 'fight-common.release-phase-handoff/v1'
        ];
        $handoffId = hash('sha256', $json->encode($handoff));
        self::assertNotFalse(file_put_contents(
            $directory->artifactPath($manifestId.'.evidence-manifest.json'),
            $json->encode([...$manifest, 'manifest_id' => $manifestId])."\n"
        ));
        self::assertNotFalse(file_put_contents(
            $directory->artifactPath($handoffId.'.phase-handoff.json'),
            $json->encode([...$handoff, 'handoff_id' => $handoffId])."\n"
        ));

        return [$manifestId, $handoffId];
    }

    /**
     * Invokes a competing mutation while this adapter's writer lease is held
     *
     * @param CanonicalRunsDirectory $directory Canonical run root.
     * @param string $runId Run identity whose lease is held.
     * @param callable(): TResult $attempt
     *
     * @template TResult
     *
     * @return TResult
     */
    abstract protected function whileRunWriterOwnsLease(
        CanonicalRunsDirectory $directory,
        string $runId,
        callable $attempt
    ): mixed;

    /**
     * Creates an isolated canonical run root
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/fight-common-run-state-conformance-'.bin2hex(random_bytes(8));
        mkdir($this->root);
    }

    /**
     * Removes only this conformance test's isolated state
     */
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->root);
        parent::tearDown();
    }

    /**
     * Returns exact immutable-plan bindings for one preparation artifact
     *
     * @param array<string, mixed> $plan Immutable plan.
     *
     * @return array<string, mixed>
     */
    private function artifactBindings(array $plan): array
    {
        return [
            'approved_version'            => $plan['approved_version'],
            'baseline'                    => $plan['baseline'],
            'compatibility_exceptions'    => $plan['compatibility_exceptions'],
            'evidence_manifest_digest'    => $plan['evidence_manifest_digest'],
            'evidence_requirements'       => $plan['evidence_requirements'],
            'expected_effect_classes'     => $plan['expected_effect_classes'],
            'minimum_release_class'       => $plan['minimum_release_class'],
            'patch_exception_authorities' => $plan['patch_exception_authorities'],
            'release_class'               => $plan['release_class'],
            'source_commit_oid'           => $plan['source_commit_oid'],
            'support_policy_identity'     => $plan['support_policy_identity']
        ];
    }
}
