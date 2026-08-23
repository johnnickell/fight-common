<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter\Fake;

use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\Boundary\AuthorizationPort;
use Fight\Release\Application\Boundary\ArchiveCreateResult;
use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\ClockPort;
use Fight\Release\Application\Boundary\FilesystemPort;
use Fight\Release\Application\Boundary\GitHubPort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\PackagistPort;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\PlanArtifactWriteResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOperationResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseBoundaryPredicateResult;
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\Boundary\ReleaseRuntimeTermination;
use Fight\Release\Application\Boundary\RunsDirectoryResolutionResult;
use Fight\Release\Application\Boundary\SigningPort;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/** Covers the deterministic release boundary fake. */
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
#[CoversClass(ReleaseBoundaryOperationResult::class)]
#[CoversClass(ReleaseBoundaryOutcome::class)]
#[CoversClass(ReleaseBoundaryPredicateResult::class)]
#[CoversClass(CanonicalRunsDirectory::class)]
#[CoversClass(RunsDirectoryResolutionResult::class)]
#[CoversClass(ArchiveCreateResult::class)]
class DeterministicReleaseBoundaryFakeTest extends UnitTestCase
{
    /**
     * Covers the fake's ports and ordered configured outcomes.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_it_implements_each_release_boundary_port_and_records_configured_outcomes_in_order(): void
    {
        $stopped = ReleaseBoundaryOperationResult::stopped(ReleaseBoundaryOutcome::FAILURE);
        self::assertNull($stopped->value);
        self::assertFalse($stopped->hasValue());

        $fake = new DeterministicReleaseBoundaryFake([
            'git.resolve_ref'     => 'refusal',
            'hashing.sha256'      => 'failure',
            'clock.now'           => 'uncertainty',
            'signing.verify'      => 'drift',
            'authorization.check' => 'failure'
        ]);

        self::assertInstanceOf(FilesystemPort::class, $fake);
        self::assertInstanceOf(GitPort::class, $fake);
        self::assertInstanceOf(HashingPort::class, $fake);
        self::assertInstanceOf(ClockPort::class, $fake);
        self::assertInstanceOf(SigningPort::class, $fake);
        self::assertInstanceOf(AuthorizationPort::class, $fake);
        self::assertInstanceOf(GitHubPort::class, $fake);
        self::assertInstanceOf(PackagistPort::class, $fake);

        self::assertSame(
            ReleaseBoundaryOutcome::REFUSAL,
            $fake->resolveBaselineTag('v1.2.3', str_repeat('c', 40))->outcome
        );
        self::assertSame(ReleaseBoundaryOutcome::UNCERTAINTY, $fake->now()->outcome);
        self::assertSame(ReleaseBoundaryOutcome::DRIFT, $fake->verify()->outcome);
        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $fake->check()->outcome);
        self::assertSame('github-release-created', $fake->release()->value);
        self::assertSame('packagist-publication-completed', $fake->publish()->value);

        $success = new DeterministicReleaseBoundaryFake();
        self::assertSame('repository-inspected', $success->inspectRepository()->value);
        self::assertTrue($success->resolveBaselineTag('v1.2.3', str_repeat('c', 40))->isResolved());
        self::assertTrue($success->resolveExactAnnotatedTag('v1.2.3')->isResolved());
        self::assertSame('2026-08-19T12:00:00.000000Z', $success->now()->value);
        self::assertSame('signature-verified', $success->verify()->value);
        self::assertSame('authorized', $success->check()->value);
        $success->recordObservedEffect(ReleaseEffect::GIT_RESOLVE_REF, ReleaseBoundaryOutcome::SUCCESS);
        self::assertSame('success', $success->effects()[array_key_last($success->effects())]['outcome']);

        $capabilities = [
            'git', 'clock', 'signing', 'authorization', 'github', 'packagist'
        ];

        self::assertSame($capabilities, array_column($fake->effects(), 'capability'));
    }

    /**
     * Covers exact-effect isolation and non-execution for configured non-success outcomes.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_each_non_success_outcome_is_exact_and_does_not_execute_or_return_success_data(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-effects-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $existing = $directory.'/existing.json';
        file_put_contents($existing, 'persisted');
        $refused = $directory.'/refused.json';
        $fake = new DeterministicReleaseBoundaryFake([
            'filesystem.write'                  => 'refusal',
            'filesystem.read'                   => 'failure',
            'filesystem.inspect_directory'      => 'uncertainty',
            'filesystem.inspect_writable'       => 'drift',
            'filesystem.inspect_exists'         => 'refusal',
            'filesystem.inspect_runs_directory' => 'failure',
            'hashing.sha256'                    => 'failure',
            'git.inspect_repository'            => 'uncertainty',
            'git.resolve_ref'                   => 'drift',
            'clock.now'                         => 'refusal',
            'signing.verify'                    => 'failure',
            'authorization.check'               => 'uncertainty',
            'github.release'                    => 'drift',
            'packagist.publish'                 => 'failure'
        ]);

        try {
            self::assertSame(
                ReleaseBoundaryOutcome::REFUSAL,
                $fake->writeArtifact(
                    new CanonicalRunsDirectory($directory, $directory),
                    basename($refused),
                    'not-written'
                )->outcome
            );
            self::assertFalse(file_exists($refused));
            self::assertFalse($fake->exists($existing)->hasValue());
            self::assertFalse($fake->read($existing)->hasValue());
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $this->readArtifact($fake, $existing)->outcome);
            self::assertFalse($fake->isDirectory($directory)->hasValue());
            self::assertFalse($fake->isWritable($directory)->hasValue());
            self::assertFalse($fake->resolveRunsDirectory($directory, $directory)->hasDirectory());
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $fake->sha256('must-not-be-hashed')->outcome);
            self::assertSame(ReleaseBoundaryOutcome::UNCERTAINTY, $fake->inspectRepository()->outcome);
            self::assertSame(
                ReleaseBoundaryOutcome::DRIFT,
                $fake->resolveBaselineTag('v1.2.3', str_repeat('c', 40))->outcome
            );
            self::assertSame(ReleaseBoundaryOutcome::REFUSAL, $fake->now()->outcome);
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $fake->verify()->outcome);
            self::assertSame(ReleaseBoundaryOutcome::UNCERTAINTY, $fake->check()->outcome);
            self::assertSame(ReleaseBoundaryOutcome::DRIFT, $fake->release()->outcome);
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $fake->publish()->outcome);

            $baselineResolution = $fake->resolveBaselineTag('v1.2.3', str_repeat('c', 40));
            self::assertFalse($baselineResolution->isResolved());
            self::assertNull($baselineResolution->tagObjectOid);

            foreach (
                [
                    $fake->sha256('still-not-hashed'),
                    $fake->now(),
                    $fake->verify(),
                    $fake->check(),
                    $fake->release(),
                    $fake->publish()
                ] as $result
            ) {
                self::assertNull($result->value);
                self::assertFalse($result->hasValue());
            }

            $independent = new DeterministicReleaseBoundaryFake(['filesystem.write' => 'refusal']);
            self::assertSame(
                ReleaseBoundaryOutcome::REFUSAL,
                $independent->writeArtifact(
                    new CanonicalRunsDirectory($directory, $directory),
                    basename($refused),
                    'not-written'
                )->outcome
            );
            self::assertTrue($independent->exists($existing)->value);
            self::assertSame('success', $independent->effects()[1]['outcome']);
        } finally {
            unlink($existing);
            rmdir($directory);
        }
    }

    /**
     * Covers deterministic credential-free filesystem and hashing operations.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_its_filesystem_and_hashing_operations_are_credential_free_and_deterministic(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-fake-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory.'/artifact.json';

        try {
            $fake = new DeterministicReleaseBoundaryFake();

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $fake->read($path)->outcome);
            self::assertTrue($this->readArtifact($fake, $path)->missing);
            self::assertTrue($fake->isDirectory($directory)->value);
            self::assertTrue($fake->isWritable($directory)->value);
            self::assertFalse($fake->exists($path)->value);
            $canonical = new CanonicalRunsDirectory($directory, $directory);
            self::assertTrue($fake->writeArtifact($canonical, basename($path), 'release-plan')->persisted());
            $collision = $fake->writeArtifact($canonical, basename($path), 'replacement');
            self::assertFalse($collision->persisted());
            self::assertTrue($collision->requiresPostconditionVerification());
            $writeEffects = array_values(array_filter(
                $fake->effects(),
                static fn (array $effect): bool => $effect['effect_class'] === 'filesystem.write'
            ));
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'already_satisfied']
            ], $writeEffects);
            self::assertSame('release-plan', $fake->read($path)->value);
            self::assertSame('release-plan', $this->readArtifact($fake, $path)->contents);
            self::assertTrue($fake->exists($path)->value);
            self::assertFalse($fake->isDirectory($path)->value);
            self::assertFalse($fake->isWritable($directory.'/missing')->value);
            self::assertSame($directory, $fake->resolveRunsDirectory($directory, $directory)->directory?->path);
            self::assertFalse($fake->resolveRunsDirectory($directory.'/missing', $directory)->hasDirectory());
            $hash = $fake->sha256('release-plan');
            self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $hash->outcome);
            self::assertSame(hash('sha256', 'release-plan'), $hash->value);
            self::assertTrue($hash->hasValue());
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers private staging of a short write before an identical retry.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_a_partial_artifact_is_never_published_before_an_identical_write_is_retried(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-partial-write-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory.'/artifact.json';
        $fake = new DeterministicReleaseBoundaryFake([], 4);
        $canonical = new CanonicalRunsDirectory($directory, $directory);

        try {
            $partial = $fake->writeArtifact($canonical, basename($path), 'release-plan');

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $partial->outcome);
            self::assertFileDoesNotExist($path);

            $retry = $fake->writeArtifact($canonical, basename($path), 'release-plan');

            self::assertTrue($retry->persisted());
            self::assertSame('release-plan', file_get_contents($path));
            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'failure'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.write', 'outcome' => 'success']
            ], $fake->effects());
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers a helper process that cannot be opened failing closed without an artifact.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_artifact_storage_fails_closed_when_the_helper_process_cannot_be_opened(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-process-open-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $fake = new DeterministicReleaseBoundaryFake([], null, null, [], null, $directory.'/missing');

        try {
            $result = $fake->writeArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                'artifact.json',
                'release-plan'
            );

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $result->outcome);
            self::assertFileDoesNotExist($directory.'/artifact.json');
            self::assertSame('failure', $fake->effects()[0]['outcome']);

            $read = $fake->readArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                'artifact.json'
            );
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $read->outcome);
            self::assertFalse($read->missing);
            self::assertSame('failure', $fake->effects()[1]['outcome']);
        } finally {
            rmdir($directory);
        }
    }

    /**
     * Covers rejected stdin and unexpected helper output failing the write contract closed.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_artifact_storage_rejects_a_helper_that_breaks_its_process_contract(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-process-contract-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $helper = dirname(__DIR__, 4).'/release/fixtures/reject-artifact-input.py';
        $fake = new DeterministicReleaseBoundaryFake([], null, null, [], $helper);

        try {
            $result = $fake->writeArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                'artifact.json',
                str_repeat('release-plan', 1024 * 1024)
            );

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $result->outcome);
            self::assertFileDoesNotExist($directory.'/artifact.json');
            self::assertSame('failure', $fake->effects()[0]['outcome']);
        } finally {
            rmdir($directory);
        }
    }

    /**
     * Covers artifact-read helper diagnostics being rejected as a protocol failure.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_artifact_reads_reject_helper_process_diagnostics(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-read-protocol-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $fake = new DeterministicReleaseBoundaryFake(
            [],
            null,
            null,
            [],
            $directory.'/missing-helper.py'
        );

        try {
            $result = $fake->readArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                'artifact.json'
            );

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $result->outcome);
            self::assertSame('failure', $fake->effects()[0]['outcome']);
        } finally {
            rmdir($directory);
        }
    }

    /**
     * Covers deterministic real exclusive-create collisions with identical and conflicting winners.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_exclusive_create_collision_injection_creates_a_real_regular_file_winner(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-create-collision-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $canonical = new CanonicalRunsDirectory($directory, $directory);
        $identicalPath = $directory.'/identical.json';
        $differentPath = $directory.'/different.json';

        try {
            $identical = (new DeterministicReleaseBoundaryFake([], null, true))
                ->writeArtifact($canonical, basename($identicalPath), 'expected');
            $different = (new DeterministicReleaseBoundaryFake([], null, false))
                ->writeArtifact($canonical, basename($differentPath), 'expected');

            self::assertTrue($identical->requiresPostconditionVerification());
            self::assertSame('expected', file_get_contents($identicalPath));
            self::assertTrue($different->requiresPostconditionVerification());
            self::assertSame('{"concurrent":"different"}'.PHP_EOL, file_get_contents($differentPath));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers the typed provider result and truthful ledger for post-publication ambiguity.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_post_publication_helper_ambiguity_is_not_recorded_as_failure(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-published-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $fake = new DeterministicReleaseBoundaryFake([], null, null, [], null, null, 'output', 'exists');

        try {
            $result = $fake->writeArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                'artifact.json',
                'release-plan'
            );

            self::assertTrue($result->publicationMayHaveCompleted());
            self::assertSame('release-plan', file_get_contents($directory.'/artifact.json'));
            self::assertSame([[
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.write',
                'outcome'      => 'uncertainty'
            ]], $fake->effects());
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers destructive final-state injection preserving uncertainty in the typed result and effect ledger.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_destructive_post_publication_final_state_is_never_recorded_as_success(): void
    {
        foreach (['missing', 'mismatch'] as $finalState) {
            $directory = sys_get_temp_dir().'/fight-common-release-final-state-'.bin2hex(random_bytes(8));
            mkdir($directory);
            $fake = new DeterministicReleaseBoundaryFake([], null, null, [], null, null, null, $finalState);

            try {
                $result = $fake->writeArtifact(
                    new CanonicalRunsDirectory($directory, $directory),
                    'artifact.json',
                    'release-plan'
                );

                self::assertTrue($result->publicationMayHaveCompleted(), $finalState);
                self::assertSame([[
                    'capability'   => 'filesystem',
                    'effect_class' => 'filesystem.write',
                    'outcome'      => 'uncertainty'
                ]], $fake->effects());
            } finally {
                (new Filesystem())->remove($directory);
            }
        }
    }

    /**
     * Covers bounded PHP transport of an oversized descriptor-relative helper read.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_artifact_read_transport_rejects_content_above_the_protocol_bound(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-read-bound-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $filename = str_repeat('b', 64).'.json';
        $stream = fopen($directory.'/'.$filename, 'wb');
        self::assertIsResource($stream);
        self::assertTrue(ftruncate($stream, (16 * 1024 * 1024) + 1));
        fclose($stream);
        $fake = new DeterministicReleaseBoundaryFake();

        try {
            $configuration = new DeterministicReleaseBoundaryFake();
            self::assertFalse($configuration->configurePlanAuthorityStatus('invalid'));
            self::assertTrue($configuration->configurePlanAuthorityStatus('approval_drift'));
            self::assertSame(
                'approval_drift',
                $configuration->revalidatePlanAuthority([])->value
            );
            $configuration->interruptRunProjectionOnce();

            $result = $fake->readArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                $filename
            );

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $result->outcome);
            self::assertSame('failure', $fake->effects()[0]['outcome']);
            self::assertSame((16 * 1024 * 1024) + 1, filesize($directory.'/'.$filename));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers bounded capture when an untrusted helper itself exceeds either process-output limit.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_artifact_transport_terminates_a_helper_with_unbounded_output(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-helper-bound-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $helper = dirname(__DIR__, 3).'/fixtures/oversized-artifact-output.py';
        $fake = new DeterministicReleaseBoundaryFake([], null, null, [], $helper);
        $canonical = new CanonicalRunsDirectory($directory, $directory);

        try {
            $write = $fake->writeArtifact($canonical, 'artifact.json', 'release-plan');
            $read = $fake->readArtifact($canonical, 'artifact.json');

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $write->outcome);
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $read->outcome);
            self::assertFileDoesNotExist($directory.'/artifact.json');
            self::assertSame(['failure', 'failure'], array_column($fake->effects(), 'outcome'));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers write-boundary revalidation after a formerly canonical parent is replaced by an escaping symlink.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_artifact_write_revalidates_its_canonical_parent_and_never_follows_a_retarget(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-write-race-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $inside = $runs.'/inside';
        $outside = $fixture.'/outside';
        mkdir($inside, 0777, true);
        mkdir($outside);
        $fake = new DeterministicReleaseBoundaryFake();
        $resolved = $fake->resolveRunsDirectory($inside, $runs);
        self::assertTrue($resolved->hasDirectory());
        $canonical = $resolved->directory;
        self::assertInstanceOf(CanonicalRunsDirectory::class, $canonical);
        rmdir($inside);
        symlink($outside, $inside);

        try {
            $write = $fake->writeArtifact($canonical, 'escaped.json', 'must-not-escape');

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $write->outcome);
            self::assertFileDoesNotExist($outside.'/escaped.json');
            self::assertSame([
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.write',
                'outcome'      => 'failure'
            ], $fake->effects()[array_key_last($fake->effects())]);
        } finally {
            unlink($inside);
            rmdir($outside);
            rmdir($runs);
            rmdir($fixture);
        }
    }

    /**
     * Covers no-follow rejection for a pre-existing symbolic-link artifact target.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_artifact_write_never_follows_a_symbolic_link_target(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-target-link-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $outside = $directory.'/outside.json';
        $link = $directory.'/artifact.json';
        file_put_contents($outside, 'outside-original');
        symlink($outside, $link);
        $fake = new DeterministicReleaseBoundaryFake();

        try {
            $result = $fake->writeArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                basename($link),
                'replacement'
            );

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $result->outcome);
            self::assertSame('outside-original', file_get_contents($outside));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers an exclusive-create failure that is not a regular artifact collision.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_artifact_write_does_not_classify_a_directory_target_as_a_collision(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-directory-target-'.bin2hex(random_bytes(8));
        $target = $directory.'/artifact.json';
        mkdir($target, 0777, true);
        $fake = new DeterministicReleaseBoundaryFake();

        try {
            $result = $fake->writeArtifact(
                new CanonicalRunsDirectory($directory, $directory),
                basename($target),
                'replacement'
            );

            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $result->outcome);
            self::assertFalse($result->requiresPostconditionVerification());
            self::assertSame([[
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.write',
                'outcome'      => 'failure'
            ]], $fake->effects());
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * Covers typed stops and non-directory rejection during canonical resolution.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_runs_directory_resolution_rejects_or_preserves_each_nested_inspection_stop(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-resolution-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $file = $directory.'/not-a-directory';
        file_put_contents($file, 'fixture');

        try {
            $notDirectory = (new DeterministicReleaseBoundaryFake())
                ->resolveRunsDirectory($file, $directory);
            self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $notDirectory->outcome);
            self::assertFalse($notDirectory->hasDirectory());

            $configuredNotDirectory = (new DeterministicReleaseBoundaryFake(
                [],
                null,
                null,
                ['filesystem.inspect_directory' => false]
            ))->resolveRunsDirectory($directory, $directory);
            self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $configuredNotDirectory->outcome);
            self::assertFalse($configuredNotDirectory->hasDirectory());

            $notWritable = (new DeterministicReleaseBoundaryFake(
                [],
                null,
                null,
                ['filesystem.inspect_writable' => false]
            ))->resolveRunsDirectory($directory, $directory);
            self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $notWritable->outcome);
            self::assertFalse($notWritable->hasDirectory());

            $directoryStop = (new DeterministicReleaseBoundaryFake([
                'filesystem.inspect_directory' => 'uncertainty'
            ]))->resolveRunsDirectory($directory, $directory);
            self::assertSame(ReleaseBoundaryOutcome::UNCERTAINTY, $directoryStop->outcome);
            self::assertFalse($directoryStop->hasDirectory());

            $writableStop = (new DeterministicReleaseBoundaryFake([
                'filesystem.inspect_writable' => 'drift'
            ]))->resolveRunsDirectory($directory, $directory);
            self::assertSame(ReleaseBoundaryOutcome::DRIFT, $writableStop->outcome);
            self::assertFalse($writableStop->hasDirectory());
        } finally {
            unlink($file);
            rmdir($directory);
        }
    }

    /**
     * Covers malformed literal authority and helper-process failures during runs resolution.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_runs_directory_resolution_fails_closed_for_invalid_authority_and_helper_contracts(): void
    {
        $directory = sys_get_temp_dir().'/fight-common-release-resolution-contract-'.bin2hex(random_bytes(8));
        $nested = $directory.'/nested';
        mkdir($nested, 0777, true);

        try {
            $fake = new DeterministicReleaseBoundaryFake();
            self::assertFalse($fake->resolveRunsDirectory('/outside', $directory)->hasDirectory());
            self::assertFalse(
                $fake->resolveRunsDirectory($nested.'/../nested', $directory)->hasDirectory()
            );

            $missingWorkingDirectory = new DeterministicReleaseBoundaryFake(
                [],
                null,
                null,
                [],
                null,
                $directory.'/missing'
            );
            self::assertFalse(
                $missingWorkingDirectory->resolveRunsDirectory($directory, $directory)->hasDirectory()
            );
            self::assertSame('failure', $missingWorkingDirectory->effects()[0]['outcome']);

            $diagnosticHelper = new DeterministicReleaseBoundaryFake(
                [],
                null,
                null,
                [],
                dirname(__DIR__, 4).'/release/fixtures/reject-artifact-input.py'
            );
            $diagnosticResolution = $diagnosticHelper->resolveRunsDirectory($directory, $directory);
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $diagnosticResolution->outcome);
            self::assertFalse($diagnosticResolution->hasDirectory());
            self::assertSame('failure', $diagnosticHelper->effects()[0]['outcome']);
        } finally {
            rmdir($nested);
            rmdir($directory);
        }
    }

    /**
     * Covers the canonical-directory and resolution-result invariants.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_canonical_runs_directory_values_reject_escaping_paths_and_filenames(): void
    {
        $directory = new CanonicalRunsDirectory('/repository/.runs/plans', '/repository/.runs');
        self::assertSame('/repository/.runs/plans/plan.json', $directory->artifactPath('plan.json'));
        self::assertTrue($directory->matches('/repository/.runs/plans', '/repository/.runs'));
        self::assertFalse($directory->matches('/repository/.runs/other', '/repository/.runs'));

        $rejected = RunsDirectoryResolutionResult::rejected();
        self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $rejected->outcome);
        self::assertFalse($rejected->hasDirectory());
        self::assertNull($rejected->directory);

        foreach (
            [
                ['', '/repository/.runs'],
                ['relative', '/repository/.runs'],
                ['/repository/.runs/plans', 'relative'],
                ['/outside', '/repository/.runs'],
                ['/repository/.runs/../outside', '/repository/.runs'],
                ['/repository/.runs/./plans', '/repository/.runs'],
                ['/repository/.runs//plans', '/repository/.runs'],
                ['/repository/.runs/plans/', '/repository/.runs'],
                ['/repository/.runs/plans', '/repository/.runs/'],
                ["/repository/.runs/plans\0escape", '/repository/.runs'],
                ["/repository/.runs/plans\xFF", '/repository/.runs']
            ] as [$path, $root]
        ) {
            try {
                new CanonicalRunsDirectory($path, $root);
                self::fail('Malformed canonical directory authority must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'A canonical runs directory must stay below its absolute runs root.',
                    $exception->getMessage()
                );
            }
        }

        foreach (['', '../escape.json', '.', '..', "plan\0.json", "plan\xFF.json"] as $filename) {
            try {
                $directory->artifactPath($filename);
                self::fail('Malformed artifact filenames must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(
                    'An artifact filename must be one non-empty path segment.',
                    $exception->getMessage()
                );
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A stopped runs-directory resolution requires a non-success outcome.');
        RunsDirectoryResolutionResult::stopped(ReleaseBoundaryOutcome::SUCCESS);
    }

    /**
     * Covers final filesystem outcomes without conflating a false predicate with a failed check.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_filesystem_reads_and_predicates_record_exactly_one_truthful_final_outcome(): void
    {
        $root = sys_get_temp_dir().'/fight-common-release-predicates-'.bin2hex(random_bytes(8));
        mkdir($root);
        $missing = $root.'/missing';
        $fake = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame(ReleaseBoundaryOutcome::FAILURE, $fake->read($missing)->outcome);
            self::assertTrue($this->readArtifact($fake, $missing)->missing);
            self::assertFalse($fake->isDirectory($missing)->value);
            self::assertFalse($fake->isWritable($missing)->value);
            self::assertFalse($fake->exists($missing)->value);
            self::assertFalse($fake->resolveRunsDirectory($missing, $root)->hasDirectory());

            self::assertSame([
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'failure'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.read', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_directory', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_writable', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_exists', 'outcome' => 'success'],
                ['capability' => 'filesystem', 'effect_class' => 'filesystem.inspect_runs_directory', 'outcome' => 'success']
            ], $fake->effects());
        } finally {
            rmdir($root);
        }
    }

    /**
     * Covers a read that opens but fails while consuming the stream.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_an_unreadable_open_stream_records_one_final_failure(): void
    {
        stream_wrapper_register('release-read-failure', FailingReleaseReadStream::class);
        $fake = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame(
                ReleaseBoundaryOutcome::FAILURE,
                $fake->read('release-read-failure://fixture')->outcome
            );
            self::assertSame([[
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.read',
                'outcome'      => 'failure'
            ]], $fake->effects());
        } finally {
            stream_wrapper_unregister('release-read-failure');
        }
    }

    /**
     * Covers the predicate result invariant.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_predicate_results_distinguish_false_values_from_governed_stops(): void
    {
        $false = ReleaseBoundaryPredicateResult::success(false);
        self::assertTrue($false->hasValue());
        self::assertFalse($false->value);

        $stopped = ReleaseBoundaryPredicateResult::stopped(ReleaseBoundaryOutcome::UNCERTAINTY);
        self::assertFalse($stopped->hasValue());
        self::assertNull($stopped->value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A stopped predicate cannot have a successful outcome.');
        ReleaseBoundaryPredicateResult::stopped(ReleaseBoundaryOutcome::SUCCESS);
    }

    /**
     * Covers valid outcome classification without recording invalid configuration.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_configured_boundary_outcomes_and_classifications_are_owned_by_the_fake_without_recording_invalid_configuration(): void
    {
        $fake = new DeterministicReleaseBoundaryFake();

        self::assertTrue($fake->configureOutcome('git.inspect_repository', 'refusal'));
        self::assertFalse($fake->configureOutcome('git.inspect_repository', 'unsupported'));
        self::assertSame(ReleaseBoundaryOutcome::REFUSAL, $fake->inspectRepository()->outcome);
        self::assertSame([
            'status'      => 'authority_required',
            'exit_class'  => 'refused',
            'exit_code'   => 3,
            'next_action' => 'obtain_boundary_authority'
        ], ReleaseBoundaryOutcome::REFUSAL->classification());
        self::assertSame([[
            'capability'   => 'git',
            'effect_class' => 'git.inspect_repository',
            'outcome'      => 'refusal'
        ]], $fake->effects());

        self::assertTrue($fake->configureOutcome('filesystem.write', 'failure'));
        self::assertSame(
            ReleaseBoundaryOutcome::FAILURE,
            $fake->writeArtifact(
                new CanonicalRunsDirectory(sys_get_temp_dir(), sys_get_temp_dir()),
                'release-boundary-refused-write',
                'not-written'
            )->outcome
        );
    }

    /**
     * Covers constructor validation before any malformed configured effect can execute.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_constructor_rejects_unknown_effects_and_outcomes_before_recording_an_effect(): void
    {
        foreach (
            [
                ['unknown.effect' => 'success'],
                ['git.inspect_repository' => 'unknown-outcome'],
                ['hashing.sha256' => 'already_satisfied'],
                ['filesystem.write' => 'already_satisfied']
            ] as $configuration
        ) {
            try {
                new DeterministicReleaseBoundaryFake($configuration);
                self::fail('Malformed boundary configuration must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Unsupported deterministic release boundary configuration.', $exception->getMessage());
            }
        }

        try {
            new DeterministicReleaseBoundaryFake([], -1);
            self::fail('A negative partial-write limit must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A partial artifact write byte limit cannot be negative.',
                $invalidArgumentException->getMessage()
            );
        }

        try {
            new DeterministicReleaseBoundaryFake([], null, null, ['filesystem.read' => false]);
            self::fail('A non-predicate override must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'Unsupported deterministic filesystem predicate configuration.',
                $invalidArgumentException->getMessage()
            );
        }

        try {
            new DeterministicReleaseBoundaryFake([], null, null, ['filesystem.inspect_writable' => 'false']);
            self::fail('A non-boolean predicate override must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'Unsupported deterministic filesystem predicate configuration.',
                $invalidArgumentException->getMessage()
            );
        }
    }

    /**
     * Covers every closed outcome classification without unchecked string lookup.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_each_ordinary_boundary_outcome_owns_one_machine_classification(): void
    {
        self::assertSame(
            [
                ['succeeded', 'success', 0, 'continue_release_planning'],
                ['evidence_indeterminate', 'uncertain', 5, 'verify_boundary_postcondition'],
                ['authority_required', 'refused', 3, 'obtain_boundary_authority'],
                ['policy_blocked', 'failed', 4, 'repair_boundary_failure'],
                ['evidence_indeterminate', 'uncertain', 5, 'reconcile_boundary_effect'],
                ['stale_plan', 'drifted', 6, 'refresh_bound_inputs']
            ],
            array_map(
                static function (ReleaseBoundaryOutcome $outcome): array {
                    $classification = $outcome->classification();

                    return [
                        $classification['status'],
                        $classification['exit_class'],
                        $classification['exit_code'],
                        $classification['next_action']
                    ];
                },
                ReleaseBoundaryOutcome::cases()
            )
        );
        self::assertNull(ReleaseBoundaryOutcome::tryFrom('crash'));
        self::assertNull(ReleaseBoundaryOutcome::tryFrom('unknown-outcome'));
    }

    /**
     * Covers closed capability contracts and fail-closed effect configuration.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong
    public function test_that_ports_expose_only_typed_operations_and_unknown_or_cross_capability_effects_fail_closed(): void
    {
        $contracts = [
            FilesystemPort::class    => [
                'exists(string):'.ReleaseBoundaryPredicateResult::class,
                'isDirectory(string):'.ReleaseBoundaryPredicateResult::class,
                'resolveRunsDirectory(string,string):'.RunsDirectoryResolutionResult::class,
                'isWritable(string):'.ReleaseBoundaryPredicateResult::class,
                'read(string):'.ReleaseBoundaryOperationResult::class
            ],
            PlanArtifactStore::class => [
                'readArtifact('.CanonicalRunsDirectory::class.',string):'.\Fight\Release\Application\Boundary\PlanArtifactReadResult::class,
                'resolveRunsDirectory(string,string):'.RunsDirectoryResolutionResult::class,
                'writeArtifact('.CanonicalRunsDirectory::class.',string,string):'.PlanArtifactWriteResult::class
            ],
            GitPort::class           => [
                'inspectRepository():'.ReleaseBoundaryOperationResult::class,
                'resolveBaselineTag(string,string):'.\Fight\Release\Application\Boundary\BaselineTagResolutionResult::class,
                'resolveExactAnnotatedTag(string):'.\Fight\Release\Application\Boundary\BaselineTagResolutionResult::class
            ],
            HashingPort::class       => ['sha256(string):'.ReleaseBoundaryOperationResult::class],
            ClockPort::class         => ['now():'.ReleaseBoundaryOperationResult::class],
            SigningPort::class       => ['verify():'.ReleaseBoundaryOperationResult::class],
            AuthorizationPort::class => ['check():'.ReleaseBoundaryOperationResult::class],
            GitHubPort::class        => ['release():'.ReleaseBoundaryOperationResult::class],
            PackagistPort::class     => ['publish():'.ReleaseBoundaryOperationResult::class]
        ];

        foreach ($contracts as $port => $signatures) {
            $contract = new \ReflectionClass($port);
            $declaredSignatures = array_map(
                static function (\ReflectionMethod $method): string {
                    $parameters = array_map(
                        static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
                        $method->getParameters()
                    );

                    return sprintf(
                        '%s(%s):%s',
                        $method->getName(),
                        implode(',', $parameters),
                        (string) $method->getReturnType()
                    );
                },
                $contract->getMethods()
            );
            sort($declaredSignatures);
            sort($signatures);

            self::assertFalse($contract->hasMethod('perform'));
            self::assertFalse($contract->hasMethod('write'));
            self::assertSame($signatures, $declaredSignatures);
        }

        $fake = new DeterministicReleaseBoundaryFake();

        self::assertFalse(method_exists($fake, 'perform'));

        foreach (['unqualified_effect', 'git.unknown', 'git.filesystem.write', 'filesystem.git.resolve_ref'] as $effect) {
            self::assertFalse($fake->configureOutcome($effect, 'success'));
        }

        self::assertSame([], $fake->effects());
    }

    /**
     * Covers every observable run-state resume classification and fail-closed creation precondition.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_run_state_creation_and_resume_fail_closed_without_mutating_bound_evidence(): void
    {
        $root = sys_get_temp_dir().'/fight-common-release-run-fake-'.bin2hex(random_bytes(8));
        mkdir($root);
        $directory = new CanonicalRunsDirectory($root.'/attempt', $root);
        mkdir($directory->path);
        $planId = str_repeat('a', 64);
        $otherPlanId = str_repeat('c', 64);
        $runId = str_repeat('b', 64);
        $fake = new DeterministicReleaseBoundaryFake();

        try {
            self::assertSame(['status' => 'missing'], $fake->resumePreparedRun($directory, $planId, $runId));
            self::assertSame([
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.read',
                'outcome'      => 'uncertainty'
            ], $fake->effects()[array_key_last($fake->effects())]);

            $state = $fake->createPreparedRun($directory, $planId, $runId);
            self::assertIsArray($state);
            self::assertSame('evidence_pending', $fake->resumePreparedRun($directory, $planId, $runId)['status']);
            self::assertSame('filesystem.read', $fake->effects()[array_key_last($fake->effects())]['effect_class']);
            self::assertSame('stale', $fake->resumePreparedRun($directory, $otherPlanId, $runId)['status']);

            file_put_contents($state['projection_path'], "corrupt\n");
            self::assertSame('indeterminate', $fake->resumePreparedRun($directory, $planId, $runId)['status']);
            unlink($state['history_path']);
            self::assertSame('indeterminate', $fake->resumePreparedRun($directory, $planId, $runId)['status']);

            $lock = fopen($directory->path.'/runs/'.$runId.'/.writer.lock', 'c');
            self::assertIsResource($lock);
            self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

            try {
                self::assertSame('conflict', $fake->resumePreparedRun($directory, $planId, $runId)['status']);
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

            $blockedDirectory = new CanonicalRunsDirectory($root.'/blocked', $root);
            mkdir($blockedDirectory->path);
            file_put_contents($blockedDirectory->path.'/runs', 'not-a-directory');
            self::assertSame(
                ['status' => 'failed'],
                $fake->createPreparedRun($blockedDirectory, $planId, str_repeat('d', 64))
            );

            $blockedRunDirectory = new CanonicalRunsDirectory($root.'/blocked-run', $root);
            mkdir($blockedRunDirectory->path);
            mkdir($blockedRunDirectory->path.'/runs');
            file_put_contents($blockedRunDirectory->path.'/runs/'.str_repeat('e', 64), 'not-a-directory');
            self::assertSame(
                ['status' => 'failed'],
                $fake->createPreparedRun($blockedRunDirectory, $planId, str_repeat('e', 64))
            );

            $blockedHistoryDirectory = new CanonicalRunsDirectory($root.'/blocked-history', $root);
            mkdir($blockedHistoryDirectory->path);
            mkdir($blockedHistoryDirectory->path.'/runs');
            $blockedRun = $blockedHistoryDirectory->path.'/runs/'.str_repeat('f', 64);
            mkdir($blockedRun);
            file_put_contents($blockedRun.'/history.jsonl', 'preexisting');
            self::assertSame(
                ['status' => 'conflict'],
                $fake->createPreparedRun($blockedHistoryDirectory, $planId, str_repeat('f', 64))
            );

            foreach (
                [
                    'open'                => 'indeterminate',
                    'write'               => 'indeterminate',
                    'append_lock'         => 'indeterminate',
                    'projection_stage'    => 'indeterminate',
                    'projection_publish'  => 'indeterminate',
                    'prepared_projection' => 'indeterminate',
                    'runs_directory'      => 'failed',
                    'writer_open'         => 'indeterminate',
                    'writer_lock'         => 'conflict'
                ] as $failurePoint => $status
            ) {
                $failureDirectory = new CanonicalRunsDirectory(
                    $root.'/failure-'.$failurePoint,
                    $root
                );
                mkdir($failureDirectory->path);
                $failing = new DeterministicReleaseBoundaryFake(runStateFailureOnce: $failurePoint);

                self::assertSame(
                    ['status' => $status],
                    $failing->createPreparedRun(
                        $failureDirectory,
                        $planId,
                        hash('sha256', $failurePoint)
                    ),
                    $failurePoint
                );
            }

            $interruptedDirectory = new CanonicalRunsDirectory($root.'/interrupted-resume', $root);
            mkdir($interruptedDirectory->path);
            $interrupted = new DeterministicReleaseBoundaryFake(interruptRunProjectionOnce: true);

            try {
                $interrupted->createPreparedRun($interruptedDirectory, $planId, str_repeat('9', 64));
                self::fail('The configured run-state interruption was not raised.');
            } catch (\Fight\Release\Application\Boundary\ReleaseBoundaryCrash $crash) {
                self::assertSame('filesystem.write', $crash->effectClass);
            }

            $projectionFailure = new DeterministicReleaseBoundaryFake(
                runStateFailureOnce: 'prepared_projection'
            );
            self::assertSame(
                ['status' => 'indeterminate'],
                $projectionFailure->resumePreparedRun($interruptedDirectory, $planId, str_repeat('9', 64))
            );
            self::assertSame('uncertainty', $projectionFailure->effects()[0]['outcome']);

            $repairedProjection = new DeterministicReleaseBoundaryFake();
            $repaired = $repairedProjection->resumePreparedRun(
                $interruptedDirectory,
                $planId,
                str_repeat('9', 64)
            );
            self::assertTrue($repaired['projection_repaired']);
            self::assertSame(
                ['filesystem.read', 'filesystem.write'],
                array_column($repairedProjection->effects(), 'effect_class')
            );

            $finalizeDirectory = new CanonicalRunsDirectory($root.'/finalize', $root);
            mkdir($finalizeDirectory->path);
            $finalizeRunId = str_repeat('7', 64);
            $finalize = new DeterministicReleaseBoundaryFake();
            self::assertSame(
                'created',
                $finalize->createPreparedRun($finalizeDirectory, $planId, $finalizeRunId)['status']
            );
            $finalizeLock = fopen($finalizeDirectory->path.'/runs/'.$finalizeRunId.'/.writer.lock', 'c');
            self::assertIsResource($finalizeLock);
            self::assertTrue(flock($finalizeLock, LOCK_EX | LOCK_NB));

            try {
                self::assertSame(
                    ['status' => 'conflict'],
                    $finalize->finalizePreparedRun(
                        $finalizeDirectory,
                        $planId,
                        $finalizeRunId,
                        str_repeat('1', 64),
                        str_repeat('2', 64),
                        2,
                        'prepared'
                    )
                );
            } finally {
                flock($finalizeLock, LOCK_UN);
                fclose($finalizeLock);
            }

            self::assertSame(
                ['status' => 'stale'],
                $finalize->finalizePreparedRun(
                    $finalizeDirectory,
                    str_repeat('3', 64),
                    $finalizeRunId,
                    str_repeat('1', 64),
                    str_repeat('2', 64),
                    2,
                    'prepared'
                )
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers rejection of constructor-bypassed run-state authority before helper invocation.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_forged_run_state_directory_authority_is_rejected_before_helper_invocation(): void
    {
        $reflection = new \ReflectionClass(CanonicalRunsDirectory::class);
        $directory = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('path')->setValue($directory, '/repository/.runs/../outside');
        $reflection->getProperty('runsRoot')->setValue($directory, '/repository/.runs');

        self::assertSame(
            ['status' => 'failed'],
            new DeterministicReleaseBoundaryFake()->createPlannedRun(
                $directory,
                str_repeat('a', 64),
                str_repeat('b', 64)
            )
        );
    }

    /**
     * Covers failure to start the descriptor-relative run-state helper process.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_run_state_helper_process_start_failure_is_closed(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-start-'.bin2hex(random_bytes(8));
        mkdir($root);

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            new DeterministicReleaseBoundaryFake(
                artifactProcessWorkingDirectory: $root.'/missing'
            )->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    /**
     * Covers a syntactically valid but non-object run-state helper response.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_malformed_run_state_helper_response_is_closed(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-response-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import sys; sys.stdout.write("[]")');
        $reflection = new \ReflectionClass(DeterministicReleaseBoundaryFake::class);
        $fake = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('runStateStoreHelper')->setValue($fake, $helper);
        $reflection->getProperty('artifactProcessWorkingDirectory')->setValue($fake, null);
        $reflection->getProperty('runStateFailureOnce')->setValue($fake, null);
        $reflection->getProperty('runStateReplacementTarget')->setValue($fake, null);
        $reflection->getProperty('runStateHelperTimeoutSeconds')->setValue($fake, 30);
        $reflection->getProperty('runStateRead')->setValue($fake, null);

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            $fake->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    /**
     * Covers a failed run-state helper process after successful process creation.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_failed_run_state_helper_execution_is_closed(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-failure-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import sys; sys.stderr.write("failure"); sys.exit(1)');
        $reflection = new \ReflectionClass(DeterministicReleaseBoundaryFake::class);
        $fake = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('runStateStoreHelper')->setValue($fake, $helper);
        $reflection->getProperty('artifactProcessWorkingDirectory')->setValue($fake, null);
        $reflection->getProperty('runStateFailureOnce')->setValue($fake, null);
        $reflection->getProperty('runStateReplacementTarget')->setValue($fake, null);
        $reflection->getProperty('runStateHelperTimeoutSeconds')->setValue($fake, 30);
        $reflection->getProperty('runStateRead')->setValue($fake, null);

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            $fake->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    /**
     * Covers invalid closed receipts and configured protocol termination
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_invalid_closed_run_state_receipts_terminate_the_runtime(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-invalid-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import sys; sys.stdout.write("{\\"status\\":\\"planned\\"}")');
        $reflection = new \ReflectionClass(DeterministicReleaseBoundaryFake::class);
        $fake = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('runStateStoreHelper')->setValue($fake, $helper);
        $reflection->getProperty('artifactProcessWorkingDirectory')->setValue($fake, null);
        $reflection->getProperty('runStateFailureOnce')->setValue($fake, null);
        $reflection->getProperty('runStateReplacementTarget')->setValue($fake, null);
        $reflection->getProperty('runStateHelperTimeoutSeconds')->setValue($fake, 30);
        $reflection->getProperty('runStateRead')->setValue($fake, null);
        $reflection->getProperty('terminateRunStateHelperOnce')->setValue($fake, false);

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            $fake->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    /**
     * Covers wrong scalar types in otherwise known helper receipt schemas.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_known_run_state_receipts_with_wrong_field_types_terminate(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-types-'.bin2hex(random_bytes(8));
        mkdir($root);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $runPath = $root.'/runs/'.$runId;
        $receipts = [
            '{"status":1}',
            '{"status":"planned","history_path":"h","projection_path":"p","sequence":"1",'.'"state":"planned","prepared_history_sha256":"h","prepared_projection_sha256":"p"}',
            '{"status":"planned","history_path":1,"projection_path":"p","sequence":1,'.'"state":"planned","prepared_history_sha256":"h","prepared_projection_sha256":"p"}'
        ];

        try {
            foreach ($receipts as $index => $receipt) {
                $helper = $root.'/helper-'.$index.'.py';
                file_put_contents($helper, 'import sys; sys.stdout.write('.var_export($receipt, true).')');
                try {
                    new DeterministicReleaseBoundaryFake(runStateStoreHelper: $helper)->createPlannedRun(
                        new CanonicalRunsDirectory($root, $root),
                        str_repeat('a', 64),
                        hash('sha256', (string) $index)
                    );
                    self::fail('The malformed known receipt was accepted.');
                } catch (ReleaseRuntimeTermination) {
                    self::assertTrue(true);
                }
            }

            $finalizeReceipt = [
                'status'                            => 'created',
                'history_path'                      => $runPath.'/history.jsonl',
                'projection_path'                   => $runPath.'/projection.json',
                'sequence'                          => 3,
                'state'                             => 'prepared',
                'history_sha256'                    => str_repeat('1', 64),
                'projection_sha256'                 => str_repeat('2', 64),
                'prepared_history_sha256'           => str_repeat('3', 64),
                'prepared_projection_sha256'        => str_repeat('4', 64),
                'prerequisite_evidence_manifest_id' => str_repeat('z', 64),
                'prerequisite_phase_handoff_id'     => str_repeat('d', 64)
            ];
            $helper = $root.'/helper-finalize.py';
            file_put_contents(
                $helper,
                'import sys; sys.stdout.write('.var_export(json_encode($finalizeReceipt, JSON_THROW_ON_ERROR), true).')'
            );
            try {
                new DeterministicReleaseBoundaryFake(runStateStoreHelper: $helper)->finalizePreparedRun(
                    new CanonicalRunsDirectory($root, $root),
                    $planId,
                    $runId,
                    str_repeat('c', 64),
                    str_repeat('d', 64),
                    2,
                    'prepared'
                );
                self::fail('The malformed prerequisite identity was accepted.');
            } catch (ReleaseRuntimeTermination) {
                self::assertTrue(true);
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers schema-shaped positive receipts that contradict the requested operation.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_schema_shaped_false_success_run_state_receipts_terminate(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-semantics-'.bin2hex(random_bytes(8));
        mkdir($root);
        $planId = str_repeat('a', 64);
        $runId = str_repeat('b', 64);
        $runPath = $root.'/runs/'.$runId;
        $base = [
            'status'                     => 'planned',
            'history_path'               => $runPath.'/history.jsonl',
            'projection_path'            => $runPath.'/projection.json',
            'sequence'                   => 1,
            'state'                      => 'planned',
            'prepared_history_sha256'    => str_repeat('c', 64),
            'prepared_projection_sha256' => str_repeat('d', 64)
        ];
        $receipts = [
            [...$base, 'sequence' => 0],
            [...$base, 'state' => 'prepared'],
            [...$base, 'history_path' => '/wrong/history.jsonl'],
            [...$base, 'prepared_history_sha256' => str_repeat('z', 64)]
        ];

        try {
            foreach ($receipts as $index => $receipt) {
                $helper = $root.'/helper-'.$index.'.py';
                file_put_contents(
                    $helper,
                    'import sys; sys.stdout.write('.var_export(json_encode($receipt, JSON_THROW_ON_ERROR), true).')'
                );
                try {
                    new DeterministicReleaseBoundaryFake(runStateStoreHelper: $helper)->createPlannedRun(
                        new CanonicalRunsDirectory($root, $root),
                        $planId,
                        $runId
                    );
                    self::fail('The semantically false helper success was accepted.');
                } catch (ReleaseRuntimeTermination) {
                    self::assertTrue(true);
                }
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers fail-closed dispatch for an impossible internal helper operation.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_an_unknown_internal_run_state_operation_terminates(): void
    {
        $method = new \ReflectionMethod(DeterministicReleaseBoundaryFake::class, 'validRunStateReceipt');

        $this->expectException(ReleaseRuntimeTermination::class);
        $method->invoke(
            new DeterministicReleaseBoundaryFake(),
            'unknown',
            ['status' => 'failed'],
            new CanonicalRunsDirectory('/tmp', '/tmp'),
            str_repeat('a', 64),
            str_repeat('b', 64),
            [],
            ''
        );
    }

    /**
     * Covers rejection of an unsolicited helper crash outside an exact configured fault
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_an_unsolicited_run_state_helper_crash_terminates(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-unsolicited-crash-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import sys; sys.stdout.write("{\\"crash\\":true}")');

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            new DeterministicReleaseBoundaryFake(runStateStoreHelper: $helper)->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    /**
     * Covers exact causal binding of positive stopped receipts
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_positive_stopped_receipts_must_match_the_exact_closed_contract(): void
    {
        $method = new \ReflectionMethod(DeterministicReleaseBoundaryFake::class, 'validRunStateReceipt');
        $runId = str_repeat('b', 64);
        $directory = new CanonicalRunsDirectory('/tmp', '/tmp');
        $path = '/tmp/runs/'.$runId;
        $stop = [
            'status'          => 'verified',
            'history_path'    => $path.'/history.jsonl',
            'projection_path' => $path.'/projection.json',
            'stop_code'       => 'baseline_missing',
            'stop_state'      => 'policy_blocked',
            'finding_id'      => 'release.prepare.baseline_tag_missing',
            'next_action'     => 'repair_baseline_authority',
            'sequence'        => 2,
            'state'           => 'policy_blocked'
        ];
        $stopArguments = [
            'baseline_missing',
            'policy_blocked',
            'release.prepare.baseline_tag_missing',
            'repair_baseline_authority',
            '',
            '',
            '1',
            'planned'
        ];

        self::assertTrue($method->invoke(
            new DeterministicReleaseBoundaryFake(),
            'stop',
            $stop,
            $directory,
            str_repeat('a', 64),
            $runId,
            $stopArguments,
            ''
        ));
        self::assertFalse($method->invoke(
            new DeterministicReleaseBoundaryFake(),
            'stop',
            [...$stop, 'finding_id' => 'release.prepare.plan_authority_failed'],
            $directory,
            str_repeat('a', 64),
            $runId,
            $stopArguments,
            ''
        ));

        $resumed = [
            'status'              => 'stopped',
            'history_path'        => $path.'/history.jsonl',
            'projection_path'     => $path.'/projection.json',
            'stop_code'           => 'not_governed',
            'stop_state'          => 'policy_blocked',
            'finding_id'          => 'release.prepare.baseline_tag_missing',
            'next_action'         => 'repair_baseline_authority',
            'sequence'            => 2,
            'state'               => 'policy_blocked',
            'projection_repaired' => false,
            'resume_state'        => 'planned',
            'resume_sequence'     => 1,
            'resume_next_action'  => 'prepare_release_run'
        ];
        self::assertFalse($method->invoke(
            new DeterministicReleaseBoundaryFake(),
            'resume',
            $resumed,
            $directory,
            str_repeat('a', 64),
            $runId,
            [],
            ''
        ));
    }

    /**
     * Covers every closed resumed-stop contract and remaining semantic receipt rejection
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_every_positive_resumed_stop_contract_is_closed_and_exact(): void
    {
        $contracts = [
            'missing' => [
                'evidence_indeterminate', 'release.prepare.resume_state_missing',
                'restore_named_release_run_evidence'
            ],
            'conflict' => [
                'conflict', 'release.prepare.resume_contention',
                'retry_named_resume_after_writer_completes'
            ],
            'failed' => [
                'policy_blocked', 'release.prepare.state_persistence_failed', 'repair_release_run_storage'
            ],
            'create_conflict' => [
                'conflict', 'release.prepare.run_identity_conflict', 'retry_release_preparation_with_new_run'
            ],
            'state_indeterminate' => [
                'evidence_indeterminate', 'release.prepare.state_persistence_indeterminate',
                'reconcile_named_release_run'
            ],
            'baseline_refusal' => [
                'authority_required', 'release.prepare.baseline_resolution_refused',
                'obtain_current_baseline_authority'
            ],
            'baseline_failure' => [
                'policy_blocked', 'release.prepare.baseline_resolution_failed',
                'repair_baseline_resolution_provider'
            ],
            'baseline_uncertainty' => [
                'evidence_indeterminate', 'release.prepare.baseline_resolution_uncertain',
                'reconcile_baseline_resolution'
            ],
            'baseline_drift' => [
                'stale_plan', 'release.prepare.baseline_resolution_drift', 'create_current_release_plan'
            ],
            'baseline_missing' => [
                'policy_blocked', 'release.prepare.baseline_tag_missing', 'repair_baseline_authority'
            ],
            'baseline_ambiguous' => [
                'policy_blocked', 'release.prepare.baseline_tag_ambiguous', 'repair_baseline_authority'
            ],
            'baseline_duplicate_normalized' => [
                'policy_blocked', 'release.prepare.baseline_tag_duplicate_normalized',
                'repair_baseline_authority'
            ],
            'baseline_non_ancestor' => [
                'policy_blocked', 'release.prepare.baseline_tag_non_ancestor', 'repair_baseline_authority'
            ],
            'support_policy_drift' => [
                'stale_plan', 'release.prepare.support_policy_drift', 'create_current_release_plan'
            ],
            'approval_drift' => [
                'authority_required', 'release.prepare.approval_authority_drift',
                'obtain_current_release_approval'
            ],
            'evidence_drift' => [
                'stale_plan', 'release.prepare.evidence_authority_drift', 'create_current_release_plan'
            ],
            'compatibility_drift' => [
                'stale_plan', 'release.prepare.compatibility_authority_drift', 'create_current_release_plan'
            ],
            'authority_refused' => [
                'authority_required', 'release.prepare.plan_authority_refused',
                'obtain_current_release_authority'
            ],
            'authority_failed' => [
                'policy_blocked', 'release.prepare.plan_authority_failed',
                'repair_release_authority_provider'
            ],
            'authority_uncertain' => [
                'evidence_indeterminate', 'release.prepare.plan_authority_uncertain',
                'reconcile_release_plan_authority'
            ],
            'stale' => [
                'stale_plan', 'release.prepare.resume_plan_drift', 'create_current_release_plan'
            ]
        ];
        $contractMethod = new \ReflectionMethod(
            DeterministicReleaseBoundaryFake::class,
            'validStoppedReceiptContract'
        );
        $fake = new DeterministicReleaseBoundaryFake();

        foreach ($contracts as $stopCode => [$stopState, $findingId, $nextAction]) {
            self::assertTrue($contractMethod->invoke($fake, [
                'stop_code'   => $stopCode,
                'stop_state'  => $stopState,
                'finding_id'  => $findingId,
                'next_action' => $nextAction
            ]), $stopCode);
        }

        $receiptMethod = new \ReflectionMethod(DeterministicReleaseBoundaryFake::class, 'validRunStateReceipt');
        $runId = str_repeat('b', 64);
        $runPath = '/tmp/runs/'.$runId;
        self::assertFalse($receiptMethod->invoke(
            $fake,
            'resume',
            [
                'status'                       => 'planned',
                'history_path'                 => $runPath.'/history.jsonl',
                'projection_path'              => $runPath.'/projection.json',
                'sequence'                     => 1,
                'state'                        => 'planned',
                'projection_repaired'          => 'false',
                'prepared_history_sha256'      => str_repeat('c', 64),
                'prepared_projection_sha256'   => str_repeat('d', 64)
            ],
            new CanonicalRunsDirectory('/tmp', '/tmp'),
            str_repeat('a', 64),
            $runId,
            [],
            ''
        ));

        $bindingMethod = new \ReflectionMethod(
            DeterministicReleaseBoundaryFake::class,
            'receiptMatchesStopArguments'
        );
        self::assertFalse($bindingMethod->invoke(
            $fake,
            [
                'stop_code'   => 'baseline_missing',
                'stop_state'  => 'policy_blocked',
                'finding_id'  => 'release.prepare.baseline_tag_missing',
                'next_action' => 'repair_baseline_authority'
            ],
            [
                'baseline_missing', 'policy_blocked', 'release.prepare.baseline_tag_missing',
                'repair_baseline_authority', str_repeat('e', 64), ''
            ]
        ));
    }

    /**
     * Covers truthful failure ledgering for a governed terminal resume receipt
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_a_failed_resume_receipt_records_one_failed_read(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-resume-failed-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import sys; sys.stdout.write("{\\"status\\":\\"failed\\"}")');
        $fake = new DeterministicReleaseBoundaryFake(runStateStoreHelper: $helper);

        try {
            self::assertSame(
                ['status' => 'failed'],
                $fake->resumePreparedRun(
                    new CanonicalRunsDirectory($root, $root),
                    str_repeat('a', 64),
                    str_repeat('b', 64)
                )
            );
            self::assertSame([[
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.read',
                'outcome'      => 'failure'
            ]], $fake->effects());
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    /**
     * Covers the one-shot configured helper protocol termination
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_archive_creation_derives_and_builds_a_deterministic_archive_with_exclusions(): void
    {
        $fake = new DeterministicReleaseBoundaryFake();
        $fake->configureArchiveFileList([
            'composer.json'    => '{"name":"fight/common"}',
            'src/Event.php'    => '<?php class Event {}',
            'nested/One.php'   => '<?php class One {}',
            'nested/Two.php'   => '<?php class Two {}',
            'excluded/skip.php' => '<?php class Skip {}'
        ]);

        $effectSet = $fake->deriveEffectSet(
            'd34db33fd34db33fd34db33fd34db33fd34db33f',
            '1.3.0',
            '/repo'
        );

        self::assertSame('fight-common-v1.3.0.zip', $effectSet->archiveName);
        self::assertSame([
            'composer.json',
            'excluded/skip.php',
            'nested/One.php',
            'nested/Two.php',
            'src/Event.php'
        ], $effectSet->includedPaths);

        $result = $fake->createArchive(
            'd34db33fd34db33fd34db33fd34db33fd34db33f',
            '1.3.0',
            '/repo',
            ['excluded/skip.php']
        );

        self::assertTrue($result->hasArchive());
        self::assertSame('/repo/.runs/fight-common-v1.3.0.zip', $result->archivePath);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $result->sha256Digest);
        self::assertSame([
            ['capability' => 'archive', 'effect_class' => 'archive.create', 'outcome' => 'success'],
            ['capability' => 'archive', 'effect_class' => 'archive.verify', 'outcome' => 'success']
        ], $fake->effects());
    }

    /**
     * Covers the one-shot deterministic archive bytes override and already-satisfied stop.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_archive_bytes_override_and_already_satisfied_stop_are_consumed(): void
    {
        $bytesFake = new DeterministicReleaseBoundaryFake();
        $bytesFake->configureArchiveBytesOnce('deterministic-bytes');

        $result = $bytesFake->createArchive(
            'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            '1.3.0',
            '/repo',
            []
        );

        self::assertTrue($result->hasArchive());
        self::assertSame(hash('sha256', 'deterministic-bytes'), $result->sha256Digest);

        $satisfiedFake = new DeterministicReleaseBoundaryFake();
        $satisfiedFake->configureOutcome('archive.create', 'already_satisfied');

        $satisfied = $satisfiedFake->createArchive(
            'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            '1.3.0',
            '/repo',
            []
        );

        self::assertFalse($satisfied->hasArchive());
        self::assertNull($satisfied->archivePath);
        self::assertSame(ReleaseBoundaryOutcome::ALREADY_SATISFIED, $satisfied->outcome);
    }

    /**
     * Covers the one-shot configured helper protocol termination
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_configured_run_state_helper_protocol_termination_is_consumed(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-configured-'.bin2hex(random_bytes(8));
        mkdir($root);
        $fake = new DeterministicReleaseBoundaryFake();
        $fake->terminateRunStateHelperOnce();

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            $fake->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            (new Filesystem())->remove($root);
        }
    }

    /**
     * Covers simultaneous bounded draining of noisy helper channels
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_noisy_run_state_helper_channels_terminate_without_deadlock(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-noisy-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import os; os.write(1,b"x"*70000); os.write(2,b"y"*70000)');
        $reflection = new \ReflectionClass(DeterministicReleaseBoundaryFake::class);
        $fake = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('runStateStoreHelper')->setValue($fake, $helper);
        $reflection->getProperty('artifactProcessWorkingDirectory')->setValue($fake, null);
        $reflection->getProperty('runStateFailureOnce')->setValue($fake, null);
        $reflection->getProperty('runStateReplacementTarget')->setValue($fake, null);
        $reflection->getProperty('runStateHelperTimeoutSeconds')->setValue($fake, 30);
        $reflection->getProperty('runStateRead')->setValue($fake, null);
        $reflection->getProperty('terminateRunStateHelperOnce')->setValue($fake, false);

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            $fake->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers deterministic helper timeout termination
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_wedged_run_state_helper_reaches_runtime_termination(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-wedged-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import time; time.sleep(5)');

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            new DeterministicReleaseBoundaryFake(
                runStateHelperTimeoutSeconds: 0,
                artifactProcessWorkingDirectory: $root,
                runStateStoreHelper: $helper
            )->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers the total deadline and KILL fallback for continuously noisy and TERM-resistant children.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_run_state_helper_total_deadline_and_kill_fallback_are_bounded(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-total-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helpers = [
            'noisy.py'    => 'import os,time
for i in range(50): os.write(1,b"x"); time.sleep(.1)',
            'stubborn.py' => 'import os,signal,time
signal.signal(signal.SIGTERM, signal.SIG_IGN)
os.write(1,b"x")
time.sleep(5)'
        ];

        try {
            foreach ($helpers as $filename => $source) {
                $helper = $root.'/'.$filename;
                file_put_contents($helper, $source);
                try {
                    new DeterministicReleaseBoundaryFake(
                        runStateHelperTimeoutSeconds: 1,
                        runStateStoreHelper: $helper
                    )->createPlannedRun(
                        new CanonicalRunsDirectory($root, $root),
                        str_repeat('a', 64),
                        hash('sha256', $filename)
                    );
                    self::fail('The unbounded helper was accepted.');
                } catch (ReleaseRuntimeTermination) {
                    self::assertTrue(true);
                }
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers bounded fallback when process exit cannot be observed even after KILL.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_unobserved_run_state_helper_exit_never_reaches_blocking_close(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-unobserved-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'import sys; sys.stdout.write("{}")');
        $started = microtime(true);

        try {
            new DeterministicReleaseBoundaryFake(
                runStateHelperTimeoutSeconds: 1,
                runStateStoreHelper: $helper,
                runStateStatus: static fn (): array => ['running' => true, 'exitcode' => -1]
            )->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
            self::fail('The unobserved helper exit was accepted.');
        } catch (ReleaseRuntimeTermination) {
            self::assertLessThan(2.0, microtime(true) - $started);
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers simultaneous bounded input/output handling for an abnormal artifact helper.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_noisy_and_wedged_artifact_helpers_fail_without_deadlock(): void
    {
        $root = sys_get_temp_dir().'/fight-common-artifact-helper-bounds-'.bin2hex(random_bytes(8));
        mkdir($root);
        $noisy = $root.'/noisy.py';
        $wedged = $root.'/wedged.py';
        file_put_contents($noisy, 'import os; os.write(2,b"x"*70000); os.read(0,1)');
        file_put_contents($wedged, 'import time; time.sleep(5)');
        $directory = new CanonicalRunsDirectory($root, $root);

        try {
            $write = new DeterministicReleaseBoundaryFake(
                artifactStoreHelper: $noisy,
                runStateHelperTimeoutSeconds: 1
            );
            self::assertSame(
                ReleaseBoundaryOutcome::FAILURE,
                $write->writeArtifact($directory, 'bounded.json', str_repeat('x', 1024 * 1024))->outcome
            );

            $read = new DeterministicReleaseBoundaryFake(
                artifactStoreHelper: $wedged,
                runStateHelperTimeoutSeconds: 0
            );
            self::assertSame(
                ReleaseBoundaryOutcome::FAILURE,
                $read->readArtifact($directory, 'bounded.json')->outcome
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * Covers helper channel read failure termination
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_failed_run_state_helper_channel_read_terminates(): void
    {
        $root = sys_get_temp_dir().'/fight-common-run-helper-read-failed-'.bin2hex(random_bytes(8));
        mkdir($root);
        $helper = $root.'/helper.py';
        file_put_contents($helper, 'print("{}")');

        try {
            $this->expectException(ReleaseRuntimeTermination::class);
            new DeterministicReleaseBoundaryFake(
                runStateStoreHelper: $helper,
                runStateRead: static fn (): bool => false
            )->createPlannedRun(
                new CanonicalRunsDirectory($root, $root),
                str_repeat('a', 64),
                str_repeat('b', 64)
            );
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /** Reads one fixture through the descriptor-relative artifact seam. */
    private function readArtifact(
        DeterministicReleaseBoundaryFake $fake,
        string $path
    ): \Fight\Release\Application\Boundary\PlanArtifactReadResult {
        $directory = dirname($path);

        return $fake->readArtifact(
            new CanonicalRunsDirectory($directory, $directory),
            basename($path)
        );
    }
}

/**
 * Class FailingReleaseReadStream
 *
 * Forces the native stream consumer down its exceptional unreadable path.
 */
final class FailingReleaseReadStream
{
    /** @var resource|null */
    public $context;

    /** Opens the deterministic test stream. */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    /** Fails deterministic stream consumption. */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_read(int $count): string
    {
        throw new RuntimeException('Deterministic unreadable stream.');
    }

    /** Reports pending data so the consumer attempts one read. */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_eof(): bool
    {
        return false;
    }

    /** Supplies the minimal stream metadata contract. */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_stat(): array
    {
        return [];
    }
}
