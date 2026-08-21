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
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryPredicateResult;
use Fight\Common\Application\Release\Boundary\RunsDirectoryResolutionResult;
use Fight\Common\Application\Release\CanonicalJson;
use Fight\Common\Application\Release\CompatibilityAssessment;
use Fight\Common\Application\Release\MachineResult;
use Fight\Common\Application\Release\ReleasePlanCapabilityFirewall;
use Fight\Common\Application\Release\ReleasePlanFactory;
use Fight\Common\Application\Release\ReleasePlanService;
use Fight\Common\Application\Release\ReleasePlanValidationFailure;
use Fight\Common\Application\Release\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

/** Covers application-owned plan persistence orchestration. */
#[CoversClass(ReleasePlanService::class)]
#[CoversClass(ReleasePlanCapabilityFirewall::class)]
#[CoversClass(MachineResult::class)]
class ReleasePlanServiceTest extends UnitTestCase
{
    /**
     * Covers canonical persistence and idempotent verification via the artifact-store port.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_persists_and_reuses_a_verified_immutable_artifact_through_its_port(): void
    {
        $output = dirname(__DIR__, 3).'/.runs/release-plan-service-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $ports = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePlanService($ports, $ports, $ports, $ports, new CanonicalJson(), new ReleasePlanFactory(), new ReleaseResultFactory());

        try {
            $first = $service->plan($this->candidate(), $output, dirname(__DIR__, 3).'/.runs');
            $secondPorts = new DeterministicReleaseBoundaryFake();
            $second = $this->service($secondPorts)->plan($this->candidate(), $output, dirname(__DIR__, 3).'/.runs');

            self::assertSame(0, $first->exitCode);
            self::assertSame($first->payload['plan_id'], $second->payload['plan_id']);
            self::assertSame($first->payload['artifact'], $second->payload['artifact']);
            self::assertSame('succeeded', $second->payload['status']);
            self::assertSame('success', $second->payload['exit_class']);
            self::assertSame('release.plan.already_satisfied', $second->payload['findings'][0]['id']);
            self::assertSame(
                'The immutable release plan already existed and was canonically verified.',
                $second->payload['findings'][0]['message']
            );
            self::assertSame(
                ['immutable_release_plan_already_persisted'],
                $second->payload['verified_postconditions']
            );
            self::assertSame(['action' => 'create_release_run'], $second->payload['next_action']);
            self::assertCount(1, $second->payload['next_action']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success']
            ], $first->payload['performed_effects']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success']
            ], $second->payload['performed_effects']);
            self::assertNotContains(
                'filesystem.write',
                array_column($second->payload['performed_effects'], 'effect_class')
            );
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers a digest-named symlink whose outside target contains matching canonical bytes.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_rejects_a_matching_artifact_reached_through_a_final_symlink(): void
    {
        $fixture = sys_get_temp_dir().'/release-plan-final-link-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $output = $runs.'/plans';
        $outside = $fixture.'/outside.json';
        mkdir($output, 0777, true);

        try {
            $created = $this->service(new DeterministicReleaseBoundaryFake())->plan(
                $this->candidate(),
                $output,
                $runs
            );
            self::assertSame(0, $created->exitCode);
            $artifactPath = $created->payload['artifact']['path'];
            $canonicalBytes = file_get_contents($artifactPath);
            unlink($artifactPath);
            file_put_contents($outside, $canonicalBytes);
            symlink($outside, $artifactPath);

            $result = $this->service(new DeterministicReleaseBoundaryFake())->plan(
                $this->candidate(),
                $output,
                $runs
            );

            self::assertSame(4, $result->exitCode);
            self::assertSame('release.boundary.failure', $result->payload['findings'][0]['id']);
            self::assertSame($canonicalBytes, file_get_contents($outside));
            self::assertTrue(is_link($artifactPath));
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            if (is_link($output.'/'.$created->payload['plan_id'].'.json')) {
                unlink($output.'/'.$created->payload['plan_id'].'.json');
            }

            if (file_exists($outside)) {
                unlink($outside);
            }

            rmdir($output);
            rmdir($runs);
            rmdir($fixture);
        }
    }

    /**
     * Covers set-equivalent plans sharing identity while changed membership does not.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_identity_canonicalizes_set_like_inputs_only_by_membership(): void
    {
        $output = dirname(__DIR__, 3).'/.runs/release-plan-set-identity-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $candidate = [
            ...$this->candidate(),
            'expected_effect_classes'  => ['filesystem.read', 'hashing.sha256'],
            'evidence_requirements'    => ['full-submit-gate', 'planning-check'],
            'compatibility_exceptions' => ['compat-001', 'compat-002'],
            'required_approvals'       => ['release-approval-001', 'release-manager'],
            'release_approval_authority' => $this->releaseApprovalAuthority([
                'compatibility_exception_ids'         => ['compat-001', 'compat-002'],
                'patch_exception_authority_digests'   => []
            ])
        ];
        $permuted = [
            ...$candidate,
            'expected_effect_classes'  => array_reverse($candidate['expected_effect_classes']),
            'evidence_requirements'    => array_reverse($candidate['evidence_requirements']),
            'compatibility_exceptions' => array_reverse($candidate['compatibility_exceptions']),
            'required_approvals'       => array_reverse($candidate['required_approvals'])
        ];
        $changed = [...$candidate, 'compatibility_exceptions' => ['compat-001']];
        $changed['release_approval_authority']['compatibility_exception_ids'] = ['compat-001'];

        try {
            $first = $this->service(new DeterministicReleaseBoundaryFake())->plan($candidate, $output, dirname(__DIR__, 3).'/.runs');
            $equivalent = $this->service(new DeterministicReleaseBoundaryFake())->plan($permuted, $output, dirname(__DIR__, 3).'/.runs');
            $materiallyChanged = $this->service(new DeterministicReleaseBoundaryFake())->plan($changed, $output, dirname(__DIR__, 3).'/.runs');

            self::assertSame($first->payload['plan_id'], $equivalent->payload['plan_id']);
            self::assertNotSame($first->payload['plan_id'], $materiallyChanged->payload['plan_id']);
            self::assertSame([
                ['effect_class' => 'filesystem.read'],
                ['effect_class' => 'hashing.sha256']
            ], $first->payload['proposed_effects']);
            self::assertSame($first->payload['proposed_effects'], $equivalent->payload['proposed_effects']);
            self::assertNotSame($first->payload['performed_effects'], $first->payload['proposed_effects']);
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
        }
    }

    /**
     * Covers refusal of an output outside the planning artifact root.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_refuses_an_artifact_store_path_outside_the_runs_root(): void
    {
        $ports = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePlanService($ports, $ports, $ports, $ports, new CanonicalJson(), new ReleasePlanFactory(), new ReleaseResultFactory());
        $result = $service->plan($this->candidate(), sys_get_temp_dir(), dirname(__DIR__, 3).'/.runs');

        self::assertSame(2, $result->exitCode);
        self::assertSame('release.plan.output_forbidden', $result->payload['findings'][0]['id']);
        self::assertSame([
            ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success']
        ], $result->payload['performed_effects']);
    }

    /**
     * Covers rejection of constructor-bypassed canonical authority before hashing or persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_forged_escaping_directory_authority_before_hashing_or_writing(): void
    {
        $output = '/repository/.runs/../outside';
        $runsRoot = '/repository/.runs';
        $reflection = new ReflectionClass(CanonicalRunsDirectory::class);
        $forged = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('path')->setValue($forged, $output);
        $reflection->getProperty('runsRoot')->setValue($forged, $runsRoot);
        $artifacts = new class ($forged) implements PlanArtifactStore {
            public bool $read = false;

            public bool $write = false;

            public function __construct(private CanonicalRunsDirectory $forged)
            {
            }

            public function readArtifact(
                CanonicalRunsDirectory $directory,
                string $filename
            ): PlanArtifactReadResult {
                $this->read = true;

                return PlanArtifactReadResult::missing();
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                $this->write = true;

                return PlanArtifactWriteResult::success();
            }

            public function resolveRunsDirectory(
                string $path,
                string $runsDirectory
            ): RunsDirectoryResolutionResult {
                return RunsDirectoryResolutionResult::success($this->forged);
            }
        };
        $ports = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePlanService($artifacts, $ports, $ports, $ports, new CanonicalJson(), new ReleasePlanFactory(), new ReleaseResultFactory());

        $result = $service->plan($this->candidate(), $output, $runsRoot);

        self::assertSame(2, $result->exitCode);
        self::assertSame('release.plan.output_forbidden', $result->payload['findings'][0]['id']);
        self::assertSame([], $result->payload['performed_effects']);
        self::assertSame([], $ports->effects());
        self::assertFalse($artifacts->read);
        self::assertFalse($artifacts->write);
    }

    /**
     * Covers refusal when the canonical runs authority itself is an escaping symlink.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_rejects_a_symlinked_runs_root_before_hashing_or_writing(): void
    {
        $fixture = sys_get_temp_dir().'/release-plan-root-link-'.bin2hex(random_bytes(8));
        $outside = $fixture.'/outside';
        $runs = $fixture.'/.runs';
        mkdir($outside, 0777, true);
        symlink($outside, $runs);
        $ports = new DeterministicReleaseBoundaryFake();

        try {
            $result = $this->service($ports)->plan($this->candidate(), $runs, $runs);

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.plan.output_forbidden', $result->payload['findings'][0]['id']);
            self::assertSame([], glob($outside.'/*') ?: []);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success']
            ], $result->payload['performed_effects']);
        } finally {
            unlink($runs);
            rmdir($outside);
            rmdir($fixture);
        }
    }

    /**
     * Covers refusal of a caller-supplied output symlink before later retargeting can matter.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_never_authorizes_a_retargetable_output_symlink(): void
    {
        $fixture = sys_get_temp_dir().'/release-plan-symlink-race-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $inside = $runs.'/inside';
        $outside = $fixture.'/outside';
        $link = $runs.'/output';
        mkdir($inside, 0777, true);
        mkdir($outside);
        symlink($inside, $link);
        $ports = new DeterministicReleaseBoundaryFake();
        $artifacts = new class ($ports, $link, $outside) implements PlanArtifactStore {
            public function __construct(
                private DeterministicReleaseBoundaryFake $delegate,
                private string $link,
                private string $outside
            ) {
            }

            public function readArtifact(
                CanonicalRunsDirectory $directory,
                string $filename
            ): PlanArtifactReadResult {
                return $this->delegate->readArtifact($directory, $filename);
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                return $this->delegate->writeArtifact($directory, $filename, $contents);
            }

            public function resolveRunsDirectory(
                string $path,
                string $runsDirectory
            ): RunsDirectoryResolutionResult {
                $resolved = $this->delegate->resolveRunsDirectory($path, $runsDirectory);
                unlink($this->link);
                symlink($this->outside, $this->link);

                return $resolved;
            }
        };
        $service = new ReleasePlanService(
            $artifacts,
            $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            new ReleaseResultFactory()
        );

        try {
            $result = $service->plan($this->candidate(), $link, $runs);

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.plan.output_forbidden', $result->payload['findings'][0]['id']);
            self::assertSame([], glob($inside.'/*') ?: []);
            self::assertSame([], glob($outside.'/*') ?: []);
            self::assertSame(realpath($outside), realpath($link));
        } finally {
            foreach (glob($inside.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            unlink($link);
            rmdir($inside);
            rmdir($outside);
            rmdir($runs);
            rmdir($fixture);
        }
    }

    /**
     * Covers unusable output and invalid plan inputs before persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_unusable_output_and_invalid_plan_inputs_before_persistence(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        mkdir($root);
        $outputFile = tempnam($root, 'release-plan-file-');
        self::assertIsString($outputFile);
        $ports = new DeterministicReleaseBoundaryFake();
        $service = $this->service($ports);

        try {
            $unusableOutput = $service->plan($this->candidate(), $outputFile, $root);
            $invalidCandidate = $this->candidate();
            unset($invalidCandidate['source_commit_oid']);
            $invalidPlan = $service->plan($invalidCandidate, $root, $root);

            self::assertSame('release.plan.output_forbidden', $unusableOutput->payload['findings'][0]['id']);
            self::assertSame('release.plan.source_commit_oid_missing', $invalidPlan->payload['findings'][0]['id']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success']
            ], $unusableOutput->payload['performed_effects']);
        } finally {
            unlink($outputFile);
            rmdir($root);
        }
    }

    /**
     * Covers exact plan-authority findings before hashing or artifact effects.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_reports_every_typed_authority_failure_before_identity_or_persistence(): void
    {
        $root = sys_get_temp_dir().'/release-plan-validation-'.bin2hex(random_bytes(8));
        mkdir($root);

        try {
            foreach ($this->invalidCandidates() as ['reason' => $reason, 'candidate' => $candidate]) {
                $ports = new DeterministicReleaseBoundaryFake();
                $result = $this->service($ports)->plan($candidate, $root, $root);

                self::assertSame(2, $result->exitCode, $reason->value);
                self::assertSame('policy_blocked', $result->payload['status'], $reason->value);
                self::assertSame('invalid_input', $result->payload['exit_class'], $reason->value);
                self::assertSame($reason->findingId(), $result->payload['findings'][0]['id'], $reason->value);
                self::assertSame($reason->message(), $result->payload['findings'][0]['message'], $reason->value);
                self::assertSame(['action' => $reason->nextAction()], $result->payload['next_action'], $reason->value);
                self::assertArrayNotHasKey('plan_id', $result->payload, $reason->value);
                self::assertArrayNotHasKey('artifact', $result->payload, $reason->value);
                self::assertSame([], $result->payload['performed_effects'], $reason->value);
                self::assertSame([], glob($root.'/*') ?: [], $reason->value);
            }
        } finally {
            rmdir($root);
        }
    }

    /**
     * Covers malformed boundary declarations and every deterministic write outcome.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_preserves_each_declared_write_outcome_without_false_persistence(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        $output = $root.'/release-plan-service-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $ports = new DeterministicReleaseBoundaryFake();
        $service = $this->service($ports);

        try {
            $forbiddenBoundary = $service->plan([
                ...$this->candidate(),
                'boundary' => ['effect_class' => 'git.tag', 'outcome' => 'success']
            ], $output, $root);
            $malformedBoundary = $service->plan([
                ...$this->candidate(),
                'boundary' => ['effect_class' => 'filesystem.write']
            ], $output, $root);
            $unsupportedBoundary = $service->plan([
                ...$this->candidate(),
                'boundary' => ['effect_class' => 'filesystem.write', 'outcome' => 'already_satisfied']
            ], $output, $root);

            self::assertSame(
                'release.capability.effect_forbidden',
                $forbiddenBoundary->payload['findings'][0]['id']
            );
            self::assertSame([], $forbiddenBoundary->payload['performed_effects']);
            self::assertSame('release.boundary.fixture_invalid', $malformedBoundary->payload['findings'][0]['id']);
            self::assertSame([], $malformedBoundary->payload['performed_effects']);
            self::assertSame(
                'release.boundary.outcome_unsupported',
                $unsupportedBoundary->payload['findings'][0]['id']
            );
            self::assertSame([], $unsupportedBoundary->payload['performed_effects']);
            $rejectingEffects = new class implements \Fight\Common\Application\Release\Boundary\ReleaseEffectLedger {
                public function configureOutcome(string $effectClass, string $outcome): bool
                {
                    return false;
                }

                public function effects(): array
                {
                    return [];
                }
            };
            $unconfigurableBoundary = (new ReleasePlanService(
                $ports,
                $ports,
                $rejectingEffects,
                $ports,
                new CanonicalJson(),
                new ReleasePlanFactory(),
                new ReleaseResultFactory()
            ))->plan([
                ...$this->candidate(),
                'boundary' => ['effect_class' => 'filesystem.write', 'outcome' => 'success']
            ], $output, $root);
            self::assertSame(
                'release.boundary.fixture_invalid',
                $unconfigurableBoundary->payload['findings'][0]['id']
            );
            self::assertSame([], $unconfigurableBoundary->payload['performed_effects']);
            self::assertSame([], glob($output.'/*') ?: []);

            $classifications = [
                'success'           => ['succeeded', 'success', 0, 'release.plan.created', 'create_release_run'],
                'refusal'           => ['authority_required', 'refused', 3, 'release.boundary.refusal', 'obtain_boundary_authority'],
                'failure'           => ['policy_blocked', 'failed', 4, 'release.boundary.failure', 'repair_boundary_failure'],
                'uncertainty'       => ['evidence_indeterminate', 'uncertain', 5, 'release.boundary.uncertainty', 'reconcile_boundary_effect'],
                'drift'             => ['stale_plan', 'drifted', 6, 'release.boundary.drift', 'refresh_bound_inputs']
            ];

            foreach ($classifications as $outcome => [$status, $exitClass, $exitCode, $finding, $nextAction]) {
                $outcomeOutput = $output.'/'.$outcome;
                mkdir($outcomeOutput);
                $outcomePorts = new DeterministicReleaseBoundaryFake();
                $result = $this->service($outcomePorts)->plan([
                    ...$this->candidate(),
                    'boundary' => ['effect_class' => 'filesystem.write', 'outcome' => $outcome]
                ], $outcomeOutput, $root);

                self::assertSame($status, $result->payload['status']);
                self::assertSame($exitClass, $result->payload['exit_class']);
                self::assertSame($exitCode, $result->exitCode);
                self::assertSame($finding, $result->payload['findings'][0]['id']);
                self::assertSame(['action' => $nextAction], $result->payload['next_action']);
                self::assertSame(
                    $outcome === 'success' ? ['immutable_release_plan_persisted'] : [],
                    $result->payload['verified_postconditions']
                );
                $expectedEffects = [
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                    ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                    ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                    ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => $outcome]
                ];

                if ($outcome === 'success') {
                    $expectedEffects[] = ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'];
                    $expectedEffects[] = ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'];
                }

                self::assertSame($expectedEffects, $result->payload['performed_effects']);
                self::assertCount($outcome === 'success' ? 1 : 0, glob($outcomeOutput.'/*') ?: []);

                foreach (glob($outcomeOutput.'/*') ?: [] as $artifact) {
                    unlink($artifact);
                }

                rmdir($outcomeOutput);
            }
        } finally {
            rmdir($output);
            rmdir($root);
        }
    }

    /**
     * Covers conflicts whose persisted representation cannot prove the expected identity.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_malformed_and_non_object_existing_artifacts(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        $output = $root.'/release-plan-service-'.bin2hex(random_bytes(8));
        mkdir($output, 0777, true);
        $ports = new DeterministicReleaseBoundaryFake();
        $service = $this->service($ports);

        try {
            $created = $service->plan($this->candidate(), $output, $root);
            $path = $created->payload['artifact']['path'];
            file_put_contents($path, '{invalid');
            $malformed = $service->plan($this->candidate(), $output, $root);
            file_put_contents($path, '"scalar"'.PHP_EOL);
            $nonObject = $service->plan($this->candidate(), $output, $root);

            self::assertSame('release.plan.artifact_conflict', $malformed->payload['findings'][0]['id']);
            self::assertSame('release.plan.artifact_conflict', $nonObject->payload['findings'][0]['id']);
        } finally {
            foreach (glob($output.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($output);
            rmdir($root);
        }
    }

    /**
     * Covers a successful write whose post-write read cannot verify immutable persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_an_artifact_that_cannot_be_verified_after_a_successful_write(): void
    {
        $artifacts = new class implements PlanArtifactStore {
            private int $reads = 0;

            public function readArtifact(
                CanonicalRunsDirectory $directory,
                string $filename
            ): PlanArtifactReadResult {
                ++$this->reads;

                if ($this->reads === 1) {
                    return PlanArtifactReadResult::missing();
                }

                return PlanArtifactReadResult::content('not-the-written-artifact');
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                return PlanArtifactWriteResult::success();
            }

            public function resolveRunsDirectory(
                string $path,
                string $runsDirectory
            ): RunsDirectoryResolutionResult {
                return RunsDirectoryResolutionResult::success(
                    new CanonicalRunsDirectory('/virtual/.runs/plan', '/virtual/.runs')
                );
            }
        };
        $ports = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePlanService($artifacts, $ports, $ports, $ports, new CanonicalJson(), new ReleasePlanFactory(), new ReleaseResultFactory());
        $result = $service->plan($this->candidate(), '/virtual/.runs/plan', '/virtual/.runs');

        self::assertSame(4, $result->exitCode);
        self::assertSame('release.plan.artifact_not_persisted', $result->payload['findings'][0]['id']);
        self::assertSame(['action' => 'repair_plan_artifact_persistence'], $result->payload['next_action']);
    }

    /**
     * Covers a concurrent immutable-create result being accepted only after canonical re-verification.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_treats_an_already_satisfied_write_as_success_only_after_reverification(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        mkdir($root);

        try {
            $ports = new DeterministicReleaseBoundaryFake([], null, true);
            $result = $this->service($ports)->plan($this->candidate(), $root, $root);
            $path = $result->payload['artifact']['path'];

            self::assertSame(0, $result->exitCode);
            self::assertSame('release.plan.already_satisfied', $result->payload['findings'][0]['id']);
            self::assertSame(
                ['immutable_release_plan_already_persisted'],
                $result->payload['verified_postconditions']
            );
            self::assertSame(
                (new CanonicalJson())->encode(
                    json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)
                ).PHP_EOL,
                file_get_contents($path)
            );
            self::assertSame([
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.write',
                'outcome'      => 'already_satisfied'
            ], $result->payload['performed_effects'][6]);
            self::assertSame(
                ['filesystem.write', 'filesystem.read', 'hashing.sha256'],
                array_slice(array_column($result->payload['performed_effects'], 'effect_class'), -3)
            );
        } finally {
            $this->removeTemporaryDirectory($root, 'release-plan-runs-');
        }
    }

    /**
     * Covers reconciliation of every post-publication helper failure against the final artifact.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_resolves_post_publication_ambiguity_only_from_an_independent_read(): void
    {
        foreach (['fstat', 'fsync', 'cleanup', 'output'] as $failure) {
            $root = sys_get_temp_dir().'/release-plan-published-'.bin2hex(random_bytes(8));
            mkdir($root);

            try {
                $ports = new DeterministicReleaseBoundaryFake([], null, null, [], null, null, $failure, 'exists');
                $result = $this->service($ports)->plan($this->candidate(), $root, $root);

                self::assertSame(0, $result->exitCode, $failure);
                self::assertSame('release.plan.created', $result->payload['findings'][0]['id']);
                self::assertFileExists($result->payload['artifact']['path']);
                self::assertSame('uncertainty', $result->payload['performed_effects'][6]['outcome']);
                self::assertSame(
                    ['filesystem.write', 'filesystem.read', 'hashing.sha256'],
                    array_slice(array_column($result->payload['performed_effects'], 'effect_class'), -3)
                );
            } finally {
                $this->removeTemporaryDirectory($root, 'release-plan-published-');
            }
        }

        foreach (['missing' => 5, 'mismatch' => 4] as $finalState => $expectedExit) {
            $root = sys_get_temp_dir().'/release-plan-published-'.bin2hex(random_bytes(8));
            mkdir($root);

            try {
                $ports = new DeterministicReleaseBoundaryFake([], null, null, [], null, null, null, $finalState);
                $result = $this->service($ports)->plan($this->candidate(), $root, $root);

                self::assertSame($expectedExit, $result->exitCode, $finalState);
                self::assertSame(
                    $finalState === 'missing' ? 'release.boundary.uncertainty' : 'release.plan.artifact_conflict',
                    $result->payload['findings'][0]['id']
                );
                self::assertSame([], $result->payload['verified_postconditions']);
                self::assertSame('uncertainty', $result->payload['performed_effects'][6]['outcome']);
                self::assertSame(
                    ['filesystem.write', 'filesystem.read'],
                    array_slice(array_column($result->payload['performed_effects'], 'effect_class'), -2)
                );
            } finally {
                $this->removeTemporaryDirectory($root, 'release-plan-published-');
            }
        }
    }

    /**
     * Covers a classified read stop retaining uncertainty after publication may have completed.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_never_downgrades_an_unverifiable_publication_to_ordinary_failure(): void
    {
        $artifacts = new class implements PlanArtifactStore {
            private int $reads = 0;

            public function readArtifact(
                CanonicalRunsDirectory $directory,
                string $filename
            ): PlanArtifactReadResult {
                ++$this->reads;

                return $this->reads === 1
                    ? PlanArtifactReadResult::missing()
                    : PlanArtifactReadResult::stopped(ReleaseBoundaryOutcome::FAILURE);
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                return PlanArtifactWriteResult::publicationVerificationRequired();
            }

            public function resolveRunsDirectory(
                string $path,
                string $runsDirectory
            ): RunsDirectoryResolutionResult {
                return RunsDirectoryResolutionResult::success(
                    new CanonicalRunsDirectory('/virtual/.runs/plan', '/virtual/.runs')
                );
            }
        };
        $ports = new DeterministicReleaseBoundaryFake();
        $result = (new ReleasePlanService(
            $artifacts,
            $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            new ReleaseResultFactory()
        ))->plan($this->candidate(), '/virtual/.runs/plan', '/virtual/.runs');

        self::assertSame(5, $result->exitCode);
        self::assertSame('evidence_indeterminate', $result->payload['status']);
        self::assertSame('release.boundary.uncertainty', $result->payload['findings'][0]['id']);
        self::assertSame([], $result->payload['verified_postconditions']);
    }

    /**
     * Covers an already-satisfied provider claim without an artifact failing closed.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_an_already_satisfied_write_without_verifiable_evidence(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        mkdir($root);
        $ports = new DeterministicReleaseBoundaryFake([], null, false);

        try {
            $result = $this->service($ports)->plan($this->candidate(), $root, $root);

            self::assertSame(4, $result->exitCode);
            self::assertSame('release.plan.artifact_conflict', $result->payload['findings'][0]['id']);
            self::assertSame([], $result->payload['verified_postconditions']);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'already_satisfied'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success']
            ], array_slice($result->payload['performed_effects'], -2));
            self::assertCount(1, glob($root.'/*') ?: []);
        } finally {
            $this->removeTemporaryDirectory($root, 'release-plan-runs-');
        }
    }

    /**
     * Covers an unrelated provider claim being rejected before artifact re-verification.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_already_satisfied_write_evidence_for_an_unrelated_postcondition(): void
    {
        $artifacts = new class implements PlanArtifactStore {
            public bool $readAttempted = false;

            public function readArtifact(
                CanonicalRunsDirectory $directory,
                string $filename
            ): PlanArtifactReadResult {
                $this->readAttempted = true;

                return PlanArtifactReadResult::missing();
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                return PlanArtifactWriteResult::alreadySatisfied('unrelated_postcondition');
            }

            public function resolveRunsDirectory(
                string $path,
                string $runsDirectory
            ): RunsDirectoryResolutionResult {
                return RunsDirectoryResolutionResult::success(
                    new CanonicalRunsDirectory('/virtual/.runs/plan', '/virtual/.runs')
                );
            }
        };
        $ports = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePlanService($artifacts, $ports, $ports, $ports, new CanonicalJson(), new ReleasePlanFactory(), new ReleaseResultFactory());
        $result = $service->plan($this->candidate(), '/virtual/.runs/plan', '/virtual/.runs');

        self::assertSame(4, $result->exitCode);
        self::assertSame('release.boundary.failure', $result->payload['findings'][0]['id']);
        self::assertTrue($artifacts->readAttempted);
        self::assertSame([], $result->payload['verified_postconditions']);
    }

    /**
     * Covers every governed read stop during post-write and idempotent artifact verification.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_preserves_each_artifact_reverification_read_outcome(): void
    {
        $classifications = [
            'refusal'     => ['authority_required', 'refused', 3, 'obtain_boundary_authority'],
            'failure'     => ['policy_blocked', 'failed', 4, 'repair_boundary_failure'],
            'uncertainty' => ['evidence_indeterminate', 'uncertain', 5, 'reconcile_boundary_effect'],
            'drift'       => ['stale_plan', 'drifted', 6, 'refresh_bound_inputs']
        ];

        foreach (['post-write', 'idempotent'] as $path) {
            foreach ($classifications as $outcome => [$status, $exitClass, $exitCode, $nextAction]) {
                $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
                mkdir($root);

                try {
                    if ($path === 'idempotent') {
                        $created = $this->service(new DeterministicReleaseBoundaryFake())->plan($this->candidate(), $root, $root);
                        self::assertSame(0, $created->exitCode);
                    }

                    $ports = new DeterministicReleaseBoundaryFake(
                        $path === 'idempotent' ? ['filesystem.read' => $outcome] : []
                    );
                    $artifacts = $ports;

                    if ($path === 'post-write') {
                        $artifacts = new class ($ports, $outcome) implements PlanArtifactStore {
                            private int $reads = 0;

                            public function __construct(
                                private DeterministicReleaseBoundaryFake $delegate,
                                private string $outcome
                            ) {
                            }

                            public function readArtifact(
                                CanonicalRunsDirectory $directory,
                                string $filename
                            ): PlanArtifactReadResult {
                                ++$this->reads;

                                if ($this->reads === 2) {
                                    $this->delegate->configureOutcome('filesystem.read', $this->outcome);
                                }

                                return $this->delegate->readArtifact($directory, $filename);
                            }

                            public function writeArtifact(
                                CanonicalRunsDirectory $directory,
                                string $filename,
                                string $contents
                            ): PlanArtifactWriteResult {
                                return $this->delegate->writeArtifact($directory, $filename, $contents);
                            }

                            public function resolveRunsDirectory(
                                string $path,
                                string $runsDirectory
                            ): RunsDirectoryResolutionResult {
                                return $this->delegate->resolveRunsDirectory($path, $runsDirectory);
                            }
                        };
                    }

                    $result = (new ReleasePlanService(
                        $artifacts,
                        $ports,
                        $ports,
                        $ports,
                        new CanonicalJson(),
                        new ReleasePlanFactory(),
                        new ReleaseResultFactory()
                    ))->plan($this->candidate(), $root, $root);

                    self::assertSame($status, $result->payload['status']);
                    self::assertSame($exitClass, $result->payload['exit_class']);
                    self::assertSame($exitCode, $result->exitCode);
                    self::assertSame('release.boundary.'.$outcome, $result->payload['findings'][0]['id']);
                    self::assertSame($outcome, $result->payload['findings'][0]['outcome']);
                    self::assertSame([], $result->payload['verified_postconditions']);
                    self::assertSame(['action' => $nextAction], $result->payload['next_action']);
                    self::assertSame($outcome, $result->payload['performed_effects'][array_key_last($result->payload['performed_effects'])]['outcome']);
                    self::assertSame('filesystem.read', $result->payload['performed_effects'][array_key_last($result->payload['performed_effects'])]['effect_class']);
                } finally {
                    foreach (glob($root.'/*') ?: [] as $artifact) {
                        unlink($artifact);
                    }

                    rmdir($root);
                }
            }
        }
    }

    /**
     * Covers disappearance between a successful immutable create and descriptor-relative verification.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_rejects_an_artifact_missing_during_post_write_verification(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        mkdir($root);
        $ports = new DeterministicReleaseBoundaryFake();
        $artifacts = new class ($ports) implements PlanArtifactStore {
            public function __construct(private DeterministicReleaseBoundaryFake $delegate)
            {
            }

            public function readArtifact(
                CanonicalRunsDirectory $directory,
                string $filename
            ): PlanArtifactReadResult {
                return $this->delegate->readArtifact($directory, $filename);
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                $result = $this->delegate->writeArtifact($directory, $filename, $contents);
                unlink($directory->artifactPath($filename));

                return $result;
            }

            public function resolveRunsDirectory(
                string $path,
                string $runsDirectory
            ): RunsDirectoryResolutionResult {
                return $this->delegate->resolveRunsDirectory($path, $runsDirectory);
            }
        };
        $service = new ReleasePlanService(
            $artifacts,
            $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            new ReleaseResultFactory()
        );

        try {
            $result = $service->plan($this->candidate(), $root, $root);

            self::assertSame(4, $result->exitCode);
            self::assertSame('release.plan.artifact_not_persisted', $result->payload['findings'][0]['id']);
        } finally {
            rmdir($root);
        }
    }

    /**
     * Covers every governed hash stop during post-write and idempotent artifact verification.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_preserves_each_artifact_reverification_hash_outcome(): void
    {
        $classifications = [
            'refusal'     => ['authority_required', 'refused', 3, 'obtain_boundary_authority'],
            'failure'     => ['policy_blocked', 'failed', 4, 'repair_boundary_failure'],
            'uncertainty' => ['evidence_indeterminate', 'uncertain', 5, 'reconcile_boundary_effect'],
            'drift'       => ['stale_plan', 'drifted', 6, 'refresh_bound_inputs']
        ];

        foreach (['post-write', 'idempotent'] as $path) {
            foreach ($classifications as $outcome => [$status, $exitClass, $exitCode, $nextAction]) {
                $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
                mkdir($root);

                try {
                    if ($path === 'idempotent') {
                        $created = $this->service(new DeterministicReleaseBoundaryFake())->plan($this->candidate(), $root, $root);
                        self::assertSame(0, $created->exitCode);
                    }

                    $ports = new DeterministicReleaseBoundaryFake();
                    $hashing = new class($ports, $outcome) implements HashingPort {
                        private int $calls = 0;

                        public function __construct(
                            private DeterministicReleaseBoundaryFake $effects,
                            private string $secondOutcome
                        ) {
                        }

                        public function sha256(string $contents): ReleaseBoundaryOperationResult
                        {
                            ++$this->calls;
                            $this->effects->configureOutcome(
                                'hashing.sha256',
                                $this->calls === 1 ? 'success' : $this->secondOutcome
                            );

                            return $this->effects->sha256($contents);
                        }
                    };
                    $service = new ReleasePlanService(
                        $ports,
                        $hashing,
                        $ports,
                        $ports,
                        new CanonicalJson(),
                        new ReleasePlanFactory(),
                        new ReleaseResultFactory()
                    );
                    $result = $service->plan($this->candidate(), $root, $root);

                    self::assertSame($status, $result->payload['status']);
                    self::assertSame($exitClass, $result->payload['exit_class']);
                    self::assertSame($exitCode, $result->exitCode);
                    self::assertSame('release.boundary.'.$outcome, $result->payload['findings'][0]['id']);
                    self::assertSame($outcome, $result->payload['findings'][0]['outcome']);
                    self::assertSame([], $result->payload['verified_postconditions']);
                    self::assertSame(['action' => $nextAction], $result->payload['next_action']);
                    self::assertSame([
                        'capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => $outcome
                    ], $result->payload['performed_effects'][array_key_last($result->payload['performed_effects'])]);
                } finally {
                    foreach (glob($root.'/*') ?: [] as $artifact) {
                        unlink($artifact);
                    }

                    rmdir($root);
                }
            }
        }
    }

    /**
     * Covers malformed successful SHA-256 data during artifact reverification.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_fails_closed_when_artifact_reverification_returns_a_malformed_successful_hash(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        mkdir($root);
        $ports = new DeterministicReleaseBoundaryFake();
        $hashing = new class($ports) implements HashingPort {
            private int $calls = 0;

            public function __construct(private DeterministicReleaseBoundaryFake $effects)
            {
            }

            public function sha256(string $contents): ReleaseBoundaryOperationResult
            {
                ++$this->calls;
                $valid = $this->effects->sha256($contents);

                return $this->calls === 1 ? $valid : ReleaseBoundaryOperationResult::success('not-a-sha256-digest');
            }
        };
        $service = new ReleasePlanService(
            $ports,
            $hashing,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            new ReleaseResultFactory()
        );

        try {
            $result = $service->plan($this->candidate(), $root, $root);

            self::assertSame(4, $result->exitCode);
            self::assertSame('release.boundary.failure', $result->payload['findings'][0]['id']);
            self::assertSame([], $result->payload['verified_postconditions']);
            self::assertSame([
                'capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'success'
            ], $result->payload['performed_effects'][array_key_last($result->payload['performed_effects'])]);
        } finally {
            foreach (glob($root.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($root);
        }
    }

    /**
     * Covers a hash outcome that cannot masquerade as a plan identity.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_stops_when_hashing_does_not_return_a_value(): void
    {
        $root = sys_get_temp_dir().'/release-plan-runs-'.bin2hex(random_bytes(8));
        mkdir($root);
        $ports = new DeterministicReleaseBoundaryFake(['hashing.sha256' => 'failure']);

        try {
            $result = $this->service($ports)->plan($this->candidate(), $root, $root);

            self::assertSame(4, $result->exitCode);
            self::assertSame('release.boundary.failure', $result->payload['findings'][0]['id']);
            self::assertArrayNotHasKey('plan_id', $result->payload);
            self::assertArrayNotHasKey('artifact', $result->payload);
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                ['capability' => 'git', 'effect_class' => 'git.resolve_ref', 'outcome' => 'success'],
                ['capability' => 'hashing', 'effect_class' => 'hashing.sha256', 'outcome' => 'failure']
            ], $result->payload['performed_effects']);
        } finally {
            rmdir($root);
        }
    }

    /**
     * Covers baseline-tag resolution before plan identity or persistence effects.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_plan_revalidates_baseline_tag_before_hashing_or_writing(): void
    {
        $root = sys_get_temp_dir().'/release-plan-baseline-'.bin2hex(random_bytes(8));
        mkdir($root);

        try {
            foreach (['missing', 'ambiguous', 'duplicate_normalized', 'non_ancestor'] as $status) {
                $ports = new DeterministicReleaseBoundaryFake();
                $ports->configureBaselineTagResolution($status);
                $result = $this->service($ports)->plan($this->candidate(), $root, $root);

                self::assertSame(4, $result->exitCode, $status);
                self::assertSame('release.plan.baseline_tag_'.$status, $result->payload['findings'][0]['id']);
                self::assertNotContains('hashing.sha256', array_column($ports->effects(), 'effect_class'));
                self::assertNotContains('filesystem.write', array_column($ports->effects(), 'effect_class'));
            }

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureBaselineTagResolution('resolved', 'v1.2.3', str_repeat('c', 40));
            $moving = $this->service($ports)->plan($this->candidate(), $root, $root);
            self::assertSame(6, $moving->exitCode);
            self::assertSame('release.plan.baseline_tag_moving', $moving->payload['findings'][0]['id']);

            $ports = new DeterministicReleaseBoundaryFake(['git.resolve_ref' => 'uncertainty']);
            $uncertain = $this->service($ports)->plan($this->candidate(), $root, $root);
            self::assertSame(5, $uncertain->exitCode);
            self::assertSame('release.boundary.uncertainty', $uncertain->payload['findings'][0]['id']);
        } finally {
            foreach (glob($root.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($root);
        }
    }

    /**
     * Covers higher approval and governed lower-patch exception authorization.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_enforces_higher_and_lower_version_authorization_before_release_effects(): void
    {
        $root = sys_get_temp_dir().'/release-plan-version-relation-'.bin2hex(random_bytes(8));
        mkdir($root);

        try {
            $higher = $this->candidate();
            $higher['approved_version'] = '1.4.0';
            $higher['release_approval_authority']['approved_version'] = '1.4.0';
            $higherResult = $this->service(new DeterministicReleaseBoundaryFake())->plan($higher, $root, $root);
            self::assertSame(0, $higherResult->exitCode);

            $lower = $this->candidate();
            $lower['release_class'] = 'major';
            $lower['approved_version'] = '1.2.4';
            $lower['release_approval_authority'] = $this->releaseApprovalAuthority([
                'approved_version'          => '1.2.4',
                'minimum_release_class'     => 'major',
                'authorized_release_class'  => 'patch'
            ]);

            foreach (
                [
                [],
                ['patch-exception:compat-001:exact-version:1.2.5']
                ] as $exceptions
            ) {
                $ports = new DeterministicReleaseBoundaryFake();
                $attempt = [...$lower, 'compatibility_exceptions' => $exceptions];
                $attempt['release_approval_authority']['compatibility_exception_ids'] = $exceptions;
                $result = $this->service($ports)->plan(
                    $attempt,
                    $root,
                    $root
                );

                self::assertSame(2, $result->exitCode);
                self::assertSame(
                    $exceptions === []
                        ? 'release.plan.lower_version_exception_required'
                        : 'release.plan.patch_exception_authority_mismatched',
                    $result->payload['findings'][0]['id']
                );
                self::assertSame(['action' => $exceptions === []
                    ? 'provide_complete_patch_exception_authority'
                    : 'correct_patch_exception_authority_bindings'], $result->payload['next_action']);
                self::assertSame([], $result->payload['performed_effects']);
            }

            $patchAuthority = $this->patchExceptionAuthority();
            $authorized = [
                ...$lower,
                'compatibility_exceptions'    => ['patch-exception:compat-001:exact-version:1.2.4'],
                'patch_exception_authorities' => [$patchAuthority],
                'required_approvals'          => ['release-approval-001', 'release-authority-001'],
                'release_approval_authority'  => $this->releaseApprovalAuthority([
                    'approved_version'            => '1.2.4',
                    'compatibility_exception_ids' => [
                        'patch-exception:compat-001:exact-version:1.2.4'
                    ],
                    'patch_exception_authority_digests' => [$patchAuthority['authority_digest']],
                    'minimum_release_class'       => 'major',
                    'authorized_release_class'    => 'patch'
                ])
            ];
            $authorizedResult = $this->service(new DeterministicReleaseBoundaryFake())->plan(
                $authorized,
                $root,
                $root
            );
            self::assertSame(0, $authorizedResult->exitCode);
            self::assertCount(2, glob($root.'/*') ?: []);
        } finally {
            foreach (glob($root.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($root);
        }
    }

    /**
     * Covers normal-plan exception exclusivity before any boundary capability is observed.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_patch_exception_material_for_normal_versions_before_effects(): void
    {
        $authority = $this->patchExceptionAuthority();

        foreach (['1.3.0', '1.4.0'] as $approvedVersion) {
            $candidate = $this->candidate();
            $candidate['approved_version'] = $approvedVersion;
            $candidate['compatibility_exceptions'] = [
                'patch-exception:compat-001:exact-version:1.2.4'
            ];
            $candidate['patch_exception_authorities'] = [$authority];
            $candidate['required_approvals'] = ['release-approval-001', 'release-authority-001'];
            $candidate['release_approval_authority'] = $this->releaseApprovalAuthority([
                'approved_version'                  => $approvedVersion,
                'compatibility_exception_ids'       => $candidate['compatibility_exceptions'],
                'patch_exception_authority_digests' => [$authority['authority_digest']]
            ]);
            $ports = new DeterministicReleaseBoundaryFake();
            $result = $this->service($ports)->plan($candidate, '/not-observed', '/not-observed');

            self::assertSame(2, $result->exitCode, $approvedVersion);
            self::assertSame(
                'release.plan.patch_exception_not_allowed',
                $result->payload['findings'][0]['id'],
                $approvedVersion
            );
            self::assertSame(
                ['action' => 'remove_patch_exception_material'],
                $result->payload['next_action'],
                $approvedVersion
            );
            self::assertSame([], $ports->effects(), $approvedVersion);
        }
    }

    /**
     * Covers stale and surplus lower-patch authorities before any boundary capability.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_non_exact_lower_patch_authority_sets_before_effects(): void
    {
        $candidate = $this->candidate();
        $candidate['release_class'] = 'major';
        $candidate['approved_version'] = '1.2.4';
        $candidate['compatibility_exceptions'] = ['patch-exception:compat-001:exact-version:1.2.4'];
        $candidate['required_approvals'] = ['release-approval-001', 'release-authority-001'];

        $staleAssessment = $this->patchExceptionAuthority()['compatibility_assessment'];
        $staleAssessment[0]['classification'] = 'minor';
        $stale = $candidate;
        $stale['patch_exception_authorities'] = [$this->patchExceptionAuthority([
            'compatibility_assessment' => $staleAssessment
        ])];

        $second = $this->patchExceptionAuthority([
            'exception_id'  => 'compat-002',
            'exact_version' => '1.2.5'
        ]);
        $extra = $candidate;
        $extra['compatibility_exceptions'][] = 'patch-exception:compat-002:exact-version:1.2.5';
        $extra['patch_exception_authorities'] = [$this->patchExceptionAuthority(), $second];

        foreach ([$stale, $extra] as $attempt) {
            $attempt['release_approval_authority'] = $this->releaseApprovalAuthority([
                'approved_version'                  => '1.2.4',
                'compatibility_exception_ids'       => $attempt['compatibility_exceptions'],
                'patch_exception_authority_digests' => array_column(
                    $attempt['patch_exception_authorities'],
                    'authority_digest'
                ),
                'minimum_release_class'             => 'major',
                'authorized_release_class'          => 'patch'
            ]);
            $ports = new DeterministicReleaseBoundaryFake();
            $result = $this->service($ports)->plan($attempt, '/not-observed', '/not-observed');

            self::assertSame(2, $result->exitCode);
            self::assertSame(
                'release.plan.patch_exception_authority_mismatched',
                $result->payload['findings'][0]['id']
            );
            self::assertSame(
                ['action' => 'correct_patch_exception_authority_bindings'],
                $result->payload['next_action']
            );
            self::assertSame([], $ports->effects());
        }
    }

    /**
     * Covers governed filesystem predicate stops before any later planning effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_preserves_filesystem_predicate_outcomes_and_stops_before_later_effects(): void
    {
        $root = sys_get_temp_dir().'/release-plan-predicate-stops-'.bin2hex(random_bytes(8));
        mkdir($root);

        $cases = [
            'filesystem.inspect_runs_directory' => ['failure', ['filesystem.inspect_runs_directory']],
            'filesystem.inspect_directory'      => ['uncertainty', ['filesystem.inspect_runs_directory', 'filesystem.inspect_directory']],
            'filesystem.inspect_writable'       => ['drift', ['filesystem.inspect_runs_directory', 'filesystem.inspect_directory', 'filesystem.inspect_writable']],
            'filesystem.read'                   => ['refusal', [
                'filesystem.inspect_runs_directory',
                'filesystem.inspect_directory',
                'filesystem.inspect_writable',
                'git.resolve_ref',
                'hashing.sha256',
                'filesystem.read'
            ]]
        ];

        try {
            foreach ($cases as $effectClass => [$outcome, $expectedEffects]) {
                $ports = new DeterministicReleaseBoundaryFake([$effectClass => $outcome]);
                $result = $this->service($ports)->plan($this->candidate(), $root, $root);

                self::assertSame('release.boundary.'.$outcome, $result->payload['findings'][0]['id']);
                self::assertSame($expectedEffects, array_column($result->payload['performed_effects'], 'effect_class'));
                self::assertSame($outcome, $result->payload['performed_effects'][array_key_last($result->payload['performed_effects'])]['outcome']);
                self::assertNotContains('filesystem.write', $expectedEffects);
            }
        } finally {
            foreach (glob($root.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($root);
        }
    }

    /**
     * Covers a malformed successful hash being rejected before artifact persistence.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_plan_rejects_an_invalid_successful_sha256_value_before_artifact_persistence(): void
    {
        $artifacts = new class implements PlanArtifactStore {
            public bool $writeAttempted = false;

            public function readArtifact(
                CanonicalRunsDirectory $directory,
                string $filename
            ): PlanArtifactReadResult {
                return PlanArtifactReadResult::stopped(ReleaseBoundaryOutcome::FAILURE);
            }

            public function writeArtifact(
                CanonicalRunsDirectory $directory,
                string $filename,
                string $contents
            ): PlanArtifactWriteResult {
                $this->writeAttempted = true;

                return PlanArtifactWriteResult::success();
            }

            public function resolveRunsDirectory(
                string $path,
                string $runsDirectory
            ): RunsDirectoryResolutionResult {
                return RunsDirectoryResolutionResult::success(
                    new CanonicalRunsDirectory('/virtual/.runs/plan', '/virtual/.runs')
                );
            }
        };
        $hashing = new class implements HashingPort {
            public function sha256(string $contents): ReleaseBoundaryOperationResult
            {
                return ReleaseBoundaryOperationResult::success('not-a-sha256-digest');
            }
        };
        $effects = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePlanService(
            $artifacts,
            $hashing,
            $effects,
            $effects,
            new CanonicalJson(),
            new ReleasePlanFactory(),
            new ReleaseResultFactory()
        );

        $result = $service->plan($this->candidate(), '/virtual/.runs/plan', '/virtual/.runs');

        self::assertSame(4, $result->exitCode);
        self::assertSame('release.boundary.failure', $result->payload['findings'][0]['id']);
        self::assertArrayNotHasKey('plan_id', $result->payload);
        self::assertArrayNotHasKey('artifact', $result->payload);
        self::assertFalse($artifacts->writeAttempted);
    }

    /**
     * Covers invalid candidate and path strings before any planning boundary effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_invalid_utf8_inputs_before_any_effect_or_artifact(): void
    {
        $root = sys_get_temp_dir().'/release-plan-utf8-'.bin2hex(random_bytes(8));
        mkdir($root);
        $candidate = $this->candidate();
        $candidate['support_policy_identity'] = "support-policy-\xFF";

        try {
            foreach (
                [
                    [$candidate, $root, $root],
                    [$this->candidate(), $root."/invalid-\xFF", $root],
                    [$this->candidate(), $root, $root."/invalid-\xFF"]
                ] as [$plan, $output, $runsDirectory]
            ) {
                $ports = new DeterministicReleaseBoundaryFake();
                $result = $this->service($ports)->plan($plan, $output, $runsDirectory);

                self::assertSame(2, $result->exitCode);
                self::assertSame('release.plan.inputs_encoding_invalid', $result->payload['findings'][0]['id']);
                self::assertSame([], $result->payload['performed_effects']);
                self::assertCount(1, $result->payload['next_action']);
                self::assertArrayNotHasKey('artifact', $result->payload);
            }

            self::assertSame([], glob($root.'/*') ?: []);
        } finally {
            rmdir($root);
        }
    }

    /**
     * Covers invalid Application candidate shapes before runs-directory resolution.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_plan_rejects_empty_and_list_candidates_before_any_effect(): void
    {
        foreach ([[], [[]]] as $candidate) {
            $ports = new DeterministicReleaseBoundaryFake();
            $result = $this->service($ports)->plan($candidate, '/virtual/.runs/plan', '/virtual/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.plan.schema_version_missing', $result->payload['findings'][0]['id']);
            self::assertSame([], $result->payload['performed_effects']);
            self::assertSame([], $ports->effects());
            self::assertCount(1, $result->payload['next_action']);
            self::assertArrayNotHasKey('artifact', $result->payload);
        }
    }

    private function service(DeterministicReleaseBoundaryFake $ports): ReleasePlanService
    {
        return new ReleasePlanService($ports, $ports, $ports, $ports, new CanonicalJson(), new ReleasePlanFactory(), new ReleaseResultFactory());
    }

    /** @return array<string, mixed> */
    private function candidate(): array
    {
        return [
            'schema_version' => 'fight-common.release-plan/v1',
            'approved_version' => '1.3.0', 'release_class' => 'minor',
            'source_commit_oid' => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline' => [
                'version'           => '1.2.3',
                'tag_name'          => 'v1.2.3',
                'tag_object_oid'    => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                'peeled_commit_oid' => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
            ],
            'support_policy_identity' => 'support-policy-2026-08', 'expected_effect_classes' => [],
            'evidence_requirements' => ['full-submit-gate', 'planning-check'],
            'evidence_manifest_digest' => str_repeat('a', 64),
            'compatibility_exceptions' => [], 'patch_exception_authorities' => [],
            'required_approvals' => ['release-approval-001'],
            'release_approval_authority' => $this->releaseApprovalAuthority()
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function releaseApprovalAuthority(array $overrides = []): array
    {
        return [
            'approval_id'                  => 'release-approval-001',
            'approved_version'             => '1.3.0',
            'candidate_commit_oid'         => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline_tag_name'            => 'v1.2.3',
            'baseline_tag_object_oid'      => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            'baseline_peeled_commit_oid'   => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
            'evidence_manifest_digest'     => str_repeat('a', 64),
            'compatibility_exception_ids' => [],
            'patch_exception_authority_digests' => [],
            'minimum_release_class'        => 'minor',
            'authorized_release_class'     => 'minor',
            ...$overrides
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function patchExceptionAuthority(array $overrides = []): array
    {
        $authority = [
            'exception_id'                => 'compat-001',
            'exact_version'               => '1.2.4',
            'candidate_commit_oid'        => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
            'baseline_tag_object_oid'     => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            'baseline_peeled_commit_oid'  => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
            'emergency_class'             => 'security',
            'no_compatible_repair'        => [
                'attested'     => true,
                'evidence_ids' => ['evidence.no-compatible-repair.analysis']
            ],
            'compatibility_assessment'    => array_map(
                static fn (string $category): array => [
                    'category'       => $category,
                    'finding_id'     => 'release.compatibility.'.$category.'.break',
                    'evidence_id'    => 'evidence.compatibility.'.$category.'.inspection',
                    'classification' => $category === 'structural-api' ? 'major' : 'patch'
                ],
                CompatibilityAssessment::CATEGORIES
            ),
            'overridden_finding_ids'      => ['release.compatibility.structural-api.break'],
            'consumer_impact'             => 'One legacy consumer requires coordinated migration.',
            'mitigation'                  => 'Publish the documented compatibility adapter.',
            'test_evidence'               => ['compatibility.patch-regression'],
            'recovery_posture'            => 'Revert the release and publish a compatible repair.',
            'evidence_manifest_digest'    => str_repeat('a', 64),
            'release_authority_approval'  => 'release-authority-001',
            ...$overrides
        ];

        $authority['authority_digest'] = hash('sha256', (new CanonicalJson())->encode($authority));

        return $authority;
    }

    /** @return list<array{reason: ReleasePlanValidationFailure, candidate: array<string, mixed>}> */
    private function invalidCandidates(): array
    {
        $candidate = $this->candidate();
        $variants = [];
        $missingFields = [
            [ReleasePlanValidationFailure::SCHEMA_VERSION_MISSING, ['schema_version']],
            [ReleasePlanValidationFailure::APPROVED_VERSION_MISSING, ['approved_version']],
            [ReleasePlanValidationFailure::BASELINE_MISSING, ['baseline']],
            [ReleasePlanValidationFailure::BASELINE_VERSION_MISSING, ['baseline', 'version']],
            [ReleasePlanValidationFailure::BASELINE_TAG_NAME_MISSING, ['baseline', 'tag_name']],
            [ReleasePlanValidationFailure::RELEASE_CLASS_MISSING, ['release_class']],
            [ReleasePlanValidationFailure::SOURCE_COMMIT_OID_MISSING, ['source_commit_oid']],
            [ReleasePlanValidationFailure::BASELINE_TAG_OBJECT_OID_MISSING, ['baseline', 'tag_object_oid']],
            [ReleasePlanValidationFailure::BASELINE_PEELED_COMMIT_OID_MISSING, ['baseline', 'peeled_commit_oid']],
            [ReleasePlanValidationFailure::SUPPORT_POLICY_IDENTITY_MISSING, ['support_policy_identity']],
            [ReleasePlanValidationFailure::EXPECTED_EFFECT_CLASSES_MISSING, ['expected_effect_classes']],
            [ReleasePlanValidationFailure::EVIDENCE_REQUIREMENTS_MISSING, ['evidence_requirements']],
            [ReleasePlanValidationFailure::EVIDENCE_MANIFEST_DIGEST_MISSING, ['evidence_manifest_digest']],
            [ReleasePlanValidationFailure::COMPATIBILITY_EXCEPTIONS_MISSING, ['compatibility_exceptions']],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_MISSING, ['patch_exception_authorities']],
            [ReleasePlanValidationFailure::REQUIRED_APPROVALS_MISSING, ['required_approvals']],
            [ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISSING, ['release_approval_authority']]
        ];

        foreach ($missingFields as [$reason, $path]) {
            $invalid = $candidate;

            if (count($path) === 1) {
                unset($invalid[$path[0]]);
            } else {
                unset($invalid[$path[0]][$path[1]]);
            }

            $variants[] = ['reason' => $reason, 'candidate' => $invalid];
        }

        foreach ([
            [ReleasePlanValidationFailure::SCHEMA_VERSION_INVALID, [...$candidate, 'schema_version' => 'release-plan/v2']],
            [ReleasePlanValidationFailure::APPROVED_VERSION_INVALID, [...$candidate, 'approved_version' => '01.3.0']],
            [ReleasePlanValidationFailure::BASELINE_INVALID, [...$candidate, 'baseline' => 'v1.2.3']],
            [ReleasePlanValidationFailure::BASELINE_VERSION_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'version' => '1.02.3']]],
            [ReleasePlanValidationFailure::BASELINE_TAG_NAME_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'tag_name' => '1.2.3']]],
            [ReleasePlanValidationFailure::RELEASE_CLASS_INVALID, [...$candidate, 'release_class' => 'feature']],
            [ReleasePlanValidationFailure::SOURCE_COMMIT_OID_INVALID, [...$candidate, 'source_commit_oid' => 'd34db33f']],
            [ReleasePlanValidationFailure::BASELINE_TAG_OBJECT_OID_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'tag_object_oid' => 'a11ce0a1']]],
            [ReleasePlanValidationFailure::BASELINE_PEELED_COMMIT_OID_INVALID, [...$candidate, 'baseline' => [...$candidate['baseline'], 'peeled_commit_oid' => 'b45e1b45']]],
            [ReleasePlanValidationFailure::SUPPORT_POLICY_IDENTITY_INVALID, [...$candidate, 'support_policy_identity' => '   ']],
            [ReleasePlanValidationFailure::EXPECTED_EFFECT_CLASSES_INVALID, [...$candidate, 'expected_effect_classes' => ['git.unknown']]],
            [ReleasePlanValidationFailure::EVIDENCE_REQUIREMENTS_INVALID, [...$candidate, 'evidence_requirements' => ['planning check']]],
            [ReleasePlanValidationFailure::EVIDENCE_MANIFEST_DIGEST_INVALID, [...$candidate, 'evidence_manifest_digest' => 'sha256:*']],
            [ReleasePlanValidationFailure::COMPATIBILITY_EXCEPTIONS_INVALID, [...$candidate, 'compatibility_exceptions' => ['compat-1', 'compat-1']]],
            [ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID, [...$candidate, 'patch_exception_authorities' => ['invalid']]],
            [ReleasePlanValidationFailure::REQUIRED_APPROVALS_INVALID, [...$candidate, 'required_approvals' => ['release-manager', 'release-manager']]],
            [ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_INVALID, [...$candidate, 'release_approval_authority' => []]],
            [
                ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISMATCHED,
                [...$candidate, 'required_approvals' => ['release-approval-002']]
            ]
        ] as [$reason, $invalid]) {
            $variants[] = ['reason' => $reason, 'candidate' => $invalid];
        }

        return $variants;
    }
}
