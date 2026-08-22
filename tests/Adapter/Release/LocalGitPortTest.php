<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release;

use Fight\Common\Adapter\Release\LocalGitPort;
use Fight\Common\Application\Release\Boundary\BaselineTagResolutionStatus;
use Fight\Common\Application\Release\Boundary\ReleaseBoundaryOutcome;
use Fight\Test\Common\TestCase\UnitTestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Process\Process;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class LocalGitPortTest
 *
 * Covers credential-free local baseline resolution.
 */
#[CoversClass(LocalGitPort::class)]
final class LocalGitPortTest extends UnitTestCase
{
    private string $repository;

    /**
     * Creates one isolated Git repository
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = sys_get_temp_dir().'/fight-common-local-git-'.bin2hex(random_bytes(8));
        mkdir($this->repository);
        $this->git(['init', '--quiet']);
    }

    /**
     * Removes the isolated repository
     */
    protected function tearDown(): void
    {
        $this->removeDirectory($this->repository);

        parent::tearDown();
    }

    /**
     * Covers resolved, missing, ambiguous, non-ancestor, and failed Git outcomes
     */
    public function test_that_local_git_classifies_current_baseline_truth(): void
    {
        $effects = [];
        $port = new LocalGitPort(
            $this->repository,
            static function ($effect, $outcome) use (&$effects): void {
                $effects[] = [$effect->value, $outcome->value];
            }
        );
        self::assertTrue($port->inspectRepository()->hasValue());
        $baseline = $this->commit('baseline');
        self::assertSame(
            BaselineTagResolutionStatus::MISSING,
            $port->resolveBaselineTag('missing', $baseline)->status
        );
        $this->git(['tag', 'lightweight']);
        self::assertSame(
            BaselineTagResolutionStatus::AMBIGUOUS,
            $port->resolveBaselineTag('lightweight', $baseline)->status
        );
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline'
        ]);
        $candidate = $this->commit('candidate');
        $resolved = $port->resolveBaselineTag('v1.2.3', $candidate);
        self::assertTrue($resolved->isResolved());
        self::assertSame($baseline, $resolved->peeledCommitOid);
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', '1.2.3', '-m', 'duplicate normalized baseline', $baseline
        ]);
        self::assertSame(
            BaselineTagResolutionStatus::DUPLICATE_NORMALIZED,
            $port->resolveBaselineTag('v1.2.3', $candidate)->status
        );
        $this->git(['tag', '-d', '1.2.3']);
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.4', '-m', 'candidate'
        ]);
        self::assertSame(
            BaselineTagResolutionStatus::NON_ANCESTOR,
            $port->resolveBaselineTag('v1.2.4', $baseline)->status
        );
        $blob = $this->git(['hash-object', '-w', 'release.txt']);
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'blobtag', '-m', 'blob', $blob
        ]);
        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $port->resolveBaselineTag('blobtag', $candidate)->outcome);
        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $port->resolveBaselineTag('v1.2.3', 'bad')->outcome);
        self::assertContains(['git.inspect_repository', 'success'], $effects);
        self::assertContains(['git.resolve_ref', 'failure'], $effects);

        $invalid = new LocalGitPort($this->repository.'/missing', static function (): void {
        });
        self::assertFalse($invalid->inspectRepository()->hasValue());
        self::assertSame(
            ReleaseBoundaryOutcome::FAILURE,
            $invalid->resolveBaselineTag('v1.2.3', $candidate)->outcome
        );

        $tagObject = $resolved->tagObjectOid;
        self::assertIsString($tagObject);
        unlink($this->repository.'/.git/objects/'.substr($tagObject, 0, 2).'/'.substr($tagObject, 2));
        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $port->resolveBaselineTag('v1.2.3', $candidate)->outcome);
    }

    /**
     * Proves mutable replacement objects cannot rewrite immutable candidate ancestry
     */
    public function test_that_local_git_ignores_replacement_objects_when_verifying_ancestry(): void
    {
        $baseline = $this->commit('baseline');
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline authority'
        ]);
        $this->git(['switch', '--orphan', 'unrelated']);
        $candidate = $this->commit('unrelated candidate');
        $this->git(['replace', '--graft', $candidate, $baseline]);
        $this->git(['merge-base', '--is-ancestor', $baseline, $candidate]);

        $port = new LocalGitPort($this->repository, static function (): void {
        });

        self::assertSame(
            BaselineTagResolutionStatus::NON_ANCESTOR,
            $port->resolveBaselineTag('v1.2.3', $candidate)->status
        );
    }

    /**
     * Proves a moved annotated tag cannot combine its prior object with the same peeled commit
     */
    public function test_that_local_git_rejects_a_tag_moved_between_object_resolution_and_peeling(): void
    {
        $baseline = $this->commit('baseline');
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'original authority'
        ]);
        $originalTagObject = $this->git(['rev-parse', 'refs/tags/v1.2.3']);
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'replacement-authority', '-m', 'replacement authority', $baseline
        ]);
        $replacementTagObject = $this->git(['rev-parse', 'refs/tags/replacement-authority']);
        self::assertNotSame($originalTagObject, $replacementTagObject);
        self::assertSame(
            $baseline,
            $this->git(['rev-parse', $replacementTagObject.'^{commit}'])
        );
        $effects = [];
        $port = new LocalGitPort(
            $this->repository,
            static function ($effect, $outcome) use (&$effects): void {
                $effects[] = [$effect->value, $outcome->value];
            },
            function (string $stage) use ($replacementTagObject): void {
                if ($stage === 'tag_object_resolved') {
                    $this->git(['update-ref', 'refs/tags/v1.2.3', $replacementTagObject]);
                }
            }
        );

        $resolution = $port->resolveBaselineTag('v1.2.3', $baseline);

        self::assertSame(BaselineTagResolutionStatus::MOVING, $resolution->status);
        self::assertFalse($resolution->isResolved());
        self::assertSame([['git.resolve_ref', 'success']], $effects);
    }

    /**
     * Proves a ref that disappears after otherwise valid resolution is classified as moving
     */
    public function test_that_local_git_rejects_a_ref_that_disappears_during_resolution(): void
    {
        $baseline = $this->commit('baseline');
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline authority'
        ]);
        $effects = [];
        $port = new LocalGitPort(
            $this->repository,
            static function ($effect, $outcome) use (&$effects): void {
                $effects[] = [$effect->value, $outcome->value];
            },
            function (string $stage): void {
                if ($stage === 'tag_object_resolved') {
                    file_put_contents($this->repository.'/.git/refs/tags/v1.2.3', "invalid\n");
                }
            }
        );

        $resolution = $port->resolveBaselineTag('v1.2.3', $baseline);

        self::assertSame(BaselineTagResolutionStatus::MOVING, $resolution->status);
        self::assertFalse($resolution->isResolved());
        self::assertSame([['git.resolve_ref', 'success']], $effects);
    }

    /**
     * Proves a provider failure during final exact-ref verification remains a failure
     */
    public function test_that_local_git_fails_when_final_ref_verification_cannot_run(): void
    {
        $baseline = $this->commit('baseline');
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline authority'
        ]);
        $effects = [];
        $port = new LocalGitPort(
            $this->repository,
            static function ($effect, $outcome) use (&$effects): void {
                $effects[] = [$effect->value, $outcome->value];
            },
            function (string $stage): void {
                if ($stage === 'candidate_ancestry_verified') {
                    rename($this->repository.'/.git', $this->repository.'/.git-held');
                }
            }
        );

        try {
            $resolution = $port->resolveBaselineTag('v1.2.3', $baseline);
        } finally {
            rename($this->repository.'/.git-held', $this->repository.'/.git');
        }

        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $resolution->outcome);
        self::assertFalse($resolution->isResolved());
        self::assertSame([['git.resolve_ref', 'failure']], $effects);
    }

    /**
     * Proves normalized tag authority must remain stable across resolution
     */
    public function test_that_local_git_rejects_a_normalized_tag_set_that_moves_during_resolution(): void
    {
        $baseline = $this->commit('baseline');
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline authority'
        ]);
        $port = new LocalGitPort(
            $this->repository,
            static function (): void {
            },
            function (string $stage) use ($baseline): void {
                if ($stage === 'normalized_tags_resolved') {
                    $this->git([
                        '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
                        'tag', '-a', '1.2.3', '-m', 'racing normalized authority', $baseline
                    ]);
                }
            }
        );

        $resolution = $port->resolveBaselineTag('v1.2.3', $baseline);

        self::assertSame(BaselineTagResolutionStatus::MOVING, $resolution->status);
        self::assertFalse($resolution->isResolved());
    }

    /**
     * Proves normalized-tag discovery drains output beyond the former single-read limit
     */
    public function test_that_local_git_drains_bounded_normalized_tag_output_before_classifying_duplicates(): void
    {
        $baseline = $this->commit('baseline');
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline authority'
        ]);
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', '1.2.3', '-m', 'duplicate normalized authority', $baseline
        ]);

        for ($index = 0; $index < 96; ++$index) {
            file_put_contents(
                sprintf('%s/.git/refs/tags/filler-%03d-%s', $this->repository, $index, str_repeat('x', 48)),
                $baseline.PHP_EOL
            );
        }

        $port = new LocalGitPort($this->repository, static function (): void {
        });

        self::assertSame(
            BaselineTagResolutionStatus::DUPLICATE_NORMALIZED,
            $port->resolveBaselineTag('v1.2.3', $baseline)->status
        );
    }

    /**
     * Proves normalized-tag output beyond the cumulative bound fails deterministically
     */
    public function test_that_local_git_rejects_oversized_normalized_tag_output(): void
    {
        $baseline = $this->commit('baseline');
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'tag', '-a', 'v1.2.3', '-m', 'baseline authority'
        ]);
        $packedReferences = "# pack-refs with: peeled fully-peeled sorted\n";

        for ($index = 0; $index < 1024; ++$index) {
            $packedReferences .= sprintf(
                "%s refs/tags/oversized-%04d-%s\n",
                $baseline,
                $index,
                str_repeat('x', 48)
            );
        }

        file_put_contents($this->repository.'/.git/packed-refs', $packedReferences);
        $effects = [];
        $port = new LocalGitPort(
            $this->repository,
            static function ($effect, $outcome) use (&$effects): void {
                $effects[] = [$effect->value, $outcome->value];
            }
        );

        self::assertSame(
            ReleaseBoundaryOutcome::FAILURE,
            $port->resolveBaselineTag('v1.2.3', $baseline)->outcome
        );
        self::assertSame([['git.resolve_ref', 'failure']], $effects);
    }

    /**
     * Proves regular output cannot extend one Git invocation beyond its total deadline
     */
    public function test_that_local_git_bounds_a_slow_trickle_provider_by_total_elapsed_time(): void
    {
        $provider = $this->repository.'/slow-trickle-git';
        file_put_contents($provider, <<<'SH'
        #!/bin/sh
        count=0
        while [ "$count" -lt 15 ]; do
            printf x
            sleep 0.02
            count=$((count + 1))
        done
        SH
        );
        chmod($provider, 0700);
        $port = new LocalGitPort(
            $this->repository,
            static function (): void {
            },
            null,
            $provider,
            50,
            50,
            80
        );
        $started = hrtime(true);

        $inspection = $port->inspectRepository();

        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $inspection->outcome);
        self::assertLessThan(500, (hrtime(true) - $started) / 1000000);

        $expired = new LocalGitPort(
            $this->repository,
            static function (): void {
            },
            null,
            $provider,
            50,
            50,
            0
        );
        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $expired->inspectRepository()->outcome);
    }

    /**
     * Proves an overflowing provider that ignores graceful termination is killed within a fixed deadline
     */
    public function test_that_local_git_bounds_a_continuously_producing_stubborn_provider(): void
    {
        $provider = $this->repository.'/stubborn-git';
        file_put_contents($provider, <<<'SH'
        #!/bin/sh
        trap '' TERM
        while :; do
            printf '%08192d' 0
        done
        SH
        );
        chmod($provider, 0700);
        $port = new LocalGitPort(
            $this->repository,
            static function (): void {
            },
            null,
            $provider,
            50,
            0,
            100
        );
        $started = hrtime(true);

        $inspection = $port->inspectRepository();

        self::assertSame(ReleaseBoundaryOutcome::FAILURE, $inspection->outcome);
        self::assertLessThan(1000, (hrtime(true) - $started) / 1000000);
    }

    /**
     * Proves a provider failure in either normalized-tag snapshot remains a failure
     */
    public function test_that_local_git_fails_when_normalized_tag_authority_cannot_be_snapshotted(): void
    {
        foreach (['tag_object_resolved', 'exact_tag_revalidated'] as $failedStage) {
            $repository = sys_get_temp_dir().'/fight-common-local-git-snapshot-'.bin2hex(random_bytes(8));
            mkdir($repository);
            $original = $this->repository;
            $this->repository = $repository;

            try {
                $this->git(['init', '--quiet']);
                $baseline = $this->commit('baseline');
                $this->git([
                    '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
                    'tag', '-a', 'v1.2.3', '-m', 'baseline authority'
                ]);
                $port = new LocalGitPort(
                    $repository,
                    static function (): void {
                    },
                    function (string $stage) use ($failedStage, $repository): void {
                        if ($stage === $failedStage) {
                            rename($repository.'/.git', $repository.'/.git-held');
                        }
                    }
                );

                try {
                    $resolution = $port->resolveBaselineTag('v1.2.3', $baseline);
                } finally {
                    rename($repository.'/.git-held', $repository.'/.git');
                }

                self::assertSame(ReleaseBoundaryOutcome::FAILURE, $resolution->outcome, $failedStage);
            } finally {
                $this->removeDirectory($repository);
                $this->repository = $original;
            }
        }
    }

    /**
     * Creates one repository commit
     */
    private function commit(string $contents): string
    {
        file_put_contents($this->repository.'/release.txt', $contents.PHP_EOL);
        $this->git(['add', 'release.txt']);
        $this->git([
            '-c', 'user.name=Fight Test', '-c', 'user.email=fight@example.test',
            'commit', '--quiet', '-m', $contents
        ]);

        return $this->git(['rev-parse', 'HEAD']);
    }

    /**
     * Runs one isolated Git command
     *
     * @param array $arguments Git argument vector.
     *
     * @phpstan-param list<string> $arguments
     */
    private function git(array $arguments): string
    {
        $process = new Process(['/usr/bin/git', ...$arguments], $this->repository, [
            'GIT_CONFIG_GLOBAL'   => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1'
        ]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * Removes one isolated directory tree
     */
    private function removeDirectory(string $directory): void
    {
        foreach (new FilesystemIterator($directory) as $path) {
            if ($path->isDir() && !$path->isLink()) {
                $this->removeDirectory($path->getPathname());
            } else {
                unlink($path->getPathname());
            }
        }

        rmdir($directory);
    }
}
