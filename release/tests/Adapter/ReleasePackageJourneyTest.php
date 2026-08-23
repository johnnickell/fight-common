<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\CanonicalJson;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\PlanArtifactReadResult;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\PlanArtifactWriteResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOperationResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\RunsDirectoryResolutionResult;
use Fight\Release\Application\MachineResult;
use Fight\Release\Application\ReleaseCertificationArtifactFactory;
use Fight\Release\Application\ReleaseCertificationService;
use Fight\Release\Application\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers the complete package journey through the bin/release command */
#[CoversClass(ReleaseCertificationArtifactFactory::class)]
#[CoversClass(ReleaseCertificationService::class)]
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
#[CoversClass(MachineResult::class)]
#[CoversClass(ReleaseResultFactory::class)]
class ReleasePackageJourneyTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    /**
     * Covers certification execution in-process, including success, durable stops, and invalid inputs.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_certification_revalidates_governed_artifacts_before_it_reports_an_outcome(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-direct-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $package = $this->livePackage($context, $context['artifacts']);
            $handoff = $package['artifacts']['certification_handoff'];
            $evidence = $this->certificationEvidence($handoff);

            $certified = $this->certificationService(new DeterministicReleaseBoundaryFake())->certify(
                $handoff['path'],
                $evidence['path'],
                $runtimeRoot.'/.runs'
            );

            self::assertSame(0, $certified->exitCode);
            self::assertSame('certified', $certified->payload['status']);
            self::assertSame('certification_manifest', array_key_first($certified->payload['artifacts']));

            $stopContext = $this->planAndPrepare($runtimeRoot.'/stop');
            $stopPackage = $this->livePackage($stopContext, $stopContext['artifacts']);
            $stopHandoff = $stopPackage['artifacts']['certification_handoff'];
            $stopEvidence = $this->certificationEvidence($stopHandoff);
            $payload = json_decode((string) file_get_contents($stopEvidence['path']), true, flags: JSON_THROW_ON_ERROR);
            $payload['lanes']['quality']['outcome'] = 'failed';
            unset($payload['evidence_id']);
            $evidenceId = hash('sha256', (new CanonicalJson())->encode($payload));
            $failedEvidencePath = dirname($stopHandoff['path']).'/'.$evidenceId.'.certification-evidence.json';
            file_put_contents(
                $failedEvidencePath,
                (new CanonicalJson())->encode([...$payload, 'evidence_id' => $evidenceId]).PHP_EOL
            );

            $stopHashFailure = new DeterministicReleaseBoundaryFake();
            self::assertSame(2, $this->certificationService(
                $stopHashFailure,
                $this->hashFailureOn($stopHashFailure, 3)
            )->certify($stopHandoff['path'], $failedEvidencePath, $runtimeRoot.'/stop/.runs')->exitCode);

            $stopWriteFailure = new DeterministicReleaseBoundaryFake();
            $stopWriteFailure->configureOutcome('filesystem.write', 'failure');
            self::assertSame(2, $this->certificationService($stopWriteFailure)->certify(
                $stopHandoff['path'],
                $failedEvidencePath,
                $runtimeRoot.'/stop/.runs'
            )->exitCode);

            $stopStateFailure = new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'state_publish');
            self::assertSame(2, $this->certificationService($stopStateFailure)->certify(
                $stopHandoff['path'],
                $failedEvidencePath,
                $runtimeRoot.'/stop/.runs'
            )->exitCode);

            $stopped = $this->certificationService(new DeterministicReleaseBoundaryFake())->certify(
                $stopHandoff['path'],
                $failedEvidencePath,
                $runtimeRoot.'/stop/.runs'
            );

            self::assertSame(4, $stopped->exitCode);
            self::assertSame('certification_failed', $stopped->payload['status']);
            self::assertSame('release.certification.lane_failed', $stopped->payload['findings'][0]['id']);

            $indeterminateContext = $this->planAndPrepare($runtimeRoot.'/indeterminate');
            $indeterminatePackage = $this->livePackage($indeterminateContext, $indeterminateContext['artifacts']);
            $indeterminateHandoff = $indeterminatePackage['artifacts']['certification_handoff'];
            $indeterminateEvidence = $this->certificationEvidence($indeterminateHandoff);
            $indeterminatePayload = json_decode(
                (string) file_get_contents($indeterminateEvidence['path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $indeterminatePayload['lanes']['quality']['outcome'] = 'queued';
            unset($indeterminatePayload['evidence_id']);
            $indeterminateId = hash('sha256', (new CanonicalJson())->encode($indeterminatePayload));
            $indeterminateEvidencePath = dirname($indeterminateHandoff['path']).'/'.$indeterminateId.'.certification-evidence.json';
            file_put_contents(
                $indeterminateEvidencePath,
                (new CanonicalJson())->encode([...$indeterminatePayload, 'evidence_id' => $indeterminateId]).PHP_EOL
            );

            $indeterminate = $this->certificationService(new DeterministicReleaseBoundaryFake())->certify(
                $indeterminateHandoff['path'],
                $indeterminateEvidencePath,
                $runtimeRoot.'/indeterminate/.runs'
            );

            self::assertSame(5, $indeterminate->exitCode);
            self::assertSame('evidence_indeterminate', $indeterminate->payload['status']);
            self::assertSame(
                'release.certification.evidence_indeterminate',
                $indeterminate->payload['findings'][0]['id']
            );

            $invalid = $this->certificationService(new DeterministicReleaseBoundaryFake())->certify(
                $runtimeRoot.'/outside-handoff.json',
                $evidence['path'],
                $runtimeRoot.'/.runs'
            );

            self::assertSame(2, $invalid->exitCode);
            self::assertSame('release.certification.handoff_invalid', $invalid->payload['findings'][0]['id']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers malformed canonical handoffs and evidence before any certification effect is recorded.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_certification_rejects_each_unverifiable_handoff_and_evidence_shape(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-invalid-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $package = $this->livePackage($context, $context['artifacts']);
            $handoff = $package['artifacts']['certification_handoff'];
            $evidence = $this->certificationEvidence($handoff);
            $directory = dirname($handoff['path']);
            $service = $this->certificationService(new DeterministicReleaseBoundaryFake());

            foreach ([
                'missing-handoff.json'      => null,
                'unterminated-handoff.json' => '{}',
                'invalid-handoff.json'      => "{\n",
                'list-handoff.json'         => "[]\n"
            ] as $name => $contents) {
                $path = $directory.'/'.$name;
                if ($contents !== null) {
                    file_put_contents($path, $contents);
                }

                self::assertSame(2, $service->certify($path, $evidence['path'], $runtimeRoot.'/.runs')->exitCode);
            }

            self::assertSame(2, $service->certify($handoff['path'], $runtimeRoot.'/outside-evidence.json', $runtimeRoot.'/.runs')->exitCode);

            foreach (['missing-evidence.json' => null, 'invalid-evidence.json' => '{', 'object-evidence.json' => '{}'] as $name => $contents) {
                $path = $directory.'/'.$name;
                if ($contents !== null) {
                    file_put_contents($path, $contents."\n");
                }

                self::assertSame(2, $service->certify($handoff['path'], $path, $runtimeRoot.'/.runs')->exitCode);
            }

            $payload = json_decode((string) file_get_contents($evidence['path']), true, flags: JSON_THROW_ON_ERROR);
            $payload['evidence_id'] = str_repeat('a', 64);
            $badIdentity = $directory.'/'.str_repeat('a', 64).'.certification-evidence.json';
            file_put_contents($badIdentity, (new CanonicalJson())->encode($payload).PHP_EOL);

            self::assertSame(2, $service->certify($handoff['path'], $badIdentity, $runtimeRoot.'/.runs')->exitCode);

            unset($payload['evidence_id']);
            $payload['classification_records']['structural-api'] = [];
            $badShapeId = hash('sha256', (new CanonicalJson())->encode($payload));
            $badShape = $directory.'/'.$badShapeId.'.certification-evidence.json';
            file_put_contents($badShape, (new CanonicalJson())->encode([...$payload, 'evidence_id' => $badShapeId]).PHP_EOL);

            self::assertSame(2, $service->certify($handoff['path'], $badShape, $runtimeRoot.'/.runs')->exitCode);

            $schemaPayload = json_decode((string) file_get_contents($evidence['path']), true, flags: JSON_THROW_ON_ERROR);
            unset($schemaPayload['evidence_id']);
            $schemaPayload['schema_version'] = 'invalid';
            $schemaId = hash('sha256', (new CanonicalJson())->encode($schemaPayload));
            $badSchema = $directory.'/'.$schemaId.'.certification-evidence.json';
            file_put_contents($badSchema, (new CanonicalJson())->encode([...$schemaPayload, 'evidence_id' => $schemaId]).PHP_EOL);

            self::assertSame(2, $service->certify($handoff['path'], $badSchema, $runtimeRoot.'/.runs')->exitCode);

            $lanePayload = json_decode((string) file_get_contents($evidence['path']), true, flags: JSON_THROW_ON_ERROR);
            unset($lanePayload['evidence_id']);
            $lanePayload['lanes']['quality'] = [];
            $laneId = hash('sha256', (new CanonicalJson())->encode($lanePayload));
            $badLane = $directory.'/'.$laneId.'.certification-evidence.json';
            file_put_contents($badLane, (new CanonicalJson())->encode([...$lanePayload, 'evidence_id' => $laneId]).PHP_EOL);

            self::assertSame(2, $service->certify($handoff['path'], $badLane, $runtimeRoot.'/.runs')->exitCode);

            $hashFailure = new DeterministicReleaseBoundaryFake();
            $hashFailure->configureOutcome('hashing.sha256', 'failure');
            self::assertSame(2, $this->certificationService($hashFailure)->certify(
                $handoff['path'],
                $evidence['path'],
                $runtimeRoot.'/.runs'
            )->exitCode);

            $writeFailure = new DeterministicReleaseBoundaryFake();
            $writeFailure->configureOutcome('filesystem.write', 'failure');
            self::assertSame(2, $this->certificationService($writeFailure)->certify(
                $handoff['path'],
                $evidence['path'],
                $runtimeRoot.'/.runs'
            )->exitCode);

            $manifestHashFailure = new DeterministicReleaseBoundaryFake();
            self::assertSame(2, $this->certificationService(
                $manifestHashFailure,
                $this->hashFailureOn($manifestHashFailure, 3)
            )->certify($handoff['path'], $evidence['path'], $runtimeRoot.'/.runs')->exitCode);

            $manifestReadFailure = new DeterministicReleaseBoundaryFake();
            self::assertSame(2, $this->certificationService(
                $manifestReadFailure,
                null,
                $this->readFailureOn($manifestReadFailure, 3)
            )->certify($handoff['path'], $evidence['path'], $runtimeRoot.'/.runs')->exitCode);

        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }
    // phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Covers that an unconfirmed certification transition cannot report a manifest success.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_certify_fails_closed_when_its_run_state_cannot_be_persisted(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-state-failure-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $package = $this->livePackage($context, $context['artifacts']);
            $handoff = $package['artifacts']['certification_handoff'];
            $evidence = $this->certificationEvidence($handoff);
            $ports = new DeterministicReleaseBoundaryFake(runStateFailureOnce: 'state_publish');
            $result = (new ReleaseCertificationService(
                $ports,
                $ports,
                $ports,
                $ports,
                new CanonicalJson(),
                new ReleaseResultFactory($ports)
            ))->certify($handoff['path'], $evidence['path'], $runtimeRoot.'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('policy_blocked', $result->payload['status']);
            self::assertSame(
                'release.certification.state_persistence_indeterminate',
                $result->payload['findings'][0]['id']
            );
            self::assertArrayNotHasKey('certification_manifest', $result->payload['artifacts'] ?? []);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers that a package handoff cannot fabricate certification lane outcomes.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_certify_requires_a_governed_evidence_artifact_in_addition_to_the_package_handoff(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-evidence-required-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $package = $this->livePackage($context, $context['artifacts']);
            $handoff = $package['artifacts']['certification_handoff'];
            $evidence = $this->certificationEvidence($handoff);
            $process = ReleaseProcess::create([
                dirname(__DIR__, 3).'/bin/release',
                'certify',
                '--handoff='.$handoff['path']
            ], $context['environment']);
            $process->run();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $result['exit_code']);
            self::assertSame('release.certification.inputs_required', $result['findings'][0]['id']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers durable fail-closed stops for failed and non-authoritative certification evidence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_certify_persists_failed_and_indeterminate_lane_stops_without_a_manifest(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-stops-'.bin2hex(random_bytes(8));

        try {
            $json = new CanonicalJson();

            foreach (
                [
                ['failed', 'certification_failed', 'release.certification.lane_failed'],
                ['raw_log_only', 'evidence_indeterminate', 'release.certification.evidence_indeterminate'],
                ['hosted_check_only', 'evidence_indeterminate', 'release.certification.evidence_indeterminate'],
                ['queued', 'evidence_indeterminate', 'release.certification.evidence_indeterminate'],
                [null, 'evidence_indeterminate', 'release.certification.evidence_indeterminate']
                ] as [$outcome, $status, $finding]
            ) {
                $context = $this->planAndPrepare($runtimeRoot.'/'.bin2hex(random_bytes(4)));
                $package = $this->livePackage($context, $context['artifacts']);
                $handoff = $package['artifacts']['certification_handoff'];
                $evidence = $this->certificationEvidence($handoff);
                $payload = json_decode((string) file_get_contents($evidence['path']), true, flags: JSON_THROW_ON_ERROR);
                $payload['lanes']['quality'] = $outcome === null ? ['evidence_ids' => [], 'outcome' => 'missing'] : [
                    'evidence_ids' => ['evidence-quality'],
                    'outcome'      => $outcome
                ];
                unset($payload['evidence_id']);
                $evidenceId = hash('sha256', $json->encode($payload));
                $payload['evidence_id'] = $evidenceId;
                $path = dirname($handoff['path']).'/'.$evidenceId.'.certification-evidence.json';
                file_put_contents($path, $json->encode($payload).PHP_EOL);

                $process = ReleaseProcess::create([
                    dirname(__DIR__, 3).'/bin/release',
                    'certify',
                    '--handoff='.$handoff['path'],
                    '--evidence='.$path
                ], $context['environment']);
                $process->run();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame($status, $result['status'], 'outcome='.(string) $outcome);
                self::assertSame($finding, $result['findings'][0]['id']);
                self::assertArrayNotHasKey('certification_manifest', $result['artifacts'] ?? []);
                self::assertFileExists($result['artifacts']['certification_stop']['path']);
                self::assertSame($status, $result['run_state']['state']);
                self::assertSame($handoff['handoff_id'], $result['run_state']['prerequisite_certification_handoff_id']);
            }
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers composition of the complete attributed certification lane set through the CLI.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_certify_composes_each_attributed_evidence_lane_into_its_manifest(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-lanes-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $package = $this->livePackage($context, $context['artifacts']);
            $handoff = $package['artifacts']['certification_handoff'];
            $evidence = $this->certificationEvidence($handoff);
            $process = ReleaseProcess::create([
                dirname(__DIR__, 3).'/bin/release',
                'certify',
                '--handoff='.$handoff['path'],
                '--evidence='.$evidence['path']
            ], $context['environment']);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $manifest = json_decode(
                (string) file_get_contents($result['artifacts']['certification_manifest']['path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            self::assertSame([
                'archive_install',
                'compatibility_git_ref',
                'dependency_latest',
                'dependency_locked',
                'dependency_lowest',
                'planning_api',
                'quality'
            ], array_keys($manifest['lanes']));
            self::assertSame($package['archive_digest'], $manifest['lanes']['archive_install']['archive_digest']);
            self::assertSame('latest-permitted', $manifest['lanes']['dependency_latest']['resolution']);
            self::assertSame('repository-locked', $manifest['lanes']['dependency_locked']['resolution']);
            self::assertSame('lowest-permitted', $manifest['lanes']['dependency_lowest']['resolution']);
            self::assertSame(
                $manifest['bindings']['evidence_manifest_digest'],
                $manifest['lanes']['quality']['evidence_manifest_digest']
            );
            self::assertSame(
                $manifest['bindings']['candidate_oid'],
                $manifest['lanes']['compatibility_git_ref']['candidate_oid']
            );
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers the governed package-to-certification handoff rather than accepting a package CLI transcript.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_certify_revalidates_a_verified_package_handoff_and_persists_its_manifest(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-handoff-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $package = $this->livePackage($context, $context['artifacts']);
            $handoff = $package['artifacts']['certification_handoff'];
            $evidence = $this->certificationEvidence($handoff);

            $process = ReleaseProcess::create([
                dirname(__DIR__, 3).'/bin/release',
                'certify',
                '--handoff='.$handoff['path'],
                '--evidence='.$evidence['path']
            ], $context['environment']);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $manifest = json_decode((string) file_get_contents($result['artifacts']['certification_manifest']['path']), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame('certified', $result['status']);
            self::assertSame('release_certification', $result['capability']);
            self::assertSame('fight-common.release-certification-manifest/v1', $manifest['schema_version']);
            self::assertSame('1.3.0', $manifest['bindings']['approved_version']);
            self::assertSame('1.2.3', $manifest['bindings']['baseline']['version']);
            self::assertSame('release-approval-001', $manifest['approvals']['release']['approval_id']);
            self::assertSame($package['archive_digest'], $manifest['bindings']['archive_digest']);
            self::assertSame($context['source_oid'], $manifest['bindings']['candidate_oid']);
            self::assertSame($result['artifacts']['certification_manifest']['manifest_id'], $manifest['manifest_id']);
            self::assertSame('certified', $result['run_state']['state']);
            self::assertSame($handoff['handoff_id'], $result['run_state']['prerequisite_certification_handoff_id']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers the CLI manifest identity against its bound evidence and direct handoff tampering.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_certify_reports_lane_results_in_its_immutable_artifact_and_rejects_tampered_bindings(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-certification-identity-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $package = $this->livePackage($context, $context['artifacts']);
            $handoff = $package['artifacts']['certification_handoff'];
            $evidence = $this->certificationEvidence($handoff);
            $process = ReleaseProcess::create([
                dirname(__DIR__, 3).'/bin/release',
                'certify',
                '--handoff='.$handoff['path'],
                '--evidence='.$evidence['path']
            ], $context['environment']);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $manifest = json_decode(
                (string) file_get_contents($result['artifacts']['certification_manifest']['path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            self::assertSame($result['artifacts']['certification_manifest']['manifest_id'], $manifest['manifest_id']);
            self::assertSame('verified', $manifest['lanes']['quality']['outcome']);

            $handoffPayload = json_decode((string) file_get_contents($handoff['path']), true, flags: JSON_THROW_ON_ERROR);

            foreach (
                [
                static function (array $payload): array {
                    $payload['bindings']['candidate_oid'] = str_repeat('a', 40);

                    return $payload;
                },
                static function (array $payload): array {
                    $payload['bindings']['baseline']['version'] = '1.2.4';

                    return $payload;
                },
                static function (array $payload): array {
                    $payload['bindings']['approved_version'] = '1.3.1';

                    return $payload;
                },
                static function (array $payload): array {
                    $payload['approvals']['release']['approval_id'] = 'release-approval-tampered';

                    return $payload;
                },
                static function (array $payload): array {
                    $payload['lanes']['quality']['outcome'] = 'failed';

                    return $payload;
                }
                ] as $mutate
            ) {
                file_put_contents($handoff['path'], (new CanonicalJson())->encode($mutate($handoffPayload)).PHP_EOL);

                $tamperedProcess = ReleaseProcess::create([
                    dirname(__DIR__, 3).'/bin/release',
                    'certify',
                    '--handoff='.$handoff['path'],
                    '--evidence='.$evidence['path']
                ], $context['environment']);
                $tamperedProcess->run();
                $tamperedResult = json_decode($tamperedProcess->getOutput(), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame(2, $tamperedResult['exit_code']);
                self::assertSame('release.certification.handoff_invalid', $tamperedResult['findings'][0]['id']);
            }
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers packaging after a complete plan-and-prepare pipeline in a real temp git repo.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_prepare_package_journey_produces_a_verified_archive_result(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-package-journey-'.bin2hex(random_bytes(8));

        try {
            $context = $this->planAndPrepare($runtimeRoot);
            $handoffFixtures = $context['artifacts'];

            $packageResult = $this->livePackage($context, $handoffFixtures);

            self::assertSame(0, $packageResult['exit_code']);
            self::assertSame('packaged', $packageResult['status']);
            self::assertSame('release_packaging', $packageResult['capability']);
            self::assertSame('release.package.completed', $packageResult['findings'][0]['id']);
            self::assertSame($context['plan_id'], $packageResult['plan_id']);
            self::assertSame($context['run_id'], $packageResult['run_id']);
            self::assertSame(['action' => 'certify_release_package'], $packageResult['next_action']);
            self::assertMatchesRegularExpression(
                '/\A[0-9a-f]{64}\z/D',
                $packageResult['archive_digest']
            );
            self::assertMatchesRegularExpression(
                '/\A[0-9a-f]{40,64}\z/D',
                $packageResult['candidate_oid']
            );
            self::assertSame(
                ['phase_handoff_revalidated', 'archive_created_and_verified'],
                $packageResult['verified_postconditions']
            );
            self::assertSame(
                'fight-common.package-effect-set/v1',
                $packageResult['effect_set']['schema_version']
            );
            self::assertSame(
                'fight-common-v1.3.0.zip',
                $packageResult['effect_set']['archive_name']
            );
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /**
     * Covers that packaging rejects an invalid handoff through the CLI.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_cli_rejects_an_invalid_handoff(): void
    {
        $runtimeRoot = sys_get_temp_dir().'/fight-common-package-invalid-'.bin2hex(random_bytes(8));
        mkdir($runtimeRoot.'/.runs/release', 0700, true);

        try {
            $handoffPath = $runtimeRoot.'/.runs/release/invalid-handoff.json';
            file_put_contents($handoffPath, '{"not":"valid"}'."\n");

            $result = ReleaseProcess::create([
                dirname(__DIR__, 3).'/bin/release',
                'package',
                '--handoff='.$handoffPath
            ], ['FIGHT_COMMON_RELEASE_TEST_REPOSITORY' => $runtimeRoot]);
            $result->run();

            $packageResult = json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(2, $packageResult['exit_code']);
            self::assertSame('release.package.handoff_invalid', $packageResult['findings'][0]['id']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $this->removeDirectory($runtimeRoot);
            }
        }
    }

    /** @return array<string, mixed> */
    private function planAndPrepare(string $runtimeRoot): array
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
        $json = new CanonicalJson();
        file_put_contents($authorityPath, $json->encode($authority).PHP_EOL);
        $prepare = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'prepare',
            '--plan='.$planResult['artifact']['path'],
            '--authority='.$authorityPath
        ], $environment);
        $prepare->mustRun();
        $prepareResult = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        return [
            'artifacts'   => $prepareResult['artifacts'],
            'environment' => $environment,
            'plan_id'     => $prepareResult['plan_id'],
            'run_id'      => $prepareResult['run_id'],
            'source_oid'  => $sourceOid
        ];
    }

    /**
     * Executes one normal package through the CLI.
     *
     * @param array $context Live release context.
     * @param array $handoffFixtures Handoff artifact references from prepare.
     *
     * @return array<string, mixed>
     *
     * @phpstan-param array<string, mixed> $context
     * @phpstan-param array{evidence_manifest: array{manifest_id: string, path: string}, phase_handoff: array{handoff_id: string, path: string}} $handoffFixtures
     */
    private function livePackage(array $context, array $handoffFixtures): array
    {
        $packageFixture = [
            'archive_file_list' => [
                'composer.json' => '{"name":"fight/common"}',
                'release.txt'   => 'candidate'
            ]
        ];
        $fixturePath = dirname($handoffFixtures['phase_handoff']['path']).'/package-fixture.json';
        file_put_contents($fixturePath, json_encode($packageFixture, JSON_THROW_ON_ERROR).PHP_EOL);

        $process = ReleaseProcess::create([
            dirname(__DIR__, 3).'/bin/release',
            'package',
            '--handoff='.$handoffFixtures['phase_handoff']['path'],
            '--fixture='.$fixturePath
        ], $context['environment']);
        $process->run();

        $output = $process->getOutput();

        if ($output === '') {
            throw new \RuntimeException('Package process produced no output. Error: '.$process->getErrorOutput());
        }

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array{handoff_id: string, path: string} $handoff @return array{evidence_id: string, path: string} */
    private function certificationEvidence(array $handoff): array
    {
        $package = json_decode((string) file_get_contents($handoff['path']), true, flags: JSON_THROW_ON_ERROR);
        $categories = [
            'structural-api', 'compatibility-manifest', 'composer-constraints', 'package-surface', 'archive-contents',
            'behavioral-fixtures', 'serialization-fixtures', 'persistence-fixtures', 'adapter-fixtures', 'dependency-lowest',
            'dependency-locked', 'dependency-latest', 'static-analysis', 'deprecation-discipline'
        ];
        $lanes = ['archive_install', 'compatibility_git_ref', 'dependency_latest', 'dependency_locked', 'dependency_lowest', 'planning_api', 'quality'];
        $records = [];

        foreach ($categories as $category) {
            $records[$category] = [
                'category'       => $category,
                'classification' => 'patch',
                'evidence_id'    => 'evidence-'.$category,
                'finding_id'     => 'finding-'.$category
            ];
        }

        $evidenceLanes = [];
        foreach ($lanes as $lane) {
            $evidenceLanes[$lane] = ['evidence_ids' => ['evidence-'.$lane], 'outcome' => 'verified'];
        }

        $payload = [
            'bindings'                 => $package['bindings'],
            'certification_handoff_id' => $package['handoff_id'],
            'classification_records'   => $records,
            'lanes'                    => $evidenceLanes,
            'schema_version'           => 'fight-common.release-certification-evidence/v1'
        ];
        $json = new CanonicalJson();
        $id = hash('sha256', $json->encode($payload));
        $path = dirname($handoff['path']).'/'.$id.'.certification-evidence.json';
        file_put_contents($path, $json->encode([...$payload, 'evidence_id' => $id]).PHP_EOL);

        return ['evidence_id' => $id, 'path' => $path];
    }

    /** Builds certification service around a deterministic release boundary. */
    private function certificationService(
        DeterministicReleaseBoundaryFake $ports,
        ?HashingPort $hashing = null,
        ?PlanArtifactStore $artifacts = null
    ): ReleaseCertificationService
    {
        return new ReleaseCertificationService(
            $artifacts ?? $ports,
            $hashing ?? $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleaseResultFactory($ports)
        );
    }

    private function hashFailureOn(DeterministicReleaseBoundaryFake $delegate, int $call): HashingPort
    {
        return new class($delegate, $call) implements HashingPort {
            private int $calls = 0;

            public function __construct(private DeterministicReleaseBoundaryFake $delegate, private int $failureCall)
            {
            }

            public function sha256(string $contents): ReleaseBoundaryOperationResult
            {
                ++$this->calls;

                if ($this->calls === $this->failureCall) {
                    return ReleaseBoundaryOperationResult::stopped(ReleaseBoundaryOutcome::FAILURE);
                }

                return $this->delegate->sha256($contents);
            }
        };
    }

    private function readFailureOn(DeterministicReleaseBoundaryFake $delegate, int $call): PlanArtifactStore
    {
        return new class($delegate, $call) implements PlanArtifactStore {
            private int $reads = 0;

            public function __construct(private DeterministicReleaseBoundaryFake $delegate, private int $failureCall)
            {
            }

            public function readArtifact(CanonicalRunsDirectory $directory, string $filename): PlanArtifactReadResult
            {
                ++$this->reads;

                return $this->reads === $this->failureCall
                    ? PlanArtifactReadResult::stopped(ReleaseBoundaryOutcome::FAILURE)
                    : $this->delegate->readArtifact($directory, $filename);
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                return $this->delegate->writeArtifact($directory, $filename, $contents);
            }

            public function resolveRunsDirectory(string $path, string $runsDirectory): RunsDirectoryResolutionResult
            {
                return $this->delegate->resolveRunsDirectory($path, $runsDirectory);
            }
        };
    }

    /** @param string[] $command */
    private function git(string $runtimeRoot, array $command): string
    {
        return trim((string) shell_exec('git -C '.escapeshellarg($runtimeRoot).' '.implode(' ', array_map('escapeshellarg', $command))));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
