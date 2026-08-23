<?php

declare(strict_types=1);

namespace Fight\Release\Adapter\Fake;

use Closure;
use Fight\Release\Application\Boundary\ArchiveCreateResult;
use Fight\Release\Application\Boundary\ArchivePort;
use Fight\Release\Application\Boundary\AuthorizationPort;
use Fight\Release\Application\Boundary\BaselineTagResolutionResult;
use Fight\Release\Application\Boundary\BaselineTagResolutionStatus;
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
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\Boundary\ReleasePackageEffectSet;
use Fight\Release\Application\Boundary\ReleasePlanAuthorityPort;
use Fight\Release\Application\Boundary\ReleasePlanAuthorityStatus;
use Fight\Release\Application\Boundary\ReleaseRuntimeTermination;
use Fight\Release\Application\Boundary\RunsDirectoryResolutionResult;
use Fight\Release\Application\Boundary\RunStateStore;
use Fight\Release\Application\Boundary\ScopedReleaseEffectLedger;
use Fight\Release\Application\Boundary\SigningPort;
use FilesystemIterator;
use InvalidArgumentException;
use Throwable;
use ZipArchive;

/**
 * Class DeterministicReleaseBoundaryFake
 *
 * Credential-free deterministic provider for every release capability.
 */
final class DeterministicReleaseBoundaryFake implements
    ArchivePort,
    FilesystemPort,
    GitPort,
    HashingPort,
    ClockPort,
    SigningPort,
    AuthorizationPort,
    GitHubPort,
    PackagistPort,
    PlanArtifactStore,
    ReleasePlanAuthorityPort,
    RunStateStore,
    ScopedReleaseEffectLedger
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
    private int $effectOffset = 0;
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
    private readonly string $runStateStoreHelper;
    /** @var array<string, bool> */
    private array $predicateValues;
    private ReleasePlanAuthorityStatus $planAuthorityStatus = ReleasePlanAuthorityStatus::VERIFIED;
    private bool $terminateRunStateHelperOnce = false;
    /** @var array<string, string> */
    private array $archiveFileList = [];
    private ?string $archiveBytesOnce = null;

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
     * @param boolean $interruptRunProjectionOnce Test-only one-shot interruption after transition append.
     * @param boolean $interruptFinalizedRunProjectionOnce Test-only finalization interruption after append.
     * @param string|null $runStateFailureOnce Test-only one-shot native run-state writer failure point.
     * @param string|null $runStateReplacementTarget Test-only syscall-window replacement destination.
     * @param integer $runStateHelperTimeoutSeconds Test-only helper protocol timeout.
     * @param string|null $runStateStoreHelper Test-only run-state helper path.
     * @param Closure|null $runStateRead Test-only helper channel read seam.
     * @param Closure|null $runStateStatus Test-only helper process-status seam.
     */
    public function __construct(
        array $outcomes = [],
        ?int $partialArtifactWriteBytesOnce = null,
        ?bool $exclusiveCreateCollisionIdenticalOnce = null,
        array $predicateValues = [],
        ?string $artifactStoreHelper = null,
        private readonly ?string $artifactProcessWorkingDirectory = null,
        private ?string $postPublishFailureOnce = null,
        private ?string $postPublishFinalOnce = null,
        private bool $interruptRunProjectionOnce = false,
        private bool $interruptFinalizedRunProjectionOnce = false,
        private ?string $runStateFailureOnce = null,
        private readonly ?string $runStateReplacementTarget = null,
        private readonly int $runStateHelperTimeoutSeconds = 30,
        ?string $runStateStoreHelper = null,
        private readonly ?Closure $runStateRead = null,
        private readonly ?Closure $runStateStatus = null
    ) {
        if ($partialArtifactWriteBytesOnce !== null && $partialArtifactWriteBytesOnce < 0) {
            throw new InvalidArgumentException('A partial artifact write byte limit cannot be negative.');
        }

        $this->partialArtifactWriteBytesOnce = $partialArtifactWriteBytesOnce;
        $this->exclusiveCreateCollisionIdenticalOnce = $exclusiveCreateCollisionIdenticalOnce;
        $defaultArtifactStoreHelper = dirname(__DIR__, 3).'/scripts/release_artifact_store.py';
        $this->artifactStoreHelper = $artifactStoreHelper ?? $defaultArtifactStoreHelper;
        $this->runStateStoreHelper = $runStateStoreHelper ?? dirname(__DIR__, 3).'/scripts/release_run_state_store.py';

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
     * Configures one deterministic helper-protocol termination
     */
    public function terminateRunStateHelperOnce(): void
    {
        $this->terminateRunStateHelperOnce = true;
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
     * Configures current non-Git plan-authority truth returned by the fake
     */
    public function configurePlanAuthorityStatus(string $status): bool
    {
        $closedStatus = ReleasePlanAuthorityStatus::tryFrom($status);

        if ($closedStatus === null) {
            return false;
        }

        $this->planAuthorityStatus = $closedStatus;

        return true;
    }

    /**
     * Configures one test-only interruption after the prepared transition is durable
     */
    public function interruptRunProjectionOnce(): void
    {
        $this->interruptRunProjectionOnce = true;
    }

    /**
     * Configures one test-only interruption after run-directory durability and before immutable binding
     */
    public function interruptBeforeRunBindingOnce(): void
    {
        $this->runStateFailureOnce = 'interrupt_before_binding';
    }

    /**
     * Configures one test-only interruption after the finalized transition is durable
     */
    public function interruptFinalizedRunProjectionOnce(): void
    {
        $this->interruptFinalizedRunProjectionOnce = true;
    }

    /**
     * Configures the deterministic archive file listing
     *
     * @param array<string, string> $files Map of archive-relative paths to their content.
     */
    public function configureArchiveFileList(array $files): void
    {
        $this->archiveFileList = $files;
    }

    /**
     * Configures exact deterministic archive bytes for one invocation
     */
    public function configureArchiveBytesOnce(string $bytes): void
    {
        $this->archiveBytesOnce = $bytes;
    }

    /**
     * Creates one deterministic archive through the fake archive boundary
     *
     * @phpstan-param list<string> $exclusions
     */
    public function createArchive(
        string $candidateOid,
        string $version,
        string $sourceRepositoryPath,
        array $exclusions
    ): ArchiveCreateResult {
        $configuredOutcome = $this->configuredOutcome('archive.create');

        if ($configuredOutcome !== ReleaseBoundaryOutcome::SUCCESS) {
            $this->recordConfiguredOutcome('archive.create', $configuredOutcome);

            if ($configuredOutcome === ReleaseBoundaryOutcome::ALREADY_SATISFIED) {
                $digest = hash('sha256', 'deterministic-archive-'.$candidateOid.'-'.$version);

                return ArchiveCreateResult::alreadySatisfied($digest);
            }

            return ArchiveCreateResult::stopped($configuredOutcome);
        }

        $archiveName = 'fight-common-v'.$version.'.zip';

        if ($this->archiveBytesOnce !== null) {
            $bytes = $this->archiveBytesOnce;
            $this->archiveBytesOnce = null;
        } else {
            $bytes = $this->buildDeterministicArchiveBytes($exclusions);
        }

        $digest = hash('sha256', $bytes);
        $this->recordEffect('archive.create', ReleaseBoundaryOutcome::SUCCESS);
        $this->recordEffect('archive.verify', ReleaseBoundaryOutcome::SUCCESS);

        return ArchiveCreateResult::created($sourceRepositoryPath.'/.runs/'.$archiveName, $digest);
    }

    /**
     * Derives one bounded archive effect set through the fake archive boundary
     */
    public function deriveEffectSet(
        string $candidateOid,
        string $version,
        string $sourceRepositoryPath
    ): ReleasePackageEffectSet {
        $archiveName = 'fight-common-v'.$version.'.zip';
        $includedPaths = array_keys($this->archiveFileList);
        sort($includedPaths, SORT_STRING);
        $excludedPaths = [];

        return new ReleasePackageEffectSet(
            $candidateOid,
            $version,
            $archiveName,
            $includedPaths,
            $excludedPaths
        );
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
     * Creates one run with append-ordered planned and prepared transitions
     *
     * @return array{
     *     status: string,
     *     history_path?: string,
     *     projection_path?: string,
     *     history_sha256?: string,
     *     projection_sha256?: string,
     *     prepared_history_sha256?: string,
     *     prepared_projection_sha256?: string,
     *     prerequisite_evidence_manifest_id?: string,
     *     prerequisite_phase_handoff_id?: string
     * }
     */
    public function createPlannedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId
    ): array {
        /** @var array{status: string, history_path?: string, projection_path?: string, prepared_history_sha256?: string, prepared_projection_sha256?: string} $result */
        $result = $this->runStateOperation('create', $directory, $planId, $runId);

        return $result;
    }

    /**
     * Appends and publishes prepared state through retained directory authority
     */
    public function publishPreparedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        int $expectedSequence,
        string $expectedState
    ): array {
        $interrupt = $this->interruptRunProjectionOnce;
        $this->interruptRunProjectionOnce = false;

        /** @var array{status: string, history_path?: string, projection_path?: string, history_sha256?: string, projection_sha256?: string, prepared_history_sha256?: string, prepared_projection_sha256?: string, prerequisite_evidence_manifest_id?: string, prerequisite_phase_handoff_id?: string} $result */
        $result = $this->runStateOperation(
            'publish',
            $directory,
            $planId,
            $runId,
            [(string) $expectedSequence, $expectedState],
            $interrupt ? 'interrupt_run_projection' : null
        );

        return $result;
    }

    /**
     * Creates package-ready state after binding its prerequisite artifacts
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
        $interrupt = $this->interruptFinalizedRunProjectionOnce;
        $this->interruptFinalizedRunProjectionOnce = false;

        /** @var array{status: string, history_path?: string, projection_path?: string, history_sha256?: string, projection_sha256?: string, prepared_history_sha256?: string, prepared_projection_sha256?: string, prerequisite_evidence_manifest_id?: string, prerequisite_phase_handoff_id?: string} $result */
        $result = $this->runStateOperation(
            'finalize',
            $directory,
            $planId,
            $runId,
            [$manifestId, $handoffId, (string) $expectedSequence, $expectedState],
            $interrupt ? 'interrupt_finalized_projection' : null
        );

        return $result;
    }

    /**
     * Appends one durable certification outcome through retained directory authority
     */
    public function publishCertificationRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        string $state,
        string $artifactId,
        string $handoffId,
        int $expectedSequence,
        string $expectedState
    ): array {
        /** @var array{status: string, history_path?: string, projection_path?: string, sequence?: int, state?: string, history_sha256?: string, projection_sha256?: string, certification_artifact_id?: string, prerequisite_certification_handoff_id?: string} $result */
        $result = $this->runStateOperation(
            'certify',
            $directory,
            $planId,
            $runId,
            [$state, $artifactId, $handoffId, (string) $expectedSequence, $expectedState]
        );

        return $result;
    }

    /**
     * Validates and repairs one named prepared run without pathname authority
     */
    public function resumePreparedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId
    ): array {
        /** @var array{status: string, history_path?: string, projection_path?: string, projection_repaired?: bool, history_sha256?: string, projection_sha256?: string, prepared_history_sha256?: string, prepared_projection_sha256?: string, prerequisite_evidence_manifest_id?: string, prerequisite_phase_handoff_id?: string} $result */
        $result = $this->runStateOperation('resume', $directory, $planId, $runId);

        return $result;
    }

    /**
     * Appends one exact stop-recovery transition through retained directory authority
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
        /** @var array{status: string, history_path?: string, projection_path?: string, sequence?: int, state?: string, next_action?: string} $result */
        $result = $this->runStateOperation(
            'recover',
            $directory,
            $planId,
            $runId,
            [
                (string) $stopSequence,
                $stopCode,
                $stopState,
                $findingId,
                $nextAction,
                $repairManifestId ?? '',
                $repairHandoffId ?? ''
            ]
        );

        return $result;
    }

    /**
     * Appends and publishes one classified preparation stop
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
        /** @var array{status: string, history_path?: string, projection_path?: string} $result */
        $result = $this->runStateOperation(
            'stop',
            $directory,
            $planId,
            $runId,
            [
                $stopCode,
                $stopState,
                $findingId,
                $nextAction,
                $manifestId ?? '',
                $handoffId ?? '',
                $expectedSequence === null ? '' : (string) $expectedSequence,
                $expectedState ?? ''
            ]
        );

        return $result;
    }

    /**
     * Creates one run with append-ordered planned and prepared transitions
     */
    public function createPreparedRun(
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId
    ): array {
        $planned = $this->createPlannedRun($directory, $planId, $runId);

        if ($planned['status'] !== 'planned') {
            return $planned;
        }

        return $this->publishPreparedRun($directory, $planId, $runId, 1, 'planned');
    }

    /**
     * Resolves current non-Git policy and approval authority again
     */
    public function revalidatePlanAuthority(array $plan): ReleasePlanAuthorityStatus
    {
        $this->record('authorization.check');

        return $this->planAuthorityStatus;
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
     * Resolves one exact annotated tag through the fake Git boundary
     */
    public function resolveExactAnnotatedTag(string $tagName): BaselineTagResolutionResult
    {
        return $this->resolveBaselineTag($tagName, $this->baselinePeeledCommitOid);
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
        return array_slice($this->effects, $this->effectOffset);
    }

    /**
     * Starts one invocation-local effect view while retaining configured outcomes
     */
    public function beginEffectScope(): void
    {
        $this->effectOffset = count($this->effects);
    }

    /**
     * Records one outcome established by a runtime adapter sharing this ordered ledger
     */
    public function recordObservedEffect(ReleaseEffect $effect, ReleaseBoundaryOutcome $outcome): void
    {
        $this->recordEffect($effect->value, $outcome);
    }

    /**
     * Builds deterministic archive bytes for testing
     *
     * @phpstan-param list<string> $exclusions
     */
    private function buildDeterministicArchiveBytes(array $exclusions): string
    {
        $tempDir = sys_get_temp_dir().'/fight-zip-'.bin2hex(random_bytes(8));
        mkdir($tempDir, 0777, true);

        try {
            foreach ($this->archiveFileList as $relativePath => $content) {
                if (in_array($relativePath, $exclusions, true)) {
                    continue;
                }

                $targetPath = $tempDir.'/'.$relativePath;
                $targetDir = dirname($targetPath);

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                file_put_contents($targetPath, $content);
                touch($targetPath, 315532800);
            }

            $zipPath = $tempDir.'/archive.zip';
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $sortedFiles = array_keys($this->archiveFileList);
            sort($sortedFiles, SORT_STRING);

            foreach ($sortedFiles as $relativePath) {
                if (in_array($relativePath, $exclusions, true)) {
                    continue;
                }

                $zip->addFile($tempDir.'/'.$relativePath, $relativePath);
            }

            $zip->close();

            $bytes = file_get_contents($zipPath);
            assert(is_string($bytes));

            return $bytes;
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * Removes one temporary directory tree without emitting native warnings
     */
    private function removeDirectory(string $path): void
    {
        $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                $this->removeDirectory($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * Invokes one descriptor-held release-run operation without a shell or inherited environment
     *
     * @param string                 $operation          Closed run-state operation.
     * @param CanonicalRunsDirectory $directory          Descriptor-authorized output and root.
     * @param string                 $planId             Immutable plan identity.
     * @param string                 $runId              Named run identity.
     * @param array                  $operationArguments Operation-specific literal values.
     * @param string|null            $forcedFault        One exact interruption override.
     *
     * @return array<string, mixed>
     *
     * @phpstan-param list<string> $operationArguments
     */
    private function runStateOperation(
        string $operation,
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $operationArguments = [],
        ?string $forcedFault = null
    ): array {
        if ($this->terminateRunStateHelperOnce) {
            $this->terminateRunStateHelperOnce = false;

            throw new ReleaseRuntimeTermination('The release run-state helper returned a malformed receipt.');
        }

        if (
            preg_match('/\A[0-9a-f]{64}\z/D', $planId) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $runId) !== 1
        ) {
            return ['status' => $operation === 'create' ? 'failed' : 'indeterminate'];
        }

        $relativeOutput = $this->relativeRunsParent($directory->path, $directory->runsRoot);

        if ($relativeOutput === null) {
            return ['status' => $operation === 'create' ? 'failed' : 'indeterminate'];
        }

        $fault = $forcedFault ?? $this->runStateFaultFor($operation);
        $command = [
            '/usr/bin/python3',
            $this->runStateStoreHelper,
            $operation,
            $directory->runsRoot,
            $relativeOutput,
            $planId,
            $runId,
            ...$operationArguments,
            $fault,
            $this->runStateReplacementTarget ?? ''
        ];
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
            throw new ReleaseRuntimeTermination('The release run-state helper could not be started.');
        }

        [$status, $output, $error, $complete] = $this->exchangeHelper(
            $process,
            $pipes,
            '',
            65536
        );

        if ($fault !== '') {
            $this->runStateFailureOnce = null;
        }

        if (!$complete || $status !== 0 || strlen($output) > 65536 || $error !== '') {
            throw new ReleaseRuntimeTermination('The release run-state helper terminated without a governed receipt.');
        }

        $result = json_decode($output, true);
        if (
            !is_array($result)
            || array_is_list($result)
            || !$this->validRunStateReceipt(
                $operation,
                $result,
                $directory,
                $planId,
                $runId,
                $operationArguments,
                $fault
            )
        ) {
            throw new ReleaseRuntimeTermination('The release run-state helper returned an invalid closed receipt.');
        }

        if (($result['crash'] ?? false) === true) {
            $this->effects[] = [
                'capability'   => 'filesystem',
                'effect_class' => 'filesystem.write',
                'outcome'      => 'crash'
            ];

            throw new ReleaseBoundaryCrash('filesystem.write');
        }

        if ($operation === 'resume') {
            $outcome = match ($result['status'] ?? null) {
                'planned', 'evidence_pending', 'stopped', 'verified' => ReleaseBoundaryOutcome::SUCCESS,
                'conflict' => ReleaseBoundaryOutcome::REFUSAL,
                'indeterminate', 'missing', 'stale' => ReleaseBoundaryOutcome::UNCERTAINTY,
                default => ReleaseBoundaryOutcome::FAILURE
            };
            $this->recordEffect('filesystem.read', $outcome);

            if (($result['projection_repaired'] ?? false) === true) {
                $this->recordEffect('filesystem.write', ReleaseBoundaryOutcome::SUCCESS);
            }
        } else {
            $outcome = match ($result['status'] ?? null) {
                'planned', 'created', 'verified' => ReleaseBoundaryOutcome::SUCCESS,
                'conflict' => ReleaseBoundaryOutcome::REFUSAL,
                'advanced', 'indeterminate', 'missing', 'stale' => ReleaseBoundaryOutcome::UNCERTAINTY,
                default => ReleaseBoundaryOutcome::FAILURE
            };
            $this->recordEffect('filesystem.write', $outcome);
        }

        return $result;
    }

    /**
     * Validates the exact closed receipt schema for one helper operation
     *
     * @param string                  $operation Helper operation name.
     * @param array<array-key, mixed> $result Decoded helper receipt.
     * @param CanonicalRunsDirectory $directory Descriptor-authorized output and root.
     * @param string $planId Immutable plan identity.
     * @param string $runId Named run identity.
     * @param array $arguments Operation-specific literal arguments.
     * @param string $fault Exact configured one-shot helper fault.
     *
     * @phpstan-param list<string> $arguments
     */
    private function validRunStateReceipt(
        string $operation,
        array $result,
        CanonicalRunsDirectory $directory,
        string $planId,
        string $runId,
        array $arguments,
        string $fault
    ): bool {
        if ($result === ['crash' => true]) {
            return $fault === match ($operation) {
                'create' => 'interrupt_before_binding',
                'publish' => 'interrupt_run_projection',
                'finalize' => 'interrupt_finalized_projection',
                default => null
            };
        }

        $status = $result['status'] ?? null;

        if (!is_string($status)) {
            return false;
        }

        $terminal = match ($operation) {
            'create' => ['conflict', 'failed', 'indeterminate'],
            'publish', 'finalize', 'recover', 'certify' => [
                'advanced', 'conflict', 'indeterminate', 'missing', 'stale'
            ],
            'resume' => ['conflict', 'failed', 'indeterminate', 'missing', 'stale'],
            'stop' => ['advanced', 'conflict', 'failed', 'indeterminate', 'missing', 'stale'],
            default => throw new ReleaseRuntimeTermination(
                'The release run-state helper operation was not recognized.'
            )
        };

        if (in_array($status, $terminal, true)) {
            return array_keys($result) === ['status'];
        }

        $schemas = [
            'create:planned'          => [
                'status', 'history_path', 'projection_path', 'sequence', 'state',
                'prepared_history_sha256', 'prepared_projection_sha256'
            ],
            'publish:created'         => [
                'status', 'history_path', 'projection_path', 'sequence', 'state',
                'history_sha256', 'projection_sha256'
            ],
            'finalize:created'        => [
                'status', 'history_path', 'projection_path', 'sequence', 'state',
                'history_sha256', 'projection_sha256', 'prepared_history_sha256',
                'prepared_projection_sha256', 'prerequisite_evidence_manifest_id',
                'prerequisite_phase_handoff_id'
            ],
            'certify:created'         => [
                'status', 'history_path', 'projection_path', 'sequence', 'state',
                'history_sha256', 'projection_sha256', 'certification_artifact_id',
                'prerequisite_certification_handoff_id'
            ],
            'recover:created'         => [
                'status', 'history_path', 'projection_path', 'sequence', 'state', 'next_action'
            ],
            'stop:created'            => [
                'status', 'history_path', 'projection_path', 'stop_code', 'stop_state',
                'finding_id', 'next_action', 'sequence', 'state'
            ],
            'stop:verified'           => [
                'status', 'history_path', 'projection_path', 'stop_code', 'stop_state',
                'finding_id', 'next_action', 'sequence', 'state'
            ],
            'resume:planned'          => [
                'status', 'history_path', 'projection_path', 'sequence', 'state', 'projection_repaired',
                'prepared_history_sha256', 'prepared_projection_sha256'
            ],
            'resume:evidence_pending' => [
                'status', 'history_path', 'projection_path', 'sequence', 'state', 'projection_repaired',
                'history_sha256', 'projection_sha256'
            ],
            'resume:verified'         => [
                'status', 'history_path', 'projection_path', 'sequence', 'state', 'projection_repaired',
                'history_sha256', 'projection_sha256', 'prepared_history_sha256',
                'prepared_projection_sha256', 'prerequisite_evidence_manifest_id',
                'prerequisite_phase_handoff_id'
            ]
        ];
        $expected = $schemas[$operation.':'.$status] ?? null;

        if ($operation === 'resume' && $status === 'stopped') {
            $expected = [
                'status', 'history_path', 'projection_path', 'stop_code', 'stop_state',
                'finding_id', 'next_action', 'sequence', 'state', 'projection_repaired'
            ];
            $resumeFields = ['resume_state', 'resume_sequence', 'resume_next_action'];
            $bindingFields = ['prerequisite_evidence_manifest_id', 'prerequisite_phase_handoff_id'];
            if (array_intersect($resumeFields, array_keys($result)) !== []) {
                $expected = [...$expected, ...$resumeFields];
            }

            if (array_intersect($bindingFields, array_keys($result)) !== []) {
                $expected = [...$expected, ...$bindingFields];
            }
        }

        if (
            $operation === 'stop'
            && in_array($status, ['created', 'verified'], true)
            && ($arguments[4] ?? '') !== ''
        ) {
            $expected = [
                ...$expected,
                'prerequisite_evidence_manifest_id',
                'prerequisite_phase_handoff_id'
            ];
        }

        if (!is_array($expected) || array_keys($result) !== $expected) {
            return false;
        }

        foreach ($expected as $field) {
            if ($field === 'sequence' || $field === 'resume_sequence') {
                if (!is_int($result[$field]) || $result[$field] < 1) {
                    return false;
                }
            } elseif ($field === 'projection_repaired') {
                if (!is_bool($result[$field])) {
                    return false;
                }
            } elseif (!is_string($result[$field])) {
                return false;
            }
        }

        $runPath = $directory->path.'/runs/'.$runId;
        if (
            isset($result['history_path'])
            && (
                $result['history_path'] !== $runPath.'/history.jsonl'
                || $result['projection_path'] !== $runPath.'/projection.json'
            )
        ) {
            return false;
        }

        foreach (array_keys($result) as $field) {
            if (str_ends_with($field, '_sha256') && preg_match('/\A[0-9a-f]{64}\z/D', $result[$field]) !== 1) {
                return false;
            }

            if (
                str_starts_with($field, 'prerequisite_')
                && str_ends_with($field, '_id')
                && preg_match('/\A[0-9a-f]{64}\z/D', $result[$field]) !== 1
            ) {
                return false;
            }
        }

        return match ($operation.':'.$status) {
            'create:planned' => $result['sequence'] === 1 && $result['state'] === 'planned',
            'publish:created' => $this->receiptAdvances($result, $arguments[0] ?? null, 'prepared'),
            'finalize:created' => $this->receiptAdvances($result, $arguments[2] ?? null, 'prepared')
                && $result['prerequisite_evidence_manifest_id'] === ($arguments[0] ?? null)
                && $result['prerequisite_phase_handoff_id'] === ($arguments[1] ?? null),
            'certify:created' => $this->receiptAdvances($result, $arguments[3] ?? null, $arguments[0] ?? null)
                && $result['certification_artifact_id'] === ($arguments[1] ?? null)
                && $result['prerequisite_certification_handoff_id'] === ($arguments[2] ?? null),
            'recover:created' => $this->receiptAdvances($result, $arguments[0] ?? null),
            'stop:created', 'stop:verified' => $this->receiptAdvances(
                $result,
                ($arguments[6] ?? '') === '' ? '0' : $arguments[6],
                $arguments[1] ?? null
            ) && $this->receiptMatchesStopArguments($result, $arguments),
            'resume:planned' => $result['state'] === 'planned',
            'resume:evidence_pending', 'resume:verified' => $result['state'] === 'prepared',
            'resume:stopped' => $result['state'] === $result['stop_state']
                && $this->validStoppedReceiptContract($result)
                && $this->validResumeStopToken($result),
            default => false
        };
    }

    /**
     * Reports whether one positive stop receipt echoes its exact requested tuple and bindings
     *
     * @param array<array-key, mixed> $result Positive stop receipt.
     * @param array $arguments Requested stop arguments.
     *
     * @phpstan-param list<string> $arguments
     */
    private function receiptMatchesStopArguments(array $result, array $arguments): bool
    {
        if (
            $result['stop_code'] !== ($arguments[0] ?? null)
            || $result['stop_state'] !== ($arguments[1] ?? null)
            || $result['finding_id'] !== ($arguments[2] ?? null)
            || $result['next_action'] !== ($arguments[3] ?? null)
        ) {
            return false;
        }

        $hasRequestedBindings = ($arguments[4] ?? '') !== '' && ($arguments[5] ?? '') !== '';

        if ((($arguments[4] ?? '') !== '') !== (($arguments[5] ?? '') !== '')) {
            return false;
        }

        if (!$hasRequestedBindings) {
            return !isset(
                $result['prerequisite_evidence_manifest_id'],
                $result['prerequisite_phase_handoff_id']
            );
        }

        return $result['prerequisite_evidence_manifest_id'] === $arguments[4]
            && $result['prerequisite_phase_handoff_id'] === $arguments[5];
    }

    /**
     * Reports whether one resumed stop has exactly the token required by its sequence
     *
     * @param array<array-key, mixed> $result Positive stopped receipt.
     */
    private function validResumeStopToken(array $result): bool
    {
        if ($result['sequence'] === 1) {
            return !isset(
                $result['resume_state'],
                $result['resume_sequence'],
                $result['resume_next_action']
            );
        }

        return isset($result['resume_state'], $result['resume_sequence'], $result['resume_next_action'])
            && $result['resume_state'] !== ''
            && $result['resume_next_action'] !== ''
            && $result['resume_sequence'] < $result['sequence'];
    }

    /**
     * Reports whether one resumed stop carries a closed causal stop contract
     *
     * @param array<array-key, mixed> $result Positive stopped receipt.
     */
    private function validStoppedReceiptContract(array $result): bool
    {
        $contract = match ($result['stop_code']) {
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
            ],
            'artifact_indeterminate' => null,
            default => false
        };

        if ($contract === false) {
            return false;
        }

        if ($contract === null) {
            return in_array([
                $result['stop_state'],
                $result['finding_id'],
                $result['next_action']
            ], [
                ['evidence_indeterminate', 'release.prepare.artifacts_indeterminate', 'reconcile_named_release_run'],
                [
                    'evidence_indeterminate',
                    'release.prepare.evidence_persistence_failed',
                    'repair_release_evidence_storage'
                ]
            ], true);
        }

        return [$result['stop_state'], $result['finding_id'], $result['next_action']] === $contract;
    }

    /**
     * Reports whether one positive receipt advances the requested predecessor
     *
     * @param array<array-key, mixed> $result Positive helper receipt.
     */
    private function receiptAdvances(array $result, mixed $rawSequence, ?string $state = null): bool
    {
        return is_string($rawSequence)
            && ctype_digit($rawSequence)
            && $result['sequence'] === ((int) $rawSequence) + 1
            && ($state === null || $result['state'] === $state);
    }

    /**
     * Reads both helper channels concurrently within one fixed protocol bound
     *
     * @param resource             $process Running helper process.
     * @param array<int, resource> $pipes Helper standard streams.
     * @param string               $input Exact standard-input payload.
     * @param integer              $outputLimit Maximum accepted standard output.
     *
     * @return array{int, string, string, bool}
     */
    private function exchangeHelper($process, array $pipes, string $input, int $outputLimit): array
    {
        [$standardInput, $standardOutput, $standardError] = $pipes;
        stream_set_blocking($standardInput, false);
        stream_set_blocking($standardOutput, false);
        stream_set_blocking($standardError, false);
        $output = '';
        $error = '';
        $open = [$standardOutput, $standardError];
        $complete = true;
        $inputOffset = 0;
        $started = microtime(true);
        $activity = $started;
        $totalDeadline = $started + max(1, $this->runStateHelperTimeoutSeconds * 2);

        while ($open !== [] || is_resource($standardInput)) {
            $now = microtime(true);
            $remaining = min(
                $totalDeadline - $now,
                ($activity + $this->runStateHelperTimeoutSeconds) - $now
            );
            if ($remaining <= 0) {
                $complete = false;
                break;
            }

            $read = $open;
            $write = is_resource($standardInput) ? [$standardInput] : [];
            $except = null;
            $seconds = (int) floor($remaining);
            $microseconds = (int) (($remaining - $seconds) * 1000000);
            $selected = @stream_select($read, $write, $except, $seconds, $microseconds);
            if (
                !is_int($selected)
                || $selected < 1
                || microtime(true) - $activity > $this->runStateHelperTimeoutSeconds
            ) {
                $complete = false;
                break;
            }

            foreach ($write as $stream) {
                $written = @fwrite($stream, substr($input, $inputOffset, 8192));
                if (!is_int($written)) {
                    $complete = false;
                    break 2;
                }

                if ($written > 0) {
                    $inputOffset += $written;
                    $activity = microtime(true);
                }

                if ($inputOffset === strlen($input)) {
                    fclose($standardInput);
                }
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if ($this->runStateRead instanceof Closure) {
                    $chunk = ($this->runStateRead)($stream, 8192);
                }

                if (!is_string($chunk)) {
                    $complete = false;
                    break 2;
                }

                if ($stream === $standardOutput) {
                    $output .= substr($chunk, 0, max(0, $outputLimit + 1 - strlen($output)));
                    $complete = $complete && strlen($output) <= $outputLimit;
                } else {
                    $error .= substr($chunk, 0, max(0, 65537 - strlen($error)));
                    $complete = $complete && strlen($error) <= 65536;
                }

                if ($chunk !== '') {
                    $activity = microtime(true);
                }

                if (feof($stream)) {
                    $open = array_values(array_filter($open, static fn ($candidate): bool => $candidate !== $stream));
                }
            }

            if (!$complete) {
                break;
            }
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $status = $this->waitForHelperExit($process, microtime(true) + 0.05);
        if ($status === null) {
            $complete = false;
            @proc_terminate($process);
            $status = $this->waitForHelperExit($process, microtime(true) + 0.25);
        }

        if ($status === null) {
            @proc_terminate($process, 9);
            $status = $this->waitForHelperExit($process, microtime(true) + 0.25);
        }

        if ($status === null) {
            return [1, $output, $error, false];
        }

        $closed = proc_close($process);
        $status = $closed >= 0 ? $closed : $status;

        return [$status, $output, $error, $complete && $inputOffset === strlen($input)];
    }

    /**
     * Returns one helper exit observed within the supplied deadline
     *
     * @param resource $process Helper process.
     */
    private function waitForHelperExit($process, float $deadline): ?int
    {
        do {
            /** @var array{running: bool, exitcode: int} $status */
            $status = isset($this->runStateStatus) ? ($this->runStateStatus)($process) : proc_get_status($process);
            if (!$status['running']) {
                return $status['exitcode'] >= 0 ? $status['exitcode'] : 1;
            }

            usleep(1000);
        } while (microtime(true) < $deadline);

        return null;
    }

    /**
     * Returns a one-shot fault only for the operation where it belongs
     */
    private function runStateFaultFor(string $operation): string
    {
        $fault = $this->runStateFailureOnce ?? '';

        if (
            $operation === 'create'
            && in_array($fault, [
                'append_lock',
                'append_short',
                'prepared_projection',
                'prepared_projection_directory_sync',
                'replace_run_before_state_stage',
                'replace_run_after_link_before_state_publish'
            ], true)
        ) {
            return '';
        }

        return $fault;
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

        [$status, $standardOutput, $standardError, $complete] = $this->exchangeHelper(
            $process,
            $pipes,
            $contents,
            65536
        );

        if ($status === 30) {
            return 30;
        }

        if (
            !$complete
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

        [$status, $standardOutput, $standardError, $complete] = $this->exchangeHelper(
            $process,
            $pipes,
            '',
            self::MAX_ARTIFACT_BYTES
        );

        if (
            !$complete || $standardError !== ''
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

        [$status, $standardOutput, $standardError, $complete] = $this->exchangeHelper(
            $process,
            $pipes,
            '',
            65536
        );

        if (!$complete || $standardOutput !== '' || $standardError !== '') {
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
