<?php

declare(strict_types=1);

namespace Fight\Test\Release\TestCase;

use Fight\Release\Application\Boundary\AuthorizationPort;
use Fight\Release\Application\Boundary\BaselineTagResolutionResult;
use Fight\Release\Application\Boundary\CanonicalRunsDirectory;
use Fight\Release\Application\Boundary\ClockPort;
use Fight\Release\Application\Boundary\FilesystemPort;
use Fight\Release\Application\Boundary\GitHubPort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\HashingPort;
use Fight\Release\Application\Boundary\PackagistPort;
use Fight\Release\Application\Boundary\PlanArtifactReadResult;
use Fight\Release\Application\Boundary\PlanArtifactStore;
use Fight\Release\Application\Boundary\PlanArtifactWriteResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryCrash;
use Fight\Release\Application\Boundary\ReleaseBoundaryOperationResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseBoundaryPredicateResult;
use Fight\Release\Application\Boundary\ReleaseEffectLedger;
use Fight\Release\Application\Boundary\RunsDirectoryResolutionResult;
use Fight\Release\Application\Boundary\SigningPort;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class ReleaseBoundaryPortConformanceTestCase
 *
 * Reusable contract for the eight explicit release capabilities and the filesystem artifact-write seam.
 */
abstract class ReleaseBoundaryPortConformanceTestCase extends UnitTestCase
{
    private string $fixtureRoot;
    private string $readPath;
    private string $writePath;
    private string $runsRoot;

    /**
     * Returns every explicit boundary operation and its exact effect identity
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function boundaryOperationProvider(): iterable
    {
        yield 'filesystem read' => ['filesystem_read', 'filesystem', 'filesystem.read'];
        yield 'filesystem artifact read' => ['filesystem_artifact_read', 'filesystem', 'filesystem.read'];
        yield 'filesystem directory predicate' => [
            'filesystem_is_directory',
            'filesystem',
            'filesystem.inspect_directory'
        ];
        yield 'filesystem writable predicate' => [
            'filesystem_is_writable',
            'filesystem',
            'filesystem.inspect_writable'
        ];
        yield 'filesystem existence predicate' => [
            'filesystem_exists',
            'filesystem',
            'filesystem.inspect_exists'
        ];
        yield 'filesystem runs-root predicate' => [
            'filesystem_resolve_runs_directory',
            'filesystem',
            'filesystem.inspect_runs_directory'
        ];
        yield 'filesystem artifact write' => ['filesystem_write', 'filesystem', 'filesystem.write'];
        yield 'Git repository inspection' => ['git_inspect_repository', 'git', 'git.inspect_repository'];
        yield 'Git baseline resolution' => ['git_resolve_baseline_tag', 'git', 'git.resolve_ref'];
        yield 'SHA-256 hashing' => ['hashing_sha256', 'hashing', 'hashing.sha256'];
        yield 'clock read' => ['clock_now', 'clock', 'clock.now'];
        yield 'signature verification' => ['signing_verify', 'signing', 'signing.verify'];
        yield 'authorization check' => ['authorization_check', 'authorization', 'authorization.check'];
        yield 'GitHub release' => ['github_release', 'github', 'github.release'];
        yield 'Packagist publication' => ['packagist_publish', 'packagist', 'packagist.publish'];
    }

    /**
     * Returns all normally completed boundary stop outcomes
     *
     * @return iterable<string, array{ReleaseBoundaryOutcome}>
     */
    public static function stoppedOutcomeProvider(): iterable
    {
        yield 'refusal' => [ReleaseBoundaryOutcome::REFUSAL];
        yield 'failure' => [ReleaseBoundaryOutcome::FAILURE];
        yield 'uncertainty' => [ReleaseBoundaryOutcome::UNCERTAINTY];
        yield 'drift' => [ReleaseBoundaryOutcome::DRIFT];
    }

    /**
     * Returns operations combined with each normally completed stop outcome
     *
     * @return iterable<string, array{string, string, string, ReleaseBoundaryOutcome}>
     */
    public static function boundaryStopProvider(): iterable
    {
        foreach (self::boundaryOperationProvider() as $operationName => $operation) {
            foreach (self::stoppedOutcomeProvider() as $outcomeName => [$outcome]) {
                yield $operationName.' / '.$outcomeName => [...$operation, $outcome];
            }
        }
    }

    /**
     * Returns every mutating boundary operation that can report an existing postcondition
     *
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function alreadySatisfiedOperationProvider(): iterable
    {
        yield 'filesystem artifact write' => [
            'filesystem_write',
            'filesystem',
            'filesystem.write',
            'immutable_artifact_exists'
        ];
        yield 'GitHub release' => [
            'github_release',
            'github',
            'github.release',
            'github_release_exists'
        ];
        yield 'Packagist publication' => [
            'packagist_publish',
            'packagist',
            'packagist.publish',
            'packagist_version_exists'
        ];
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong

    /**
     * Asserts every explicit operation's typed success value and one truthful ledger entry
     */
    #[DataProvider('boundaryOperationProvider')]
    public function test_that_every_release_boundary_operation_conforms_on_success(
        string $operation,
        string $capability,
        string $effectClass
    ): void {
        $boundary = $this->createReleaseBoundary();
        $result = $this->invokeBoundaryOperation($boundary, $operation);

        $this->assertSuccessfulResult($operation, $result);
        $expectedEffects = [[
            'capability'   => $capability,
            'effect_class' => $effectClass,
            'outcome'      => 'success'
        ]];

        if ($operation === 'filesystem_resolve_runs_directory') {
            $expectedEffects[] = [
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.inspect_directory',
                'outcome'      => 'success'
            ];
            $expectedEffects[] = [
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.inspect_writable',
                'outcome'      => 'success'
            ];
        }

        self::assertSame($expectedEffects, $boundary->effects());
    }

    /**
     * Asserts ordinary stops have no values, writes, later work, or misleading ledger entries
     */
    #[DataProvider('boundaryStopProvider')]
    public function test_that_every_release_boundary_operation_conforms_on_an_ordinary_stop(
        string $operation,
        string $capability,
        string $effectClass,
        ReleaseBoundaryOutcome $outcome
    ): void {
        $boundary = $this->createReleaseBoundary([$effectClass => $outcome->value]);
        $result = $this->invokeBoundaryOperation($boundary, $operation);

        self::assertSame($outcome, $result->outcome);
        $this->assertStoppedResultHasNoValue($result);
        self::assertFalse(file_exists($this->writePath));
        self::assertSame('filesystem-read-known-literal', file_get_contents($this->readPath));
        self::assertSame([[
            'capability'   => $capability,
            'effect_class' => $effectClass,
            'outcome'      => $outcome->value
        ]], $boundary->effects());
    }

    /**
     * Asserts an existing postcondition is typed, ledgered, and never repeats an underlying mutation
     */
    #[DataProvider('alreadySatisfiedOperationProvider')]
    public function test_that_every_mutating_release_boundary_operation_conforms_when_already_satisfied(
        string $operation,
        string $capability,
        string $effectClass,
        string $postconditionEvidence
    ): void {
        if ($operation === 'filesystem_write') {
            file_put_contents($this->writePath, 'already-satisfied-original');
            $boundary = $this->createReleaseBoundary();
        } else {
            $boundary = $this->createReleaseBoundary([$effectClass => 'already_satisfied']);
        }

        $result = $this->invokeBoundaryOperation($boundary, $operation);

        self::assertSame(ReleaseBoundaryOutcome::ALREADY_SATISFIED, $result->outcome);

        if ($operation === 'filesystem_write') {
            self::assertInstanceOf(PlanArtifactWriteResult::class, $result);
            self::assertSame($postconditionEvidence, $result->postconditionEvidence);
            self::assertTrue($result->requiresPostconditionVerification());
            self::assertFalse($result->persisted());
        } else {
            self::assertInstanceOf(ReleaseBoundaryOperationResult::class, $result);
            self::assertSame($postconditionEvidence, $result->postconditionEvidence);
            self::assertTrue($result->requiresPostconditionVerification());
            self::assertFalse($result->hasValue());
        }

        if ($operation === 'filesystem_write') {
            self::assertSame('already-satisfied-original', file_get_contents($this->writePath));
        }

        self::assertSame([[
            'capability'   => $capability,
            'effect_class' => $effectClass,
            'outcome'      => 'already_satisfied'
        ]], $boundary->effects());
    }

    /**
     * Asserts configured crashes record only the attempted exact effect and perform no underlying work
     */
    #[DataProvider('boundaryOperationProvider')]
    public function test_that_every_release_boundary_operation_conforms_at_a_crash_point(
        string $operation,
        string $capability,
        string $effectClass
    ): void {
        $boundary = $this->createReleaseBoundary([$effectClass => 'crash']);

        try {
            $this->invokeBoundaryOperation($boundary, $operation);
            self::fail('A configured boundary crash must interrupt the operation.');
        } catch (ReleaseBoundaryCrash $releaseBoundaryCrash) {
            self::assertSame($effectClass, $releaseBoundaryCrash->effectClass);
        }

        self::assertFalse(file_exists($this->writePath));
        self::assertSame('filesystem-read-known-literal', file_get_contents($this->readPath));
        self::assertSame([[
            'capability'   => $capability,
            'effect_class' => $effectClass,
            'outcome'      => 'crash'
        ]], $boundary->effects());
    }

    /**
     * Asserts unknown and cross-capability configuration fails closed without an effect
     */
    public function test_that_release_boundary_configuration_fails_closed_for_unknown_and_cross_capability_effects(): void
    {
        $boundary = $this->createReleaseBoundary();

        foreach (
            [
                'unknown.effect',
                'git.filesystem.write',
                'filesystem.git.resolve_ref',
                'hashing.clock.now',
                'github.packagist.publish'
            ] as $effectClass
        ) {
            self::assertFalse($boundary->configureOutcome($effectClass, 'success'));
        }

        self::assertSame([], $boundary->effects());
    }

    // phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Generic.Files.LineLength.TooLong

    /**
     * Creates the boundary implementation under test
     *
     * @param array<string, string> $outcomes Exact-effect outcomes.
     */
    abstract protected function createReleaseBoundary(array $outcomes = []): FilesystemPort
        &GitPort
        &HashingPort
        &ClockPort
        &SigningPort
        &AuthorizationPort
        &GitHubPort
        &PackagistPort
        &PlanArtifactStore
        &ReleaseEffectLedger;

    /**
     * Creates isolated, independently known filesystem values
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = sys_get_temp_dir().'/fight-common-release-port-conformance-'.bin2hex(random_bytes(8));
        $this->readPath = $this->fixtureRoot.'/read-known.txt';
        $this->runsRoot = $this->fixtureRoot.'/runs';
        $this->writePath = $this->runsRoot.'/write-known.txt';

        mkdir($this->fixtureRoot);
        mkdir($this->runsRoot);
        file_put_contents($this->readPath, 'filesystem-read-known-literal');
    }

    /**
     * Removes only the isolated conformance fixtures
     */
    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory($this->fixtureRoot, 'fight-common-release-port-conformance-');

        parent::tearDown();
    }

    // phpcs:disable Generic.Files.LineLength.TooLong

    /**
     * Invokes one named conformance operation with independent known values
     */
    private function invokeBoundaryOperation(
        FilesystemPort
        &GitPort
        &HashingPort
        &ClockPort
        &SigningPort
        &AuthorizationPort
        &GitHubPort
        &PackagistPort
        &PlanArtifactStore
        &ReleaseEffectLedger $boundary,
        string $operation
    ): ReleaseBoundaryOperationResult
        |ReleaseBoundaryPredicateResult
        |RunsDirectoryResolutionResult
        |BaselineTagResolutionResult
        |PlanArtifactReadResult
        |PlanArtifactWriteResult
    {
        return match ($operation) {
            'filesystem_read' => $boundary->read($this->readPath),
            'filesystem_artifact_read' => $boundary->readArtifact(
                new CanonicalRunsDirectory($this->runsRoot, $this->runsRoot),
                basename($this->writePath)
            ),
            'filesystem_is_directory' => $boundary->isDirectory($this->fixtureRoot),
            'filesystem_is_writable' => $boundary->isWritable($this->runsRoot),
            'filesystem_exists' => $boundary->exists($this->fixtureRoot.'/known-missing'),
            'filesystem_resolve_runs_directory' => $boundary->resolveRunsDirectory($this->runsRoot, $this->runsRoot),
            'filesystem_write' => $boundary->writeArtifact(
                new CanonicalRunsDirectory($this->runsRoot, $this->runsRoot),
                basename($this->writePath),
                'filesystem-write-known-literal'
            ),
            'git_inspect_repository' => $boundary->inspectRepository(),
            'git_resolve_baseline_tag' => $boundary->resolveBaselineTag('v7.8.9', str_repeat('c', 40)),
            'hashing_sha256' => $boundary->sha256('hashing-known-literal'),
            'clock_now' => $boundary->now(),
            'signing_verify' => $boundary->verify(),
            'authorization_check' => $boundary->check(),
            'github_release' => $boundary->release(),
            'packagist_publish' => $boundary->publish(),
            default => throw new LogicException('Unknown release-boundary conformance operation.')
        };
    }

    // phpcs:enable Generic.Files.LineLength.TooLong

    /**
     * Asserts each operation's exact typed successful value
     */
    private function assertSuccessfulResult(
        string $operation,
        ReleaseBoundaryOperationResult
        |ReleaseBoundaryPredicateResult
        |RunsDirectoryResolutionResult
        |BaselineTagResolutionResult
        |PlanArtifactReadResult
        |PlanArtifactWriteResult $result
    ): void {
        self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $result->outcome);

        if ($result instanceof ReleaseBoundaryOperationResult) {
            self::assertTrue($result->hasValue());
            self::assertSame($this->expectedOperationValue($operation), $result->value);

            return;
        }

        if ($result instanceof ReleaseBoundaryPredicateResult) {
            self::assertTrue($result->hasValue());
            self::assertSame($this->expectedPredicateValue($operation), $result->value);

            return;
        }

        if ($result instanceof RunsDirectoryResolutionResult) {
            self::assertTrue($result->hasDirectory());
            self::assertSame($this->runsRoot, $result->directory->path);
            self::assertSame($this->runsRoot, $result->directory->runsRoot);

            return;
        }

        if ($result instanceof BaselineTagResolutionResult) {
            self::assertTrue($result->isResolved());
            self::assertSame('v7.8.9', $result->tagName);
            self::assertSame('a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1', $result->tagObjectOid);
            self::assertSame('b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45', $result->peeledCommitOid);

            return;
        }

        if ($result instanceof PlanArtifactReadResult) {
            self::assertTrue($result->missing);
            self::assertFalse($result->hasContent());

            return;
        }

        self::assertTrue($result->persisted());
        self::assertSame('filesystem-write-known-literal', file_get_contents($this->writePath));
    }

    /**
     * Asserts a governed stop cannot expose any successful value
     */
    private function assertStoppedResultHasNoValue(
        ReleaseBoundaryOperationResult
        |ReleaseBoundaryPredicateResult
        |RunsDirectoryResolutionResult
        |BaselineTagResolutionResult
        |PlanArtifactReadResult
        |PlanArtifactWriteResult $result
    ): void {
        if ($result instanceof ReleaseBoundaryOperationResult || $result instanceof ReleaseBoundaryPredicateResult) {
            self::assertFalse($result->hasValue());
            self::assertNull($result->value);

            return;
        }

        if ($result instanceof RunsDirectoryResolutionResult) {
            self::assertFalse($result->hasDirectory());
            self::assertNull($result->directory);

            return;
        }

        if ($result instanceof BaselineTagResolutionResult) {
            self::assertFalse($result->isResolved());
            self::assertNull($result->status);
            self::assertNull($result->tagName);
            self::assertNull($result->tagObjectOid);
            self::assertNull($result->peeledCommitOid);

            return;
        }

        if ($result instanceof PlanArtifactReadResult) {
            self::assertFalse($result->hasContent());
            self::assertFalse($result->missing);
            self::assertNull($result->contents);

            return;
        }

        self::assertFalse($result->persisted());
    }

    /**
     * Returns exact successful operation values without reusing boundary inputs
     */
    private function expectedOperationValue(string $operation): string
    {
        return match ($operation) {
            'filesystem_read' => 'filesystem-read-known-literal',
            'git_inspect_repository' => 'repository-inspected',
            'hashing_sha256' => '86b470f60119ea438767b4359196d1840b4d1475ea503e8015c6f78e0409e34d',
            'clock_now' => '2026-08-19T12:00:00.000000Z',
            'signing_verify' => 'signature-verified',
            'authorization_check' => 'authorized',
            'github_release' => 'github-release-created',
            'packagist_publish' => 'packagist-publication-completed',
            default => throw new LogicException('Operation does not return a string value.')
        };
    }

    /**
     * Returns exact successful predicate values, including false-as-success
     */
    private function expectedPredicateValue(string $operation): bool
    {
        return match ($operation) {
            'filesystem_is_directory', 'filesystem_is_writable' => true,
            'filesystem_exists' => false,
            default => throw new LogicException('Operation does not return a predicate value.')
        };
    }
}
