<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversNothing]
/**
 * Class PlanningPortfolioTest
 *
 * Covers the repository planning portfolio validator.
 */
final class PlanningPortfolioTest extends UnitTestCase
{
    private string $directory;

    /**
     * Creates an isolated planning portfolio and Git test double
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fight-common-planning-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/bin', 0777, true);
        mkdir($this->directory.'/planning/epics', 0777, true);
        mkdir($this->directory.'/planning/specs', 0777, true);
        mkdir($this->directory.'/planning/tickets', 0777, true);
        mkdir($this->directory.'/scripts', 0777, true);

        copy(dirname(__DIR__, 2).'/scripts/planning_portfolio.py', $this->directory.'/scripts/planning_portfolio.py');
        file_put_contents($this->directory.'/bin/git', <<<'BASH'
#!/usr/bin/env bash
set -eu

printf '%s\n' "$@" > "${PLANNING_GIT_LOG}"
exit "${PLANNING_GIT_STATUS:-0}"
BASH
        );
        chmod($this->directory.'/bin/git', 0755);
    }

    /**
     * Removes the isolated planning portfolio
     */
    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory($this->directory, 'fight-common-planning-');

        parent::tearDown();
    }

    /**
     * Proves planning fixture cleanup never follows a directory symbolic link
     */
    public function test_that_fixture_cleanup_unlinks_directory_symlinks_without_removing_their_targets(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $outsideDirectory = sys_get_temp_dir().'/fight-common-planning-outside-'.$suffix;
        $outsideSentinel = $outsideDirectory.'/sentinel';
        mkdir($outsideDirectory);
        file_put_contents($outsideSentinel, 'must survive cleanup');
        symlink($outsideDirectory, $this->directory.'/outside');

        try {
            $this->removeTemporaryDirectory($this->directory, 'fight-common-planning-');

            self::assertDirectoryDoesNotExist($this->directory);
            self::assertFileExists($outsideSentinel);
            self::assertSame('must survive cleanup', file_get_contents($outsideSentinel));
        } finally {
            if (!is_dir($this->directory)) {
                mkdir($this->directory);
            }

            $this->removeTemporaryDirectory($outsideDirectory, 'fight-common-planning-outside-');
        }
    }

    /**
     * Covers the repository-scoped Git ownership exception
     */
    public function test_that_git_ignore_check_trusts_only_the_resolved_repository_root(): void
    {
        $process = $this->validatePlanning();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame(
            implode("\n", [
                '-c',
                'safe.directory='.realpath($this->directory),
                'check-ignore',
                '-q',
                '.runs/planning-check',
                ''
            ]),
            file_get_contents($this->directory.'/git.log')
        );
    }

    /**
     * Covers fail-closed validation when the runs directory is not ignored
     */
    public function test_that_a_non_ignored_runs_directory_still_fails_planning_validation(): void
    {
        $process = $this->validatePlanning(1);

        self::assertSame(1, $process->getExitCode());
        self::assertSame(
            "Planning validation failed:\n- .runs/ must be gitignored\n",
            $process->getOutput()
        );
    }

    /**
     * Runs the planning validator with a controlled Git result
     */
    private function validatePlanning(int $gitStatus = 0): Process
    {
        $process = new Process(
            ['python3', 'scripts/planning_portfolio.py'],
            $this->directory,
            [
                'PATH'                => $this->directory.'/bin:'.(getenv('PATH') ?: ''),
                'PLANNING_GIT_LOG'    => $this->directory.'/git.log',
                'PLANNING_GIT_STATUS' => (string) $gitStatus
            ],
            null,
            20
        );
        $process->run();

        return $process;
    }
}
