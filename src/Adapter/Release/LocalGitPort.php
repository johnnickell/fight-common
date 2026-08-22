<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Release;

use Closure;
use Fight\Common\Application\Release\Boundary\BaselineTagResolutionResult;
use Fight\Common\Application\Release\Boundary\BaselineTagResolutionStatus;
use Fight\Common\Application\Release\Boundary\GitPort;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOperationResult;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Common\Application\Release\Boundary\ReleaseEffect;
use Fight\Common\Application\Release\StableSemVer;

/**
 * Class LocalGitPort
 *
 * Resolves immutable baseline identities from the local repository.
 */
final readonly class LocalGitPort implements GitPort
{
    private const int MAX_GIT_OUTPUT_BYTES = 65536;
    private const int MAX_GIT_ERROR_BYTES = 65536;
    private const int PROCESS_IO_TIMEOUT_MILLISECONDS = 30000;
    private const int PROCESS_TOTAL_TIMEOUT_MILLISECONDS = 60000;
    private const int PROCESS_SHUTDOWN_TIMEOUT_MILLISECONDS = 250;

    /**
     * Constructs LocalGitPort
     *
     * @param string $repository Local repository root.
     * @param Closure      $record                 Effect recorder.
     * @param Closure|null $observeResolutionStage Test seam for deterministic ref-race coverage.
     * @param string       $gitBinary              Exact Git provider executable.
     * @param integer      $processIoTimeout       Inactive provider timeout in milliseconds.
     * @param integer      $processShutdownTimeout Provider shutdown deadline in milliseconds.
     * @param integer      $processTotalTimeout    Total provider I/O deadline in milliseconds.
     *
     * @phpstan-param Closure(ReleaseEffect, ReleaseBoundaryOutcome): void $record
     * @phpstan-param Closure(string): void|null $observeResolutionStage
     */
    public function __construct(
        private string $repository,
        private Closure $record,
        private ?Closure $observeResolutionStage = null,
        private string $gitBinary = '/usr/bin/git',
        private int $processIoTimeout = self::PROCESS_IO_TIMEOUT_MILLISECONDS,
        private int $processShutdownTimeout = self::PROCESS_SHUTDOWN_TIMEOUT_MILLISECONDS,
        private int $processTotalTimeout = self::PROCESS_TOTAL_TIMEOUT_MILLISECONDS
    ) {
    }

    /**
     * Checks whether the configured path is a local Git repository
     */
    public function inspectRepository(): ReleaseBoundaryOperationResult
    {
        [$status] = $this->git(['rev-parse', '--git-dir']);
        $outcome = $status === 0 ? ReleaseBoundaryOutcome::SUCCESS : ReleaseBoundaryOutcome::FAILURE;
        ($this->record)(ReleaseEffect::GIT_INSPECT_REPOSITORY, $outcome);

        if ($outcome === ReleaseBoundaryOutcome::SUCCESS) {
            return ReleaseBoundaryOperationResult::success('repository-inspected');
        }

        return ReleaseBoundaryOperationResult::stopped($outcome);
    }

    /**
     * Checks one annotated baseline tag and its candidate ancestry
     */
    public function resolveBaselineTag(string $tagName, string $candidateOid): BaselineTagResolutionResult
    {
        [$referenceStatus, $tagObject] = $this->git([
            'rev-parse', '--verify', '--quiet', 'refs/tags/'.$tagName
        ]);

        if ($referenceStatus === 1) {
            $this->record(ReleaseBoundaryOutcome::SUCCESS);

            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::MISSING);
        }

        if ($referenceStatus !== 0 || preg_match('/\A[0-9a-f]{40,64}\z/D', $tagObject) !== 1) {
            return $this->failed();
        }

        if ($this->observeResolutionStage instanceof Closure) {
            ($this->observeResolutionStage)('tag_object_resolved');
        }

        $normalizedTags = $this->normalizedReleaseTags($tagName);

        if ($normalizedTags === null) {
            return $this->failed();
        }

        if ($this->observeResolutionStage instanceof Closure) {
            ($this->observeResolutionStage)('normalized_tags_resolved');
        }

        [$typeStatus, $type] = $this->git(['cat-file', '-t', $tagObject]);

        if ($typeStatus !== 0) {
            return $this->failed();
        }

        if ($type !== 'tag') {
            $this->record(ReleaseBoundaryOutcome::SUCCESS);

            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::AMBIGUOUS);
        }

        [$peeledStatus, $peeled] = $this->git(['rev-parse', '--verify', $tagObject.'^{commit}']);

        if ($peeledStatus !== 0 || preg_match('/\A[0-9a-f]{40,64}\z/D', $peeled) !== 1) {
            return $this->failed();
        }

        [$ancestorStatus] = $this->git(['merge-base', '--is-ancestor', $peeled, $candidateOid]);

        if ($ancestorStatus === 1) {
            $this->record(ReleaseBoundaryOutcome::SUCCESS);

            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::NON_ANCESTOR);
        }

        if ($ancestorStatus !== 0) {
            return $this->failed();
        }

        if ($this->observeResolutionStage instanceof Closure) {
            ($this->observeResolutionStage)('candidate_ancestry_verified');
        }

        [$currentStatus, $currentTagObject] = $this->git([
            'rev-parse', '--verify', '--quiet', 'refs/tags/'.$tagName
        ]);

        if ($currentStatus === 1) {
            $this->record(ReleaseBoundaryOutcome::SUCCESS);

            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::MOVING);
        }

        if ($currentStatus !== 0 || preg_match('/\A[0-9a-f]{40,64}\z/D', $currentTagObject) !== 1) {
            return $this->failed();
        }

        if ($currentTagObject !== $tagObject) {
            $this->record(ReleaseBoundaryOutcome::SUCCESS);

            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::MOVING);
        }

        if ($this->observeResolutionStage instanceof Closure) {
            ($this->observeResolutionStage)('exact_tag_revalidated');
        }

        $currentNormalizedTags = $this->normalizedReleaseTags($tagName);

        if ($currentNormalizedTags === null) {
            return $this->failed();
        }

        if ($currentNormalizedTags !== $normalizedTags) {
            $this->record(ReleaseBoundaryOutcome::SUCCESS);

            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::MOVING);
        }

        if (count($normalizedTags) > 1) {
            $this->record(ReleaseBoundaryOutcome::SUCCESS);

            return BaselineTagResolutionResult::rejected(BaselineTagResolutionStatus::DUPLICATE_NORMALIZED);
        }

        $this->record(ReleaseBoundaryOutcome::SUCCESS);

        return BaselineTagResolutionResult::resolved($tagName, $tagObject, $peeled);
    }

    /**
     * Returns the stable set of tags representing the requested release version
     *
     * Non-release tag names retain their existing exact-ref behavior.
     *
     * @return list<string>|null
     */
    private function normalizedReleaseTags(string $requestedTag): ?array
    {
        $version = $this->releaseVersion($requestedTag);

        if ($version === null) {
            return [$requestedTag];
        }

        [$status, $output] = $this->git([
            'for-each-ref', '--format=%(refname:strip=2)', 'refs/tags'
        ]);

        if ($status !== 0) {
            return null;
        }

        $tags = explode("\n", $output);
        $normalized = array_values(array_filter(
            $tags,
            fn (string $tag): bool => $this->releaseVersion($tag) === $version
        ));
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * Returns the strict stable SemVer represented by one Composer-style tag
     */
    private function releaseVersion(string $tag): ?string
    {
        $version = str_starts_with($tag, 'v') ? substr($tag, 1) : $tag;

        return StableSemVer::isValid($version) ? $version : null;
    }

    /**
     * Invokes one closed Git argument vector in the configured repository
     *
     * @param array $arguments Closed Git argument vector.
     *
     * @return array{int, string}
     *
     * @phpstan-param list<string> $arguments
     */
    private function git(array $arguments): array
    {
        $pipes = [];
        $process = @proc_open(
            [$this->gitBinary, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->repository,
            [
                'LANG'                   => 'C',
                'LC_ALL'                 => 'C',
                'PATH'                   => '/usr/bin:/bin',
                'GIT_CONFIG_GLOBAL'      => '/dev/null',
                'GIT_CONFIG_NOSYSTEM'    => '1',
                'GIT_NO_REPLACE_OBJECTS' => '1'
            ],
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            return [127, ''];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        [$output, , $complete] = $this->drainProcessPipes($pipes[1], $pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = $this->finishProcess($process, !$complete);

        if (!$complete) {
            return [$status === 0 ? 1 : $status, ''];
        }

        return [$status, trim($output)];
    }

    /**
     * Reads both subprocess channels concurrently with fixed cumulative memory bounds
     *
     * @param resource $standardOutput Git standard-output pipe.
     * @param resource $standardError  Git standard-error pipe.
     *
     * @return array{string, string, bool}
     */
    private function drainProcessPipes($standardOutput, $standardError): array
    {
        $output = '';
        $error = '';
        $complete = true;
        $open = [$standardOutput, $standardError];
        $started = hrtime(true);
        $totalDeadline = $started + (max(0, $this->processTotalTimeout) * 1000000);
        $activityDeadline = $started + (max(0, $this->processIoTimeout) * 1000000);

        while ($open !== []) {
            $now = hrtime(true);
            $remaining = min($totalDeadline, $activityDeadline) - $now;

            if ($remaining <= 0) {
                return [$output, $error, false];
            }

            $read = $open;
            $write = null;
            $except = null;
            $remainingMicroseconds = intdiv($remaining + 999, 1000);
            $seconds = intdiv($remainingMicroseconds, 1000000);
            $microseconds = $remainingMicroseconds % 1000000;
            $selected = @stream_select($read, $write, $except, $seconds, $microseconds);
            $complete = $complete && is_int($selected) && $selected > 0;
            $read = $selected === false || $selected === 0 ? [] : $read;
            $open = $selected === false || $selected === 0 ? [] : $open;

            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                $complete = $complete && is_string($chunk);
                $chunk = is_string($chunk) ? $chunk : '';

                if ($chunk !== '') {
                    $activityDeadline = hrtime(true) + (max(0, $this->processIoTimeout) * 1000000);

                    if ($stream === $standardOutput) {
                        [$output, $withinBound] = $this->appendBounded(
                            $output,
                            $chunk,
                            self::MAX_GIT_OUTPUT_BYTES
                        );
                    } else {
                        [$error, $withinBound] = $this->appendBounded(
                            $error,
                            $chunk,
                            self::MAX_GIT_ERROR_BYTES
                        );
                    }

                    if (!$withinBound) {
                        return [$output, $error, false];
                    }
                }

                if (feof($stream)) {
                    $open = array_values(array_filter(
                        $open,
                        static fn ($candidate): bool => $candidate !== $stream
                    ));
                }
            }
        }

        return [$output, $error, $complete];
    }

    /**
     * Returns one provider status after bounded TERM then KILL escalation when required
     *
     * @param resource $process Git provider process.
     */
    private function finishProcess($process, bool $terminate): int
    {
        if ($terminate) {
            @proc_terminate($process);
        }

        $status = $this->waitForExit($process);

        if ($status === null) {
            @proc_terminate($process, 9);
            $status = $this->waitForExit($process);
        }

        if ($status === null) {
            return 1;
        }

        $closed = proc_close($process);

        return $closed >= 0 ? $closed : max(1, $status);
    }

    /**
     * Returns provider exit status observed within one fixed nonblocking interval
     *
     * @param resource $process Git provider process.
     */
    private function waitForExit($process): ?int
    {
        $deadline = hrtime(true) + ($this->processShutdownTimeout * 1000000);

        do {
            $status = proc_get_status($process);

            if (!$status['running']) {
                return $status['exitcode'];
            }

            usleep(1000);
        } while (hrtime(true) < $deadline);

        return null;
    }

    /**
     * Appends at most one byte beyond a cumulative channel bound while the caller keeps draining
     *
     * @return array{string, bool}
     */
    private function appendBounded(string $contents, string $chunk, int $limit): array
    {
        $remaining = max(0, $limit + 1 - strlen($contents));

        if ($remaining > 0) {
            $contents .= substr($chunk, 0, $remaining);
        }

        return [$contents, strlen($contents) <= $limit && strlen($chunk) <= $remaining];
    }

    /**
     * Returns and records a failed Git resolution
     */
    private function failed(): BaselineTagResolutionResult
    {
        $this->record(ReleaseBoundaryOutcome::FAILURE);

        return BaselineTagResolutionResult::stopped(ReleaseBoundaryOutcome::FAILURE);
    }

    /**
     * Records one Git reference resolution outcome
     */
    private function record(ReleaseBoundaryOutcome $outcome): void
    {
        ($this->record)(ReleaseEffect::GIT_RESOLVE_REF, $outcome);
    }
}
