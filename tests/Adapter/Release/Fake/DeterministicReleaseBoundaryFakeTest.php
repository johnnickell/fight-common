<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release\Fake;

use Fight\Common\Adapter\Release\Fake\DeterministicReleaseBoundaryFake;
use Fight\Common\Application\Release\Boundary\AuthorizationPort;
use Fight\Common\Application\Release\Boundary\CanonicalRunsDirectory;
use Fight\Common\Application\Release\Boundary\ClockPort;
use Fight\Common\Application\Release\Boundary\FilesystemPort;
use Fight\Common\Application\Release\Boundary\GitHubPort;
use Fight\Common\Application\Release\Boundary\GitPort;
use Fight\Common\Application\Release\Boundary\HashingPort;
use Fight\Common\Application\Release\Boundary\PackagistPort;
use Fight\Common\Application\Release\Boundary\PlanArtifactStore;
use Fight\Common\Application\Release\Boundary\PlanArtifactWriteResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOperationResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryPredicateResult;
use Fight\Common\Application\Release\Boundary\RunsDirectoryResolutionResult;
use Fight\Common\Application\Release\Boundary\SigningPort;
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
        self::assertSame('2026-08-19T12:00:00.000000Z', $success->now()->value);
        self::assertSame('signature-verified', $success->verify()->value);
        self::assertSame('authorized', $success->check()->value);

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
        $helper = dirname(__DIR__, 4).'/Fixture/Release/reject-artifact-input.py';
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
        $helper = dirname(__DIR__, 3).'/Fixture/Release/oversized-artifact-output.py';
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
                dirname(__DIR__, 4).'/Fixture/Release/reject-artifact-input.py'
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
            FilesystemPort::class => [
                'exists(string):'.ReleaseBoundaryPredicateResult::class,
                'isDirectory(string):'.ReleaseBoundaryPredicateResult::class,
                'resolveRunsDirectory(string,string):'.RunsDirectoryResolutionResult::class,
                'isWritable(string):'.ReleaseBoundaryPredicateResult::class,
                'read(string):'.ReleaseBoundaryOperationResult::class
            ],
            PlanArtifactStore::class => [
                'readArtifact('.CanonicalRunsDirectory::class.',string):'.\Fight\Common\Application\Release\Boundary\PlanArtifactReadResult::class,
                'resolveRunsDirectory(string,string):'.RunsDirectoryResolutionResult::class,
                'writeArtifact('.CanonicalRunsDirectory::class.',string,string):'.PlanArtifactWriteResult::class
            ],
            GitPort::class => [
                'inspectRepository():'.ReleaseBoundaryOperationResult::class,
                'resolveBaselineTag(string,string):'.\Fight\Common\Application\Release\Boundary\BaselineTagResolutionResult::class
            ],
            HashingPort::class => ['sha256(string):'.ReleaseBoundaryOperationResult::class],
            ClockPort::class => ['now():'.ReleaseBoundaryOperationResult::class],
            SigningPort::class => ['verify():'.ReleaseBoundaryOperationResult::class],
            AuthorizationPort::class => ['check():'.ReleaseBoundaryOperationResult::class],
            GitHubPort::class => ['release():'.ReleaseBoundaryOperationResult::class],
            PackagistPort::class => ['publish():'.ReleaseBoundaryOperationResult::class]
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

    /** Reads one fixture through the descriptor-relative artifact seam. */
    private function readArtifact(
        DeterministicReleaseBoundaryFake $fake,
        string $path
    ): \Fight\Common\Application\Release\Boundary\PlanArtifactReadResult {
        $directory = dirname($path);

        return $fake->readArtifact(
            new CanonicalRunsDirectory($directory, $directory),
            basename($path)
        );
    }
}

/** Forces the native stream consumer down its exceptional unreadable path. */
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
