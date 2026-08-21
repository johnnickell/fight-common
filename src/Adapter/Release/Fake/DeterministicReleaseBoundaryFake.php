<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Release\Fake;

use Fight\Common\Application\Release\Boundary\AuthorizationPort;
use Fight\Common\Application\Release\Boundary\BaselineTagResolutionResult;
use Fight\Common\Application\Release\Boundary\BaselineTagResolutionStatus;
use Fight\Common\Application\Release\Boundary\CanonicalRunsDirectory;
use Fight\Common\Application\Release\Boundary\ClockPort;
use Fight\Common\Application\Release\Boundary\FilesystemPort;
use Fight\Common\Application\Release\Boundary\GitHubPort;
use Fight\Common\Application\Release\Boundary\GitPort;
use Fight\Common\Application\Release\Boundary\HashingPort;
use Fight\Common\Application\Release\Boundary\PackagistPort;
use Fight\Common\Application\Release\Boundary\PlanArtifactReadResult;
use Fight\Common\Application\Release\Boundary\PlanArtifactStore;
use Fight\Common\Application\Release\Boundary\PlanArtifactWriteResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryCrash;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOperationResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryPredicateResult;
use Fight\Common\Application\Release\Boundary\ReleaseEffect;
use Fight\Common\Application\Release\Boundary\ReleaseEffectLedger;
use Fight\Common\Application\Release\Boundary\RunsDirectoryResolutionResult;
use Fight\Common\Application\Release\Boundary\SigningPort;
use InvalidArgumentException;
use Throwable;

/**
 * Class DeterministicReleaseBoundaryFake
 *
 * Credential-free deterministic provider for every release capability.
 */
final class DeterministicReleaseBoundaryFake implements
    FilesystemPort,
    GitPort,
    HashingPort,
    ClockPort,
    SigningPort,
    AuthorizationPort,
    GitHubPort,
    PackagistPort,
    PlanArtifactStore,
    ReleaseEffectLedger
{
    private const int MAX_ARTIFACT_BYTES = 16 * 1024 * 1024;
    /** @var array<string, true> */
    private const array PREDICATE_EFFECTS = [
        'filesystem.inspect_directory' => true,
        'filesystem.inspect_writable'  => true,
        'filesystem.inspect_exists'    => true
    ];

    /** @var list<array{capability: string, effect_class: string, outcome: string}> */
    private array $effects = [];
    /** @var array<string, ReleaseBoundaryOutcome> */
    private array $outcomes = [];
    /** @var array<string, true> */
    private array $crashPoints = [];
    private BaselineTagResolutionStatus $baselineStatus = BaselineTagResolutionStatus::RESOLVED;
    private string $baselineTagName = '';
    private string $baselineTagObjectOid = 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1';
    private string $baselinePeeledCommitOid = 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45';
    private ?int $partialArtifactWriteBytesOnce;
    private ?bool $exclusiveCreateCollisionIdenticalOnce;
    private readonly string $artifactStoreHelper;
    /** @var array<string, bool> */
    private array $predicateValues;

    /**
     * Constructs DeterministicReleaseBoundaryFake
     *
     * @param array<string, string> $outcomes Configured exact-effect outcomes.
     * @param integer|null $partialArtifactWriteBytesOnce Test-only one-shot short-write byte limit.
     * @param boolean|null $exclusiveCreateCollisionIdenticalOnce Test-only one-shot create-race injection.
     * @param array<string, mixed> $predicateValues Test-only native-predicate result injection.
     * @param string|null $artifactStoreHelper Descriptor-relative storage helper path.
     * @param string|null $artifactProcessWorkingDirectory Controlled helper working directory.
     * @param string|null $postPublishFailureOnce Test-only one-shot post-publication failure injection.
     * @param string|null $postPublishFinalOnce Test-only one-shot post-publication final-state injection.
     */
    public function __construct(
        array $outcomes = [],
        ?int $partialArtifactWriteBytesOnce = null,
        ?bool $exclusiveCreateCollisionIdenticalOnce = null,
        array $predicateValues = [],
        ?string $artifactStoreHelper = null,
        private readonly ?string $artifactProcessWorkingDirectory = null,
        private ?string $postPublishFailureOnce = null,
        private ?string $postPublishFinalOnce = null
    ) {
        if ($partialArtifactWriteBytesOnce !== null && $partialArtifactWriteBytesOnce < 0) {
            throw new InvalidArgumentException('A partial artifact write byte limit cannot be negative.');
        }

        $this->partialArtifactWriteBytesOnce = $partialArtifactWriteBytesOnce;
        $this->exclusiveCreateCollisionIdenticalOnce = $exclusiveCreateCollisionIdenticalOnce;
        $defaultArtifactStoreHelper = dirname(__DIR__, 4).'/scripts/release_artifact_store.py';
        $this->artifactStoreHelper = $artifactStoreHelper ?? $defaultArtifactStoreHelper;

        $validatedPredicateValues = [];

        foreach ($predicateValues as $effectClass => $value) {
            if (!isset(self::PREDICATE_EFFECTS[$effectClass]) || !is_bool($value)) {
                throw new InvalidArgumentException('Unsupported deterministic filesystem predicate configuration.');
            }

            $validatedPredicateValues[$effectClass] = $value;
        }

        $this->predicateValues = $validatedPredicateValues;

        foreach ($outcomes as $effectClass => $outcome) {
            if (!$this->configureOutcome($effectClass, $outcome)) {
                throw new InvalidArgumentException('Unsupported deterministic release boundary configuration.');
            }
        }
    }

    /**
     * Configures the deterministic result for one exact effect
     */
    public function configureOutcome(string $effectClass, string $outcome): bool
    {
        $effect = ReleaseEffect::tryFrom($effectClass);

        if ($effect === null) {
            return false;
        }

        if ($outcome === 'crash') {
            $this->crashPoints[$effectClass] = true;
            unset($this->outcomes[$effectClass]);

            return true;
        }

        $closedOutcome = ReleaseBoundaryOutcome::tryFrom($outcome);

        if ($closedOutcome === null) {
            return false;
        }

        if (
            $closedOutcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED
            && $effect->configuredAlreadySatisfiedEvidence() === null
        ) {
            return false;
        }

        $this->outcomes[$effectClass] = $closedOutcome;
        unset($this->crashPoints[$effectClass]);

        return true;
    }

    /**
     * Reads one fixture through the fake filesystem boundary
     */
    public function read(string $path): ReleaseBoundaryOperationResult
    {
        $configuredOutcome = $this->configuredOutcome('filesystem.read');

        if ($configuredOutcome !== ReleaseBoundaryOutcome::SUCCESS) {
            $this->recordConfiguredOutcome('filesystem.read', $configuredOutcome);

            return ReleaseBoundaryOperationResult::stopped($configuredOutcome);
        }

        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            $this->recordEffect('filesystem.read', ReleaseBoundaryOutcome::FAILURE);

            return ReleaseBoundaryOperationResult::stopped(ReleaseBoundaryOutcome::FAILURE);
        }

        try {
            $contents = stream_get_contents($stream);
            /** @var string $contents Boundary type enforcement converts false into the caught failure path. */
            $result = ReleaseBoundaryOperationResult::success($contents);
            $actualOutcome = ReleaseBoundaryOutcome::SUCCESS;
            $this->recordEffect('filesystem.read', $actualOutcome);

            return $result;
        } catch (Throwable) {
            $this->recordEffect('filesystem.read', ReleaseBoundaryOutcome::FAILURE);

            return ReleaseBoundaryOperationResult::stopped(ReleaseBoundaryOutcome::FAILURE);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Reads one planning artifact with its governed boundary outcome
     */
    public function readArtifact(
        CanonicalRunsDirectory $directory,
        string $filename
    ): PlanArtifactReadResult {
        $outcome = $this->configuredOutcome('filesystem.read');

        if ($outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            $this->recordConfiguredOutcome('filesystem.read', $outcome);

            return PlanArtifactReadResult::stopped($outcome);
        }

        [$status, $contents] = $this->readStoredArtifact($directory, $filename);

        if ($status === 0) {
            $this->recordEffect('filesystem.read', ReleaseBoundaryOutcome::SUCCESS);

            return PlanArtifactReadResult::content($contents);
        }

        if ($status === 10) {
            $this->recordEffect('filesystem.read', ReleaseBoundaryOutcome::SUCCESS);

            return PlanArtifactReadResult::missing();
        }

        $this->recordEffect('filesystem.read', ReleaseBoundaryOutcome::FAILURE);

        return PlanArtifactReadResult::stopped(ReleaseBoundaryOutcome::FAILURE);
    }

    /**
     * Writes one new immutable planning artifact with its deterministic outcome
     */
    public function writeArtifact(
        CanonicalRunsDirectory $directory,
        string $filename,
        string $contents
    ): PlanArtifactWriteResult {
        $outcome = $this->configuredOutcome('filesystem.write');

        if ($outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            $this->recordEffect('filesystem.write', $outcome);

            return PlanArtifactWriteResult::stopped($outcome);
        }

        $status = $this->storeArtifact($directory, $filename, $contents);

        if ($status === 0) {
            $this->recordEffect('filesystem.write', ReleaseBoundaryOutcome::SUCCESS);

            return PlanArtifactWriteResult::success();
        }

        if ($status === 10) {
            $this->recordEffect('filesystem.write', ReleaseBoundaryOutcome::ALREADY_SATISFIED);

            return PlanArtifactWriteResult::alreadySatisfied('immutable_artifact_exists');
        }

        if ($status === 30) {
            $this->recordEffect('filesystem.write', ReleaseBoundaryOutcome::UNCERTAINTY);

            return PlanArtifactWriteResult::publicationVerificationRequired();
        }

        $this->recordEffect('filesystem.write', ReleaseBoundaryOutcome::FAILURE);

        return PlanArtifactWriteResult::stopped(ReleaseBoundaryOutcome::FAILURE);
    }

    /**
     * Checks a directory through the fake filesystem boundary
     */
    public function isDirectory(string $path): ReleaseBoundaryPredicateResult
    {
        return $this->predicate('filesystem.inspect_directory', is_dir(...), $path);
    }

    /**
     * Checks output writability through the fake filesystem boundary
     */
    public function isWritable(string $path): ReleaseBoundaryPredicateResult
    {
        return $this->predicate('filesystem.inspect_writable', is_writable(...), $path);
    }

    /**
     * Checks a generic boundary path through the fake filesystem boundary
     */
    public function exists(string $path): ReleaseBoundaryPredicateResult
    {
        return $this->predicate('filesystem.inspect_exists', file_exists(...), $path);
    }

    /**
     * Checks the release artifact root through the fake filesystem boundary
     */
    public function resolveRunsDirectory(string $path, string $runsDirectory): RunsDirectoryResolutionResult
    {
        $outcome = $this->configuredOutcome('filesystem.inspect_runs_directory');

        if ($outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            $this->recordConfiguredOutcome('filesystem.inspect_runs_directory', $outcome);

            return RunsDirectoryResolutionResult::stopped($outcome);
        }

        $relativeParent = $this->relativeRunsParent($path, $runsDirectory);
        $status = $relativeParent === null ? 10 : $this->resolveStoredDirectory($runsDirectory, $relativeParent);

        if ($status !== 0 && $status !== 10) {
            $this->recordEffect('filesystem.inspect_runs_directory', ReleaseBoundaryOutcome::FAILURE);

            return RunsDirectoryResolutionResult::stopped(ReleaseBoundaryOutcome::FAILURE);
        }

        $this->recordEffect('filesystem.inspect_runs_directory', ReleaseBoundaryOutcome::SUCCESS);

        if ($status !== 0) {
            return RunsDirectoryResolutionResult::rejected();
        }

        $isDirectory = $this->predicate(
            'filesystem.inspect_directory',
            static fn (string $ignored): bool => true,
            $path
        );

        if (!$isDirectory->hasValue()) {
            return RunsDirectoryResolutionResult::stopped($isDirectory->outcome);
        }

        if ($isDirectory->value === false) {
            return RunsDirectoryResolutionResult::rejected();
        }

        $isWritable = $this->predicate(
            'filesystem.inspect_writable',
            static fn (string $ignored): bool => true,
            $path
        );

        if (!$isWritable->hasValue()) {
            return RunsDirectoryResolutionResult::stopped($isWritable->outcome);
        }

        if ($isWritable->value === false) {
            return RunsDirectoryResolutionResult::rejected();
        }

        return RunsDirectoryResolutionResult::success(new CanonicalRunsDirectory($path, $runsDirectory));
    }

    /**
     * Computes an immutable content identity through the fake hashing boundary
     */
    public function sha256(string $contents): ReleaseBoundaryOperationResult
    {
        $outcome = $this->record('hashing.sha256');

        if ($outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return ReleaseBoundaryOperationResult::stopped($outcome);
        }

        return ReleaseBoundaryOperationResult::success(hash('sha256', $contents));
    }

    /**
     * Returns one repository inspection outcome through the fake Git boundary
     */
    public function inspectRepository(): ReleaseBoundaryOperationResult
    {
        return $this->operationResult('git.inspect_repository', 'repository-inspected');
    }

    /**
     * Resolves a reference through the fake Git boundary
     */
    public function resolveBaselineTag(string $tagName, string $candidateOid): BaselineTagResolutionResult
    {
        $outcome = $this->record('git.resolve_ref');

        if ($outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            return BaselineTagResolutionResult::stopped($outcome);
        }

        if ($this->baselineStatus !== BaselineTagResolutionStatus::RESOLVED) {
            return BaselineTagResolutionResult::rejected($this->baselineStatus);
        }

        return BaselineTagResolutionResult::resolved(
            $this->baselineTagName === '' ? $tagName : $this->baselineTagName,
            $this->baselineTagObjectOid,
            $this->baselinePeeledCommitOid
        );
    }

    /**
     * Configures the read-only deterministic Git resolution returned by the fake
     */
    public function configureBaselineTagResolution(
        string $status,
        string $tagName = '',
        string $tagObjectOid = 'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
        string $peeledCommitOid = 'b45e1b45b45e1b45b45e1b45b45e1b45b45e1b45'
    ): bool {
        $closedStatus = BaselineTagResolutionStatus::tryFrom($status);

        if ($closedStatus === null) {
            return false;
        }

        $this->baselineStatus = $closedStatus;
        $this->baselineTagName = $tagName;
        $this->baselineTagObjectOid = $tagObjectOid;
        $this->baselinePeeledCommitOid = $peeledCommitOid;

        return true;
    }

    /**
     * Reads the fake release clock
     */
    public function now(): ReleaseBoundaryOperationResult
    {
        return $this->operationResult('clock.now', '2026-08-19T12:00:00.000000Z');
    }

    /**
     * Verifies through the fake signing boundary
     */
    public function verify(): ReleaseBoundaryOperationResult
    {
        return $this->operationResult('signing.verify', 'signature-verified');
    }

    /**
     * Checks through the fake authorization boundary
     */
    public function check(): ReleaseBoundaryOperationResult
    {
        return $this->operationResult('authorization.check', 'authorized');
    }

    /**
     * Creates through the fake GitHub boundary
     */
    public function release(): ReleaseBoundaryOperationResult
    {
        return $this->operationResult('github.release', 'github-release-created');
    }

    /**
     * Performs one fake Packagist publication
     */
    public function publish(): ReleaseBoundaryOperationResult
    {
        return $this->operationResult('packagist.publish', 'packagist-publication-completed');
    }

    /**
     * Returns the ordered deterministic effect ledger
     *
     * @return list<array{capability: string, effect_class: string, outcome: string}>
     */
    public function effects(): array
    {
        return $this->effects;
    }

    /**
     * Invokes the descriptor-relative storage primitive without a shell or inherited environment
     */
    private function storeArtifact(
        CanonicalRunsDirectory $directory,
        string $filename,
        string $contents
    ): int {
        $relativeParent = $directory->path === $directory->runsRoot ? '' : substr(
            $directory->path,
            strlen($directory->runsRoot) + 1
        );
        $command = [
            '/usr/bin/python3',
            $this->artifactStoreHelper,
            'write',
            $directory->runsRoot,
            $relativeParent,
            $filename,
            (string) strlen($contents),
            hash('sha256', $contents)
        ];

        if ($this->partialArtifactWriteBytesOnce !== null) {
            $command[] = '--write-limit='.$this->partialArtifactWriteBytesOnce;
            $this->partialArtifactWriteBytesOnce = null;
        }

        if ($this->exclusiveCreateCollisionIdenticalOnce !== null) {
            $collision = 'different';

            if ($this->exclusiveCreateCollisionIdenticalOnce) {
                $collision = 'identical';
            }

            $command[] = '--collision='.$collision;
            $this->exclusiveCreateCollisionIdenticalOnce = null;
        }

        if ($this->postPublishFailureOnce !== null) {
            $command[] = '--fail-after-publish='.$this->postPublishFailureOnce;
            $this->postPublishFailureOnce = null;
        }

        if ($this->postPublishFinalOnce !== null) {
            $command[] = '--post-publish-final='.$this->postPublishFinalOnce;
            $this->postPublishFinalOnce = null;
        }

        $pipes = [];
        $process = @proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $this->artifactProcessWorkingDirectory,
            [
                'LANG'                    => 'C',
                'LC_ALL'                  => 'C',
                'PATH'                    => '/usr/bin:/bin',
                'PYTHONDONTWRITEBYTECODE' => '1'
            ],
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            return 20;
        }

        $remaining = $contents;

        while ($remaining !== '') {
            $written = @fwrite($pipes[0], $remaining);

            if ($written === false || $written === 0) {
                break;
            }

            $remaining = substr($remaining, $written);
        }

        fclose($pipes[0]);
        $standardOutput = stream_get_contents($pipes[1], 65537);

        if (!is_string($standardOutput) || strlen($standardOutput) > 65536) {
            fclose($pipes[1]);
            @proc_terminate($process);
            $standardError = stream_get_contents($pipes[2], 65537);
            fclose($pipes[2]);
            proc_close($process);

            return 20;
        }

        $standardError = stream_get_contents($pipes[2], 65537);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if ($status === 30) {
            return 30;
        }

        if (
            $remaining !== ''
            || $standardOutput !== ''
            || $standardError !== ''
        ) {
            return 20;
        }

        return $status;
    }

    /**
     * Reads one immutable artifact through the descriptor-relative helper
     *
     * @return array{int, string}
     */
    private function readStoredArtifact(CanonicalRunsDirectory $directory, string $filename): array
    {
        $relativeParent = $directory->path === $directory->runsRoot ? '' : substr(
            $directory->path,
            strlen($directory->runsRoot) + 1
        );
        $pipes = [];
        $process = @proc_open(
            [
                '/usr/bin/python3',
                $this->artifactStoreHelper,
                'read',
                $directory->runsRoot,
                $relativeParent,
                $filename
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $this->artifactProcessWorkingDirectory,
            [
                'LANG'                    => 'C',
                'LC_ALL'                  => 'C',
                'PATH'                    => '/usr/bin:/bin',
                'PYTHONDONTWRITEBYTECODE' => '1'
            ],
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            return [20, ''];
        }

        fclose($pipes[0]);
        $standardOutput = stream_get_contents($pipes[1], self::MAX_ARTIFACT_BYTES + 1);

        if (!is_string($standardOutput) || strlen($standardOutput) > self::MAX_ARTIFACT_BYTES) {
            fclose($pipes[1]);
            @proc_terminate($process);
            $standardError = stream_get_contents($pipes[2], 65537);
            fclose($pipes[2]);
            proc_close($process);

            return [20, ''];
        }

        $standardError = stream_get_contents($pipes[2], 65537);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if (
            $standardError !== ''
        ) {
            return [20, ''];
        }

        return [$status, $standardOutput];
    }

    /**
     * Validates one literal output path relative to a descriptor-held runs root
     */
    private function resolveStoredDirectory(string $runsRoot, string $relativeParent): int
    {
        $pipes = [];
        $process = @proc_open(
            [
                '/usr/bin/python3',
                $this->artifactStoreHelper,
                'resolve',
                $runsRoot,
                $relativeParent
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $this->artifactProcessWorkingDirectory,
            [
                'LANG'                    => 'C',
                'LC_ALL'                  => 'C',
                'PATH'                    => '/usr/bin:/bin',
                'PYTHONDONTWRITEBYTECODE' => '1'
            ],
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            return 20;
        }

        fclose($pipes[0]);
        $standardOutput = stream_get_contents($pipes[1]);
        $standardError = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if ($standardOutput !== '' || $standardError !== '') {
            return 20;
        }

        return $status;
    }

    /**
     * Returns a descriptor-safe relative parent only for a literal path below the supplied root
     */
    private function relativeRunsParent(string $path, string $runsRoot): ?string
    {
        if (
            $path === ''
            || $runsRoot === ''
            || $path[0] !== DIRECTORY_SEPARATOR
            || $runsRoot[0] !== DIRECTORY_SEPARATOR
            || str_ends_with($path, DIRECTORY_SEPARATOR)
            || str_ends_with($runsRoot, DIRECTORY_SEPARATOR)
            || ($path !== $runsRoot && !str_starts_with($path, $runsRoot.DIRECTORY_SEPARATOR))
        ) {
            return null;
        }

        $relativeParent = $path === $runsRoot ? '' : substr($path, strlen($runsRoot) + 1);

        foreach (explode(DIRECTORY_SEPARATOR, $relativeParent) as $component) {
            if ($relativeParent !== '' && in_array($component, ['', '.', '..'], true)) {
                return null;
            }
        }

        return $relativeParent;
    }

    /**
     * Returns one outcome separately from its successful operation value
     */
    private function operationResult(string $effectClass, string $successValue): ReleaseBoundaryOperationResult
    {
        $outcome = $this->record($effectClass);

        if ($outcome === ReleaseBoundaryOutcome::SUCCESS) {
            return ReleaseBoundaryOperationResult::success($successValue);
        }

        if ($outcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED) {
            $effect = ReleaseEffect::from($effectClass);
            $evidence = $effect->configuredAlreadySatisfiedEvidence();
            assert($evidence !== null);

            return ReleaseBoundaryOperationResult::alreadySatisfied(
                $evidence
            );
        }

        return ReleaseBoundaryOperationResult::stopped($outcome);
    }

    /**
     * Evaluates one filesystem predicate and records successful evaluation separately from its boolean value
     *
     * @param string                 $effectClass Exact filesystem effect class.
     * @param callable(string): bool $predicate   Native predicate to evaluate.
     * @param string                 $path        Path supplied to the predicate.
     */
    private function predicate(
        string $effectClass,
        callable $predicate,
        string $path
    ): ReleaseBoundaryPredicateResult {
        $outcome = $this->configuredOutcome($effectClass);

        if ($outcome !== ReleaseBoundaryOutcome::SUCCESS) {
            $this->recordConfiguredOutcome($effectClass, $outcome);

            return ReleaseBoundaryPredicateResult::stopped($outcome);
        }

        $value = $this->predicateValues[$effectClass] ?? $predicate($path);
        $this->recordEffect($effectClass, ReleaseBoundaryOutcome::SUCCESS);

        return ReleaseBoundaryPredicateResult::success($value);
    }

    /**
     * Records a configured non-success outcome
     */
    private function recordConfiguredOutcome(string $effectClass, ReleaseBoundaryOutcome $outcome): void
    {
        $this->recordEffect($effectClass, $outcome);
    }

    /**
     * Records one closed, capability-qualified effect
     */
    private function record(string $effectClass): ReleaseBoundaryOutcome
    {
        $outcome = $this->configuredOutcome($effectClass);

        $this->recordEffect($effectClass, $outcome);

        return $outcome;
    }

    /**
     * Resolves one configured ordinary outcome or interrupts at a configured crash point
     */
    private function configuredOutcome(string $effectClass): ReleaseBoundaryOutcome
    {
        if (isset($this->crashPoints[$effectClass])) {
            $effect = ReleaseEffect::from($effectClass);
            $this->effects[] = [
                'capability'   => $effect->capability(),
                'effect_class' => $effectClass,
                'outcome'      => 'crash'
            ];

            throw new ReleaseBoundaryCrash($effectClass);
        }

        return $this->outcomes[$effectClass] ?? ReleaseBoundaryOutcome::SUCCESS;
    }

    /**
     * Records one final closed outcome for an exact effect
     */
    private function recordEffect(string $effectClass, ReleaseBoundaryOutcome $outcome): void
    {
        $effect = ReleaseEffect::from($effectClass);

        $this->effects[] = [
            'capability'   => $effect->capability(),
            'effect_class' => $effectClass,
            'outcome'      => $outcome->value
        ];
    }
}
