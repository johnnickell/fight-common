<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\Boundary\ArchiveCreateResult;
use Fight\Release\Application\Boundary\ArchivePort;
use Fight\Release\Application\Boundary\ReleasePackageEffectSet;
use Fight\Release\Application\CanonicalJson;
use Fight\Release\Application\MachineResult;
use Fight\Release\Application\ReleaseCommand;
use Fight\Release\Application\ReleasePackageService;
use Fight\Release\Application\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers application-owned package orchestration. */
#[CoversClass(ReleasePackageService::class)]
#[CoversClass(MachineResult::class)]
#[CoversClass(ReleaseResultFactory::class)]
#[CoversClass(ReleaseCommand::class)]
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
#[CoversClass(ArchiveCreateResult::class)]
#[CoversClass(ReleasePackageEffectSet::class)]
class ReleasePackageServiceTest extends UnitTestCase
{
    /**
     * Covers the complete packaging journey: handoff validation, effect-set derivation, and archive creation.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_creates_one_deterministic_archive_from_a_valid_prepared_handoff(): void
    {
        $planId = hash('sha256', 'test-plan-001');
        $runId = hash('sha256', 'test-run-001');
        $wait = dirname(__DIR__, 3).'/.runs/release-package-service-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff($planId, $runId);
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $persisted = [...$handoff, 'handoff_id' => $handoffId];
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode($persisted).PHP_EOL);

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureArchiveFileList([
                'composer.json' => '{"name":"fight/common"}',
                'src/EventStore.php' => '<?php class EventStore {}',
                'README.md' => '# Fight Common'
            ]);

            $service = new ReleasePackageService(
                $ports,
                $ports,
                $ports,
                $ports,
                $ports,
                new CanonicalJson(),
                new ReleaseResultFactory($ports)
            );

            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(0, $result->exitCode);
            self::assertSame('packaged', $result->payload['status']);
            self::assertSame('release_packaging', $result->payload['capability']);
            self::assertSame($planId, $result->payload['plan_id']);
            self::assertSame($runId, $result->payload['run_id']);
            self::assertSame('d34db33fd34db33fd34db33fd34db33fd34db33f', $result->payload['candidate_oid']);
            self::assertSame('release.package.completed', $result->payload['findings'][0]['id']);
            self::assertSame(
                ['phase_handoff_revalidated', 'archive_created_and_verified'],
                $result->payload['verified_postconditions']
            );
            self::assertSame(['action' => 'certify_release_package'], $result->payload['next_action']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $result->payload['archive_digest']);
            self::assertSame(
                'fight-common.package-effect-set/v1',
                $result->payload['effect_set']['schema_version']
            );
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of a handoff outside the .runs directory.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_a_handoff_outside_runs_directory(): void
    {
        $ports = new DeterministicReleaseBoundaryFake();
        $service = new ReleasePackageService(
            $ports,
            $ports,
            $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleaseResultFactory($ports)
        );

        $result = $service->package('/tmp/outside-handoff.json', dirname(__DIR__, 3).'/.runs');

        self::assertSame(2, $result->exitCode);
        self::assertSame('release.package.handoff_forbidden', $result->payload['findings'][0]['id']);
    }

    /**
     * Covers rejection of an invalid handoff.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_an_invalid_handoff(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-invalid-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);
        $handoffPath = $wait.'/'.hash('sha256', 'wrong').'.phase-handoff.json';

        try {
            file_put_contents($handoffPath, '{"schema_version":"wrong"}'."\n");

            $ports = new DeterministicReleaseBoundaryFake();
            $service = new ReleasePackageService(
                $ports,
                $ports,
                $ports,
                $ports,
                $ports,
                new CanonicalJson(),
                new ReleaseResultFactory($ports)
            );

            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_invalid', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers archive creation failure.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_reports_archive_creation_failure(): void
    {
        $planId = hash('sha256', 'test-plan-fail');
        $runId = hash('sha256', 'test-run-fail');
        $wait = dirname(__DIR__, 3).'/.runs/release-package-fail-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff($planId, $runId);
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $persisted = [...$handoff, 'handoff_id' => $handoffId];
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode($persisted).PHP_EOL);

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureOutcome('archive.create', 'failure');
            $ports->configureArchiveFileList([
                'composer.json' => '{"name":"fight/common"}'
            ]);

            $service = new ReleasePackageService(
                $ports,
                $ports,
                $ports,
                $ports,
                $ports,
                new CanonicalJson(),
                new ReleaseResultFactory($ports)
            );

            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(4, $result->exitCode);
            self::assertSame('release.package.archive_creation_failed', $result->payload['findings'][0]['id']);
            self::assertSame(['action' => 'repair_archive_creation_provider'], $result->payload['next_action']);
            self::assertSame($planId, $result->payload['plan_id']);
            self::assertSame($runId, $result->payload['run_id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers already-satisfied outcome when archive already exists.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_reports_already_satisfied_when_archive_exists(): void
    {
        $planId = hash('sha256', 'test-plan-as');
        $runId = hash('sha256', 'test-run-as');
        $wait = dirname(__DIR__, 3).'/.runs/release-package-as-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff($planId, $runId);
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $persisted = [...$handoff, 'handoff_id' => $handoffId];
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode($persisted).PHP_EOL);

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureOutcome('archive.create', 'already_satisfied');

            $service = new ReleasePackageService(
                $ports,
                $ports,
                $ports,
                $ports,
                $ports,
                new CanonicalJson(),
                new ReleaseResultFactory($ports)
            );

            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(0, $result->exitCode);
            self::assertSame('packaged', $result->payload['status']);
            self::assertSame('release.package.already_satisfied', $result->payload['findings'][0]['id']);
            self::assertSame(['action' => 'certify_release_package'], $result->payload['next_action']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $result->payload['archive_digest']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers the effect set return when successful.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_returns_effect_set_with_included_paths(): void
    {
        $planId = hash('sha256', 'test-plan-fx');
        $runId = hash('sha256', 'test-run-fx');
        $wait = dirname(__DIR__, 3).'/.runs/release-package-fx-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff($planId, $runId);
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $persisted = [...$handoff, 'handoff_id' => $handoffId];
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode($persisted).PHP_EOL);

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureArchiveFileList([
                'composer.json' => '{}',
                'src/One.php' => '<?php',
                'src/Two.php' => '<?php'
            ]);

            $service = new ReleasePackageService(
                $ports,
                $ports,
                $ports,
                $ports,
                $ports,
                new CanonicalJson(),
                new ReleaseResultFactory($ports)
            );

            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(0, $result->exitCode);
            $effectSet = $result->payload['effect_set'];
            self::assertSame(['composer.json', 'src/One.php', 'src/Two.php'], $effectSet['included_paths']);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $effectSet['effect_set_id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of an unreadable handoff artifact.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_an_unreadable_handoff(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-unreadable-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoffPath = $wait.'/'.hash('sha256', 'x').'.phase-handoff.json';
            file_put_contents($handoffPath, '{"schema_version":"fight-common.release-phase-handoff/v1"}'."\n");

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureOutcome('filesystem.read', 'failure');
            $service = $this->service($ports);

            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_unreadable', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of a handoff missing its canonical trailing newline.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_a_handoff_without_a_canonical_newline(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-newline-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoffPath = $wait.'/'.hash('sha256', 'y').'.phase-handoff.json';
            file_put_contents($handoffPath, '{"schema_version":"fight-common.release-phase-handoff/v1"}');

            $service = $this->service(new DeterministicReleaseBoundaryFake());
            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_invalid', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of a handoff with invalid JSON.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_a_handoff_with_invalid_json(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-badjson-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoffPath = $wait.'/'.hash('sha256', 'z').'.phase-handoff.json';
            file_put_contents($handoffPath, '{invalid-json'."\n");

            $service = $this->service(new DeterministicReleaseBoundaryFake());
            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_invalid', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of a handoff with an invalid identity.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_a_handoff_with_an_invalid_identity(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-identity-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoffPath = $wait.'/'.hash('sha256', 'w').'.phase-handoff.json';
            $handoff = $this->handoff(hash('sha256', 'p'), hash('sha256', 'r'));
            $handoff['plan_id'] = 'not-a-digest';
            file_put_contents($handoffPath, (new CanonicalJson())->encode($handoff).PHP_EOL);

            $service = $this->service(new DeterministicReleaseBoundaryFake());
            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_invalid', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of a handoff when its digest cannot be re-derived.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_a_handoff_when_hashing_fails(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-hash-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff(hash('sha256', 'p2'), hash('sha256', 'r2'));
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL);

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureOutcome('hashing.sha256', 'failure');
            $service = $this->service($ports);
            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_invalid', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of a handoff named with a non-digest filename prefix.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_a_handoff_with_an_invalid_filename_prefix(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-prefix-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff(hash('sha256', 'p3'), hash('sha256', 'r3'));
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $handoffPath = $wait.'/not-a-real-digest-prefix.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL);

            $service = $this->service(new DeterministicReleaseBoundaryFake());
            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_invalid', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers rejection of a handoff missing its candidate bindings.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_a_handoff_missing_candidate_bindings(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-bindings-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff(hash('sha256', 'p4'), hash('sha256', 'r4'));
            unset($handoff['bindings']['source_commit_oid']);
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL);

            $service = $this->service(new DeterministicReleaseBoundaryFake());
            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.handoff_invalid', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers archive refusal, uncertainty, and drift stops.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_reports_archive_refusal_uncertainty_and_drift(): void
    {
        foreach (
            [
            'refusal'    => ['release.package.archive_creation_refused', 3, 'obtain_archive_creation_authority'],
            'uncertainty' => ['release.package.archive_creation_uncertain', 5, 'reconcile_archive_creation'],
            'drift'      => ['release.package.archive_creation_drift', 6, 'create_current_release_plan']
            ] as $outcome => [$finding, $exitCode, $action]
        ) {
            $wait = dirname(__DIR__, 3).'/.runs/release-package-stop-'.bin2hex(random_bytes(8));
            mkdir($wait, 0777, true);

            try {
                $handoff = $this->handoff(hash('sha256', 'p-stop'), hash('sha256', 'r-stop'));
                $encoded = (new CanonicalJson())->encode($handoff);
                $handoffId = hash('sha256', $encoded);
                $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
                file_put_contents(
                    $handoffPath,
                    (new CanonicalJson())->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL
                );

                $ports = new DeterministicReleaseBoundaryFake();
                $ports->configureOutcome('archive.create', $outcome);
                $service = $this->service($ports);
                $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

                self::assertSame($exitCode, $result->exitCode, $outcome);
                self::assertSame($finding, $result->payload['findings'][0]['id'], $outcome);
                self::assertSame(['action' => $action], $result->payload['next_action'], $outcome);
            } finally {
                foreach (glob($wait.'/*') ?: [] as $artifact) {
                    unlink($artifact);
                }

                rmdir($wait);
            }
        }
    }

    /**
     * Covers approval refusal when the supplied effect-set identity does not match.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_refuses_a_mismatched_effect_set_approval(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-refusal-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff(hash('sha256', 'p-refuse'), hash('sha256', 'r-refuse'));
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL);

            $approvalPath = $wait.'/approval.json';
            file_put_contents($approvalPath, '{"effect_set_id":"'.str_repeat('0', 64).'"}'."\n");

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureArchiveFileList(['composer.json' => '{}']);
            $service = $this->service($ports);
            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs', $approvalPath);

            self::assertSame(3, $result->exitCode);
            self::assertSame('release.package.effect_set_refused', $result->payload['findings'][0]['id']);
            self::assertSame(['action' => 'approve_exact_packaging_effects'], $result->payload['next_action']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers unreadable and invalid approval inputs.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_rejects_unreadable_and_invalid_approval_inputs(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-approval-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff(hash('sha256', 'p-appr'), hash('sha256', 'r-appr'));
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL);

            $ports = new DeterministicReleaseBoundaryFake();
            $ports->configureArchiveFileList(['composer.json' => '{}']);

            $invalidJsonPath = $wait.'/invalid.json';
            file_put_contents($invalidJsonPath, '{bad');

            $unreadableResult = $this->service(new DeterministicReleaseBoundaryFake())->package(
                $handoffPath,
                dirname(__DIR__, 3).'/.runs',
                $wait.'/missing-approval.json'
            );

            self::assertSame(2, $unreadableResult->exitCode);
            self::assertSame('release.package.approval_unreadable', $unreadableResult->payload['findings'][0]['id']);

            $invalidApproval = $this->service(new DeterministicReleaseBoundaryFake())->package(
                $handoffPath,
                dirname(__DIR__, 3).'/.runs',
                $invalidJsonPath
            );

            self::assertSame(2, $invalidApproval->exitCode);
            self::assertSame('release.package.approval_invalid', $invalidApproval->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Covers effect-set derivation failure when the archive boundary cannot derive one.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_package_reports_effect_set_derivation_failure(): void
    {
        $wait = dirname(__DIR__, 3).'/.runs/release-package-derive-'.bin2hex(random_bytes(8));
        mkdir($wait, 0777, true);

        try {
            $handoff = $this->handoff(hash('sha256', 'p-derive'), hash('sha256', 'r-derive'));
            $encoded = (new CanonicalJson())->encode($handoff);
            $handoffId = hash('sha256', $encoded);
            $handoffPath = $wait.'/'.$handoffId.'.phase-handoff.json';
            file_put_contents($handoffPath, (new CanonicalJson())->encode([...$handoff, 'handoff_id' => $handoffId]).PHP_EOL);

            $ports = new DeterministicReleaseBoundaryFake();
            $archive = new class implements ArchivePort {
                public function createArchive(
                    string $candidateOid,
                    string $version,
                    string $sourceRepositoryPath,
                    array $exclusions
                ): ArchiveCreateResult {
                    return ArchiveCreateResult::created('/tmp/archive.zip', str_repeat('a', 64));
                }

                public function deriveEffectSet(
                    string $candidateOid,
                    string $version,
                    string $sourceRepositoryPath
                ): ?ReleasePackageEffectSet {
                    return null;
                }
            };
            $service = new ReleasePackageService(
                $ports,
                $archive,
                $ports,
                $ports,
                $ports,
                new CanonicalJson(),
                new ReleaseResultFactory($ports)
            );

            $result = $service->package($handoffPath, dirname(__DIR__, 3).'/.runs');

            self::assertSame(2, $result->exitCode);
            self::assertSame('release.package.effect_set_derivation_failed', $result->payload['findings'][0]['id']);
        } finally {
            foreach (glob($wait.'/*') ?: [] as $artifact) {
                unlink($artifact);
            }

            rmdir($wait);
        }
    }

    /**
     * Builds a service around one deterministic boundary.
     */
    private function service(DeterministicReleaseBoundaryFake $ports): ReleasePackageService
    {
        return new ReleasePackageService(
            $ports,
            $ports,
            $ports,
            $ports,
            $ports,
            new CanonicalJson(),
            new ReleaseResultFactory($ports)
        );
    }

    /**
     * Builds a valid phase-handoff payload for testing.
     *
     * @return array<string, mixed>
     */
    private function handoff(string $planId, string $runId): array
    {
        return [
            'schema_version'    => 'fight-common.release-phase-handoff/v1',
            'approvals'         => [
                'release'  => [
                    'approval_id'                 => 'release-approval-001',
                    'approved_version'            => '1.3.0',
                    'candidate_commit_oid'        => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
                    'baseline_tag_name'           => 'v1.2.3',
                    'baseline_tag_object_oid'     => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
                    'baseline_peeled_commit_oid'  => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
                    'evidence_manifest_digest'    => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'compatibility_exception_ids' => [],
                    'patch_exception_authority_digests' => [],
                    'minimum_release_class'       => 'minor',
                    'authorized_release_class'    => 'minor'
                ],
                'required' => ['release-approval-001']
            ],
            'bindings'          => [
                'approved_version'            => '1.3.0',
                'baseline'                    => [
                    'version'           => '1.2.3',
                    'peeled_commit_oid' => 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45',
                    'tag_name'          => 'v1.2.3',
                    'tag_object_oid'    => 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1'
                ],
                'compatibility_exceptions'    => [],
                'evidence_manifest_digest'    => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'evidence_manifest_id'        => hash('sha256', 'test-manifest'),
                'evidence_requirements'       => ['full-submit-gate'],
                'expected_effect_classes'     => [],
                'minimum_release_class'       => 'minor',
                'patch_exception_authorities' => [],
                'release_class'               => 'minor',
                'source_commit_oid'           => 'd34db33fd34db33fd34db33fd34db33fd34db33f',
                'support_policy_identity'     => 'support-policy-2026-08'
            ],
            'next_action'       => ['action' => 'package_release_run'],
            'phase'             => 'preparation',
            'plan_id'           => $planId,
            'run_id'            => $runId,
            'status'            => 'prepared',
            'stop_state'        => null,
            'verified_evidence' => [
                'history_sha256'    => hash('sha256', 'test-history'),
                'projection_sha256' => hash('sha256', 'test-projection'),
                'postconditions'    => [
                    'immutable_plan_revalidated',
                    'prepared_run_projection_published'
                ]
            ]
        ];
    }
}