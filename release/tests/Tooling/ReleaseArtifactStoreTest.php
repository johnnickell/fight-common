<?php

declare(strict_types=1);

namespace Fight\Test\Release\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Covers the descriptor-relative release artifact storage helper. */
#[CoversNothing]
final class ReleaseArtifactStoreTest extends UnitTestCase
{
    /**
     * Covers no-follow resolution of the supplied runs root and every ancestor.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_rejects_symlinked_runs_roots_and_root_ancestors(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-resolve-root-'.bin2hex(random_bytes(8));
        $real = $fixture.'/real';
        $outside = $fixture.'/outside';
        $rootLink = $fixture.'/.runs';
        $ancestorLink = $fixture.'/repository';
        $nestedLink = $real.'/.runs/linked-output';
        $rootFile = $fixture.'/runs-file';
        $readOnlyRoot = $fixture.'/read-only-runs';
        mkdir($real.'/.runs/output', 0777, true);
        mkdir($outside);
        mkdir($readOnlyRoot);
        file_put_contents($rootFile, 'not-a-directory');
        symlink($outside, $rootLink);
        symlink($real, $ancestorLink);
        symlink($outside, $nestedLink);
        chmod($readOnlyRoot, 0555);

        try {
            self::assertSame(10, $this->resolve($rootLink, '')->getExitCode());
            self::assertSame(10, $this->resolve($ancestorLink.'/.runs', 'output')->getExitCode());
            self::assertSame(10, $this->resolve($real.'/.runs', 'linked-output')->getExitCode());
            self::assertSame(10, $this->resolve($rootFile, '')->getExitCode());
            self::assertSame(10, $this->resolve($readOnlyRoot, '')->getExitCode());
            self::assertSame(0, $this->resolve($real.'/.runs', 'output')->getExitCode());
        } finally {
            unlink($rootLink);
            unlink($ancestorLink);
            unlink($nestedLink);
            unlink($rootFile);
            chmod($readOnlyRoot, 0755);
            rmdir($readOnlyRoot);
            rmdir($real.'/.runs/output');
            rmdir($real.'/.runs');
            rmdir($real);
            rmdir($outside);
            rmdir($fixture);
        }
    }

    /**
     * Covers immutable creation below a held runs-root descriptor.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_creates_an_artifact_relative_to_the_runs_root_descriptor(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $output = $runs.'/plans/nested';
        mkdir($output, 0777, true);

        try {
            $process = $this->store($runs, 'plans/nested', 'artifact.json', 'release-plan');

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame('', $process->getOutput());
            self::assertSame('release-plan', file_get_contents($output.'/artifact.json'));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers an output parent retargeted after pathname validation but before helper traversal.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_never_follows_a_retargeted_parent_outside_the_runs_root(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-race-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $plans = $runs.'/plans';
        $output = $plans.'/output';
        $outside = $fixture.'/outside';
        mkdir($output, 0777, true);
        rename($output, $outside);
        symlink($outside, $output);

        try {
            $process = $this->store($runs, 'plans/output', 'escaped.json', 'must-not-escape');

            self::assertSame(20, $process->getExitCode());
            self::assertFileDoesNotExist($outside.'/escaped.json');
        } finally {
            unlink($output);
            rmdir($outside);
            rmdir($plans);
            rmdir($runs);
            rmdir($fixture);
        }
    }

    /**
     * Covers identical and different immutable-create collisions without replacing either winner.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_reports_regular_file_collisions_without_replacing_the_winner(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-collision-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        mkdir($runs, 0777, true);

        try {
            $created = $this->store($runs, '', 'artifact.json', 'winner');
            $identical = $this->store($runs, '', 'artifact.json', 'winner');
            $different = $this->store($runs, '', 'artifact.json', 'replacement');

            self::assertSame(0, $created->getExitCode());
            self::assertSame(10, $identical->getExitCode());
            self::assertSame(10, $different->getExitCode());
            self::assertSame('winner', file_get_contents($runs.'/artifact.json'));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers private retention of a short write before a clean retry.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_never_publishes_a_short_write_and_allows_a_clean_retry(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-short-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        mkdir($runs, 0777, true);

        try {
            $partial = $this->store($runs, '', 'artifact.json', 'release-plan', ['--write-limit=4']);

            self::assertSame(20, $partial->getExitCode());
            self::assertFileDoesNotExist($runs.'/artifact.json');

            $retry = $this->store($runs, '', 'artifact.json', 'release-plan');

            self::assertSame(0, $retry->getExitCode());
            self::assertSame('release-plan', file_get_contents($runs.'/artifact.json'));
            self::assertDirectoryExists($runs.'/.release-artifact-staging-v1');
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers framed input refusing truncation and digest disagreement before publication.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_rejects_truncated_and_wrong_digest_input_before_a_clean_retry(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-frame-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        mkdir($runs, 0777, true);

        try {
            $truncated = $this->storeFramed($runs, 'artifact.json', 'release', 12, hash('sha256', 'release-plan'));
            $wrongDigest = $this->storeFramed($runs, 'artifact.json', 'release-plan', 12, str_repeat('0', 64));
            $extraBytes = $this->storeFramed($runs, 'artifact.json', 'release-plan', 7, hash('sha256', 'release'));

            self::assertSame(20, $truncated->getExitCode());
            self::assertSame(20, $wrongDigest->getExitCode());
            self::assertSame(20, $extraBytes->getExitCode());
            self::assertFileDoesNotExist($runs.'/artifact.json');

            $retry = $this->storeFramed(
                $runs,
                'artifact.json',
                'release-plan',
                12,
                hash('sha256', 'release-plan')
            );

            self::assertSame(0, $retry->getExitCode(), $retry->getErrorOutput());
            self::assertSame('release-plan', file_get_contents($runs.'/artifact.json'));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers the bounded canonical ASCII decimal grammar for frame lengths and fault controls.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_rejects_noncanonical_or_unbounded_numeric_arguments_before_staging(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-numeric-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $contents = 'release-plan';
        $digest = hash('sha256', $contents);
        mkdir($runs, 0777, true);

        $invalidNumbers = [
            '',
            '١٢',
            '１２',
            '1٢',
            '+12',
            '-12',
            ' 12',
            '12 ',
            '012',
            '16777217',
            str_repeat('9', 256)
        ];

        try {
            foreach ($invalidNumbers as $invalidNumber) {
                $length = $this->storeFramedText($runs, 'artifact.json', $contents, $invalidNumber, $digest);
                $writeLimit = $this->storeFramedText(
                    $runs,
                    'artifact.json',
                    $contents,
                    (string) strlen($contents),
                    $digest,
                    ['--write-limit='.$invalidNumber]
                );

                self::assertSame(20, $length->getExitCode(), 'length accepted: '.bin2hex($invalidNumber));
                self::assertSame(20, $writeLimit->getExitCode(), 'write limit accepted: '.bin2hex($invalidNumber));
                self::assertFileDoesNotExist($runs.'/artifact.json');
                self::assertDirectoryDoesNotExist($runs.'/.release-artifact-staging-v1');
            }

            $overFrameWriteLimit = $this->storeFramedText(
                $runs,
                'artifact.json',
                $contents,
                (string) strlen($contents),
                $digest,
                ['--write-limit=13']
            );
            self::assertSame(20, $overFrameWriteLimit->getExitCode());
            self::assertFileDoesNotExist($runs.'/artifact.json');
            self::assertDirectoryDoesNotExist($runs.'/.release-artifact-staging-v1');

            $empty = $this->store($runs, '', 'empty.json', '');
            self::assertSame(0, $empty->getExitCode(), $empty->getErrorOutput());
            self::assertSame('', file_get_contents($runs.'/empty.json'));

            $retry = $this->store($runs, '', 'artifact.json', $contents);

            self::assertSame(0, $retry->getExitCode(), $retry->getErrorOutput());
            self::assertSame($contents, file_get_contents($runs.'/artifact.json'));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers an adversarial replacement of the visible staged name before failure return.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_failure_cleanup_never_deletes_an_adversarial_staging_replacement(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-replacement-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        mkdir($runs, 0777, true);

        try {
            $process = $this->store($runs, '', 'artifact.json', 'release-plan', [
                '--write-limit=4',
                '--replace-staged-on-failure'
            ]);

            self::assertSame(20, $process->getExitCode());
            self::assertFileDoesNotExist($runs.'/artifact.json');
            $stagingDirectory = $runs.'/.release-artifact-staging-v1';
            $stagedEntries = scandir($stagingDirectory);
            self::assertIsArray($stagedEntries);
            $stagedEntries = array_values(array_diff($stagedEntries, ['.', '..']));
            $staged = array_map(
                static fn (string $entry): string => $stagingDirectory.'/'.$entry,
                $stagedEntries
            );
            self::assertCount(2, $staged);
            self::assertContains('adversarial-replacement', array_map(file_get_contents(...), $staged));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers hard-link fallback cleanup and fail-closed substituted-name handling.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_hard_link_publication_removes_only_its_own_staged_name(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-store-link-'.bin2hex(random_bytes(8));
        $normalRuns = $fixture.'/normal/.runs';
        $adversarialRuns = $fixture.'/adversarial/.runs';
        mkdir($normalRuns, 0777, true);
        mkdir($adversarialRuns, 0777, true);

        try {
            $normal = $this->store($normalRuns, '', 'artifact.json', 'release-plan', [
                '--force-link-fallback'
            ]);

            self::assertSame(0, $normal->getExitCode(), $normal->getErrorOutput());
            self::assertSame('release-plan', file_get_contents($normalRuns.'/artifact.json'));
            self::assertDirectoryDoesNotExist($normalRuns.'/.release-artifact-staging-v1');

            $adversarial = $this->store($adversarialRuns, '', 'artifact.json', 'release-plan', [
                '--force-link-fallback',
                '--replace-staged-after-link'
            ]);

            self::assertSame(30, $adversarial->getExitCode());
            self::assertSame('release-plan', file_get_contents($adversarialRuns.'/artifact.json'));
            $stagingDirectory = $adversarialRuns.'/.release-artifact-staging-v1';
            $stagedNames = scandir($stagingDirectory);
            self::assertIsArray($stagedNames);
            $staged = array_map(
                static fn (string $name): string => $stagingDirectory.'/'.$name,
                array_values(array_diff($stagedNames, ['.', '..']))
            );
            self::assertContains('adversarial-replacement', array_map(file_get_contents(...), $staged));

            $retry = $this->store($adversarialRuns, '', 'artifact.json', 'release-plan', [
                '--force-link-fallback'
            ]);
            self::assertSame(10, $retry->getExitCode());
            self::assertSame('release-plan', file_get_contents($adversarialRuns.'/artifact.json'));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers atomic publication on the repository's Docker Desktop bind filesystem.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_atomically_publishes_on_the_repository_bind_filesystem(): void
    {
        $fixture = getcwd().'/.runs/release-store-bind-'.bin2hex(random_bytes(8));
        mkdir($fixture, 0777, true);

        try {
            $process = $this->store($fixture, '', 'artifact.json', 'release-plan');

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame('release-plan', file_get_contents($fixture.'/artifact.json'));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Covers descriptor-relative missing, regular-content, and no-follow final-entry reads.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_reads_only_regular_artifacts_without_following_symlinks(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-read-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $outside = $fixture.'/outside.json';
        mkdir($runs, 0777, true);
        file_put_contents($outside, 'release-plan');

        try {
            $missing = $this->read($runs, '', 'artifact.json');
            self::assertSame(10, $missing->getExitCode());
            self::assertSame('', $missing->getOutput());

            file_put_contents($runs.'/artifact.json', 'release-plan');
            $content = $this->read($runs, '', 'artifact.json');
            self::assertSame(0, $content->getExitCode(), $content->getErrorOutput());
            self::assertSame('release-plan', $content->getOutput());
            unlink($runs.'/artifact.json');

            symlink($outside, $runs.'/artifact.json');
            $link = $this->read($runs, '', 'artifact.json');
            self::assertSame(20, $link->getExitCode());
            self::assertSame('', $link->getOutput());
        } finally {
            if (is_link($runs.'/artifact.json')) {
                unlink($runs.'/artifact.json');
            }

            unlink($outside);
            rmdir($runs);
            rmdir($fixture);
        }
    }

    /**
     * Covers the shared 16 MiB read bound without loading an oversized artifact into transport memory.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_rejects_an_oversized_digest_named_artifact_without_mutation(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-release-read-bound-'.bin2hex(random_bytes(8));
        $runs = $fixture.'/.runs';
        $filename = str_repeat('a', 64).'.json';
        mkdir($runs, 0777, true);
        $stream = fopen($runs.'/'.$filename, 'wb');
        self::assertIsResource($stream);
        self::assertTrue(ftruncate($stream, (16 * 1024 * 1024) + 1));
        fclose($stream);

        try {
            $before = stat($runs.'/'.$filename);
            $process = $this->read($runs, '', $filename);
            $after = stat($runs.'/'.$filename);

            self::assertSame(20, $process->getExitCode());
            self::assertSame('', $process->getOutput());
            self::assertSame('', $process->getErrorOutput());
            self::assertIsArray($before);
            self::assertIsArray($after);
            self::assertSame($before['ino'], $after['ino']);
            self::assertSame($before['size'], $after['size']);
        } finally {
            unlink($runs.'/'.$filename);
            rmdir($runs);
            rmdir($fixture);
        }
    }

    /**
     * Covers every injected failure after final publication using the ambiguity status.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_post_publication_failures_never_report_ordinary_failure(): void
    {
        foreach (['fstat', 'fsync', 'cleanup', 'output'] as $failure) {
            $fixture = sys_get_temp_dir().'/fight-common-release-post-publish-'.bin2hex(random_bytes(8));
            $runs = $fixture.'/.runs';
            mkdir($runs, 0777, true);

            try {
                $process = $this->store($runs, '', 'artifact.json', 'release-plan', [
                    '--fail-after-publish='.$failure,
                    '--post-publish-final=exists'
                ]);

                self::assertSame(30, $process->getExitCode(), $failure);
                self::assertSame('release-plan', file_get_contents($runs.'/artifact.json'));
            } finally {
                new Filesystem()->remove($fixture);
            }
        }
    }

    /**
     * Covers final-state fault injection retaining publication uncertainty without a second injected failure.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_destructive_post_publication_final_states_require_independent_verification(): void
    {
        foreach (['missing', 'mismatch'] as $finalState) {
            $fixture = sys_get_temp_dir().'/fight-common-release-final-state-'.bin2hex(random_bytes(8));
            $runs = $fixture.'/.runs';
            mkdir($runs, 0777, true);

            try {
                $process = $this->store($runs, '', 'artifact.json', 'release-plan', [
                    '--post-publish-final='.$finalState
                ]);

                self::assertSame(30, $process->getExitCode(), $finalState);

                if ($finalState === 'missing') {
                    self::assertFileDoesNotExist($runs.'/artifact.json');
                } else {
                    self::assertSame(
                        '{"post_publish":"mismatch"}'.PHP_EOL,
                        file_get_contents($runs.'/artifact.json')
                    );
                }
            } finally {
                new Filesystem()->remove($fixture);
            }
        }
    }

    /**
     * Covers parent replacement after traversal for both create cleanup and reads.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_helper_rejects_a_parent_replaced_after_its_descriptor_is_held(): void
    {
        foreach (['write', 'read'] as $operation) {
            $fixture = sys_get_temp_dir().'/fight-common-release-held-parent-'.bin2hex(random_bytes(8));
            $runs = $fixture.'/.runs';
            $parent = $runs.'/plans/output';
            $outside = $fixture.'/outside';
            mkdir($parent, 0777, true);
            mkdir($outside);

            if ($operation === 'read') {
                file_put_contents($parent.'/artifact.json', 'inside');
                file_put_contents($outside.'/artifact.json', 'outside');
            }

            try {
                $options = ['--replace-parent='.$outside];
                if ($operation === 'write') {
                    $process = $this->store($runs, 'plans/output', 'artifact.json', 'release-plan', [
                        '--write-limit=4',
                        ...$options
                    ]);
                } else {
                    $process = $this->read($runs, 'plans/output', 'artifact.json', $options);
                }

                self::assertSame(20, $process->getExitCode());
                self::assertSame('', $process->getOutput());
                self::assertSame(
                    $operation === 'read' ? 'outside' : null,
                    file_exists($outside.'/artifact.json') ? file_get_contents($outside.'/artifact.json') : null
                );

                if ($operation === 'write') {
                    self::assertFileDoesNotExist($parent.'.held/artifact.json');
                }
            } finally {
                unlink($parent);

                foreach ([$parent.'.held/artifact.json', $outside.'/artifact.json'] as $artifact) {
                    if (file_exists($artifact)) {
                        unlink($artifact);
                    }
                }

                rmdir($parent.'.held');
                rmdir($outside);
                rmdir(dirname($parent));
                rmdir($runs);
                rmdir($fixture);
            }
        }
    }

    /**
     * Runs the storage helper with bytes supplied only over standard input.
     *
     * @param string       $runsRoot      Absolute held runs root.
     * @param string       $relativeParent Descriptor-relative parent.
     * @param string       $filename      Final path segment.
     * @param string       $contents      Artifact bytes.
     * @param list<string> $options       Controlled helper options.
     */
    private function store(
        string $runsRoot,
        string $relativeParent,
        string $filename,
        string $contents,
        array $options = []
    ): Process {
        $process = new Process([
            '/usr/bin/python3',
            dirname(__DIR__, 2).'/scripts/release_artifact_store.py',
            'write',
            $runsRoot,
            $relativeParent,
            $filename,
            (string) strlen($contents),
            hash('sha256', $contents),
            ...$options
        ], null, ['PATH' => '/usr/bin:/bin', 'LANG' => 'C'], $contents);
        $process->run();

        return $process;
    }

    /**
     * Runs the framed write protocol with explicit producer metadata.
     */
    private function storeFramed(
        string $runsRoot,
        string $filename,
        string $contents,
        int $expectedLength,
        string $expectedDigest
    ): Process {
        $process = new Process([
            '/usr/bin/python3',
            dirname(__DIR__, 2).'/scripts/release_artifact_store.py',
            'write',
            $runsRoot,
            '',
            $filename,
            (string) $expectedLength,
            $expectedDigest
        ], null, ['PATH' => '/usr/bin:/bin', 'LANG' => 'C'], $contents);
        $process->run();

        return $process;
    }

    /**
     * Runs the framed write protocol with an untrusted textual length and fault controls.
     *
     * @param list<string> $options Controlled helper options.
     */
    private function storeFramedText(
        string $runsRoot,
        string $filename,
        string $contents,
        string $expectedLength,
        string $expectedDigest,
        array $options = []
    ): Process {
        $process = new Process([
            '/usr/bin/python3',
            dirname(__DIR__, 2).'/scripts/release_artifact_store.py',
            'write',
            $runsRoot,
            '',
            $filename,
            $expectedLength,
            $expectedDigest,
            ...$options
        ], null, ['PATH' => '/usr/bin:/bin', 'LANG' => 'C'], $contents);
        $process->run();

        return $process;
    }

    /**
     * Runs the read protocol against one descriptor-relative artifact.
     *
     * @param string       $runsRoot      Absolute held runs root.
     * @param string       $relativeParent Descriptor-relative parent.
     * @param string       $filename      Final path segment.
     * @param list<string> $options       Controlled helper options.
     */
    private function read(
        string $runsRoot,
        string $relativeParent,
        string $filename,
        array $options = []
    ): Process {
        $process = new Process([
            '/usr/bin/python3',
            dirname(__DIR__, 2).'/scripts/release_artifact_store.py',
            'read',
            $runsRoot,
            $relativeParent,
            $filename,
            ...$options
        ], null, ['PATH' => '/usr/bin:/bin', 'LANG' => 'C']);
        $process->run();

        return $process;
    }

    /**
     * Resolves one output beneath a literal, descriptor-held runs root.
     */
    private function resolve(string $runsRoot, string $relativeParent): Process
    {
        $process = new Process([
            '/usr/bin/python3',
            dirname(__DIR__, 2).'/scripts/release_artifact_store.py',
            'resolve',
            $runsRoot,
            $relativeParent
        ], null, ['PATH' => '/usr/bin:/bin', 'LANG' => 'C']);
        $process->run();

        return $process;
    }
}
