<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release;

use Fight\Common\Adapter\Release\Fake\DeterministicReleaseBoundaryFake;
use Fight\Common\Application\Release\Boundary\CanonicalRunsDirectory;
use Fight\Common\Application\Release\Boundary\HashingPort;
use Fight\Common\Application\Release\Boundary\PlanArtifactReadResult;
use Fight\Common\Application\Release\Boundary\PlanArtifactStore;
use Fight\Common\Application\Release\Boundary\PlanArtifactWriteResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOperationResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\Boundary\ReleaseEffect;
use Fight\Common\Application\Release\Boundary\ReleaseEffectLedger;
use Fight\Common\Application\Release\Boundary\RunIdGenerator;
use Fight\Common\Application\Release\Boundary\RunsDirectoryResolutionResult;
use Fight\Common\Application\Release\Boundary\RunStateStore;
use Fight\Common\Application\Release\CanonicalJson;
use Fight\Common\Application\Release\MachineResult;
use Fight\Common\Application\Release\ReleasePlanFactory;
use Fight\Common\Application\Release\ReleasePlanService;
use Fight\Common\Application\Release\ReleasePreparationService;
use Fight\Common\Application\Release\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Filesystem;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class ReleasePreparationServiceTest
 *
 * Covers preparation coordination through inward-owned release boundaries.
 */
#[CoversClass(ReleasePreparationService::class)]
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
#[CoversClass(ReleaseResultFactory::class)]
#[CoversClass(MachineResult::class)]
final class ReleasePreparationServiceTest extends UnitTestCase
{
    /**
     * Asserts an immutable plan advances through one distinct prepared run
     */
    public function test_that_prepare_revalidates_an_immutable_plan_and_persists_prepared_state(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-prepare-service-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);
        $ports = new DeterministicReleaseBoundaryFake();
        $json = new CanonicalJson();
        $plans = new ReleasePlanFactory();
        $results = new ReleaseResultFactory($ports);
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            $plan = new ReleasePlanService($ports, $ports, $ports, $ports, $json, $plans, $results)->plan(
                $candidate,
                $output,
                $root.'/.runs'
            );
            $runIds = new class implements RunIdGenerator {
                /**
                 * Returns one independently known test identity
                 */
                public function generate(): string
                {
                    return str_repeat('b', 64);
                }
            };
            $service = new ReleasePreparationService(
                $ports,
                $ports,
                $ports,
                $runIds,
                $ports,
                $ports,
                $ports,
                $json,
                $plans,
                $results
            );

            $prepared = $service->prepare($plan->payload['artifact']['path'], $root.'/.runs');

            self::assertSame(0, $prepared->exitCode);
            self::assertSame(
                'fcb11e02d0cffd7d2b9760e2868cf083367aab32f6fb640b3141b542df585de1',
                $prepared->payload['plan_id']
            );
            self::assertSame(str_repeat('b', 64), $prepared->payload['run_id']);
            self::assertSame('prepared', $prepared->payload['status']);
            self::assertSame(['action' => 'package_release_run'], $prepared->payload['next_action']);
            self::assertSame(
                ['immutable_plan_revalidated', 'prepared_run_projection_published'],
                $prepared->payload['verified_postconditions']
            );
        } finally {
            new Filesystem()->remove($output);
        }
    }

    /**
     * Covers fail-closed plan, authority, run-identity, state, and artifact validation stops.
     */
    public function test_that_prepare_rejects_each_invalid_or_indeterminate_precondition(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-prepare-stops-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);
        $directory = new CanonicalRunsDirectory(
            $output,
            $root.'/.runs'
        );
        $json = new CanonicalJson();
        $plans = new ReleasePlanFactory();

        try {
            $ports = new DeterministicReleaseBoundaryFake();
            $service = $this->service($ports, str_repeat('b', 64));
            self::assertSame(
                'release.prepare.plan_forbidden',
                $service->prepare('/tmp/not-a-release-plan.json', $root.'/.runs')->payload['findings'][0]['id']
            );
            self::assertSame(
                'release.prepare.plan_unreadable',
                $service->prepare($output.'/missing.json', $root.'/.runs')->payload['findings'][0]['id']
            );

            $invalidId = str_repeat('a', 64);
            $ports->writeArtifact($directory, $invalidId.'.json', "{not-json}\n");
            self::assertSame(
                'release.prepare.plan_invalid',
                $service->prepare($output.'/'.$invalidId.'.json', $root.'/.runs')->payload['findings'][0]['id']
            );

            $listId = str_repeat('c', 64);
            $ports->writeArtifact($directory, $listId.'.json', '["not-an-object"]'.PHP_EOL);
            self::assertSame(
                'release.prepare.plan_invalid',
                $service->prepare($output.'/'.$listId.'.json', $root.'/.runs')->payload['findings'][0]['id']
            );

            $badIdentityDirectory = $root.'/.runs/prepare-bad-identity-'.bin2hex(random_bytes(4));
            mkdir($badIdentityDirectory);
            file_put_contents(
                $badIdentityDirectory.'/'.str_repeat('d', 64).'.json',
                $json->encode(['plan_id' => 'invalid']).PHP_EOL
            );
            self::assertSame(
                'release.prepare.plan_invalid',
                $service->prepare(
                    $badIdentityDirectory.'/'.str_repeat('d', 64).'.json',
                    $root.'/.runs'
                )->payload['findings'][0]['id']
            );

            $incompleteDirectory = $root.'/.runs/prepare-incomplete-'.bin2hex(random_bytes(4));
            mkdir($incompleteDirectory);
            $incompleteId = str_repeat('e', 64);
            file_put_contents(
                $incompleteDirectory.'/'.$incompleteId.'.json',
                $json->encode(['plan_id' => $incompleteId]).PHP_EOL
            );
            self::assertSame(
                'release.prepare.plan_invalid',
                $service->prepare($incompleteDirectory.'/'.$incompleteId.'.json', $root.'/.runs')
                    ->payload['findings'][0]['id']
            );

            $candidate = json_decode(
                (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $planService = new ReleasePlanService(
                $ports,
                $ports,
                $ports,
                $ports,
                $json,
                $plans,
                new ReleaseResultFactory($ports)
            );
            $plan = $planService->plan($candidate, $output, $root.'/.runs');
            $planPath = $plan->payload['artifact']['path'];

            $authorityPipeline = new DeterministicReleaseBoundaryFake();
            self::assertTrue($authorityPipeline->configurePlanAuthorityStatus('support_policy_drift'));
            $authorityRunId = hash('sha256', 'authority-repair-before-baseline-stop');
            $authorityService = $this->service($authorityPipeline, $authorityRunId);
            $authorityStopped = $authorityService->prepare($planPath, $root.'/.runs');
            self::assertSame(
                'release.prepare.support_policy_drift',
                $authorityStopped->payload['findings'][0]['id']
            );
            $authorityHistory = $output.'/runs/'.$authorityRunId.'/history.jsonl';
            $unrepairedHistory = file_get_contents($authorityHistory);
            self::assertIsString($unrepairedHistory);
            $stillUnrepaired = $authorityService->prepare($planPath, $root.'/.runs', $authorityRunId);
            self::assertSame(
                'release.prepare.support_policy_drift',
                $stillUnrepaired->payload['findings'][0]['id']
            );
            self::assertSame($unrepairedHistory, file_get_contents($authorityHistory));

            self::assertTrue($authorityPipeline->configurePlanAuthorityStatus('verified'));
            self::assertTrue($authorityPipeline->configureBaselineTagResolution('missing'));
            $currentBaselineStop = $authorityService->prepare($planPath, $root.'/.runs', $authorityRunId);
            self::assertSame(
                'release.prepare.baseline_tag_missing',
                $currentBaselineStop->payload['findings'][0]['id']
            );
            $pipelineHistory = file($authorityHistory, FILE_IGNORE_NEW_LINES);
            self::assertIsArray($pipelineHistory);
            self::assertCount(4, $pipelineHistory);
            self::assertStringContainsString('recover_release_preparation_stop', $pipelineHistory[2]);
            self::assertStringContainsString('release.prepare.baseline_tag_missing', $pipelineHistory[3]);

            $baselineProvider = new DeterministicReleaseBoundaryFake();
            self::assertTrue($baselineProvider->configureOutcome('git.resolve_ref', 'failure'));
            $baselineProviderRunId = hash('sha256', 'baseline-provider-repair-before-missing');
            $baselineProviderService = $this->service($baselineProvider, $baselineProviderRunId);
            self::assertSame(
                'release.prepare.baseline_resolution_failed',
                $baselineProviderService->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );
            self::assertTrue($baselineProvider->configureOutcome('git.resolve_ref', 'success'));
            self::assertTrue($baselineProvider->configureBaselineTagResolution('missing'));
            $baselineMissing = $baselineProviderService->prepare(
                $planPath,
                $root.'/.runs',
                $baselineProviderRunId
            );
            self::assertSame('release.prepare.baseline_tag_missing', $baselineMissing->payload['findings'][0]['id']);
            $baselineProviderHistory = file(
                $output.'/runs/'.$baselineProviderRunId.'/history.jsonl',
                FILE_IGNORE_NEW_LINES
            );
            self::assertIsArray($baselineProviderHistory);
            self::assertCount(4, $baselineProviderHistory);
            self::assertStringContainsString('recover_release_preparation_stop', $baselineProviderHistory[2]);

            $sameActionBaseline = new DeterministicReleaseBoundaryFake();
            self::assertTrue($sameActionBaseline->configureBaselineTagResolution('missing'));
            $sameActionBaselineRunId = hash('sha256', 'baseline-missing-to-ambiguous');
            $sameActionBaselineService = $this->service($sameActionBaseline, $sameActionBaselineRunId);
            self::assertSame(
                'release.prepare.baseline_tag_missing',
                $sameActionBaselineService->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );
            $sameActionBaselineHistoryPath = $output.'/runs/'.$sameActionBaselineRunId.'/history.jsonl';
            $sameActionBaselineHistory = file_get_contents($sameActionBaselineHistoryPath);
            self::assertIsString($sameActionBaselineHistory);
            self::assertTrue($sameActionBaseline->configureBaselineTagResolution('ambiguous'));
            self::assertSame(
                'release.prepare.baseline_tag_missing',
                $sameActionBaselineService->prepare(
                    $planPath,
                    $root.'/.runs',
                    $sameActionBaselineRunId
                )->payload['findings'][0]['id']
            );
            self::assertSame($sameActionBaselineHistory, file_get_contents($sameActionBaselineHistoryPath));

            foreach (['failure', 'uncertainty'] as $unavailableBaseline) {
                $baselineAuthority = new DeterministicReleaseBoundaryFake();
                self::assertTrue($baselineAuthority->configureBaselineTagResolution('missing'));
                $baselineAuthorityRunId = hash('sha256', 'baseline-missing-'.$unavailableBaseline);
                $baselineAuthorityService = $this->service($baselineAuthority, $baselineAuthorityRunId);
                $baselineAuthorityStop = $baselineAuthorityService->prepare($planPath, $root.'/.runs');
                self::assertSame(
                    'release.prepare.baseline_tag_missing',
                    $baselineAuthorityStop->payload['findings'][0]['id']
                );
                $baselineAuthorityHistoryPath = $output.'/runs/'.$baselineAuthorityRunId.'/history.jsonl';
                $baselineAuthorityHistory = file_get_contents($baselineAuthorityHistoryPath);
                self::assertIsString($baselineAuthorityHistory);
                self::assertTrue($baselineAuthority->configureOutcome('git.resolve_ref', $unavailableBaseline));
                $retainedBaseline = $baselineAuthorityService->prepare(
                    $planPath,
                    $root.'/.runs',
                    $baselineAuthorityRunId
                );
                self::assertSame(
                    'release.prepare.baseline_tag_missing',
                    $retainedBaseline->payload['findings'][0]['id'],
                    $unavailableBaseline
                );
                self::assertNotSame(
                    'release.prepare.evidence_persistence_failed',
                    $retainedBaseline->payload['findings'][0]['id'],
                    $unavailableBaseline
                );
                self::assertSame($baselineAuthorityHistory, file_get_contents($baselineAuthorityHistoryPath));
            }

            foreach (
                [
                    ['failed', 'support_policy_drift', 'release.prepare.support_policy_drift'],
                    ['approval_drift', 'support_policy_drift', 'release.prepare.support_policy_drift']
                ] as [$priorAuthority, $currentAuthority, $currentFinding]
            ) {
                $authorityTransition = new DeterministicReleaseBoundaryFake();
                self::assertTrue($authorityTransition->configurePlanAuthorityStatus($priorAuthority));
                if ($priorAuthority === 'failed') {
                    self::assertTrue($authorityTransition->configureOutcome('authorization.check', 'failure'));
                }

                $authorityTransitionRunId = hash('sha256', $priorAuthority.'-to-'.$currentAuthority);
                $authorityTransitionService = $this->service($authorityTransition, $authorityTransitionRunId);
                $authorityTransitionService->prepare($planPath, $root.'/.runs');
                self::assertTrue($authorityTransition->configurePlanAuthorityStatus($currentAuthority));
                self::assertTrue($authorityTransition->configureOutcome('authorization.check', 'success'));
                $currentAuthorityStop = $authorityTransitionService->prepare(
                    $planPath,
                    $root.'/.runs',
                    $authorityTransitionRunId
                );
                self::assertSame($currentFinding, $currentAuthorityStop->payload['findings'][0]['id']);
                $authorityTransitionHistory = file(
                    $output.'/runs/'.$authorityTransitionRunId.'/history.jsonl',
                    FILE_IGNORE_NEW_LINES
                );
                self::assertIsArray($authorityTransitionHistory);
                self::assertCount(4, $authorityTransitionHistory);
                self::assertStringContainsString('recover_release_preparation_stop', $authorityTransitionHistory[2]);
            }

            $sameActionAuthority = new DeterministicReleaseBoundaryFake();
            self::assertTrue($sameActionAuthority->configurePlanAuthorityStatus('support_policy_drift'));
            $sameActionAuthorityRunId = hash('sha256', 'support-policy-to-evidence-drift');
            $sameActionAuthorityService = $this->service($sameActionAuthority, $sameActionAuthorityRunId);
            self::assertSame(
                'release.prepare.support_policy_drift',
                $sameActionAuthorityService->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );
            $sameActionAuthorityHistoryPath = $output.'/runs/'.$sameActionAuthorityRunId.'/history.jsonl';
            $sameActionAuthorityHistory = file_get_contents($sameActionAuthorityHistoryPath);
            self::assertIsString($sameActionAuthorityHistory);
            self::assertTrue($sameActionAuthority->configurePlanAuthorityStatus('evidence_drift'));
            self::assertSame(
                'release.prepare.support_policy_drift',
                $sameActionAuthorityService->prepare(
                    $planPath,
                    $root.'/.runs',
                    $sameActionAuthorityRunId
                )->payload['findings'][0]['id']
            );
            self::assertSame($sameActionAuthorityHistory, file_get_contents($sameActionAuthorityHistoryPath));

            $unavailableAuthority = new DeterministicReleaseBoundaryFake();
            self::assertTrue($unavailableAuthority->configurePlanAuthorityStatus('failed'));
            self::assertTrue($unavailableAuthority->configureOutcome('authorization.check', 'failure'));
            $unavailableAuthorityRunId = hash('sha256', 'authority-failed-to-uncertain');
            $unavailableAuthorityService = $this->service($unavailableAuthority, $unavailableAuthorityRunId);
            self::assertSame(
                'release.prepare.plan_authority_failed',
                $unavailableAuthorityService->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );
            $unavailableAuthorityHistoryPath = $output.'/runs/'.$unavailableAuthorityRunId.'/history.jsonl';
            $unavailableAuthorityHistory = file_get_contents($unavailableAuthorityHistoryPath);
            self::assertIsString($unavailableAuthorityHistory);
            self::assertTrue($unavailableAuthority->configurePlanAuthorityStatus('uncertain'));
            self::assertTrue($unavailableAuthority->configureOutcome('authorization.check', 'uncertainty'));
            self::assertSame(
                'release.prepare.plan_authority_failed',
                $unavailableAuthorityService->prepare(
                    $planPath,
                    $root.'/.runs',
                    $unavailableAuthorityRunId
                )->payload['findings'][0]['id']
            );
            self::assertSame($unavailableAuthorityHistory, file_get_contents($unavailableAuthorityHistoryPath));

            foreach (["\n", "\r\n"] as $index => $suffix) {
                $framingDirectory = $root.'/.runs/prepare-framing-'.$index.'-'.bin2hex(random_bytes(4));
                mkdir($framingDirectory);
                file_put_contents(
                    $framingDirectory.'/'.$plan->payload['plan_id'].'.json',
                    (string) file_get_contents($planPath).$suffix
                );
                self::assertSame(
                    'release.prepare.plan_invalid',
                    $service->prepare(
                        $framingDirectory.'/'.$plan->payload['plan_id'].'.json',
                        $root.'/.runs'
                    )->payload['findings'][0]['id']
                );
                new Filesystem()->remove($framingDirectory);
            }

            $driftedDirectory = $root.'/.runs/prepare-drifted-'.bin2hex(random_bytes(4));
            mkdir($driftedDirectory);
            $drifted = json_decode((string) file_get_contents($planPath), true, flags: JSON_THROW_ON_ERROR);
            $drifted['minimum_release_class'] = $drifted['minimum_release_class'] === 'patch' ? 'minor' : 'patch';
            file_put_contents(
                $driftedDirectory.'/'.$plan->payload['plan_id'].'.json',
                $json->encode($drifted).PHP_EOL
            );
            self::assertSame(
                'release.prepare.plan_invalid',
                $service->prepare(
                    $driftedDirectory.'/'.$plan->payload['plan_id'].'.json',
                    $root.'/.runs'
                )->payload['findings'][0]['id']
            );

            $invalidRun = $this->service(new DeterministicReleaseBoundaryFake(), 'not-a-sha256')
                ->prepare($planPath, $root.'/.runs');
            self::assertSame('release.prepare.run_identity_invalid', $invalidRun->payload['findings'][0]['id']);

            $moving = new DeterministicReleaseBoundaryFake();
            self::assertTrue($moving->configureBaselineTagResolution('moving'));
            self::assertSame(
                'release.prepare.baseline_resolution_drift',
                $this->service($moving, str_repeat('d', 64))
                    ->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );

            foreach (['conflict', 'advanced', 'indeterminate', 'failed'] as $index => $stopReceipt) {
                $stopPorts = new DeterministicReleaseBoundaryFake();
                self::assertTrue($stopPorts->configureBaselineTagResolution('moving'));
                $runId = hash('sha256', 'stop-receipt-'.$index.'-'.$stopReceipt);
                $stopStore = $this->runStateStoreReturning(
                    $stopPorts,
                    [],
                    [],
                    null,
                    ['status' => $stopReceipt]
                );
                $result = $this->service($stopPorts, $runId, runs: $stopStore)
                    ->prepare($planPath, $root.'/.runs');

                if ($stopReceipt === 'conflict') {
                    self::assertSame(23, $result->exitCode);
                    self::assertSame('conflict', $result->payload['status']);
                    self::assertSame(
                        'release.prepare.resume_contention',
                        $result->payload['findings'][0]['id']
                    );
                    self::assertSame('evidence_only', json_decode(
                        (string) file_get_contents($result->payload['artifacts']['evidence_manifest']['path']),
                        true,
                        flags: JSON_THROW_ON_ERROR
                    )['activation']['mode']);

                    continue;
                }

                if ($stopReceipt === 'advanced') {
                    self::assertSame(5, $result->exitCode);
                    self::assertSame('evidence_indeterminate', $result->payload['status']);
                    self::assertSame(
                        'release.prepare.state_persistence_indeterminate',
                        $result->payload['findings'][0]['id']
                    );
                    self::assertSame(
                        ['action' => 'reconcile_named_release_run'],
                        $result->payload['next_action']
                    );

                    continue;
                }

                self::assertSame(5, $result->exitCode, $stopReceipt);
                self::assertSame('evidence_indeterminate', $result->payload['status'], $stopReceipt);
                self::assertSame(
                    'release.prepare.evidence_persistence_failed',
                    $result->payload['findings'][0]['id'],
                    $stopReceipt
                );
                self::assertSame(
                    ['action' => 'repair_release_evidence_storage'],
                    $result->payload['next_action'],
                    $stopReceipt
                );
                self::assertArrayNotHasKey('artifacts', $result->payload, $stopReceipt);
            }

            $exhaustedPorts = new DeterministicReleaseBoundaryFake();
            self::assertTrue($exhaustedPorts->configureBaselineTagResolution('moving'));
            $exhaustedStore = $this->runStateStoreReturning(
                $exhaustedPorts,
                [],
                [],
                null,
                ['status' => 'indeterminate']
            );
            $exhaustedEffects = new class implements ReleaseEffectLedger {
                /**
                 * Refuses test reconfiguration
                 */
                public function configureOutcome(string $effectClass, string $outcome): bool
                {
                    return false;
                }

                /**
                 * Returns one exact failed fallback-persistence effect
                 */
                public function effects(): array
                {
                    return [
                        [
                            'capability'   => 'filesystem',
                            'effect_class' => 'filesystem.inspect_runs_directory',
                            'outcome'      => 'success'
                        ],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'failure']
                    ];
                }
            };
            $exhausted = $this->service(
                $exhaustedPorts,
                hash('sha256', 'stop-receipt-evidence-exhaustion'),
                $this->hashingStopOn(4),
                runs: $exhaustedStore,
                effects: $exhaustedEffects
            )->prepare($planPath, $root.'/.runs');
            self::assertSame(5, $exhausted->exitCode);
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $exhausted->payload['findings'][0]['id']
            );
            self::assertArrayNotHasKey('artifacts', $exhausted->payload);

            $blocked = new DeterministicReleaseBoundaryFake();
            $blockedRunId = str_repeat('e', 64);
            if (!is_dir($output.'/runs')) {
                mkdir($output.'/runs');
            }

            mkdir($output.'/runs/'.$blockedRunId);
            self::assertSame(
                'release.prepare.run_identity_conflict',
                $this->service($blocked, $blockedRunId)
                    ->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );

            $failedState = new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'runs_directory');
            self::assertSame(
                'release.prepare.state_persistence_failed',
                $this->service($failedState, str_repeat('8', 64))
                    ->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );

            $indeterminateState = new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'open');
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $this->service($indeterminateState, str_repeat('9', 64))
                    ->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );

            $shortAppend = new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'append_short');
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $this->service($shortAppend, str_repeat('0', 64))
                    ->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );

            $uncertainArtifacts = new DeterministicReleaseBoundaryFake(
                postPublishFailureOnce: 'fsync',
                postPublishFinalOnce: 'missing'
            );
            $artifactStopRunId = str_repeat('f', 64);
            self::assertSame(
                'release.prepare.artifacts_indeterminate',
                $this->service($uncertainArtifacts, $artifactStopRunId)
                    ->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );

            $finalizePorts = new DeterministicReleaseBoundaryFake();
            $finalizeStore = $this->runStateStoreReturning(
                $finalizePorts,
                [],
                [],
                ['status' => 'indeterminate']
            );
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $this->service(
                    $finalizePorts,
                    hash('sha256', 'finalize-store'),
                    runs: $finalizeStore
                )->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );
            self::assertSame(
                'evidence_indeterminate',
                json_decode(
                    (string) file_get_contents($output.'/runs/'.$artifactStopRunId.'/projection.json'),
                    true,
                    flags: JSON_THROW_ON_ERROR
                )['state']
            );
            self::assertSame(
                ['action' => 'reconcile_named_release_run'],
                json_decode(
                    (string) file_get_contents($output.'/runs/'.$artifactStopRunId.'/projection.json'),
                    true,
                    flags: JSON_THROW_ON_ERROR
                )['next_action']
            );

            $failedArtifactWrite = new DeterministicReleaseBoundaryFake(['filesystem.write' => 'failure']);
            $artifactFailure = $this->service($failedArtifactWrite, str_repeat('1', 64))
                ->prepare($planPath, $root.'/.runs');
            self::assertSame(5, $artifactFailure->exitCode);
            self::assertSame('prepare', $artifactFailure->payload['command']);
            self::assertSame('evidence_indeterminate', $artifactFailure->payload['status']);
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $artifactFailure->payload['findings'][0]['id']
            );
            self::assertSame(
                ['action' => 'repair_release_evidence_storage'],
                $artifactFailure->payload['next_action']
            );
            self::assertArrayNotHasKey('artifacts', $artifactFailure->payload);

            self::assertSame(
                'release.prepare.plan_invalid',
                $this->service(
                    new DeterministicReleaseBoundaryFake(),
                    str_repeat('3', 64),
                    $this->hashingStopOn(1, true)
                )->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );

            foreach ([2, 3] as $hashCall) {
                $hashing = $this->hashingStopOn($hashCall);
                $fallback = $this->service(
                    new DeterministicReleaseBoundaryFake(),
                    str_repeat((string) (3 + $hashCall), 64),
                    $hashing,
                    effects: $this->effectLedger([
                        [
                            'capability'   => 'filesystem',
                            'effect_class' => 'filesystem.inspect_runs_directory',
                            'outcome'      => 'success'
                        ],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'failure'],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
                    ])
                )->prepare($planPath, $root.'/.runs');

                self::assertSame('evidence_indeterminate', $fallback->payload['status']);
                self::assertSame(
                    'release.prepare.artifacts_indeterminate',
                    $fallback->payload['findings'][0]['id']
                );
                self::assertSame(
                    ['action' => 'reconcile_named_release_run'],
                    $fallback->payload['next_action']
                );
                self::assertArrayHasKey('artifacts', $fallback->payload);
                self::assertFileExists($fallback->payload['artifacts']['evidence_manifest']['path']);
                self::assertFileExists($fallback->payload['artifacts']['phase_handoff']['path']);
            }

            $handoffPorts = new DeterministicReleaseBoundaryFake();
            $handoffStore = new class ($handoffPorts) implements PlanArtifactStore {
                private int $writes = 0;

                /**
                 * Constructs one second-write failure store
                 */
                public function __construct(private readonly PlanArtifactStore $delegate)
                {
                }

                /**
                 * Delegates canonical runs-directory resolution
                 */
                public function resolveRunsDirectory(
                    string $path,
                    string $runsDirectory
                ): RunsDirectoryResolutionResult {
                    return $this->delegate->resolveRunsDirectory($path, $runsDirectory);
                }

                /**
                 * Delegates immutable artifact reads
                 */
                public function readArtifact(
                    CanonicalRunsDirectory $directory,
                    string $filename
                ): PlanArtifactReadResult {
                    return $this->delegate->readArtifact($directory, $filename);
                }

                /**
                 * Stops exactly the second immutable artifact write
                 */
                public function writeArtifact(
                    CanonicalRunsDirectory $directory,
                    string $filename,
                    string $contents
                ): PlanArtifactWriteResult {
                    ++$this->writes;

                    if ($this->writes === 2) {
                        return PlanArtifactWriteResult::stopped(ReleaseBoundaryOutcome::FAILURE);
                    }

                    return $this->delegate->writeArtifact($directory, $filename, $contents);
                }
            };
            self::assertSame(
                'release.prepare.artifacts_indeterminate',
                $this->service(
                    $handoffPorts,
                    str_repeat('7', 64),
                    artifacts: $handoffStore,
                    effects: $this->effectLedger([
                        [
                            'capability'   => 'filesystem',
                            'effect_class' => 'filesystem.inspect_runs_directory',
                            'outcome'      => 'success'
                        ],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure'],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
                    ])
                )->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );
        } finally {
            new Filesystem()->remove([
                $output,
                $badIdentityDirectory ?? '',
                $incompleteDirectory ?? '',
                $driftedDirectory ?? ''
            ]);
        }
    }

    /**
     * Covers verified resume plus missing, stale, indeterminate, and contended named-run stops.
     */
    public function test_that_resume_revalidates_existing_state_and_classifies_every_stop(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-release-resume-stops-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);
        $ports = new DeterministicReleaseBoundaryFake();
        $json = new CanonicalJson();
        $plans = new ReleasePlanFactory();
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            $planService = new ReleasePlanService(
                $ports,
                $ports,
                $ports,
                $ports,
                $json,
                $plans,
                new ReleaseResultFactory($ports)
            );
            $plan = $planService->plan($candidate, $output, $root.'/.runs');
            $planPath = $plan->payload['artifact']['path'];
            foreach (
                [
                    'refusal'     => [
                        'authority_required',
                        'release.prepare.baseline_resolution_refused',
                        3,
                        'obtain_current_baseline_authority'
                    ],
                    'failure'     => [
                        'policy_blocked',
                        'release.prepare.baseline_resolution_failed',
                        4,
                        'repair_baseline_resolution_provider'
                    ],
                    'uncertainty' => [
                        'evidence_indeterminate',
                        'release.prepare.baseline_resolution_uncertain',
                        5,
                        'reconcile_baseline_resolution'
                    ],
                    'drift'       => [
                        'stale_plan',
                        'release.prepare.baseline_resolution_drift',
                        6,
                        'create_current_release_plan'
                    ]
                ] as $outcome => [$status, $finding, $exitCode, $action]
            ) {
                $runId = hash('sha256', 'baseline-stop-'.$outcome);
                $gitStop = new DeterministicReleaseBoundaryFake(['git.resolve_ref' => $outcome]);
                $gitResult = $this->service($gitStop, $runId)
                    ->prepare($planPath, $root.'/.runs');

                self::assertSame($status, $gitResult->payload['status']);
                self::assertSame($finding, $gitResult->payload['findings'][0]['id']);
                self::assertSame($exitCode, $gitResult->exitCode);
                self::assertSame($plan->payload['plan_id'], $gitResult->payload['plan_id']);
                self::assertSame($runId, $gitResult->payload['run_id']);
                self::assertSame(['action' => $action], $gitResult->payload['next_action']);
                self::assertCount(1, $gitResult->payload['next_action']);
                self::assertFileExists($gitResult->payload['artifacts']['evidence_manifest']['path']);
                self::assertFileExists($gitResult->payload['artifacts']['phase_handoff']['path']);
            }

            foreach (
                [
                    'missing'              => 'release.prepare.baseline_tag_missing',
                    'ambiguous'            => 'release.prepare.baseline_tag_ambiguous',
                    'duplicate_normalized' => 'release.prepare.baseline_tag_duplicate_normalized',
                    'non_ancestor'         => 'release.prepare.baseline_tag_non_ancestor',
                    'moving'               => 'release.prepare.baseline_resolution_drift'
                ] as $baselineStatus => $expectedFinding
            ) {
                $runId = hash('sha256', 'baseline-status-'.$baselineStatus);
                $baseline = new DeterministicReleaseBoundaryFake();
                self::assertTrue($baseline->configureBaselineTagResolution($baselineStatus));
                $result = $this->service($baseline, $runId)->prepare($planPath, $root.'/.runs');

                self::assertSame($expectedFinding, $result->payload['findings'][0]['id'], $baselineStatus);
                self::assertNotSame(
                    'release.prepare.resume_plan_drift',
                    $result->payload['findings'][0]['id'],
                    $baselineStatus
                );
            }

            $recoveryConflictRunId = hash('sha256', 'persisted-stop-recovery-conflict');
            $recoveryConflict = $this->runStateStoreReturning(
                $ports,
                [
                    'status'             => 'stopped',
                    'sequence'           => 2,
                    'state'              => 'policy_blocked',
                    'history_path'       => $output.'/runs/'.$recoveryConflictRunId.'/history.jsonl',
                    'projection_path'    => $output.'/runs/'.$recoveryConflictRunId.'/projection.json',
                    'stop_code'          => 'baseline_missing',
                    'stop_state'         => 'policy_blocked',
                    'finding_id'         => 'release.prepare.baseline_tag_missing',
                    'next_action'        => 'repair_baseline_authority',
                    'resume_state'       => 'planned',
                    'resume_sequence'    => 1,
                    'resume_next_action' => 'prepare_release_run'
                ],
                [],
                recover: ['status' => 'conflict']
            );
            $recoveryConflictResult = $this->service(
                $ports,
                $recoveryConflictRunId,
                runs: $recoveryConflict
            )->prepare($planPath, $root.'/.runs', $recoveryConflictRunId);
            self::assertSame(
                'release.prepare.resume_contention',
                $recoveryConflictResult->payload['findings'][0]['id']
            );
            $recoveryConflictManifest = json_decode(
                (string) file_get_contents(
                    $recoveryConflictResult->payload['artifacts']['evidence_manifest']['path']
                ),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('evidence_only', $recoveryConflictManifest['activation']['mode']);

            $authorityRecoveryRunId = hash('sha256', 'authority-stop-recovery-conflict');
            $authorityRecovery = $this->runStateStoreReturning(
                $ports,
                [
                    'status'             => 'stopped',
                    'sequence'           => 2,
                    'state'              => 'stale_plan',
                    'history_path'       => $output.'/runs/'.$authorityRecoveryRunId.'/history.jsonl',
                    'projection_path'    => $output.'/runs/'.$authorityRecoveryRunId.'/projection.json',
                    'stop_code'          => 'support_policy_drift',
                    'stop_state'         => 'stale_plan',
                    'finding_id'         => 'release.prepare.support_policy_drift',
                    'next_action'        => 'create_current_release_plan',
                    'resume_state'       => 'planned',
                    'resume_sequence'    => 1,
                    'resume_next_action' => 'prepare_release_run'
                ],
                [],
                recover: ['status' => 'conflict']
            );
            $authorityRecoveryResult = $this->service(
                $ports,
                $authorityRecoveryRunId,
                runs: $authorityRecovery
            )->prepare($planPath, $root.'/.runs', $authorityRecoveryRunId);
            self::assertSame(
                'release.prepare.resume_contention',
                $authorityRecoveryResult->payload['findings'][0]['id']
            );

            $lateStopRunId = hash('sha256', 'late-stop-recovery-conflict');
            $lateStop = [
                'status'             => 'stopped',
                'sequence'           => 2,
                'state'              => 'policy_blocked',
                'history_path'       => $output.'/runs/'.$lateStopRunId.'/history.jsonl',
                'projection_path'    => $output.'/runs/'.$lateStopRunId.'/projection.json',
                'stop_code'          => 'baseline_missing',
                'stop_state'         => 'policy_blocked',
                'finding_id'         => 'release.prepare.baseline_tag_missing',
                'next_action'        => 'repair_baseline_authority',
                'resume_state'       => 'planned',
                'resume_sequence'    => 1,
                'resume_next_action' => 'prepare_release_run'
            ];
            $lateStopStore = $this->runStateStoreReturning(
                $ports,
                [
                    ['status' => 'planned', 'sequence' => 1, 'state' => 'planned'],
                    $lateStop
                ],
                [],
                recover: ['status' => 'conflict']
            );
            $lateStopResult = $this->service($ports, $lateStopRunId, runs: $lateStopStore)
                ->prepare($planPath, $root.'/.runs', $lateStopRunId);
            self::assertSame('release.prepare.resume_contention', $lateStopResult->payload['findings'][0]['id']);

            $stalePublishRunId = hash('sha256', 'stale-resume-publish');
            $stalePublishStore = $this->runStateStoreReturning(
                $ports,
                [
                    ['status' => 'planned', 'sequence' => 1, 'state' => 'planned'],
                    ['status' => 'planned', 'sequence' => 1, 'state' => 'planned']
                ],
                ['status' => 'advanced'],
                stop: ['status' => 'advanced']
            );
            $stalePublish = $this->service(
                $ports,
                $stalePublishRunId,
                runs: $stalePublishStore
            )
                ->prepare($planPath, $root.'/.runs', $stalePublishRunId);
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $stalePublish->payload['findings'][0]['id']
            );

            $missingFinalizeTokenRunId = hash('sha256', 'missing-resume-finalize-token');
            $missingFinalizeTokenStore = $this->runStateStoreReturning(
                $ports,
                [
                    [
                        'status'            => 'evidence_pending',
                        'sequence'          => 2,
                        'state'             => 'evidence_pending',
                        'history_sha256'    => str_repeat('1', 64),
                        'projection_sha256' => str_repeat('2', 64)
                    ],
                    [
                        'status'            => 'evidence_pending',
                        'history_sha256'    => str_repeat('1', 64),
                        'projection_sha256' => str_repeat('2', 64)
                    ]
                ],
                [],
                stop: ['status' => 'advanced']
            );
            $missingFinalizeToken = $this->service(
                $ports,
                $missingFinalizeTokenRunId,
                runs: $missingFinalizeTokenStore
            )->prepare($planPath, $root.'/.runs', $missingFinalizeTokenRunId);
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $missingFinalizeToken->payload['findings'][0]['id']
            );

            $missingCreateTokenRunId = hash('sha256', 'missing-create-token');
            $missingCreateTokenStore = $this->runStateStoreReturning(
                $ports,
                [],
                [],
                stop: ['status' => 'advanced'],
                create: ['status' => 'planned']
            );
            $missingCreateToken = $this->service(
                $ports,
                $missingCreateTokenRunId,
                runs: $missingCreateTokenStore
            )->prepare($planPath, $root.'/.runs');
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $missingCreateToken->payload['findings'][0]['id']
            );

            $missingPublishedTokenRunId = hash('sha256', 'missing-published-token');
            $missingPublishedTokenStore = $this->runStateStoreReturning(
                $ports,
                [],
                [
                    'status'            => 'created',
                    'history_sha256'    => str_repeat('3', 64),
                    'projection_sha256' => str_repeat('4', 64)
                ],
                stop: ['status' => 'advanced'],
                create: ['status' => 'planned', 'sequence' => 1, 'state' => 'planned']
            );
            $missingPublishedToken = $this->service(
                $ports,
                $missingPublishedTokenRunId,
                runs: $missingPublishedTokenStore
            )->prepare($planPath, $root.'/.runs');
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $missingPublishedToken->payload['findings'][0]['id']
            );

            $preparedRunId = str_repeat('5', 64);
            $service = $this->service($ports, $preparedRunId);
            $prepared = $service->prepare($planPath, $root.'/.runs');
            self::assertSame(0, $prepared->exitCode);

            $resumed = $service->prepare($planPath, $root.'/.runs', $preparedRunId);
            self::assertSame(0, $resumed->exitCode);
            self::assertSame('release.prepare.already_satisfied', $resumed->payload['findings'][0]['id']);

            $projection = json_decode(
                (string) file_get_contents($prepared->payload['run_state']['projection_path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $prerequisiteManifestPath = sprintf(
                '%s/%s.evidence-manifest.json',
                $output,
                $projection['prerequisite_evidence_manifest_id']
            );
            $prerequisiteManifestBytes = file_get_contents($prerequisiteManifestPath);
            self::assertIsString($prerequisiteManifestBytes);
            unlink($prerequisiteManifestPath);
            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $service->prepare($planPath, $root.'/.runs', $preparedRunId)->payload['findings'][0]['id']
            );
            file_put_contents($prerequisiteManifestPath, $prerequisiteManifestBytes);

            $arbitraryRunId = str_repeat('f', 64);
            $arbitrary = $this->service($ports, $arbitraryRunId)->prepare($planPath, $root.'/.runs');
            self::assertSame(0, $arbitrary->exitCode);
            $arbitraryProjectionPath = $arbitrary->payload['run_state']['projection_path'];
            $arbitraryHistoryPath = $arbitrary->payload['run_state']['history_path'];
            $arbitraryProjection = json_decode(
                (string) file_get_contents($arbitraryProjectionPath),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $replacementManifestId = str_repeat('1', 64);
            $replacementHandoffId = str_repeat('2', 64);
            copy(
                $output.'/'.$arbitraryProjection['prerequisite_evidence_manifest_id'].'.evidence-manifest.json',
                $output.'/'.$replacementManifestId.'.evidence-manifest.json'
            );
            copy(
                $output.'/'.$arbitraryProjection['prerequisite_phase_handoff_id'].'.phase-handoff.json',
                $output.'/'.$replacementHandoffId.'.phase-handoff.json'
            );

            foreach ([$arbitraryHistoryPath, $arbitraryProjectionPath] as $statePath) {
                $stateBytes = (string) file_get_contents($statePath);
                $stateBytes = str_replace(
                    [
                        $arbitraryProjection['prerequisite_evidence_manifest_id'],
                        $arbitraryProjection['prerequisite_phase_handoff_id']
                    ],
                    [$replacementManifestId, $replacementHandoffId],
                    $stateBytes
                );
                file_put_contents($statePath, $stateBytes);
            }

            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $this->service($ports, $arbitraryRunId)
                    ->prepare($planPath, $root.'/.runs', $arbitraryRunId)->payload['findings'][0]['id']
            );

            $missing = $service->prepare($planPath, $root.'/.runs', str_repeat('c', 64));
            self::assertSame('release.prepare.resume_state_missing', $missing->payload['findings'][0]['id']);

            $missingRunId = hash('sha256', 'missing-without-evidence');
            $unwritableEvidence = new readonly class ($ports) implements PlanArtifactStore {
                /**
                 * Constructs one write-refusing evidence store.
                 */
                public function __construct(private PlanArtifactStore $delegate)
                {
                }

                /**
                 * Delegates canonical directory authority
                 */
                public function resolveRunsDirectory(
                    string $path,
                    string $runsDirectory
                ): RunsDirectoryResolutionResult {
                    return $this->delegate->resolveRunsDirectory($path, $runsDirectory);
                }

                /**
                 * Delegates immutable artifact reads
                 */
                public function readArtifact(
                    CanonicalRunsDirectory $directory,
                    string $filename
                ): PlanArtifactReadResult {
                    return $this->delegate->readArtifact($directory, $filename);
                }

                /**
                 * Refuses every immutable evidence write
                 */
                public function writeArtifact(
                    CanonicalRunsDirectory $directory,
                    string $filename,
                    string $contents
                ): PlanArtifactWriteResult {
                    if ($this->delegate instanceof DeterministicReleaseBoundaryFake) {
                        $this->delegate->recordObservedEffect(
                            ReleaseEffect::FILESYSTEM_WRITE,
                            ReleaseBoundaryOutcome::FAILURE
                        );
                    }

                    return PlanArtifactWriteResult::stopped(ReleaseBoundaryOutcome::FAILURE);
                }
            };
            $missingWithoutEvidence = $this->service(
                $ports,
                $missingRunId,
                artifacts: $unwritableEvidence
            )->prepare($planPath, $root.'/.runs', $missingRunId);
            self::assertSame(5, $missingWithoutEvidence->exitCode);
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $missingWithoutEvidence->payload['findings'][0]['id']
            );
            self::assertArrayNotHasKey('artifacts', $missingWithoutEvidence->payload);
            self::assertDirectoryDoesNotExist($output.'/runs/'.$missingRunId);

            foreach (['conflict', 'advanced'] as $terminalReceipt) {
                $terminalPorts = new DeterministicReleaseBoundaryFake();
                self::assertTrue($terminalPorts->configureBaselineTagResolution('moving'));
                $terminalRunId = hash('sha256', 'terminal-artifact-'.$terminalReceipt);
                $terminalStore = $this->runStateStoreReturning(
                    $ports,
                    [],
                    [],
                    stop: ['status' => $terminalReceipt]
                );
                $terminal = $this->service(
                    $terminalPorts,
                    $terminalRunId,
                    artifacts: $unwritableEvidence,
                    runs: $terminalStore,
                    effects: $this->effectLedger([
                        [
                            'capability'   => 'filesystem',
                            'effect_class' => 'filesystem.inspect_runs_directory',
                            'outcome'      => 'success'
                        ],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                        ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                        ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure']
                    ])
                )->prepare($planPath, $root.'/.runs');
                self::assertSame(5, $terminal->exitCode);
                self::assertSame(
                    'release.prepare.evidence_persistence_failed',
                    $terminal->payload['findings'][0]['id']
                );
                self::assertArrayNotHasKey('artifacts', $terminal->payload);
            }

            $artifactlessRunId = hash('sha256', 'valid-artifactless-stop-receipt');
            $artifactlessReceipt = [
                'status'          => 'created',
                'sequence'        => 1,
                'state'           => 'evidence_indeterminate',
                'history_path'    => $output.'/runs/'.$artifactlessRunId.'/history.jsonl',
                'projection_path' => $output.'/runs/'.$artifactlessRunId.'/projection.json',
                'stop_code'       => 'artifact_indeterminate',
                'stop_state'      => 'evidence_indeterminate',
                'finding_id'      => 'release.prepare.evidence_persistence_failed',
                'next_action'     => 'repair_release_evidence_storage'
            ];
            $artifactlessPorts = new DeterministicReleaseBoundaryFake();
            self::assertTrue($artifactlessPorts->configureBaselineTagResolution('moving'));
            $artifactlessStore = $this->runStateStoreReturning(
                $artifactlessPorts,
                [],
                [],
                stop: $artifactlessReceipt
            );
            $artifactless = $this->service(
                $artifactlessPorts,
                $artifactlessRunId,
                artifacts: $unwritableEvidence,
                runs: $artifactlessStore,
                effects: $this->effectLedger([
                    [
                        'capability'   => 'filesystem',
                        'effect_class' => 'filesystem.inspect_runs_directory',
                        'outcome'      => 'success'
                    ],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                    ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success']
                ])
            )->prepare($planPath, $root.'/.runs');
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $artifactless->payload['findings'][0]['id']
            );
            self::assertSame(
                $artifactlessReceipt['history_path'],
                $artifactless->payload['run_state']['history_path']
            );

            $invalidArtifactlessRunId = hash('sha256', 'invalid-artifactless-stop-receipt');
            $invalidArtifactlessPorts = new DeterministicReleaseBoundaryFake();
            self::assertTrue($invalidArtifactlessPorts->configureBaselineTagResolution('moving'));
            $invalidArtifactlessStore = $this->runStateStoreReturning(
                $invalidArtifactlessPorts,
                [],
                [],
                stop: [
                    ...$artifactlessReceipt,
                    'history_path' => '/outside/history.jsonl'
                ]
            );
            $invalidArtifactless = $this->service(
                $invalidArtifactlessPorts,
                $invalidArtifactlessRunId,
                artifacts: $unwritableEvidence,
                runs: $invalidArtifactlessStore,
                effects: $this->effectLedger([
                    [
                        'capability'   => 'filesystem',
                        'effect_class' => 'filesystem.inspect_runs_directory',
                        'outcome'      => 'success'
                    ],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                    ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure']
                ])
            )->prepare($planPath, $root.'/.runs');
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $invalidArtifactless->payload['findings'][0]['id']
            );
            self::assertArrayNotHasKey('run_state', $invalidArtifactless->payload);

            $evidenceStopRunId = hash('sha256', 'persisted-evidence-stop');
            $evidenceStop = [
                'status'             => 'stopped',
                'sequence'           => 3,
                'state'              => 'evidence_indeterminate',
                'history_path'       => $output.'/runs/'.$evidenceStopRunId.'/history.jsonl',
                'projection_path'    => $output.'/runs/'.$evidenceStopRunId.'/projection.json',
                'stop_code'          => 'artifact_indeterminate',
                'stop_state'         => 'evidence_indeterminate',
                'finding_id'         => 'release.prepare.evidence_persistence_failed',
                'next_action'        => 'repair_release_evidence_storage',
                'resume_state'       => 'prepared',
                'resume_sequence'    => 2,
                'resume_next_action' => 'finalize_release_preparation_evidence'
            ];
            $persistentFailure = $this->runStateStoreReturning(
                $ports,
                $evidenceStop,
                [],
                recover: ['status' => 'conflict']
            );
            $stillStopped = $this->service(
                $ports,
                $evidenceStopRunId,
                artifacts: $unwritableEvidence,
                runs: $persistentFailure
            )->prepare($planPath, $root.'/.runs', $evidenceStopRunId);
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $stillStopped->payload['findings'][0]['id']
            );

            $repairedEvidence = $this->runStateStoreReturning(
                $ports,
                $evidenceStop,
                [],
                recover: ['status' => 'conflict']
            );
            $recoveryRace = $this->service($ports, $evidenceStopRunId, runs: $repairedEvidence)
                ->prepare($planPath, $root.'/.runs', $evidenceStopRunId);
            self::assertSame('release.prepare.resume_contention', $recoveryRace->payload['findings'][0]['id']);

            foreach (
                [
                    'support_policy_drift' => ['stale_plan', 'release.prepare.support_policy_drift'],
                    'approval_drift'       => ['authority_required', 'release.prepare.approval_authority_drift'],
                    'evidence_drift'       => ['stale_plan', 'release.prepare.evidence_authority_drift'],
                    'compatibility_drift'  => ['stale_plan', 'release.prepare.compatibility_authority_drift']
                ] as $authorityStatus => [$expectedStatus, $expectedFinding]
            ) {
                $runId = hash('sha256', 'authority-drift-'.$authorityStatus);
                $driftedAuthority = new DeterministicReleaseBoundaryFake();
                self::assertTrue($driftedAuthority->configurePlanAuthorityStatus($authorityStatus));
                $driftedResult = $this->service($driftedAuthority, $runId)
                    ->prepare($planPath, $root.'/.runs');

                self::assertSame($expectedStatus, $driftedResult->payload['status']);
                self::assertSame($expectedFinding, $driftedResult->payload['findings'][0]['id']);
                self::assertSame($plan->payload['plan_id'], $driftedResult->payload['plan_id']);
                self::assertSame($runId, $driftedResult->payload['run_id']);
                self::assertCount(1, $driftedResult->payload['next_action']);
            }

            foreach (
                [
                    'refused'   => [
                        'authority_required', 'release.prepare.plan_authority_refused',
                        3, 'obtain_current_release_authority'
                    ],
                    'failed'    => [
                        'policy_blocked', 'release.prepare.plan_authority_failed',
                        4, 'repair_release_authority_provider'
                    ],
                    'uncertain' => [
                        'evidence_indeterminate', 'release.prepare.plan_authority_uncertain',
                        5, 'reconcile_release_plan_authority'
                    ]
                ] as $authorityStatus => [$expectedStatus, $expectedFinding, $expectedExit, $expectedAction]
            ) {
                $runId = hash('sha256', 'authority-stop-'.$authorityStatus);
                $unavailableAuthority = new DeterministicReleaseBoundaryFake();
                self::assertTrue($unavailableAuthority->configurePlanAuthorityStatus($authorityStatus));
                self::assertTrue($unavailableAuthority->configureOutcome(
                    'authorization.check',
                    match ($authorityStatus) {
                        'refused' => 'refusal',
                        'failed' => 'failure',
                        default => 'uncertainty'
                    }
                ));
                $authorityResult = $this->service($unavailableAuthority, $runId)
                    ->prepare($planPath, $root.'/.runs');

                self::assertSame($expectedStatus, $authorityResult->payload['status']);
                self::assertSame($expectedFinding, $authorityResult->payload['findings'][0]['id']);
                self::assertSame($expectedExit, $authorityResult->exitCode);
                self::assertSame(['action' => $expectedAction], $authorityResult->payload['next_action']);
            }

            $directory = new CanonicalRunsDirectory(
                $output,
                $root.'/.runs'
            );
            $plannedRunId = str_repeat('4', 64);
            self::assertSame(
                'planned',
                $ports->createPlannedRun($directory, $plan->payload['plan_id'], $plannedRunId)['status']
            );
            self::assertSame(
                'release.prepare.resumed_completed',
                $this->service($ports, $plannedRunId)
                    ->prepare($planPath, $root.'/.runs', $plannedRunId)->payload['findings'][0]['id']
            );
            $changedDuringRevalidation = $this->runStateStoreReturning(
                $ports,
                [
                    [
                        'status'                     => 'planned',
                        'history_path'               => '/history',
                        'projection_path'            => '/projection',
                        'prepared_history_sha256'    => str_repeat('1', 64),
                        'prepared_projection_sha256' => str_repeat('2', 64)
                    ],
                    ['status' => 'conflict']
                ],
                []
            );
            self::assertSame(
                'release.prepare.resume_contention',
                $this->service($ports, str_repeat('6', 64), runs: $changedDuringRevalidation)
                    ->prepare($planPath, $root.'/.runs', str_repeat('6', 64))->payload['findings'][0]['id']
            );

            $invalidLateStopRunId = hash('sha256', 'invalid-late-stop');
            $invalidLateStop = $this->runStateStoreReturning(
                $ports,
                [
                    ['status' => 'planned'],
                    ['status' => 'stopped', 'state' => 'policy_blocked']
                ],
                []
            );
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $this->service($ports, $invalidLateStopRunId, runs: $invalidLateStop)
                    ->prepare($planPath, $root.'/.runs', $invalidLateStopRunId)->payload['findings'][0]['id']
            );

            $unrepairedLateStopRunId = hash('sha256', 'unrepaired-late-stop');
            $unrepairedArtifactSeeder = new DeterministicReleaseBoundaryFake(
                runStateFailureOnce: 'runs_directory'
            );
            self::assertSame(
                'release.prepare.state_persistence_failed',
                $this->service($unrepairedArtifactSeeder, $unrepairedLateStopRunId)
                    ->prepare($planPath, $root.'/.runs')->payload['findings'][0]['id']
            );
            $unrepairedLateStop = $this->runStateStoreReturning(
                $ports,
                [
                    ['status' => 'planned'],
                    [
                        'status'      => 'stopped',
                        'state'       => 'policy_blocked',
                        'stop_code'   => 'failed',
                        'stop_state'  => 'policy_blocked',
                        'finding_id'  => 'release.prepare.state_persistence_failed',
                        'next_action' => 'repair_release_run_storage'
                    ]
                ],
                []
            );
            $unrepairedLateEffects = $this->effectLedger([
                [
                    'capability'   => 'filesystem',
                    'effect_class' => 'filesystem.inspect_runs_directory',
                    'outcome'      => 'success'
                ],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                ['capability' => 'authorization', 'effect_class' => 'authorization.check', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
            ]);
            self::assertSame(
                'release.prepare.artifacts_indeterminate',
                $this->service(
                    $ports,
                    $unrepairedLateStopRunId,
                    runs: $unrepairedLateStop,
                    effects: $unrepairedLateEffects
                )->prepare($planPath, $root.'/.runs', $unrepairedLateStopRunId)->payload['findings'][0]['id']
            );

            $missingDigestRunId = str_repeat('5', 64);
            $missingDigests = $this->runStateStoreReturning(
                $ports,
                [
                    'status'          => 'planned',
                    'history_path'    => '/history',
                    'projection_path' => '/projection',
                    'sequence'        => 1,
                    'state'           => 'planned'
                ],
                ['status' => 'created', 'sequence' => 2, 'state' => 'prepared'],
                stop: ['status' => 'created']
            );
            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $this->service($ports, $missingDigestRunId, runs: $missingDigests)
                    ->prepare($planPath, $root.'/.runs', $missingDigestRunId)->payload['findings'][0]['id']
            );
            $verifiedWithoutDigests = $this->runStateStoreReturning(
                $ports,
                [
                    'status'            => 'verified',
                    'history_path'      => '/history',
                    'projection_path'   => '/projection',
                    'history_sha256'    => str_repeat('1', 64),
                    'projection_sha256' => str_repeat('2', 64)
                ],
                []
            );
            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $this->service($ports, str_repeat('7', 64), runs: $verifiedWithoutDigests)
                    ->prepare($planPath, $root.'/.runs', str_repeat('7', 64))->payload['findings'][0]['id']
            );
            $verifiedWithInvalidBindings = $this->runStateStoreReturning(
                $ports,
                [
                    'status'                            => 'verified',
                    'history_path'                      => '/history',
                    'projection_path'                   => '/projection',
                    'history_sha256'                    => str_repeat('1', 64),
                    'projection_sha256'                 => str_repeat('2', 64),
                    'prepared_history_sha256'           => 1,
                    'prepared_projection_sha256'        => 2,
                    'prerequisite_evidence_manifest_id' => 3,
                    'prerequisite_phase_handoff_id'     => 4
                ],
                []
            );
            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $this->service($ports, str_repeat('7', 64), runs: $verifiedWithInvalidBindings)
                    ->prepare($planPath, $root.'/.runs', str_repeat('7', 64))->payload['findings'][0]['id']
            );
            $verifiedWithMismatchedBindings = $this->runStateStoreReturning(
                $ports,
                [
                    'status'                            => 'verified',
                    'state'                             => 'package_ready',
                    'sequence'                          => 3,
                    'history_path'                      => '/history',
                    'projection_path'                   => '/projection',
                    'history_sha256'                    => str_repeat('1', 64),
                    'projection_sha256'                 => str_repeat('2', 64),
                    'prepared_history_sha256'           => str_repeat('3', 64),
                    'prepared_projection_sha256'        => str_repeat('4', 64),
                    'prerequisite_evidence_manifest_id' => str_repeat('5', 64),
                    'prerequisite_phase_handoff_id'     => str_repeat('6', 64)
                ],
                [],
                stop: ['status' => 'created']
            );
            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $this->service($ports, str_repeat('7', 64), runs: $verifiedWithMismatchedBindings)
                    ->prepare($planPath, $root.'/.runs', str_repeat('7', 64))->payload['findings'][0]['id']
            );

            $stoppedRunId = str_repeat('4', 64);
            $stoppedWithoutArtifacts = $this->runStateStoreReturning(
                $ports,
                [
                    'status'          => 'stopped',
                    'history_path'    => '/history',
                    'projection_path' => '/projection',
                    'stop_code'       => 'artifact_indeterminate',
                    'stop_state'      => 'evidence_indeterminate',
                    'finding_id'      => 'release.prepare.evidence_persistence_failed',
                    'next_action'     => 'repair_release_evidence_storage'
                ],
                []
            );
            $replayedExhaustion = $this->service($ports, $stoppedRunId, runs: $stoppedWithoutArtifacts)
                ->prepare($planPath, $root.'/.runs', $stoppedRunId);
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $replayedExhaustion->payload['findings'][0]['id']
            );
            self::assertSame([], $replayedExhaustion->payload['verified_postconditions']);
            self::assertArrayNotHasKey('run_state', $replayedExhaustion->payload);

            $unknownStopRunId = str_repeat('2', 64);
            $unknownStop = $this->runStateStoreReturning(
                $ports,
                [
                    'status'             => 'stopped',
                    'sequence'           => 2,
                    'state'              => 'policy_blocked',
                    'history_path'       => $output.'/runs/'.$unknownStopRunId.'/history.jsonl',
                    'projection_path'    => $output.'/runs/'.$unknownStopRunId.'/projection.json',
                    'stop_code'          => 'unrecognized_stop',
                    'stop_state'         => 'policy_blocked',
                    'finding_id'         => 'release.prepare.baseline_tag_missing',
                    'next_action'        => 'repair_baseline_authority',
                    'resume_state'       => 'planned',
                    'resume_sequence'    => 1,
                    'resume_next_action' => 'prepare_release_run'
                ],
                [],
                recover: ['status' => 'created']
            );
            $unknownResult = $this->service($ports, $unknownStopRunId, runs: $unknownStop)
                ->prepare($planPath, $root.'/.runs', $unknownStopRunId);
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $unknownResult->payload['findings'][0]['id']
            );
            self::assertSame([], $unknownResult->payload['verified_postconditions']);

            $invalidStoppedRunId = str_repeat('5', 64);
            $invalidStopped = $this->runStateStoreReturning(
                $ports,
                [
                    'status'                            => 'stopped',
                    'state'                             => 'evidence_indeterminate',
                    'history_path'                      => '/history',
                    'projection_path'                   => '/projection',
                    'stop_code'                         => 'artifact_indeterminate',
                    'stop_state'                        => 'evidence_indeterminate',
                    'finding_id'                        => 'release.prepare.artifacts_indeterminate',
                    'next_action'                       => 'reconcile_named_release_run',
                    'prerequisite_evidence_manifest_id' => str_repeat('a', 64),
                    'prerequisite_phase_handoff_id'     => str_repeat('b', 64)
                ],
                []
            );
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $this->service($ports, $invalidStoppedRunId, runs: $invalidStopped)
                    ->prepare($planPath, $root.'/.runs', $invalidStoppedRunId)->payload['findings'][0]['id']
            );

            $failedRepairArtifacts = new DeterministicReleaseBoundaryFake(['filesystem.write' => 'failure']);
            $validReconcileStopRunId = str_repeat('3', 64);
            $validReconcileStop = $this->runStateStoreReturning(
                $failedRepairArtifacts,
                [
                    'status'             => 'stopped',
                    'sequence'           => 2,
                    'state'              => 'evidence_indeterminate',
                    'history_path'       => $output.'/runs/'.$validReconcileStopRunId.'/history.jsonl',
                    'projection_path'    => $output.'/runs/'.$validReconcileStopRunId.'/projection.json',
                    'stop_code'          => 'artifact_indeterminate',
                    'stop_state'         => 'evidence_indeterminate',
                    'finding_id'         => 'release.prepare.artifacts_indeterminate',
                    'next_action'        => 'reconcile_named_release_run',
                    'resume_state'       => 'planned',
                    'resume_sequence'    => 1,
                    'resume_next_action' => 'prepare_release_run'
                ],
                []
            );
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $this->service($failedRepairArtifacts, $validReconcileStopRunId, runs: $validReconcileStop)
                    ->prepare($planPath, $root.'/.runs', $validReconcileStopRunId)->payload['findings'][0]['id']
            );

            $missingStoppedBinding = $this->runStateStoreReturning(
                $ports,
                [
                    'status'          => 'stopped',
                    'history_path'    => '/history',
                    'projection_path' => '/projection',
                    'stop_code'       => 'artifact_indeterminate'
                ],
                []
            );
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $this->service($ports, str_repeat('6', 64), runs: $missingStoppedBinding)
                    ->prepare($planPath, $root.'/.runs', str_repeat('6', 64))->payload['findings'][0]['id']
            );

            $resumeArtifactFailure = new DeterministicReleaseBoundaryFake(['filesystem.write' => 'failure']);
            $resumeArtifactRunId = str_repeat('8', 64);
            self::assertSame(
                'created',
                $resumeArtifactFailure->createPreparedRun(
                    $directory,
                    $plan->payload['plan_id'],
                    $resumeArtifactRunId
                )['status']
            );
            $resumeArtifactStop = $this->service($resumeArtifactFailure, $resumeArtifactRunId)
                ->prepare($planPath, $root.'/.runs', $resumeArtifactRunId);
            self::assertSame(5, $resumeArtifactStop->exitCode);
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $resumeArtifactStop->payload['findings'][0]['id']
            );

            $resumeFinalizeRunId = str_repeat('9', 64);
            self::assertSame(
                'created',
                $ports->createPreparedRun($directory, $plan->payload['plan_id'], $resumeFinalizeRunId)['status']
            );
            $resumeFinalizeFailure = $this->runStateStoreReturning(
                $ports,
                [],
                [],
                ['status' => 'conflict']
            );
            $resumeFinalizeStop = $this->service($ports, $resumeFinalizeRunId, runs: $resumeFinalizeFailure)
                ->prepare($planPath, $root.'/.runs', $resumeFinalizeRunId);
            self::assertSame(
                'release.prepare.resume_contention',
                $resumeFinalizeStop->payload['findings'][0]['id']
            );
            $resumeFinalizeManifest = json_decode(
                (string) file_get_contents(
                    $resumeFinalizeStop->payload['artifacts']['evidence_manifest']['path']
                ),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertSame('conflict', $resumeFinalizeManifest['status']);
            self::assertSame(
                ['action' => 'retry_named_resume_after_writer_completes'],
                $resumeFinalizeManifest['next_action']
            );
            self::assertSame(
                [
                    'finding_id' => 'release.prepare.resume_contention',
                    'status'     => 'conflict'
                ],
                $resumeFinalizeManifest['stop_state']
            );
            $publishFailureRunId = str_repeat('6', 64);
            $publishFailure = $this->runStateStoreReturning(
                $ports,
                [
                    'status'                     => 'planned',
                    'history_path'               => '/history',
                    'projection_path'            => '/projection',
                    'prepared_history_sha256'    => str_repeat('7', 64),
                    'prepared_projection_sha256' => str_repeat('8', 64)
                ],
                ['status' => 'indeterminate']
            );
            self::assertSame(
                'release.prepare.state_persistence_indeterminate',
                $this->service($ports, $publishFailureRunId, runs: $publishFailure)
                    ->prepare($planPath, $root.'/.runs', $publishFailureRunId)->payload['findings'][0]['id']
            );
            $staleRunId = str_repeat('d', 64);
            $ports->createPreparedRun($directory, str_repeat('a', 64), $staleRunId);
            self::assertSame(
                'release.prepare.resume_plan_drift',
                $service->prepare($planPath, $root.'/.runs', $staleRunId)->payload['findings'][0]['id']
            );

            $indeterminateRunId = str_repeat('e', 64);
            $state = $ports->createPreparedRun($directory, $plan->payload['plan_id'], $indeterminateRunId);
            file_put_contents($state['projection_path'], "corrupt\n");
            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $service->prepare($planPath, $root.'/.runs', $indeterminateRunId)->payload['findings'][0]['id']
            );

            $lock = fopen($output.'/runs/'.$preparedRunId.'/.writer.lock', 'c');
            self::assertIsResource($lock);
            self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

            try {
                self::assertSame(
                    'release.prepare.resume_contention',
                    $service->prepare($planPath, $root.'/.runs', $preparedRunId)->payload['findings'][0]['id']
                );
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

            unlink($prepared->payload['artifacts']['evidence_manifest']['path']);
            $lostEvidence = $service->prepare($planPath, $root.'/.runs', $preparedRunId);
            self::assertSame(
                'release.prepare.resume_state_indeterminate',
                $lostEvidence->payload['findings'][0]['id']
            );
            self::assertFileDoesNotExist($prepared->payload['artifacts']['evidence_manifest']['path']);
            self::assertNotSame(
                $prepared->payload['artifacts']['evidence_manifest']['manifest_id'],
                $lostEvidence->payload['artifacts']['evidence_manifest']['manifest_id']
            );

            $secondRunId = str_repeat('2', 64);
            self::assertSame(0, $this->service($ports, $secondRunId)->prepare($planPath, $root.'/.runs')->exitCode);
            $resumedArtifactFailure = new DeterministicReleaseBoundaryFake(
                postPublishFailureOnce: 'fsync',
                postPublishFinalOnce: 'missing'
            );
            self::assertSame(
                'release.prepare.already_satisfied',
                $this->service($resumedArtifactFailure, $secondRunId)
                    ->prepare($planPath, $root.'/.runs', $secondRunId)->payload['findings'][0]['id']
            );

            $thirdRunId = str_repeat('3', 64);
            $third = $this->service($ports, $thirdRunId)->prepare($planPath, $root.'/.runs');
            self::assertSame(0, $third->exitCode);
            unlink($third->payload['artifacts']['evidence_manifest']['path']);
            $failedFinalEvidence = new DeterministicReleaseBoundaryFake(['filesystem.write' => 'failure']);
            $failedFinalStop = $this->service($failedFinalEvidence, $thirdRunId)
                ->prepare($planPath, $root.'/.runs', $thirdRunId);
            self::assertSame(5, $failedFinalStop->exitCode);
            self::assertSame(
                'release.prepare.evidence_persistence_failed',
                $failedFinalStop->payload['findings'][0]['id']
            );

            $fourthRunId = str_repeat('a', 64);
            $fourth = $this->service($ports, $fourthRunId)->prepare($planPath, $root.'/.runs');
            self::assertSame(0, $fourth->exitCode);
            $otherPlanId = str_repeat('c', 64);

            foreach (['history_path', 'projection_path'] as $statePath) {
                $path = $fourth->payload['run_state'][$statePath];
                file_put_contents(
                    $path,
                    str_replace($plan->payload['plan_id'], $otherPlanId, (string) file_get_contents($path))
                );
            }

            self::assertSame(
                'release.prepare.resume_plan_drift',
                $service->prepare($planPath, $root.'/.runs', $fourthRunId)->payload['findings'][0]['id']
            );
        } finally {
            new Filesystem()->remove($output);
        }
    }

    /**
     * Proves service resume can recover after recovery proof loss was append-only compensated
     */
    public function test_that_compensated_stopped_truth_can_be_recovered_on_the_next_resume(): void
    {
        $root = dirname(__DIR__, 3);
        $output = $root.'/.runs/fight-common-compensated-stop-resume-'.bin2hex(random_bytes(8));
        mkdir($output, 0700, true);
        $ports = new DeterministicReleaseBoundaryFake();
        $json = new CanonicalJson();
        $candidate = json_decode(
            (string) file_get_contents($root.'/tests/Fixture/Release/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        try {
            $plan = new ReleasePlanService(
                $ports,
                $ports,
                $ports,
                $ports,
                $json,
                new ReleasePlanFactory(),
                new ReleaseResultFactory($ports)
            )->plan($candidate, $output, $root.'/.runs');
            $planPath = $plan->payload['artifact']['path'];
            $runId = hash('sha256', 'service-compensated-stop-recovery');
            $historyPath = $output.'/runs/'.$runId.'/history.jsonl';
            $projectionPath = $output.'/runs/'.$runId.'/projection.json';
            $stopped = [
                'status'             => 'stopped',
                'sequence'           => 5,
                'state'              => 'evidence_indeterminate',
                'history_path'       => $historyPath,
                'projection_path'    => $projectionPath,
                'stop_code'          => 'artifact_indeterminate',
                'stop_state'         => 'evidence_indeterminate',
                'finding_id'         => 'release.prepare.artifacts_indeterminate',
                'next_action'        => 'reconcile_named_release_run',
                'resume_state'       => 'prepared',
                'resume_sequence'    => 2,
                'resume_next_action' => 'finalize_release_preparation_evidence'
            ];
            $evidencePending = [
                'status'            => 'evidence_pending',
                'sequence'          => 6,
                'state'             => 'prepared',
                'history_path'      => $historyPath,
                'projection_path'   => $projectionPath,
                'history_sha256'    => str_repeat('a', 64),
                'projection_sha256' => str_repeat('b', 64)
            ];
            $runs = $this->runStateStoreReturning(
                $ports,
                [$stopped, $evidencePending, $evidencePending],
                [],
                [
                    'status'          => 'created',
                    'sequence'        => 7,
                    'state'           => 'prepared',
                    'history_path'    => $historyPath,
                    'projection_path' => $projectionPath
                ],
                recover: [
                    'status'          => 'created',
                    'sequence'        => 6,
                    'state'           => 'prepared',
                    'history_path'    => $historyPath,
                    'projection_path' => $projectionPath,
                    'next_action'     => 'finalize_release_preparation_evidence'
                ]
            );
            $recovered = $this->service($ports, $runId, runs: $runs)
                ->prepare($planPath, $root.'/.runs', $runId);
            self::assertSame(0, $recovered->exitCode);
            self::assertSame('release.prepare.resumed_completed', $recovered->payload['findings'][0]['id']);
        } finally {
            new Filesystem()->remove($output);
        }
    }

    /**
     * Builds one preparation service around an exact test run identity
     */
    private function service(
        DeterministicReleaseBoundaryFake $ports,
        string $runId,
        ?HashingPort $hashing = null,
        ?PlanArtifactStore $artifacts = null,
        ?RunStateStore $runs = null,
        ?ReleaseEffectLedger $effects = null
    ): ReleasePreparationService {
        $runIds = new readonly class ($runId) implements RunIdGenerator {
            /**
             * Constructs one exact test run identity
             */
            public function __construct(private string $runId)
            {
            }

            /**
             * Returns the exact configured identity
             */
            public function generate(): string
            {
                return $this->runId;
            }
        };

        return new ReleasePreparationService(
            $artifacts ?? $ports,
            $runs ?? $ports,
            $ports,
            $runIds,
            $ports,
            $hashing ?? $ports,
            $effects ?? $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            new ReleaseResultFactory($ports)
        );
    }

    /**
     * Returns one exact immutable effect projection for a synthetic split-port scenario
     *
     * @param array $effects Exact causal effects.
     *
     * @phpstan-param list<array{capability: string, effect_class: string, outcome: string}> $effects
     */
    private function effectLedger(array $effects): ReleaseEffectLedger
    {
        return new readonly class ($effects) implements ReleaseEffectLedger {
            /**
             * @param list<array{capability: string, effect_class: string, outcome: string}> $effects
             */
            public function __construct(private array $effects)
            {
            }

            /**
             * Refuses test reconfiguration
             */
            public function configureOutcome(string $effectClass, string $outcome): bool
            {
                return false;
            }

            /**
             * Returns the exact configured causal ledger
             */
            public function effects(): array
            {
                return $this->effects;
            }
        };
    }

    /**
     * Returns a deterministic hasher that stops or mismatches on one exact invocation
     */
    private function hashingStopOn(int $stopOn, bool $mismatch = false): HashingPort
    {
        return new class ($stopOn, $mismatch) implements HashingPort {
            private int $calls = 0;

            /**
             * Constructs one exact hashing failure
             */
            public function __construct(private readonly int $stopOn, private readonly bool $mismatch)
            {
            }

            /**
             * Returns a digest except at the configured invocation
             */
            public function sha256(string $contents): ReleaseBoundaryOperationResult
            {
                ++$this->calls;

                if ($this->calls === $this->stopOn) {
                    if ($this->mismatch) {
                        return ReleaseBoundaryOperationResult::success(str_repeat('0', 64));
                    }

                    return ReleaseBoundaryOperationResult::stopped(ReleaseBoundaryOutcome::FAILURE);
                }

                return ReleaseBoundaryOperationResult::success(hash('sha256', $contents));
            }
        };
    }

    /**
     * Returns a run-state boundary with exact resume and publication receipts
     *
     * @param RunStateStore $delegate Delegate for unused operations.
     * @param array<string, mixed>|list<array<string, mixed>> $resume Resume receipt sequence.
     * @param array<string, mixed>      $publish  Publication receipt.
     * @param array<string, mixed>|null $finalize Finalization receipt.
     * @param array<string, mixed>|null $stop     Stop-publication receipt.
     * @param array<string, mixed>|null $recover  Stop-recovery receipt.
     * @param array<string, mixed>|null $create   Planned-creation receipt.
     */
    private function runStateStoreReturning(
        RunStateStore $delegate,
        array $resume,
        array $publish,
        ?array $finalize = null,
        ?array $stop = null,
        ?array $recover = null,
        ?array $create = null
    ): RunStateStore {
        return new class ($delegate, $resume, $publish, $finalize, $stop, $recover, $create) implements RunStateStore {
            private int $resumeCalls = 0;

            /**
             * @param RunStateStore $delegate Delegate for unused operations.
             * @param array<string, mixed>|list<array<string, mixed>> $resume Exact resume receipt.
             * @param array<string, mixed> $publish Exact publication receipt.
             * @param array<string, mixed>|null $finalize Exact finalization receipt.
             * @param array<string, mixed>|null $stop Exact stop receipt.
             * @param array<string, mixed>|null $recover Exact recovery receipt.
             * @param array<string, mixed>|null $create Exact planned-creation receipt.
             */
            public function __construct(
                private readonly RunStateStore $delegate,
                private array $resume,
                private readonly array $publish,
                private readonly ?array $finalize,
                private readonly ?array $stop,
                private readonly ?array $recover,
                private readonly ?array $create
            ) {
            }

            /**
             * Delegates planned-run creation
             */
            public function createPlannedRun(
                CanonicalRunsDirectory $directory,
                string $planId,
                string $runId
            ): array {
                if ($this->create !== null) {
                    $this->recordReceiptEffect('create', $this->create);
                }

                /** @phpstan-ignore return.type */
                return $this->create ?? $this->delegate->createPlannedRun($directory, $planId, $runId);
            }

            /**
             * Returns the exact prepared-publication receipt
             */
            public function publishPreparedRun(
                CanonicalRunsDirectory $directory,
                string $planId,
                string $runId,
                int $expectedSequence,
                string $expectedState
            ): array {
                if ($this->publish === []) {
                    return $this->delegate->publishPreparedRun(
                        $directory,
                        $planId,
                        $runId,
                        $expectedSequence,
                        $expectedState
                    );
                }

                $this->recordReceiptEffect('publish', $this->publish);

                /** @phpstan-ignore return.type */
                return $this->publish;
            }

            /**
             * Delegates evidence-backed prepared-state finalization
             */
            public function finalizePreparedRun(
                CanonicalRunsDirectory $directory,
                string $planId,
                string $runId,
                string $manifestId,
                string $handoffId,
                int $expectedSequence,
                string $expectedState
            ): array {
                if ($this->finalize !== null) {
                    $this->recordReceiptEffect('finalize', $this->finalize);
                }

                /** @phpstan-ignore return.type */
                return $this->finalize ?? $this->delegate->finalizePreparedRun(
                    $directory,
                    $planId,
                    $runId,
                    $manifestId,
                    $handoffId,
                    $expectedSequence,
                    $expectedState
                );
            }

            /**
             * Delegates classified preparation-stop publication
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
            ): array {
                if ($this->stop !== null) {
                    $this->recordReceiptEffect('stop', $this->stop);

                    /** @phpstan-ignore return.type */
                    return $this->stop;
                }

                return $this->delegate->publishPreparationStop(
                    $directory,
                    $planId,
                    $runId,
                    $stopCode,
                    $stopState,
                    $findingId,
                    $nextAction,
                    $manifestId,
                    $handoffId,
                    $expectedSequence,
                    $expectedState
                );
            }

            /**
             * Delegates complete prepared-run creation
             */
            public function createPreparedRun(
                CanonicalRunsDirectory $directory,
                string $planId,
                string $runId
            ): array {
                return $this->delegate->createPreparedRun($directory, $planId, $runId);
            }

            /**
             * Returns the exact resume receipt
             */
            public function resumePreparedRun(
                CanonicalRunsDirectory $directory,
                string $planId,
                string $runId
            ): array {
                if ($this->resume === []) {
                    return $this->delegate->resumePreparedRun($directory, $planId, $runId);
                }

                if (array_is_list($this->resume) && is_array($this->resume[0])) {
                    $receipt = $this->resume[min($this->resumeCalls, count($this->resume) - 1)];
                    ++$this->resumeCalls;
                    $this->recordReceiptEffect('resume', $receipt);

                    return $receipt;
                }

                $this->recordReceiptEffect('resume', $this->resume);

                /** @phpstan-ignore return.type */
                return $this->resume;
            }

            /**
             * Delegates exact persisted-stop recovery
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
            ): array {
                if ($this->recover !== null) {
                    $this->recordReceiptEffect('recover', $this->recover);

                    /** @phpstan-ignore return.type */
                    return $this->recover;
                }

                return $this->delegate->recoverPreparationStop(
                    $directory,
                    $planId,
                    $runId,
                    $stopSequence,
                    $stopCode,
                    $stopState,
                    $findingId,
                    $nextAction,
                    $repairManifestId,
                    $repairHandoffId
                );
            }

            /**
             * Records the synthetic receipt at the instant its boundary operation returns
             *
             * @param string               $operation Exact run-state operation.
             * @param array<string, mixed> $receipt Exact synthetic receipt.
             */
            private function recordReceiptEffect(string $operation, array $receipt): void
            {
                if (!$this->delegate instanceof DeterministicReleaseBoundaryFake) {
                    return;
                }

                $status = $receipt['status'] ?? null;
                $malformedCreated = $status === 'created'
                    && $operation === 'publish'
                    && (
                        !is_int($receipt['sequence'] ?? null)
                        || !is_string($receipt['state'] ?? null)
                        || !is_string($receipt['history_sha256'] ?? null)
                        || !is_string($receipt['projection_sha256'] ?? null)
                    );
                $malformedResume = $operation === 'resume'
                    && in_array($status, ['planned', 'evidence_pending', 'verified', 'stopped'], true)
                    && (
                        !is_int($receipt['sequence'] ?? null)
                        || !is_string($receipt['state'] ?? null)
                        || !is_string($receipt['history_path'] ?? null)
                        || !is_string($receipt['projection_path'] ?? null)
                        || ($status === 'planned' && $receipt['state'] !== 'planned')
                        || (in_array($status, ['evidence_pending', 'verified'], true)
                            && $receipt['state'] !== 'prepared')
                        || ($status === 'stopped' && ($receipt['stop_code'] ?? null) === 'unrecognized_stop')
                    );
                $outcome = match (true) {
                    $malformedCreated, $malformedResume => ReleaseBoundaryOutcome::UNCERTAINTY,
                    default => match ($status) {
                        'planned', 'created', 'verified', 'evidence_pending', 'stopped' =>
                            ReleaseBoundaryOutcome::SUCCESS,
                        'conflict' => ReleaseBoundaryOutcome::REFUSAL,
                        'failed' => ReleaseBoundaryOutcome::FAILURE,
                        default => ReleaseBoundaryOutcome::UNCERTAINTY
                    }
                };
                $effect = $operation === 'resume' ? ReleaseEffect::FILESYSTEM_READ : ReleaseEffect::FILESYSTEM_WRITE;
                $this->delegate->recordObservedEffect($effect, $outcome);
            }
        };
    }
}
