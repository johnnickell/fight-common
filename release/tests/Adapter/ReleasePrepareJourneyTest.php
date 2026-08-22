<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Application\CanonicalJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class ReleasePrepareJourneyTest
 *
 * Covers creation of one distinct prepared release run.
 */
#[CoversNothing]
class ReleasePrepareJourneyTest extends UnitTestCase
{
    /**
     * Covers live baseline and artifact authority revalidation without fake outcome controls.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_normal_prepare_revalidates_live_repository_and_authority_artifacts(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-release-live-'.bin2hex(random_bytes(8));

        try {
            $context = $this->createLiveReleaseContext($runtimeRoot);
            $prepared = $this->livePrepare($context);

            self::assertSame('prepared', $prepared['status']);
            self::assertSame('release.prepare.completed', $prepared['findings'][0]['id']);
            self::assertContains([
                'capability'   => 'git',
                'effect_class' => 'git.resolve_ref',
                'outcome'      => 'success'
            ], $prepared['performed_effects']);

            $this->git($runtimeRoot, [
                '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
                'tag', '-a', '1.2.3', '-m', 'duplicate normalized baseline', $context['tag_oid'].'^{commit}'
            ]);
            $duplicate = $this->livePrepare($context);

            self::assertSame(4, $duplicate['exit_code']);
            self::assertSame('policy_blocked', $duplicate['status']);
            self::assertSame(
                'release.prepare.baseline_tag_duplicate_normalized',
                $duplicate['findings'][0]['id']
            );
            self::assertSame(['action' => 'repair_baseline_authority'], $duplicate['next_action']);
            $this->git($runtimeRoot, ['tag', '-d', '1.2.3']);
            self::assertContains([
                'capability'   => 'authorization',
                'effect_class' => 'authorization.check',
                'outcome'      => 'success'
            ], $prepared['performed_effects']);

            $this->git($runtimeRoot, ['update-ref', 'refs/tags/v1.2.3', $context['source_oid']]);
            $ambiguous = $this->livePrepare($context);

            self::assertSame(4, $ambiguous['exit_code']);
            self::assertSame('policy_blocked', $ambiguous['status']);
            self::assertSame('release.prepare.baseline_tag_ambiguous', $ambiguous['findings'][0]['id']);
            self::assertSame(['action' => 'repair_baseline_authority'], $ambiguous['next_action']);

            $this->git($runtimeRoot, ['update-ref', 'refs/tags/v1.2.3', $context['tag_oid']]);
            $authority = $context['authority'];
            $authority['support_policy_identity'] = 'support-policy-2026-09';
            $this->writeAuthority($context['authority_path'], $authority);
            $policy = $this->livePrepare($context);

            self::assertSame(6, $policy['exit_code']);
            self::assertSame('stale_plan', $policy['status']);
            self::assertSame('release.prepare.support_policy_drift', $policy['findings'][0]['id']);
            self::assertSame(['action' => 'create_current_release_plan'], $policy['next_action']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers append-only recovery of one repaired live baseline stop on the same named run.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_repaired_live_baseline_stop_resumes_the_same_run_append_only(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-release-live-repair-'.bin2hex(random_bytes(8));

        try {
            $context = $this->createLiveReleaseContext($runtimeRoot);
            $this->git($runtimeRoot, ['tag', '-d', 'v1.2.3']);
            $stopped = $this->livePrepare($context);

            self::assertSame('release.prepare.baseline_tag_missing', $stopped['findings'][0]['id']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $stopped['run_id']);
            $runPath = $runtimeRoot.'/.runs/release/runs/'.$stopped['run_id'];
            $stoppedHistory = (string) file_get_contents($runPath.'/history.jsonl');
            self::assertCount(2, $this->decodeLines($runPath.'/history.jsonl'));
            $stopManifest = json_decode(
                (string) file_get_contents($stopped['artifacts']['evidence_manifest']['path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('projection_bound', $stopManifest['activation']['mode']);
            self::assertTrue($stopManifest['activation']['projection_must_bind_artifact_ids']);

            $this->git($runtimeRoot, ['update-ref', 'refs/tags/v1.2.3', $context['source_oid']]);
            $sameActionStop = $this->liveResume($context, $stopped['run_id']);

            self::assertSame('release.prepare.baseline_tag_missing', $sameActionStop['findings'][0]['id']);
            self::assertSame($stoppedHistory, (string) file_get_contents($runPath.'/history.jsonl'));

            $this->git($runtimeRoot, ['update-ref', 'refs/tags/v1.2.3', $context['tag_oid']]);
            $authority = $context['authority'];
            $authority['support_policy_identity'] = 'support-policy-2026-09';
            $this->writeAuthority($context['authority_path'], $authority);
            $nextStop = $this->liveResume($context, $stopped['run_id']);

            self::assertSame('release.prepare.support_policy_drift', $nextStop['findings'][0]['id']);
            $history = $this->decodeLines($runPath.'/history.jsonl');
            self::assertCount(4, $history);
            self::assertSame('recover_release_preparation_stop', $history[2]['operation']);
            self::assertSame('release.prepare.support_policy_drift', $history[3]['finding_id']);

            $this->writeAuthority($context['authority_path'], $context['authority']);
            $resumed = $this->liveResume($context, $stopped['run_id']);

            self::assertSame(0, $resumed['exit_code']);
            self::assertSame($stopped['run_id'], $resumed['run_id']);
            self::assertSame('release.prepare.resumed_completed', $resumed['findings'][0]['id']);
            $history = $this->decodeLines($runPath.'/history.jsonl');
            self::assertCount(7, $history);
            self::assertSame($stoppedHistory, implode("\n", array_map(
                static fn (array $event): string => new CanonicalJson()->encode($event),
                array_slice($history, 0, 2)
            ))."\n");
            self::assertSame('recover_release_preparation_stop', $history[2]['operation']);
            self::assertSame(2, $history[2]['recovered_stop_sequence']);
            self::assertSame('planned', $history[2]['state']);
            self::assertSame('stop_release_preparation', $history[3]['operation']);
            self::assertSame('recover_release_preparation_stop', $history[4]['operation']);
            self::assertSame('prepare_release_run', $history[5]['operation']);
            self::assertSame('finalize_release_preparation_evidence', $history[6]['operation']);
            self::assertSame(7, json_decode(
                (string) file_get_contents($runPath.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            )['sequence']);
            $projection = json_decode(
                (string) file_get_contents($runPath.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $manifest = json_decode(
                (string) file_get_contents($resumed['artifacts']['evidence_manifest']['path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('projection_bound', $manifest['activation']['mode']);
            self::assertSame(
                $resumed['artifacts']['evidence_manifest']['manifest_id'],
                $projection['prerequisite_evidence_manifest_id']
            );
            self::assertSame(
                $resumed['artifacts']['phase_handoff']['handoff_id'],
                $projection['prerequisite_phase_handoff_id']
            );
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers independently classified current approval, evidence, and compatibility authority drift.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_normal_prepare_classifies_each_live_authority_artifact_change(): void
    {
        foreach (['approval', 'evidence', 'compatibility', 'patch_authority'] as $change) {
            $runtimeRoot = sys_get_temp_dir().'/fight-common-release-live-'.$change.'-'.bin2hex(random_bytes(8));

            try {
                $context = $this->createLiveReleaseContext($runtimeRoot);
                $authority = $context['authority'];

                if ($change === 'approval') {
                    $authority['required_approvals'] = ['release-approval-002'];
                    $authority['release_approval_authority']['approval_id'] = 'release-approval-002';
                } elseif ($change === 'evidence') {
                    $authority['evidence_manifest_digest'] = str_repeat('b', 64);
                    $authority['release_approval_authority']['evidence_manifest_digest'] = str_repeat('b', 64);
                } elseif ($change === 'compatibility') {
                    $authority['compatibility_exceptions'] = ['legacy-client-v1'];
                    $authority['release_approval_authority']['compatibility_exception_ids'] = ['legacy-client-v1'];
                } else {
                    $authority['patch_exception_authorities'] = [['authority_id' => 'incomplete']];
                }

                $this->writeAuthority($context['authority_path'], $authority);
                $result = $this->livePrepare($context);
                $expected = match ($change) {
                    'approval' => ['authority_required', 'release.prepare.approval_authority_drift', 3],
                    'evidence' => ['stale_plan', 'release.prepare.evidence_authority_drift', 6],
                    'compatibility' => ['stale_plan', 'release.prepare.compatibility_authority_drift', 6],
                    default => ['evidence_indeterminate', 'release.prepare.plan_authority_uncertain', 5]
                };

                self::assertSame($expected[2], $result['exit_code'], $change);
                self::assertSame($expected[0], $result['status'], $change);
                self::assertSame($expected[1], $result['findings'][0]['id'], $change);
                self::assertArrayNotHasKey('run_state', $result, $change);
            } finally {
                if (is_dir($runtimeRoot)) {
                    $this->removeDirectory($runtimeRoot);
                }
            }
        }
    }

    /**
     * Covers a real authority artifact read failure as a provider failure
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_normal_prepare_preserves_a_live_authority_provider_failure(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-release-live-failed-'.bin2hex(random_bytes(8));

        try {
            $context = $this->createLiveReleaseContext($runtimeRoot);
            $authorityDirectory = dirname($context['authority_path']).'/authority-directory';
            mkdir($authorityDirectory, 0700);
            $context['authority_path'] = $authorityDirectory;
            $result = $this->livePrepare($context);

            self::assertSame(4, $result['exit_code']);
            self::assertSame('policy_blocked', $result['status']);
            self::assertSame('release.prepare.plan_authority_failed', $result['findings'][0]['id']);
            self::assertSame(['action' => 'repair_release_authority_provider'], $result['next_action']);
            self::assertContains([
                'capability'   => 'authorization',
                'effect_class' => 'authorization.check',
                'outcome'      => 'failure'
            ], $result['performed_effects']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers named resume after every claimed prepared postcondition is reverified.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_prepare_resume_retains_the_named_run_after_revalidating_current_truth(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-resume-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $prepared = $this->prepare($root, $planResult['artifact']['path']);
            $resume = ReleaseProcess::create([
                $root.'/bin/release',
                'prepare',
                '--plan='.$planResult['artifact']['path'],
                '--resume='.$prepared['run_id'],
                '--fixture='.$root.'/release/fixtures/prepare-deterministic.json'
            ]);

            $resume->mustRun();
            $result = json_decode($resume->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame($prepared['run_id'], $result['run_id']);
            self::assertSame($prepared['plan_id'], $result['plan_id']);
            self::assertSame('prepared', $result['status']);
            self::assertSame('success', $result['exit_class']);
            self::assertSame('release.prepare.already_satisfied', $result['findings'][0]['id']);
            self::assertSame([
                'immutable_plan_revalidated',
                'run_event_chain_revalidated',
                'prepared_run_projection_revalidated',
                'prepared_postconditions_reverified'
            ], $result['verified_postconditions']);
            self::assertSame(['action' => 'package_release_run'], $result['next_action']);
            self::assertSame($prepared['run_state'], $result['run_state']);
            $this->assertContentAddressedArtifacts($result['artifacts']);

            $manifestPath = $prepared['artifacts']['evidence_manifest']['path'];
            $manifestBytes = (string) file_get_contents($manifestPath);
            chmod($manifestPath, 0600);
            file_put_contents($manifestPath, str_replace($prepared['plan_id'], str_repeat('e', 64), $manifestBytes));
            $contradictory = $this->resume($root, $planResult['artifact']['path'], $prepared['run_id']);

            self::assertSame('evidence_indeterminate', $contradictory['status']);
            self::assertSame('release.prepare.resume_state_indeterminate', $contradictory['findings'][0]['id']);
            self::assertSame(['action' => 'reconcile_named_release_run'], $contradictory['next_action']);
            self::assertNotSame('release.prepare.already_satisfied', $contradictory['findings'][0]['id']);
            $this->assertContentAddressedArtifacts($contradictory['artifacts']);
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers governed stops for missing, drifted, contradictory, and contended named state.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_prepare_resume_classifies_each_failure_to_revalidate_current_truth(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-resume-stops-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $prepared = $this->prepare($root, $planResult['artifact']['path']);
            $projectionPath = $prepared['run_state']['projection_path'];
            $validProjection = file_get_contents($projectionPath);

            file_put_contents($projectionPath, "not-json\n");
            $indeterminate = $this->resume($root, $planResult['artifact']['path'], $prepared['run_id']);
            self::assertSame('evidence_indeterminate', $indeterminate['status']);
            self::assertSame('uncertain', $indeterminate['exit_class']);
            self::assertSame(5, $indeterminate['exit_code']);
            self::assertSame('release.prepare.resume_state_indeterminate', $indeterminate['findings'][0]['id']);
            self::assertSame(['action' => 'reconcile_named_release_run'], $indeterminate['next_action']);
            self::assertCount(1, $indeterminate['next_action']);
            $this->assertContentAddressedArtifacts($indeterminate['artifacts']);

            file_put_contents($projectionPath, $validProjection);
            $missing = $this->resume($root, $planResult['artifact']['path'], str_repeat('d', 64));
            self::assertSame('evidence_indeterminate', $missing['status']);
            self::assertSame('release.prepare.resume_state_missing', $missing['findings'][0]['id']);
            self::assertSame(['action' => 'restore_named_release_run_evidence'], $missing['next_action']);
            self::assertCount(1, $missing['next_action']);
            $this->assertContentAddressedArtifacts($missing['artifacts']);
            self::assertSame(
                [
                    'mode'                              => 'evidence_only',
                    'projection_must_bind_artifact_ids' => false
                ],
                json_decode(
                    (string) file_get_contents($missing['artifacts']['evidence_manifest']['path']),
                    true,
                    flags: JSON_THROW_ON_ERROR
                )['activation']
            );
            self::assertDirectoryDoesNotExist($output.'/runs/'.str_repeat('d', 64));
            self::assertNotContains('git.resolve_ref', array_column($missing['performed_effects'], 'effect_class'));
            self::assertNotContains('authorization.check', array_column($missing['performed_effects'], 'effect_class'));

            $historyPath = $prepared['run_state']['history_path'];
            $validHistory = file_get_contents($historyPath);
            $driftedPlanId = str_repeat('c', 64);
            file_put_contents($historyPath, str_replace($prepared['plan_id'], $driftedPlanId, $validHistory));
            file_put_contents($projectionPath, str_replace($prepared['plan_id'], $driftedPlanId, $validProjection));
            $drifted = $this->resume($root, $planResult['artifact']['path'], $prepared['run_id']);
            self::assertSame('stale_plan', $drifted['status']);
            self::assertSame('drifted', $drifted['exit_class']);
            self::assertSame(6, $drifted['exit_code']);
            self::assertSame('release.prepare.resume_plan_drift', $drifted['findings'][0]['id']);
            self::assertSame(['action' => 'create_current_release_plan'], $drifted['next_action']);
            self::assertCount(1, $drifted['next_action']);
            $this->assertContentAddressedArtifacts($drifted['artifacts']);
            file_put_contents($historyPath, $validHistory);
            file_put_contents($projectionPath, $validProjection);

            $lock = fopen(dirname($projectionPath).'/.writer.lock', 'c');
            self::assertIsResource($lock);
            self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

            try {
                $conflict = $this->resume($root, $planResult['artifact']['path'], $prepared['run_id']);
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

            self::assertSame('conflict', $conflict['status']);
            self::assertSame('refused', $conflict['exit_class']);
            self::assertSame(23, $conflict['exit_code']);
            self::assertSame('release.prepare.resume_contention', $conflict['findings'][0]['id']);
            self::assertSame(['action' => 'retry_named_resume_after_writer_completes'], $conflict['next_action']);
            self::assertCount(1, $conflict['next_action']);
            $this->assertContentAddressedArtifacts($conflict['artifacts']);
            self::assertSame(
                'evidence_only',
                json_decode(
                    (string) file_get_contents($conflict['artifacts']['evidence_manifest']['path']),
                    true,
                    flags: JSON_THROW_ON_ERROR
                )['activation']['mode']
            );
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers a fresh prepared execution attempt at the public process seam.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_prepare_creates_a_distinct_prepared_run_for_each_execution_attempt(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-prepare-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            $first = $this->prepare($root, $planResult['artifact']['path']);
            $second = $this->prepare($root, $planResult['artifact']['path']);

            self::assertSame('fight-common.release-result/v1', $first['schema_version']);
            self::assertSame('prepare', $first['command']);
            self::assertSame('release_preparation', $first['capability']);
            self::assertSame('prepared', $first['status']);
            self::assertSame('success', $first['exit_class']);
            self::assertSame(0, $first['exit_code']);
            self::assertSame($planResult['plan_id'], $first['plan_id']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $first['run_id']);
            self::assertNotSame($first['run_id'], $second['run_id']);
            self::assertSame(
                ['immutable_plan_revalidated', 'prepared_run_projection_published'],
                $first['verified_postconditions']
            );
            self::assertSame(['action' => 'package_release_run'], $first['next_action']);
            self::assertCount(1, $first['next_action']);
            self::assertSame('release.prepare.completed', $first['findings'][0]['id']);
            $this->assertContentAddressedArtifacts($first['artifacts']);

            $firstHistory = $this->decodeLines($first['run_state']['history_path']);
            $intermediateManifestId = $firstHistory[2]['prerequisite_evidence_manifest_id'];
            $intermediateHandoffId = $firstHistory[2]['prerequisite_phase_handoff_id'];
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $intermediateManifestId);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $intermediateHandoffId);
            self::assertSame([
                [
                    'from'        => null,
                    'next_action' => ['action' => 'prepare_release_run'],
                    'operation'   => 'create_release_run',
                    'plan_id'     => $planResult['plan_id'],
                    'run_id'      => $first['run_id'],
                    'sequence'    => 1,
                    'state'       => 'planned'
                ],
                [
                    'from'        => 'planned',
                    'next_action' => ['action' => 'finalize_release_preparation_evidence'],
                    'operation'   => 'prepare_release_run',
                    'plan_id'     => $planResult['plan_id'],
                    'run_id'      => $first['run_id'],
                    'sequence'    => 2,
                    'state'       => 'prepared'
                ],
                [
                    'from'                              => 'prepared',
                    'next_action'                       => ['action' => 'package_release_run'],
                    'operation'                         => 'finalize_release_preparation_evidence',
                    'plan_id'                           => $planResult['plan_id'],
                    'prerequisite_evidence_manifest_id' => $intermediateManifestId,
                    'prerequisite_phase_handoff_id'     => $intermediateHandoffId,
                    'run_id'                            => $first['run_id'],
                    'sequence'                          => 3,
                    'state'                             => 'prepared'
                ]
            ], $firstHistory);
            self::assertSame([
                'next_action'                       => ['action' => 'package_release_run'],
                'plan_id'                           => $planResult['plan_id'],
                'prerequisite_evidence_manifest_id' => $intermediateManifestId,
                'prerequisite_phase_handoff_id'     => $intermediateHandoffId,
                'run_id'                            => $first['run_id'],
                'schema_version'                    => 'fight-common.release-run-state/v1',
                'sequence'                          => 3,
                'state'                             => 'prepared'
            ], json_decode(
                (string) file_get_contents($first['run_state']['projection_path']),
                true,
                flags: JSON_THROW_ON_ERROR
            ));
            self::assertSame(
                $intermediateManifestId,
                $first['artifacts']['evidence_manifest']['manifest_id']
            );
            self::assertSame(
                $intermediateHandoffId,
                $first['artifacts']['phase_handoff']['handoff_id']
            );
            self::assertFileExists($second['run_state']['history_path']);
            self::assertFileExists($second['run_state']['projection_path']);
            self::assertFileExists($first['run_state']['history_path']);
            self::assertFileExists($first['run_state']['projection_path']);
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers a governed artifact-publication stop before prepared state becomes visible.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_prepare_artifact_failure_fails_under_the_runtime_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-artifact-stop-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $prepare = ReleaseProcess::create([
                $root.'/bin/release',
                'prepare',
                '--plan='.$planResult['artifact']['path'],
                '--fixture='.$root.'/release/fixtures/prepare-artifact-failure.json'
            ]);

            $prepare->run();
            self::assertNotSame('', $prepare->getOutput(), $prepare->getErrorOutput());
            $result = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(5, $prepare->getExitCode());
            self::assertSame('prepare', $result['command']);
            self::assertSame('release_preparation', $result['capability']);
            self::assertSame('evidence_indeterminate', $result['status']);
            self::assertSame('uncertain', $result['exit_class']);
            self::assertSame($planResult['plan_id'], $result['plan_id']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $result['run_id']);
            self::assertSame('release.prepare.evidence_persistence_failed', $result['findings'][0]['id']);
            self::assertSame(['action' => 'repair_release_evidence_storage'], $result['next_action']);
            self::assertCount(1, $result['next_action']);
            self::assertArrayNotHasKey('artifacts', $result);
            self::assertNotEmpty($result['performed_effects']);
            self::assertSame([], $result['proposed_effects']);
            $runDirectories = glob($output.'/runs/*', GLOB_ONLYDIR);
            self::assertIsArray($runDirectories);
            self::assertCount(1, $runDirectories);
            $projection = json_decode(
                (string) file_get_contents($runDirectories[0].'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('evidence_indeterminate', $projection['state']);
            self::assertSame('release.prepare.evidence_persistence_failed', $projection['finding_id']);
            self::assertSame(
                ['action' => 'repair_release_evidence_storage'],
                $projection['next_action']
            );
            $stoppedHistory = file_get_contents($runDirectories[0].'/history.jsonl');
            self::assertIsString($stoppedHistory);

            $resume = ReleaseProcess::create([
                $root.'/bin/release',
                'prepare',
                '--plan='.$planResult['artifact']['path'],
                '--resume='.$result['run_id'],
                '--fixture='.$root.'/release/fixtures/prepare-artifact-failure.json'
            ]);
            $resume->run();
            $resumedStop = json_decode($resume->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(5, $resume->getExitCode());
            self::assertSame('release.prepare.evidence_persistence_failed', $resumedStop['findings'][0]['id']);
            self::assertSame($result['run_id'], $resumedStop['run_id']);
            self::assertSame(
                ['action' => 'repair_release_evidence_storage'],
                json_decode(
                    (string) file_get_contents($runDirectories[0].'/projection.json'),
                    true,
                    flags: JSON_THROW_ON_ERROR
                )['next_action']
            );
            self::assertSame(
                $stoppedHistory,
                file_get_contents($runDirectories[0].'/history.jsonl')
            );
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers helper protocol termination at the public prepare boundary
     */
    public function test_that_run_state_helper_protocol_termination_exits_as_infrastructure_terminated(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-helper-termination-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $prepare = ReleaseProcess::create([
                $root.'/bin/release', 'prepare', '--plan='.$planResult['artifact']['path'],
                '--fixture='.$root.'/release/fixtures/prepare-helper-termination.json'
            ]);
            $prepare->run();
            $result = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(71, $prepare->getExitCode());
            self::assertSame('infrastructure_terminated', $result['status']);
            self::assertSame('release.runtime.result_unavailable', $result['findings'][0]['id']);
            self::assertSame(['action' => 'inspect_release_runtime_termination'], $result['next_action']);
            self::assertSame([], $result['verified_postconditions']);
            self::assertSame([], $result['performed_effects']);
            self::assertSame([], $result['proposed_effects']);
            self::assertArrayNotHasKey('artifacts', $result);
            self::assertArrayNotHasKey('run_id', $result);
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers precise public Git-resolution stops with durable evidence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_prepare_classifies_each_git_resolution_outcome_with_durable_evidence(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-git-stops-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            foreach (
                [
                    'refusal'     => ['authority_required', 'release.prepare.baseline_resolution_refused', 3,
                        'obtain_current_baseline_authority'],
                    'failure'     => ['policy_blocked', 'release.prepare.baseline_resolution_failed', 4,
                        'repair_baseline_resolution_provider'],
                    'uncertainty' => ['evidence_indeterminate', 'release.prepare.baseline_resolution_uncertain', 5,
                        'reconcile_baseline_resolution']
                ] as $outcome => [$status, $finding, $exitCode, $action]
            ) {
                $fixture = $output.'/prepare-'.$outcome.'.json';
                file_put_contents($fixture, '{"git_resolution_outcome":"'.$outcome.'"}'.PHP_EOL);
                $prepare = ReleaseProcess::create([
                    $root.'/bin/release',
                    'prepare',
                    '--plan='.$planResult['artifact']['path'],
                    '--fixture='.$fixture
                ]);
                $prepare->run();
                $result = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame($exitCode, $prepare->getExitCode());
                self::assertSame($status, $result['status']);
                self::assertSame($finding, $result['findings'][0]['id']);
                self::assertSame(['action' => $action], $result['next_action']);
                $this->assertContentAddressedArtifacts($result['artifacts']);
                $runPath = $output.'/runs/'.$result['run_id'];
                $projection = json_decode(
                    (string) file_get_contents($runPath.'/projection.json'),
                    true,
                    flags: JSON_THROW_ON_ERROR
                );
                self::assertSame($status, $projection['state']);
                self::assertSame($finding, $projection['finding_id']);
                self::assertSame(['action' => $action], $projection['next_action']);
                self::assertSame(
                    $result['artifacts']['evidence_manifest']['manifest_id'],
                    $projection['evidence_manifest_id']
                );
                self::assertSame(
                    $result['artifacts']['phase_handoff']['handoff_id'],
                    $projection['phase_handoff_id']
                );
                self::assertCount(2, file($runPath.'/history.jsonl', FILE_IGNORE_NEW_LINES));

                $resumed = $this->resume($root, $planResult['artifact']['path'], $result['run_id']);
                self::assertSame('prepared', $resumed['status']);
                self::assertSame('release.prepare.resumed_completed', $resumed['findings'][0]['id']);
                self::assertSame(['action' => 'package_release_run'], $resumed['next_action']);
                self::assertNotSame($result['artifacts'], $resumed['artifacts']);
                self::assertCount(5, file($runPath.'/history.jsonl', FILE_IGNORE_NEW_LINES));
            }
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers abrupt public-command interruption and recovery through named resume.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_prepare_crash_is_reconciled_only_by_named_resume(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-crash-resume-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $crash = ReleaseProcess::create([
                $root.'/bin/release',
                'prepare',
                '--plan='.$planResult['artifact']['path'],
                '--fixture='.$root.'/release/fixtures/prepare-crash.json'
            ]);

            $crash->run();

            self::assertSame(86, $crash->getExitCode());
            self::assertSame('', $crash->getOutput());
            self::assertStringContainsString('ReleaseBoundaryCrash', $crash->getErrorOutput());
            $runDirectories = glob($output.'/runs/*', GLOB_ONLYDIR);
            self::assertIsArray($runDirectories);
            self::assertCount(1, $runDirectories);
            $runDirectory = $runDirectories[0];
            $runId = basename($runDirectory);
            $projection = json_decode(
                (string) file_get_contents($runDirectory.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('planned', $projection['state']);
            $manifests = glob($output.'/*.evidence-manifest.json');
            $handoffs = glob($output.'/*.phase-handoff.json');
            self::assertIsArray($manifests);
            self::assertIsArray($handoffs);
            self::assertCount(0, $manifests);
            self::assertCount(0, $handoffs);

            $resumed = $this->resume($root, $planResult['artifact']['path'], $runId);

            self::assertSame(0, $resumed['exit_code']);
            self::assertSame($runId, $resumed['run_id']);
            self::assertSame('release.prepare.resumed_completed', $resumed['findings'][0]['id']);
            self::assertSame('prepared', json_decode(
                (string) file_get_contents($runDirectory.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            )['state']);
            $this->assertContentAddressedArtifacts($resumed['artifacts']);
            $manifest = json_decode(
                (string) file_get_contents($resumed['artifacts']['evidence_manifest']['path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $handoff = json_decode(
                (string) file_get_contents($resumed['artifacts']['phase_handoff']['path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('prepared', $manifest['status']);
            self::assertSame([
                'immutable_plan_revalidated',
                'prepared_run_projection_published'
            ], $manifest['verified_evidence']['postconditions']);
            $historyLines = file($runDirectory.'/history.jsonl', FILE_IGNORE_NEW_LINES);
            self::assertIsArray($historyLines);
            self::assertSame(
                hash('sha256', implode("\n", array_slice($historyLines, 0, 2))."\n"),
                $manifest['verified_evidence']['history_sha256']
            );
            $preparedProjection = new CanonicalJson()->encode([
                'schema_version' => 'fight-common.release-run-state/v1',
                'plan_id'        => $planResult['plan_id'],
                'run_id'         => $runId,
                'sequence'       => 2,
                'state'          => 'prepared',
                'next_action'    => ['action' => 'finalize_release_preparation_evidence']
            ]).PHP_EOL;
            self::assertSame(
                hash('sha256', $preparedProjection),
                $manifest['verified_evidence']['projection_sha256']
            );
            self::assertSame($manifest['status'], $handoff['status']);
            self::assertSame($manifest['verified_evidence'], $handoff['verified_evidence']);
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers fail-closed named resume when interruption precedes the immutable plan binding.
     */
    public function test_that_pre_binding_crash_cannot_rebind_the_named_run_to_any_plan(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-binding-crash-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);

        try {
            $plans = [];

            foreach (['plan-candidate.json', 'plan-candidate-changed.json'] as $fixture) {
                $plan = ReleaseProcess::create([
                    $root.'/bin/release',
                    'plan',
                    '--fixture='.$root.'/release/fixtures/'.$fixture,
                    '--output='.$output
                ]);
                $plan->mustRun();
                $plans[] = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            $crash = ReleaseProcess::create([
                $root.'/bin/release',
                'prepare',
                '--plan='.$plans[0]['artifact']['path'],
                '--fixture='.$root.'/release/fixtures/prepare-binding-crash.json'
            ]);
            $crash->run();

            self::assertSame(86, $crash->getExitCode());
            self::assertSame('', $crash->getOutput());
            $runDirectories = glob($output.'/runs/*', GLOB_ONLYDIR);
            self::assertIsArray($runDirectories);
            self::assertCount(1, $runDirectories);
            $runId = basename($runDirectories[0]);
            self::assertFileDoesNotExist($runDirectories[0].'/binding.json');

            foreach ($plans as $plan) {
                $result = $this->resume($root, $plan['artifact']['path'], $runId);
                self::assertSame('evidence_indeterminate', $result['status']);
                self::assertSame('release.prepare.resume_state_indeterminate', $result['findings'][0]['id']);
                self::assertSame(['action' => 'reconcile_named_release_run'], $result['next_action']);
                self::assertFileDoesNotExist($runDirectories[0].'/history.jsonl');
                self::assertFileDoesNotExist($runDirectories[0].'/projection.json');
            }
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Covers interruption after evidence finalization append and exact same-run recovery.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_finalization_crash_reuses_verified_prerequisite_artifacts_on_named_resume(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-finalization-crash-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);

        try {
            $plan = ReleaseProcess::create([
                $root.'/bin/release',
                'plan',
                '--fixture='.$root.'/release/fixtures/plan-candidate.json',
                '--output='.$output
            ]);
            $plan->mustRun();
            $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $crash = ReleaseProcess::create([
                $root.'/bin/release',
                'prepare',
                '--plan='.$planResult['artifact']['path'],
                '--fixture='.$root.'/release/fixtures/prepare-finalization-crash.json'
            ]);

            $crash->run();

            self::assertSame(86, $crash->getExitCode());
            self::assertSame('', $crash->getOutput());
            self::assertStringContainsString('ReleaseBoundaryCrash', $crash->getErrorOutput());
            $runDirectories = glob($output.'/runs/*', GLOB_ONLYDIR);
            self::assertIsArray($runDirectories);
            self::assertCount(1, $runDirectories);
            $runDirectory = $runDirectories[0];
            $runId = basename($runDirectory);
            $history = $this->decodeLines($runDirectory.'/history.jsonl');
            self::assertCount(3, $history);
            self::assertSame(2, json_decode(
                (string) file_get_contents($runDirectory.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            )['sequence']);
            $manifestId = $history[2]['prerequisite_evidence_manifest_id'];
            $handoffId = $history[2]['prerequisite_phase_handoff_id'];
            $manifestPath = $output.'/'.$manifestId.'.evidence-manifest.json';
            $handoffPath = $output.'/'.$handoffId.'.phase-handoff.json';
            $manifestBytes = (string) file_get_contents($manifestPath);
            $handoffBytes = (string) file_get_contents($handoffPath);
            $handoff = json_decode($handoffBytes, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([
                'mode'                              => 'projection_bound',
                'projection_must_bind_artifact_ids' => true,
                'required_projection_state'         => 'prepared'
            ], $handoff['activation']);
            self::assertSame(2, json_decode(
                (string) file_get_contents($runDirectory.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            )['sequence']);
            self::assertCount(1, glob($output.'/*.evidence-manifest.json'));
            self::assertCount(1, glob($output.'/*.phase-handoff.json'));

            $resumed = $this->resume($root, $planResult['artifact']['path'], $runId);

            self::assertSame(0, $resumed['exit_code']);
            self::assertSame($runId, $resumed['run_id']);
            self::assertSame('release.prepare.resumed_completed', $resumed['findings'][0]['id']);
            self::assertSame($manifestBytes, file_get_contents($manifestPath));
            self::assertSame($handoffBytes, file_get_contents($handoffPath));
            self::assertSame($manifestId, $resumed['artifacts']['evidence_manifest']['manifest_id']);
            self::assertSame($handoffId, $resumed['artifacts']['phase_handoff']['handoff_id']);
            self::assertSame(3, json_decode(
                (string) file_get_contents($runDirectory.'/projection.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            )['sequence']);
            $this->assertContentAddressedArtifacts($resumed['artifacts']);
        } finally {
            $this->removeDirectory($output);
        }
    }

    /**
     * Executes one public preparation attempt.
     *
     * @return array<string, mixed>
     */
    private function prepare(string $root, string $planPath): array
    {
        $process = ReleaseProcess::create([
            $root.'/bin/release',
            'prepare',
            '--plan='.$planPath,
            '--fixture='.$root.'/release/fixtures/prepare-deterministic.json'
        ]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Executes one named public resume and decodes its governed stop.
     *
     * @return array<string, mixed>
     */
    private function resume(string $root, string $planPath, string $runId): array
    {
        $process = ReleaseProcess::create([
            $root.'/bin/release',
            'prepare',
            '--plan='.$planPath,
            '--resume='.$runId,
            '--fixture='.$root.'/release/fixtures/prepare-deterministic.json'
        ]);
        $process->run();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Decodes one canonical JSON object per history line.
     *
     * @return list<array<string, mixed>>
     */
    private function decodeLines(string $path): array
    {
        $contents = trim((string) file_get_contents($path));

        return array_map(
            static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            explode("\n", $contents)
        );
    }

    /**
     * Proves public artifacts use SHA-256 over their canonical bytes without their own identity field
     *
     * @param array<string, array<string, string>> $artifacts Public artifact references.
     */
    private function assertContentAddressedArtifacts(array $artifacts): void
    {
        $manifest = $artifacts['evidence_manifest'];
        $manifestBytes = trim((string) file_get_contents($manifest['path']));
        $manifestIdentity = '"manifest_id":"'.$manifest['manifest_id'].'",';

        self::assertStringContainsString($manifestIdentity, $manifestBytes);
        self::assertSame(
            $manifest['manifest_id'],
            hash('sha256', str_replace($manifestIdentity, '', $manifestBytes))
        );

        $handoff = $artifacts['phase_handoff'];
        $handoffBytes = trim((string) file_get_contents($handoff['path']));
        $handoffIdentity = '"handoff_id":"'.$handoff['handoff_id'].'",';

        self::assertStringContainsString($handoffIdentity, $handoffBytes);
        self::assertSame(
            $handoff['handoff_id'],
            hash('sha256', str_replace($handoffIdentity, '', $handoffBytes))
        );
    }

    /** @return array<string, mixed> */
    private function createLiveReleaseContext(string $runtimeRoot): array
    {
        mkdir($runtimeRoot.'/.runs/release', 0700, true);
        $this->git($runtimeRoot, ['init', '--quiet']);
        file_put_contents($runtimeRoot.'/release.txt', "baseline\n");
        $this->git($runtimeRoot, ['add', 'release.txt']);
        $this->git($runtimeRoot, [
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'commit', '--quiet', '-m', 'baseline'
        ]);
        $baselineOid = $this->git($runtimeRoot, ['rev-parse', 'HEAD']);
        $this->git($runtimeRoot, [
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline'
        ]);
        $tagOid = $this->git($runtimeRoot, ['rev-parse', 'refs/tags/v1.2.3']);
        file_put_contents($runtimeRoot.'/release.txt', "candidate\n");
        $this->git($runtimeRoot, ['add', 'release.txt']);
        $this->git($runtimeRoot, [
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'commit', '--quiet', '-m', 'candidate'
        ]);
        $sourceOid = $this->git($runtimeRoot, ['rev-parse', 'HEAD']);
        $candidate = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/release/fixtures/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate['source_commit_oid'] = $sourceOid;
        $candidate['baseline']['tag_object_oid'] = $tagOid;
        $candidate['baseline']['peeled_commit_oid'] = $baselineOid;
        $candidate['release_approval_authority']['candidate_commit_oid'] = $sourceOid;
        $candidate['release_approval_authority']['baseline_tag_object_oid'] = $tagOid;
        $candidate['release_approval_authority']['baseline_peeled_commit_oid'] = $baselineOid;
        $candidate['git_resolution'] = [
            'status'            => 'resolved',
            'tag_name'          => 'v1.2.3',
            'tag_object_oid'    => $tagOid,
            'peeled_commit_oid' => $baselineOid
        ];
        $fixturePath = $runtimeRoot.'/.runs/release/candidate.json';
        file_put_contents($fixturePath, json_encode($candidate, JSON_THROW_ON_ERROR).PHP_EOL);
        $environment = ['FIGHT_COMMON_RELEASE_TEST_REPOSITORY' => $runtimeRoot];
        $plan = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'plan',
            '--fixture='.$fixturePath,
            '--output='.$runtimeRoot.'/.runs/release'
        ], $environment);
        $plan->mustRun();

        $planResult = json_decode($plan->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $planArtifact = json_decode(
            (string) file_get_contents($planResult['artifact']['path']),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $authority = array_intersect_key($planArtifact, array_flip([
            'compatibility_exceptions',
            'evidence_manifest_digest',
            'patch_exception_authorities',
            'release_approval_authority',
            'required_approvals',
            'support_policy_identity'
        ]));
        $authority['schema_version'] = 'fight-common.release-plan-authority/v1';
        $authorityPath = $runtimeRoot.'/.runs/release/current-authority.json';
        $this->writeAuthority($authorityPath, $authority);

        return [
            'authority'      => $authority,
            'authority_path' => $authorityPath,
            'environment'    => $environment,
            'plan_path'      => $planResult['artifact']['path'],
            'source_oid'     => $sourceOid,
            'tag_oid'        => $tagOid
        ];
    }

    /**
     * Executes one normal preparation through live local authority adapters
     *
     * @param array $context Isolated live authority context.
     *
     * @return array<string, mixed>
     *
     * @phpstan-param array<string, mixed> $context
     */
    private function livePrepare(array $context): array
    {
        $process = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'prepare',
            '--plan='.$context['plan_path'],
            '--authority='.$context['authority_path']
        ], $context['environment']);
        $process->run();
        self::assertJson($process->getOutput(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $context Live release context.
     *
     * @return array<string, mixed>
     */
    private function liveResume(array $context, string $runId): array
    {
        $process = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'prepare',
            '--plan='.$context['plan_path'],
            '--resume='.$runId,
            '--authority='.$context['authority_path']
        ], $context['environment']);
        $process->run();
        self::assertJson($process->getOutput(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Replaces the mutable canonical authority artifact
     *
     * @param string $path Authority artifact path.
     * @param array  $authority Current release-plan authority.
     *
     * @phpstan-param array<string, mixed> $authority
     */
    private function writeAuthority(string $path, array $authority): void
    {
        file_put_contents($path, new CanonicalJson()->encode($authority).PHP_EOL);
    }

    /**
     * Runs one Git command in the isolated repository
     *
     * @param string $repository Isolated repository root.
     * @param array  $arguments Git argument vector.
     *
     * @phpstan-param list<string> $arguments
     */
    private function git(string $repository, array $arguments): string
    {
        $process = new Process(['/usr/bin/git', ...$arguments], $repository, [
            'GIT_CONFIG_GLOBAL'   => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1'
        ]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * Removes one task-owned temporary directory tree
     */
    private function removeDirectory(string $directory): void
    {
        foreach (new FilesystemIterator($directory) as $path) {
            if ($path->isDir()) {
                $this->removeDirectory($path->getPathname());
            } else {
                unlink($path->getPathname());
            }
        }

        rmdir($directory);
    }
}
