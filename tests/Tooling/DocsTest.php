<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DocsTest extends UnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fight-common-docs-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/bin', 0777, true);
        mkdir($this->directory.'/site', 0777, true);
        file_put_contents($this->directory.'/site/stale-output', 'remove only this output');

        copy(dirname(__DIR__, 2).'/bin/docs', $this->directory.'/bin/docs');
        chmod($this->directory.'/bin/docs', 0755);

        file_put_contents($this->directory.'/docker', <<<'BASH'
#!/usr/bin/env bash
set -eu

printf '%s\n' "$*" >> "${FAKE_DOCKER_LOG}"

if [[ "${FAKE_DOCKER_STATUS:-0}" != '0' ]]; then
    exit "${FAKE_DOCKER_STATUS}"
fi

if [[ -n "${FAKE_DOCKER_FAIL_COMMAND:-}" && " ${*} " == *" ${FAKE_DOCKER_FAIL_COMMAND} "* ]]; then
    exit "${FAKE_DOCKER_FAIL_STATUS}"
fi

if [[ "${1:-}" == 'container' && "${2:-}" == 'run' && " ${*} " == *' mkdocs build --strict --site-dir site '* ]]; then
    mkdir -p site
    printf '%s\n' 'generated documentation' > site/generated-output
fi
BASH
        );
        chmod($this->directory.'/docker', 0755);

        file_put_contents($this->directory.'/id', <<<'BASH'
#!/usr/bin/env bash
set -eu

if [[ "${1:-}" == '-u' ]]; then
    echo 501
else
    echo 20
fi
BASH
        );
        chmod($this->directory.'/id', 0755);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    public function test_that_preview_build_and_validate_use_the_pinned_docs_runtime_and_reject_unsupported_arguments(): void
    {
        $unsupported = new Process(
            ['bash', 'bin/docs', 'unsupported'],
            $this->directory,
            ['DOCKER_BIN' => $this->directory.'/missing-docker'],
            null,
            20,
        );
        $unsupported->run();

        self::assertSame(2, $unsupported->getExitCode());
        self::assertSame("Usage: ./bin/docs {preview|build|validate}\n", $unsupported->getErrorOutput());

        $preview = $this->runDocs('preview');
        $build = $this->runDocs('build');
        $validate = $this->runDocs('validate');

        self::assertSame(0, $preview->getExitCode(), $preview->getErrorOutput());
        self::assertSame(0, $build->getExitCode(), $build->getErrorOutput());
        self::assertSame(0, $validate->getExitCode(), $validate->getErrorOutput());
        self::assertFileDoesNotExist($this->directory.'/site/stale-output');
        self::assertSame("generated documentation\n", file_get_contents($this->directory.'/site/generated-output'));

        $log = (string) file_get_contents($this->directory.'/docker.log');
        self::assertSame(
            3,
            substr_count($log, 'build -t fight-common-docs-python-3.13.7 ./etc/docker/python/'),
        );
        self::assertStringContainsString(
            'container run --rm -p 127.0.0.1:8000:8000 -v '.$this->directory.':/app:delegated -w /app --user 501:20 '
            .'fight-common-docs-python-3.13.7 mkdocs serve --dev-addr 0.0.0.0:8000',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python scripts/generate_identity_assets.py --check',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python scripts/validate_identity_assets.py docs/assets/identity',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python tests/Tooling/test_identity_assets.py',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python scripts/validate_readme.py .',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python tests/Tooling/test_readme_validator.py',
            $log,
        );
        self::assertStringContainsString(
            'container run --rm -v '.$this->directory.':/app:delegated -w /app --user 501:20 '
            .'fight-common-docs-python-3.13.7 mkdocs build --strict --site-dir site',
            $log,
        );
        preg_match_all('/^container run .*$/m', $log, $containerRuns);
        self::assertStringContainsString(
            'container run --rm -v '.$this->directory.':/app:delegated -w /app --user 501:20 '
            .'fight-common-docs-python-3.13.7 python scripts/validate_docs_artifact.py site',
            $log,
        );
        self::assertStringContainsString(
            'container run --rm -v '.$this->directory.':/app:delegated -w /app --user 501:20 '
            .'fight-common-docs-python-3.13.7 python scripts/validate_docs_workflow.py .github/workflows/docs.yml',
            $log,
        );
        self::assertStringContainsString(
            'container run --rm -v '.$this->directory.':/app:delegated -w /app --user 501:20 '
            .'fight-common-docs-python-3.13.7 python tests/Tooling/test_docs_workflow_validator.py',
            $log,
        );
        self::assertTrue(
            strpos($log, 'python scripts/validate_docs_workflow.py .github/workflows/docs.yml')
            < strpos($log, 'python tests/Tooling/test_docs_workflow_validator.py'),
        );
        self::assertTrue(
            strpos($log, 'python scripts/validate_readme.py .')
            < strpos($log, 'python tests/Tooling/test_readme_validator.py'),
        );
        self::assertTrue(
            strpos($log, 'python tests/Tooling/test_readme_validator.py')
            < strrpos($log, 'mkdocs build --strict --site-dir site'),
        );
        self::assertCount(11, $containerRuns[0]);
        foreach ($containerRuns[0] as $containerRun) {
            self::assertStringNotContainsString('-it', $containerRun);
            self::assertStringNotContainsString(' -i ', $containerRun);
            self::assertStringNotContainsString(' -t ', $containerRun);
        }
    }

    public function test_that_docker_failures_are_propagated_before_deleting_site_output(): void
    {
        $process = $this->runDocs('build', 37);

        self::assertSame(37, $process->getExitCode());
        self::assertFileExists($this->directory.'/site/stale-output');
    }

    public function test_that_validate_propagates_the_artifact_validator_failure_after_the_strict_build(): void
    {
        $process = $this->runDocs('validate', 0, 'python scripts/validate_docs_artifact.py site', 53);

        self::assertSame(53, $process->getExitCode());
        self::assertFileDoesNotExist($this->directory.'/site/stale-output');
        self::assertFileExists($this->directory.'/site/generated-output');

        $log = (string) file_get_contents($this->directory.'/docker.log');
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 mkdocs build --strict --site-dir site',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python scripts/validate_docs_artifact.py site',
            $log,
        );
    }

    public function test_that_validate_propagates_readme_fixture_failures_before_the_strict_build(): void
    {
        $process = $this->runDocs('validate', 0, 'python tests/Tooling/test_readme_validator.py', 47);

        self::assertSame(47, $process->getExitCode());

        $log = (string) file_get_contents($this->directory.'/docker.log');
        self::assertStringContainsString('python scripts/validate_readme.py .', $log);
        self::assertStringContainsString('python tests/Tooling/test_readme_validator.py', $log);
        self::assertStringNotContainsString('mkdocs build --strict --site-dir site', $log);
    }

    public function test_that_validate_propagates_readme_validator_failures_before_its_fixtures(): void
    {
        $process = $this->runDocs('validate', 0, 'python scripts/validate_readme.py .', 43);

        self::assertSame(43, $process->getExitCode());

        $log = (string) file_get_contents($this->directory.'/docker.log');
        self::assertStringContainsString('python scripts/validate_readme.py .', $log);
        self::assertStringNotContainsString('python tests/Tooling/test_readme_validator.py', $log);
        self::assertStringNotContainsString('mkdocs build --strict --site-dir site', $log);
    }

    public function test_that_validate_stops_before_artifact_validation_when_the_strict_build_fails(): void
    {
        $process = $this->runDocs('validate', 0, 'mkdocs build --strict --site-dir site', 41);

        self::assertSame(41, $process->getExitCode());

        $log = (string) file_get_contents($this->directory.'/docker.log');
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 mkdocs build --strict --site-dir site',
            $log,
        );
        self::assertStringNotContainsString('python scripts/validate_docs_artifact.py site', $log);
    }

    public function test_that_validate_propagates_the_workflow_validator_failure_after_artifact_validation(): void
    {
        $process = $this->runDocs('validate', 0, 'python scripts/validate_docs_workflow.py .github/workflows/docs.yml', 67);

        self::assertSame(67, $process->getExitCode());

        $log = (string) file_get_contents($this->directory.'/docker.log');
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python scripts/validate_docs_artifact.py site',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python scripts/validate_docs_workflow.py .github/workflows/docs.yml',
            $log,
        );
        self::assertStringNotContainsString('python tests/Tooling/test_docs_workflow_validator.py', $log);
    }

    public function test_that_validate_propagates_workflow_fixture_failures_after_workflow_validation(): void
    {
        $process = $this->runDocs('validate', 0, 'python tests/Tooling/test_docs_workflow_validator.py', 71);

        self::assertSame(71, $process->getExitCode());

        $log = (string) file_get_contents($this->directory.'/docker.log');
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python scripts/validate_docs_workflow.py .github/workflows/docs.yml',
            $log,
        );
        self::assertStringContainsString(
            'fight-common-docs-python-3.13.7 python tests/Tooling/test_docs_workflow_validator.py',
            $log,
        );
    }

    private function runDocs(
        string $command,
        int $dockerStatus = 0,
        string $failingCommand = '',
        int $failingCommandStatus = 0,
    ): Process
    {
        $process = new Process(
            ['bash', 'bin/docs', $command],
            $this->directory,
            [
                'DOCKER_BIN' => $this->directory.'/docker',
                'FAKE_DOCKER_LOG' => $this->directory.'/docker.log',
                'FAKE_DOCKER_STATUS' => (string) $dockerStatus,
                'FAKE_DOCKER_FAIL_COMMAND' => $failingCommand,
                'FAKE_DOCKER_FAIL_STATUS' => (string) $failingCommandStatus,
                'ID_BIN' => $this->directory.'/id',
            ],
            null,
            20,
        );
        $process->run();

        return $process;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
