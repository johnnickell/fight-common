<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter\Fake;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\ReleaseBoundaryCrash;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\RunStateStore;
use Fight\Test\Release\TestCase\RunStateStoreConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Filesystem;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class DeterministicReleaseRunStateStoreConformanceTest
 *
 * Applies the reusable run-state contract to the deterministic filesystem boundary.
 */
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
final class DeterministicReleaseRunStateStoreConformanceTest extends RunStateStoreConformanceTestCase
{
    /**
     * Covers a regular state file growing beyond the governed bound after it is opened.
     */
    public function test_that_concurrent_state_growth_after_open_is_bounded_and_rejected(): void
    {
        foreach (['binding', 'history', 'projection'] as $stateFile) {
            $root = sys_get_temp_dir().'/fight-common-run-'.$stateFile.'-growth-'.bin2hex(random_bytes(8));
            mkdir($root);
            $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
            mkdir($directory->path);
            $planId = str_repeat('a', 64);
            $runId = str_repeat('b', 64);

            try {
                self::assertSame(
                    'created',
                    new DeterministicReleaseBoundaryFake()->createPreparedRun($directory, $planId, $runId)['status']
                );
                self::assertSame(
                    ['status' => 'indeterminate'],
                    new DeterministicReleaseBoundaryFake(runStateFailureOnce: $stateFile.'_growth_after_open')
                        ->resumePreparedRun($directory, $planId, $runId),
                    $stateFile
                );
            } finally {
                new Filesystem()->remove($root);
            }
        }
    }

    /**
     * Applies the reusable symlinked-output authority assertion to this adapter
     */
    public function test_that_a_symlinked_output_authority_cannot_receive_run_state(): void
    {
        parent::test_that_a_symlinked_output_authority_cannot_receive_run_state();
    }

    /**
     * Applies the reusable replaced-ancestor authority assertion to this adapter
     */
    public function test_that_replaced_output_and_root_authorities_cannot_resume_a_named_run(): void
    {
        parent::test_that_replaced_output_and_root_authorities_cannot_resume_a_named_run();
    }

    /**
     * Covers interruption before the immutable run-to-plan binding becomes authoritative.
     */
    public function test_that_an_unbound_created_run_directory_is_never_resumable(): void
    {
        $root = sys_get_temp_dir().'/fight-common-unbound-run-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $runId = str_repeat('b', 64);
        $originalPlanId = str_repeat('a', 64);

        try {
            $store = new DeterministicReleaseBoundaryFake();
            $store->interruptBeforeRunBindingOnce();

            try {
                $store->createPlannedRun($directory, $originalPlanId, $runId);
                self::fail('The configured pre-binding interruption was not raised.');
            } catch (ReleaseBoundaryCrash $releaseBoundaryCrash) {
                self::assertSame('filesystem.write', $releaseBoundaryCrash->effectClass);
            }

            $runPath = $directory->path.'/runs/'.$runId;
            self::assertDirectoryExists($runPath);
            self::assertFileDoesNotExist($runPath.'/binding.json');

            foreach ([$originalPlanId, str_repeat('c', 64)] as $planId) {
                self::assertSame(
                    ['status' => 'indeterminate'],
                    new DeterministicReleaseBoundaryFake()->resumePreparedRun($directory, $planId, $runId)
                );
                self::assertFileDoesNotExist($runPath.'/history.jsonl');
                self::assertFileDoesNotExist($runPath.'/projection.json');
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers confinement against symlinked roots and authoritative run-state paths.
     */
    public function test_that_symlinked_run_state_authority_never_writes_external_bytes(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-symlink-'.bin2hex(random_bytes(8));
        $external = sys_get_temp_dir().'/fight-common-run-external-'.bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($external);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);

        try {
            $rootSymlinkDirectory = new CanonicalRunsDirectory($root.'/root-symlink', $root);
            mkdir($rootSymlinkDirectory->path);
            symlink($external, $rootSymlinkDirectory->path.'/runs');
            self::assertSame(
                ['status' => 'failed'],
                new DeterministicReleaseBoundaryFake()->createPlannedRun($rootSymlinkDirectory, $planId, $runId)
            );
            self::assertSame([], array_values(array_diff(scandir($external), ['.', '..'])));

            $pathSymlinkDirectory = new CanonicalRunsDirectory($root.'/path-symlink', $root);
            mkdir($pathSymlinkDirectory->path);
            $store = new DeterministicReleaseBoundaryFake();
            self::assertSame('planned', $store->createPlannedRun($pathSymlinkDirectory, $planId, $runId)['status']);
            $runPath = $pathSymlinkDirectory->path.'/runs/'.$runId;
            unlink($runPath.'/projection.json');
            file_put_contents($external.'/projection.json', "external\n");
            symlink($external.'/projection.json', $runPath.'/projection.json');

            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake()->resumePreparedRun($pathSymlinkDirectory, $planId, $runId)
            );
            self::assertSame("external\n", file_get_contents($external.'/projection.json'));
        } finally {
            new Filesystem()->remove([$root, $external]);
        }
    }

    /**
     * Covers replacement of the bound run directory with a fresh lock namespace.
     */
    public function test_that_run_directory_identity_replacement_cannot_split_the_writer_lock(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-replacement-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $store = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame('planned', $store->createPlannedRun($directory, $planId, $runId)['status']);
            $runPath = $directory->path.'/runs/'.$runId;
            $displaced = $runPath.'.displaced';
            rename($runPath, $displaced);
            mkdir($runPath, 0700);

            foreach (['binding.json', 'history.jsonl', 'projection.json'] as $filename) {
                copy($displaced.'/'.$filename, $runPath.'/'.$filename);
            }

            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake()->resumePreparedRun($directory, $planId, $runId)
            );
            self::assertSame(1, json_decode(
                (string) file_get_contents($runPath.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            )['sequence']);
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers replacement after authority has been opened but before staged state publication.
     */
    public function test_that_replacement_inside_the_state_syscall_window_cannot_write_external_bytes(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-syscall-race-'.bin2hex(random_bytes(8));
        $external = sys_get_temp_dir().'/fight-common-run-syscall-external-'.bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($external);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $creator = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame('planned', $creator->createPlannedRun($directory, $planId, $runId)['status']);
            $replacing = new DeterministicReleaseBoundaryFake(
                runStateFailureOnce: 'replace_run_before_state_stage',
                runStateReplacementTarget: $external
            );

            self::assertSame(
                ['status' => 'indeterminate'],
                $replacing->publishPreparedRun($directory, $planId, $runId, 1, 'planned')
            );
            self::assertSame([], array_values(array_diff(scandir($external), ['.', '..'])));
        } finally {
            new Filesystem()->remove([$root, $external]);
        }
    }

    /**
     * Covers raw identifiers before any path segment is derived from them.
     */
    public function test_that_raw_non_digest_identifiers_cannot_escape_run_state_authority(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-raw-identity-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $store = new DeterministicReleaseBoundaryFake();

        try {
            foreach (['../../escaped', str_repeat('A', 64), str_repeat('a', 63)] as $runId) {
                self::assertSame(
                    ['status' => 'failed'],
                    $store->createPlannedRun($directory, str_repeat('a', 64), $runId),
                    $runId
                );
            }

            self::assertSame(
                ['status' => 'failed'],
                $store->createPlannedRun($directory, '../../plan', str_repeat('b', 64))
            );
            self::assertFileDoesNotExist($root.'/escaped');
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers deterministic native ambiguity controls at every confined creation and writer boundary.
     */
    public function test_that_each_confined_native_ambiguity_stops_without_publishing_prepared_state(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-native-ambiguity-'.bin2hex(random_bytes(8));
        mkdir($root);
        $planId = str_repeat('a', 64);
        try {
            foreach (
                [
                    'canonical_authority_identity'          => 'failed',
                    'runs_native_create'                    => 'failed',
                    'runs_identity_after_create'            => 'failed',
                    'run_identity_after_create'             => 'indeterminate',
                    'binding_collision'                     => 'indeterminate',
                    'writer_directory_mismatch_before_open' => 'indeterminate',
                    'writer_native_open'                    => 'indeterminate',
                    'writer_lock_identity_after_open'       => 'indeterminate',
                    'writer_directory_after_lock'           => 'indeterminate',
                    'history_target_symlink'                => 'indeterminate',
                    'projection_target_symlink'             => 'indeterminate'
                ] as $control => $expectedStatus
            ) {
                $directory = new CanonicalRunsDirectory($root.'/'.$control, $root);
                mkdir($directory->path);
                $runId = hash('sha256', $control);
                $store = new DeterministicReleaseBoundaryFake(runStateFailureOnce: $control);
                $result = $store->createPreparedRun($directory, $planId, $runId);

                self::assertSame(['status' => $expectedStatus], $result, $control);

                $runPath = $directory->path.'/runs/'.$runId;

                if (is_dir($runPath)) {
                    if ('projection_target_symlink' === $control) {
                        self::assertTrue(is_link($runPath.'/projection.json'), $control);
                    } else {
                        self::assertFileDoesNotExist($runPath.'/projection.json', $control);
                    }

                    if (is_file($runPath.'/binding.json')) {
                        self::assertStringNotContainsString(
                            'prepared',
                            (string) file_get_contents($runPath.'/binding.json'),
                            $control
                        );
                    }
                }
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers malformed binding, run, lock, and read identities without following replacement targets.
     */
    public function test_that_each_named_resume_authority_ambiguity_fails_closed(): void
    {
        $root = sys_get_temp_dir().'/fight-common-resume-authority-'.bin2hex(random_bytes(8));
        $external = sys_get_temp_dir().'/fight-common-resume-external-'.bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($external);
        file_put_contents($external.'/sentinel', 'unchanged');
        $planId = str_repeat('a', 64);

        try {
            $symlinkDirectory = new CanonicalRunsDirectory($root.'/run-symlink', $root);
            mkdir($symlinkDirectory->path);
            mkdir($symlinkDirectory->path.'/runs');
            $symlinkRunId = str_repeat('1', 64);
            symlink($external, $symlinkDirectory->path.'/runs/'.$symlinkRunId);
            $store = new DeterministicReleaseBoundaryFake();
            self::assertSame(
                ['status' => 'failed'],
                $store->createPlannedRun($symlinkDirectory, $planId, $symlinkRunId)
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                $store->resumePreparedRun($symlinkDirectory, $planId, $symlinkRunId)
            );

            foreach (['malformed_binding', 'lock_symlink', 'read_native_open', 'read_identity_after_open'] as $case) {
                $directory = new CanonicalRunsDirectory($root.'/'.$case, $root);
                mkdir($directory->path);
                $runId = hash('sha256', $case);
                $creator = new DeterministicReleaseBoundaryFake();
                self::assertSame('planned', $creator->createPlannedRun($directory, $planId, $runId)['status']);
                $runPath = $directory->path.'/runs/'.$runId;

                $resumer = match ($case) {
                    'malformed_binding' => $creator,
                    'lock_symlink' => $creator,
                    default => new DeterministicReleaseBoundaryFake(runStateFailureOnce: $case)
                };

                if ($case === 'malformed_binding') {
                    file_put_contents($runPath.'/binding.json', 'not-json');
                }

                if ($case === 'lock_symlink') {
                    unlink($runPath.'/.writer.lock');
                    symlink($external.'/sentinel', $runPath.'/.writer.lock');
                }

                self::assertSame(
                    ['status' => 'indeterminate'],
                    $resumer->resumePreparedRun($directory, $planId, $runId),
                    $case
                );
                self::assertSame('unchanged', file_get_contents($external.'/sentinel'), $case);
            }
        } finally {
            new Filesystem()->remove([$root, $external]);
        }
    }

    /**
     * Covers actual pathname replacement after each authoritative file descriptor has been opened.
     */
    public function test_that_in_window_file_replacement_cannot_be_trusted_or_mutate_external_bytes(): void
    {
        $root = sys_get_temp_dir().'/fight-common-state-file-race-'.bin2hex(random_bytes(8));
        $external = sys_get_temp_dir().'/fight-common-state-file-race-external-'.bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($external);
        file_put_contents($external.'/sentinel', "unchanged\n");
        $planId = str_repeat('a', 64);
        try {
            foreach (
                [
                    'binding_identity_after_read',
                    'history_identity_after_read',
                    'projection_identity_after_read'
                ] as $control
            ) {
                $directory = new CanonicalRunsDirectory($root.'/'.$control, $root);
                mkdir($directory->path);
                $runId = hash('sha256', $control);
                $creator = new DeterministicReleaseBoundaryFake();
                self::assertSame('planned', $creator->createPlannedRun($directory, $planId, $runId)['status']);
                $runPath = $directory->path.'/runs/'.$runId;
                $history = file_get_contents($runPath.'/history.jsonl');
                $projection = file_get_contents($runPath.'/projection.json');
                $replacing = new DeterministicReleaseBoundaryFake(
                    runStateFailureOnce: $control,
                    runStateReplacementTarget: $external.'/sentinel'
                );

                self::assertSame(
                    ['status' => 'indeterminate'],
                    $replacing->resumePreparedRun($directory, $planId, $runId),
                    $control
                );
                self::assertSame("unchanged\n", file_get_contents($external.'/sentinel'), $control);
                $replaced = match ($control) {
                    'binding_identity_after_read' => 'binding.json',
                    'history_identity_after_read' => 'history.jsonl',
                    default => 'projection.json'
                };
                self::assertFileExists($runPath.'/'.$replaced.'.held', $control);
                self::assertTrue(is_link($runPath.'/'.$replaced), $control);
                self::assertSame(
                    $history,
                    file_get_contents($runPath.'/history.jsonl'.($replaced === 'history.jsonl' ? '.held' : '')),
                    $control
                );
                self::assertSame(
                    $projection,
                    file_get_contents($runPath.'/projection.json'.($replaced === 'projection.json' ? '.held' : '')),
                    $control
                );
            }
        } finally {
            new Filesystem()->remove([$root, $external]);
        }
    }

    /**
     * Covers actual writer-lock replacement after open and before the lease is trusted.
     */
    public function test_that_in_window_writer_lock_replacement_cannot_split_run_mutation(): void
    {
        $root = sys_get_temp_dir().'/fight-common-writer-lock-race-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $creator = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame('planned', $creator->createPlannedRun($directory, $planId, $runId)['status']);
            $runPath = $directory->path.'/runs/'.$runId;
            $history = file_get_contents($runPath.'/history.jsonl');
            $projection = file_get_contents($runPath.'/projection.json');
            $replacing = new DeterministicReleaseBoundaryFake(
                runStateFailureOnce: 'writer_lock_identity_after_open'
            );

            self::assertSame(
                ['status' => 'indeterminate'],
                $replacing->publishPreparedRun($directory, $planId, $runId, 1, 'planned')
            );
            self::assertFileExists($runPath.'/.writer.lock.held');
            self::assertFileExists($runPath.'/.writer.lock');
            self::assertSame($history, file_get_contents($runPath.'/history.jsonl'));
            self::assertSame($projection, file_get_contents($runPath.'/projection.json'));
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers deterministic finalization and resume failures after complete history publication
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_finalization_projection_failures_preserve_recoverable_complete_history(): void
    {
        $root = sys_get_temp_dir().'/fight-common-finalize-failure-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('b', 64);
        $creator = new DeterministicReleaseBoundaryFake();
        try {
            self::assertSame('created', $creator->createPreparedRun($directory, $planId, $runId)['status']);
            [$manifestId, $handoffId] = $this->writePreparationArtifacts($directory, $planId, $runId);
            $finalizer = new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'prepared_projection');
            self::assertSame(
                ['status' => 'indeterminate'],
                $finalizer->finalizePreparedRun(
                    $directory,
                    $planId,
                    $runId,
                    $manifestId,
                    $handoffId,
                    2,
                    'prepared'
                )
            );
            self::assertCount(3, file(
                $directory->path.'/runs/'.$runId.'/history.jsonl',
                FILE_IGNORE_NEW_LINES
            ));
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'prepared_projection')
                    ->resumePreparedRun($directory, $planId, $runId)
            );
            self::assertSame(
                'verified',
                new DeterministicReleaseBoundaryFake()->resumePreparedRun($directory, $planId, $runId)['status']
            );

            $projectionPath = $directory->path.'/runs/'.$runId.'/projection.json';
            file_put_contents(
                $projectionPath,
                str_replace('"sequence":3', '"sequence":4', (string) file_get_contents($projectionPath))
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake()->resumePreparedRun($directory, $planId, $runId)
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers artifact unlink and substitution inside the finalization verification window.
     */
    public function test_that_finalization_rejects_in_window_artifact_identity_replacement(): void
    {
        $root = sys_get_temp_dir().'/fight-common-finalize-artifact-race-'.bin2hex(random_bytes(8));
        mkdir($root);
        $planId = str_repeat('a', 64);

        try {
            foreach (
                [
                'manifest_unlink_after_read',
                'handoff_substitute_after_read',
                'manifest_unlink_before_state_publish',
                'handoff_substitute_before_state_publish',
                'manifest_unlink_after_state_publish',
                'handoff_substitute_after_state_publish'
                ] as $fault
            ) {
                $directory = new CanonicalRunsDirectory($root.'/'.$fault, $root);
                mkdir($directory->path);
                $planId = $this->writeReleasePlan($directory);
                $runId = hash('sha256', $fault);
                $creator = new DeterministicReleaseBoundaryFake();
                self::assertSame('created', $creator->createPreparedRun($directory, $planId, $runId)['status']);
                [$manifestId, $handoffId] = $this->writePreparationArtifacts($directory, $planId, $runId);
                $result = new DeterministicReleaseBoundaryFake(runStateFailureOnce: $fault)->finalizePreparedRun(
                    $directory,
                    $planId,
                    $runId,
                    $manifestId,
                    $handoffId,
                    2,
                    'prepared'
                );
                self::assertSame(['status' => 'indeterminate'], $result, $fault);
                if (str_ends_with($fault, '_after_state_publish')) {
                    self::assertCount(
                        4,
                        file(
                            $directory->path.'/runs/'.$runId.'/history.jsonl',
                            FILE_IGNORE_NEW_LINES
                        ),
                        $fault.' must append compensation instead of truncating history'
                    );
                }

                self::assertSame(
                    str_ends_with($fault, '_after_state_publish') ? 4 : 2,
                    $creator->resumePreparedRun($directory, $planId, $runId)['sequence'],
                    $fault
                );
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers proof loss after absent-run stop history publication without exposing a named run.
     */
    public function test_that_absent_run_stop_proof_loss_quarantines_the_unpublished_named_run(): void
    {
        $root = sys_get_temp_dir().'/fight-common-absent-stop-proof-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('b', 64);
        [$manifestId, $handoffId] = $this->writeStopArtifacts(
            $directory,
            $planId,
            $runId,
            'stale_plan',
            'release.prepare.baseline_resolution_drift',
            'create_current_release_plan'
        );

        try {
            $result = new DeterministicReleaseBoundaryFake(
                runStateFailureOnce: 'handoff_substitute_after_state_publish'
            )->publishPreparationStop(
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

            self::assertSame(['status' => 'indeterminate'], $result);
            self::assertDirectoryDoesNotExist($directory->path.'/runs/'.$runId);
            self::assertSame(
                ['status' => 'missing'],
                new DeterministicReleaseBoundaryFake()->resumePreparedRun($directory, $planId, $runId)
            );

            $syncRunId = str_repeat('c', 64);
            [$syncManifestId, $syncHandoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $syncRunId,
                'stale_plan',
                'release.prepare.baseline_resolution_drift',
                'create_current_release_plan'
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(
                    runStateFailureOnce: 'stop_reveal_directory_sync'
                )->publishPreparationStop(
                    $directory,
                    $planId,
                    $syncRunId,
                    'baseline_drift',
                    'stale_plan',
                    'release.prepare.baseline_resolution_drift',
                    'create_current_release_plan',
                    $syncManifestId,
                    $syncHandoffId,
                    null,
                    null
                )
            );
            self::assertDirectoryDoesNotExist($directory->path.'/runs/'.$syncRunId);
            self::assertSame(
                ['status' => 'missing'],
                new DeterministicReleaseBoundaryFake()->resumePreparedRun($directory, $planId, $syncRunId)
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers a normal create publishing the canonical run name during an absent-stop reveal.
     */
    public function test_that_absent_stop_reveal_never_replaces_a_concurrent_create_directory(): void
    {
        $root = sys_get_temp_dir().'/fight-common-absent-stop-contention-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('d', 64);
        [$manifestId, $handoffId] = $this->writeStopArtifacts(
            $directory,
            $planId,
            $runId,
            'stale_plan',
            'release.prepare.baseline_resolution_drift',
            'create_current_release_plan'
        );

        try {
            $result = new DeterministicReleaseBoundaryFake(
                runStateFailureOnce: 'stop_reveal_create_contention'
            )->publishPreparationStop(
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

            $runPath = $directory->path.'/runs/'.$runId;
            self::assertSame(['status' => 'conflict'], $result);
            self::assertDirectoryExists($runPath);
            self::assertSame(['.', '..'], scandir($runPath));
            self::assertFileDoesNotExist($runPath.'/binding.json');
            self::assertSame([], glob($directory->path.'/runs/.pending-*'));
            self::assertCount(1, glob($directory->path.'/runs/.rejected-*'));
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers append-only compensation when stop or recovery artifact proof is lost after publication.
     */
    public function test_that_stop_and_recovery_proof_loss_never_shrink_history(): void
    {
        $root = sys_get_temp_dir().'/fight-common-stop-compensation-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);

        try {
            $stopRunId = hash('sha256', 'stop-proof-loss');
            $creator = new DeterministicReleaseBoundaryFake();
            self::assertSame('created', $creator->createPreparedRun($directory, $planId, $stopRunId)['status']);
            [$stopManifestId, $stopHandoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $stopRunId,
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run'
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(
                    runStateFailureOnce: 'handoff_substitute_after_state_publish'
                )->publishPreparationStop(
                    $directory,
                    $planId,
                    $stopRunId,
                    'artifact_indeterminate',
                    'evidence_indeterminate',
                    'release.prepare.artifacts_indeterminate',
                    'reconcile_named_release_run',
                    $stopManifestId,
                    $stopHandoffId,
                    2,
                    'prepared'
                )
            );
            self::assertCount(4, file(
                $directory->path.'/runs/'.$stopRunId.'/history.jsonl',
                FILE_IGNORE_NEW_LINES
            ));
            self::assertSame(4, $creator->resumePreparedRun($directory, $planId, $stopRunId)['sequence']);

            $recoverRunId = hash('sha256', 'recover-proof-loss');
            self::assertSame('created', $creator->createPreparedRun($directory, $planId, $recoverRunId)['status']);
            [$boundManifestId, $boundHandoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $recoverRunId,
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run'
            );
            self::assertSame('created', $creator->publishPreparationStop(
                $directory,
                $planId,
                $recoverRunId,
                'artifact_indeterminate',
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run',
                $boundManifestId,
                $boundHandoffId,
                2,
                'prepared'
            )['status']);
            [$repairManifestId, $repairHandoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $recoverRunId,
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run',
                'evidence_only'
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(
                    runStateFailureOnce: 'handoff_substitute_after_state_publish'
                )->recoverPreparationStop(
                    $directory,
                    $planId,
                    $recoverRunId,
                    3,
                    'artifact_indeterminate',
                    'evidence_indeterminate',
                    'release.prepare.artifacts_indeterminate',
                    'reconcile_named_release_run',
                    $repairManifestId,
                    $repairHandoffId
                )
            );
            self::assertCount(5, file(
                $directory->path.'/runs/'.$recoverRunId.'/history.jsonl',
                FILE_IGNORE_NEW_LINES
            ));
            $resumed = $creator->resumePreparedRun($directory, $planId, $recoverRunId);
            self::assertSame('stopped', $resumed['status']);
            self::assertSame(5, $resumed['sequence']);

            $repairHandoffPath = $directory->path.'/'.$repairHandoffId.'.phase-handoff.json';
            self::assertFileExists($repairHandoffPath.'.held');
            unlink($repairHandoffPath);
            rename($repairHandoffPath.'.held', $repairHandoffPath);
            $recoveredAgain = $creator->recoverPreparationStop(
                $directory,
                $planId,
                $recoverRunId,
                5,
                'artifact_indeterminate',
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run',
                $repairManifestId,
                $repairHandoffId
            );
            self::assertSame('created', $recoveredAgain['status']);
            self::assertSame(6, $recoveredAgain['sequence']);
            self::assertSame(
                'evidence_pending',
                $creator->resumePreparedRun($directory, $planId, $recoverRunId)['status']
            );

            $verifiedRunId = hash('sha256', 'verified-stop-proof-loss');
            self::assertSame('created', $creator->createPreparedRun($directory, $planId, $verifiedRunId)['status']);
            [$verifiedManifestId, $verifiedHandoffId] = $this->writePreparationArtifacts(
                $directory,
                $planId,
                $verifiedRunId
            );
            self::assertSame('created', $creator->finalizePreparedRun(
                $directory,
                $planId,
                $verifiedRunId,
                $verifiedManifestId,
                $verifiedHandoffId,
                2,
                'prepared'
            )['status']);
            [$verifiedStopManifestId, $verifiedStopHandoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $verifiedRunId,
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run'
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(
                    runStateFailureOnce: 'handoff_substitute_after_state_publish'
                )->publishPreparationStop(
                    $directory,
                    $planId,
                    $verifiedRunId,
                    'artifact_indeterminate',
                    'evidence_indeterminate',
                    'release.prepare.artifacts_indeterminate',
                    'reconcile_named_release_run',
                    $verifiedStopManifestId,
                    $verifiedStopHandoffId,
                    3,
                    'prepared'
                )
            );
            self::assertCount(5, file(
                $directory->path.'/runs/'.$verifiedRunId.'/history.jsonl',
                FILE_IGNORE_NEW_LINES
            ));
            $verified = $creator->resumePreparedRun($directory, $planId, $verifiedRunId);
            self::assertSame('verified', $verified['status']);
            self::assertSame(5, $verified['sequence']);
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers exact stopped-artifact proof and closed stop vocabulary during resume.
     */
    public function test_that_stopped_resume_rejects_missing_artifacts_and_unknown_contracts(): void
    {
        $root = sys_get_temp_dir().'/fight-common-stopped-proof-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $store = new DeterministicReleaseBoundaryFake();

        try {
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
            self::assertSame('created', $store->publishPreparationStop(
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
            )['status']);
            $historyPath = $directory->path.'/runs/'.$runId.'/history.jsonl';
            $history = file_get_contents($historyPath);
            unlink($directory->artifactPath($manifestId.'.evidence-manifest.json'));
            self::assertSame(['status' => 'indeterminate'], $store->resumePreparedRun($directory, $planId, $runId));
            self::assertSame($history, file_get_contents($historyPath));

            $unknownRunId = str_repeat('c', 64);
            self::assertSame('planned', $store->createPlannedRun($directory, $planId, $unknownRunId)['status']);
            [$unknownManifestId, $unknownHandoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $unknownRunId,
                'evidence_indeterminate',
                'release.prepare.unknown',
                'reconcile_named_release_run'
            );
            self::assertSame(['status' => 'indeterminate'], $store->publishPreparationStop(
                $directory,
                $planId,
                $unknownRunId,
                'unknown_stop',
                'evidence_indeterminate',
                'release.prepare.unknown',
                'reconcile_named_release_run',
                $unknownManifestId,
                $unknownHandoffId,
                1,
                'planned'
            ));
            self::assertCount(1, file(
                $directory->path.'/runs/'.$unknownRunId.'/history.jsonl',
                FILE_IGNORE_NEW_LINES
            ));

            [$validManifestId, $validHandoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $unknownRunId,
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run'
            );
            self::assertSame('created', $store->publishPreparationStop(
                $directory,
                $planId,
                $unknownRunId,
                'artifact_indeterminate',
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run',
                $validManifestId,
                $validHandoffId,
                1,
                'planned'
            )['status']);
            $unknownRunPath = $directory->path.'/runs/'.$unknownRunId;
            file_put_contents(
                $unknownRunPath.'/history.jsonl',
                str_replace(
                    '"stop_code":"artifact_indeterminate"',
                    '"stop_code":"unknown_stop"',
                    (string) file_get_contents($unknownRunPath.'/history.jsonl')
                )
            );
            file_put_contents(
                $unknownRunPath.'/projection.json',
                str_replace(
                    '"stop_code":"artifact_indeterminate"',
                    '"stop_code":"unknown_stop"',
                    (string) file_get_contents($unknownRunPath.'/projection.json')
                )
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                $store->resumePreparedRun($directory, $planId, $unknownRunId)
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers rejection of canonical-looking stopped state whose earlier transition chain was rewritten.
     */
    public function test_that_stopped_resume_and_append_reject_tampering_in_any_prior_transition(): void
    {
        $root = sys_get_temp_dir().'/fight-common-stopped-chain-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = $this->writeReleasePlan($directory);
        $runId = str_repeat('b', 64);
        $store = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame('created', $store->createPreparedRun($directory, $planId, $runId)['status']);
            [$manifestId, $handoffId] = $this->writeStopArtifacts(
                $directory,
                $planId,
                $runId,
                'evidence_indeterminate',
                'release.prepare.artifacts_indeterminate',
                'reconcile_named_release_run'
            );
            self::assertSame(
                'created',
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
            $runPath = $directory->path.'/runs/'.$runId;
            $history = (string) file_get_contents($runPath.'/history.jsonl');
            foreach (
                [
                    'first operation'  => str_replace(
                        '"operation":"create_release_run"',
                        '"operation":"prepare_release_run"',
                        $history
                    ),
                    'first sequence'   => preg_replace('/"sequence":1/', '"sequence":9', $history, 1),
                    'second operation' => str_replace(
                        '"operation":"prepare_release_run"',
                        '"operation":"create_release_run"',
                        $history
                    )
                ] as $case => $tampered
            ) {
                self::assertIsString($tampered);
                file_put_contents($runPath.'/history.jsonl', $tampered);
                self::assertSame(
                    ['status' => 'indeterminate'],
                    $store->resumePreparedRun($directory, $planId, $runId),
                    $case
                );
            }

            file_put_contents(
                $runPath.'/history.jsonl',
                str_replace('"operation":"prepare_release_run"', '"operation":"create_release_run"', $history)
            );
            self::assertSame(
                ['status' => 'indeterminate'],
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
                )
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers replacement after the last link check and before descriptor-relative state publication.
     */
    public function test_that_syscall_window_replacement_stays_confined_to_the_retained_run_descriptor(): void
    {
        $root = sys_get_temp_dir().'/fight-common-publish-window-'.bin2hex(random_bytes(8));
        $external = sys_get_temp_dir().'/fight-common-publish-window-external-'.bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($external);
        file_put_contents($external.'/sentinel', 'unchanged');
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $creator = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame('planned', $creator->createPlannedRun($directory, $planId, $runId)['status']);
            $store = new DeterministicReleaseBoundaryFake(
                runStateFailureOnce: 'replace_run_after_link_before_state_publish',
                runStateReplacementTarget: $external
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                $store->publishPreparedRun($directory, $planId, $runId, 1, 'planned')
            );
            self::assertSame('unchanged', file_get_contents($external.'/sentinel'));
            self::assertFileDoesNotExist($external.'/history.jsonl');
            self::assertFileDoesNotExist($external.'/projection.json');
        } finally {
            new Filesystem()->remove([$root, $external]);
        }
    }

    /**
     * Covers deterministic fail-closed native publication and parent-sync recovery branches
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_creation_recovery_classifies_native_publication_failures(): void
    {
        $root = sys_get_temp_dir().'/fight-common-create-recovery-'.bin2hex(random_bytes(8));
        mkdir($root);
        $planId = str_repeat('a', 64);

        try {
            $publishDirectory = new CanonicalRunsDirectory($root.'/publish', $root);
            mkdir($publishDirectory->path);
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'state_publish')
                    ->createPlannedRun($publishDirectory, $planId, str_repeat('1', 64))
            );

            foreach (['resume_parent_directory_sync', 'projection_stage'] as $failurePoint) {
                $directory = new CanonicalRunsDirectory($root.'/'.$failurePoint, $root);
                mkdir($directory->path);
                $runId = hash('sha256', $failurePoint);
                self::assertSame(
                    ['status' => 'indeterminate'],
                    new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'runs_parent_directory_sync')
                        ->createPlannedRun($directory, $planId, $runId)
                );
                self::assertSame(
                    ['status' => 'indeterminate'],
                    new DeterministicReleaseBoundaryFake(runStateFailureOnce: $failurePoint)
                        ->resumePreparedRun($directory, $planId, $runId)
                );

                self::assertSame(
                    ['status' => 'indeterminate'],
                    new DeterministicReleaseBoundaryFake()->resumePreparedRun($directory, $planId, $runId)
                );
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers the remaining fail-closed publication and planned-state recovery branches
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_publication_and_planned_recovery_ambiguities_fail_closed(): void
    {
        $root = sys_get_temp_dir().'/fight-common-publication-recovery-'.bin2hex(random_bytes(8));
        mkdir($root);
        $planId = str_repeat('a', 64);

        try {
            $missingDirectory = new CanonicalRunsDirectory($root.'/missing', $root);
            mkdir($missingDirectory->path);
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake()->publishPreparedRun(
                    $missingDirectory,
                    $planId,
                    str_repeat('0', 64),
                    1,
                    'planned'
                )
            );

            $finalizeDirectory = new CanonicalRunsDirectory($root.'/finalize', $root);
            mkdir($finalizeDirectory->path);
            $finalizeRunId = str_repeat('1', 64);
            $finalizeStore = new DeterministicReleaseBoundaryFake();
            $finalizeState = $finalizeStore->createPreparedRun($finalizeDirectory, $planId, $finalizeRunId);
            self::assertSame('created', $finalizeState['status']);
            file_put_contents($finalizeState['projection_path'], "tampered\n");
            self::assertSame(
                ['status' => 'indeterminate'],
                $finalizeStore->finalizePreparedRun(
                    $finalizeDirectory,
                    $planId,
                    $finalizeRunId,
                    str_repeat('2', 64),
                    str_repeat('3', 64),
                    2,
                    'prepared'
                )
            );
            self::assertCount(2, file($finalizeState['history_path'], FILE_IGNORE_NEW_LINES));

            $syncDirectory = new CanonicalRunsDirectory($root.'/sync', $root);
            mkdir($syncDirectory->path);
            $syncRunId = str_repeat('4', 64);
            $syncState = new DeterministicReleaseBoundaryFake()->createPlannedRun(
                $syncDirectory,
                $planId,
                $syncRunId
            );
            self::assertSame('planned', $syncState['status']);
            $historyBefore = file_get_contents($syncState['history_path']);
            $projectionBefore = file_get_contents($syncState['projection_path']);
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'resume_parent_directory_sync')
                    ->resumePreparedRun($syncDirectory, $planId, $syncRunId)
            );
            self::assertSame($historyBefore, file_get_contents($syncState['history_path']));
            self::assertSame($projectionBefore, file_get_contents($syncState['projection_path']));

            $projectionDirectory = new CanonicalRunsDirectory($root.'/projection', $root);
            mkdir($projectionDirectory->path);
            $projectionRunId = str_repeat('5', 64);
            $projectionState = new DeterministicReleaseBoundaryFake()->createPlannedRun(
                $projectionDirectory,
                $planId,
                $projectionRunId
            );
            self::assertSame('planned', $projectionState['status']);
            unlink($projectionState['projection_path']);
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'projection_stage')
                    ->resumePreparedRun($projectionDirectory, $planId, $projectionRunId)
            );
            self::assertFileDoesNotExist($projectionState['projection_path']);
            self::assertSame(
                'planned',
                new DeterministicReleaseBoundaryFake()
                    ->resumePreparedRun($projectionDirectory, $planId, $projectionRunId)['status']
            );
            self::assertFileExists($projectionState['projection_path']);

            $emptyDirectory = new CanonicalRunsDirectory($root.'/empty', $root);
            mkdir($emptyDirectory->path);
            $emptyRunId = str_repeat('6', 64);
            $emptyState = new DeterministicReleaseBoundaryFake()->createPlannedRun(
                $emptyDirectory,
                $planId,
                $emptyRunId
            );
            self::assertSame('planned', $emptyState['status']);
            unlink($emptyState['history_path']);
            unlink($emptyState['projection_path']);
            self::assertSame(
                ['status' => 'indeterminate'],
                new DeterministicReleaseBoundaryFake()
                    ->resumePreparedRun($emptyDirectory, $planId, $emptyRunId)
            );
            self::assertFileDoesNotExist($emptyState['history_path']);
            self::assertFileDoesNotExist($emptyState['projection_path']);
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Creates one isolated deterministic run-state store
     */
    protected function createRunStateStore(
        bool $interruptAfterAppend = false,
        bool $shortAppend = false,
        bool $failPreparedProjectionDirectorySync = false,
        bool $interruptFinalizedProjection = false,
        ?string $directorySyncFailure = null
    ): RunStateStore&ReleaseEffectLedger {
        $store = new DeterministicReleaseBoundaryFake(
            interruptRunProjectionOnce: $interruptAfterAppend,
            runStateFailureOnce: match (true) {
                $shortAppend => 'append_short',
                $failPreparedProjectionDirectorySync => 'prepared_projection_directory_sync',
                default => $directorySyncFailure
            }
        );

        if ($interruptFinalizedProjection) {
            $store->interruptFinalizedRunProjectionOnce();
        }

        return $store;
    }

    /**
     * Invokes a competing mutation while the filesystem writer lease is held
     *
     * @param CanonicalRunsDirectory $directory Canonical run root.
     * @param string $runId Run identity whose lease is held.
     * @param callable(): TResult $attempt
     *
     * @template TResult
     *
     * @return TResult
     */
    protected function whileRunWriterOwnsLease(
        CanonicalRunsDirectory $directory,
        string $runId,
        callable $attempt
    ): mixed {
        $lock = fopen($directory->path.'/runs/'.$runId.'/.writer.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        try {
            return $attempt();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
