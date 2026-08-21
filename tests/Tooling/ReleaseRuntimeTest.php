<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Fight\Common\Application\Release\MachineResult;
use Fight\Common\Application\Release\ReleaseResultFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
#[CoversNothing]
/**
 * Class ReleaseRuntimeTest
 *
 * Covers the public release wrapper's canonical-runtime handoff.
 */
final class ReleaseRuntimeTest extends UnitTestCase
{
    private const int CONFIGURED_CRASH_STATUS = 86;

    private const string CANONICAL_RUNTIME = 'fight-common-release-php-8.5-v1';

    private const string RUNTIME_MARKER = '/usr/local/share/fight-common/release-runtime-v1';

    private string $directory;

    /**
     * Creates isolated wrapper and runtime doubles
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fight-common-release-runtime-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/bin', 0777, true);
        mkdir($this->directory.'/scripts', 0777, true);
        mkdir($this->directory.'/tooling', 0777, true);
        copy(dirname(__DIR__, 2).'/bin/release', $this->directory.'/bin/release');
        chmod($this->directory.'/bin/release', 0755);

        file_put_contents($this->directory.'/scripts/release.php', <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        $exitCode = 0;
        $output = json_encode(
            [
                'schema_version' => 'fight-common.release-result/v1',
                'command' => 'inspect',
                'capability' => 'release_inspection',
                'status' => 'succeeded',
                'exit_class' => 'success',
                'exit_code' => $exitCode,
                'findings' => [['id' => 'release.test.observation', 'message' => 'Observed runtime isolation.']],
                'verified_postconditions' => [],
                'performed_effects' => [],
                'proposed_effects' => [],
                'next_action' => ['action' => 'finish_test_observation'],
                'environment' => getenv(),
                'arguments' => array_slice($_SERVER['argv'] ?? [], 1)
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        ).PHP_EOL;

        $resultOutput = (string) getenv('FIGHT_COMMON_RELEASE_RESULT_OUTPUT');
        $resultEvidence = (string) getenv('FIGHT_COMMON_RELEASE_RESULT_EVIDENCE');
        $resultNonce = (string) getenv('FIGHT_COMMON_RELEASE_RESULT_NONCE');
        file_put_contents($resultOutput, $output, LOCK_EX);
        file_put_contents(
            $resultEvidence,
            $resultNonce.PHP_EOL.$exitCode.PHP_EOL.hash('sha256', $output).PHP_EOL,
            LOCK_EX
        );
        echo $output;
        PHP_WRAP
        );

        $fakeDocker = <<<'BASH'
#!/usr/bin/env bash
set -eu

host_php=__HOST_PHP_BINARY__

printf '%s\n' "$*" >> "${FAKE_DOCKER_LOG}"

if [[ "${1:-}" == "build" && -n "${FAKE_BUILD_STATUS:-}" ]]; then
    printf '%s\n' 'synthetic Docker build failure' >&2
    exit "${FAKE_BUILD_STATUS}"
fi

if [[ "${1:-}" == "container" && "${2:-}" == "run" ]]; then
    cid_file=''
    for ((argument = 1; argument <= $#; argument++)); do
        if [[ "${!argument}" == '--cidfile' ]]; then
            cid_argument=$((argument + 1))
            cid_file="${!cid_argument}"
            break
        fi
    done

    if [[ -n "${cid_file}" && ( -z "${FAKE_CONTAINER_STATUS:-}" || "${FAKE_WRITE_CID_ON_FAILURE:-}" == '1' ) ]]; then
        printf '%064d\n' 0 > "${cid_file}"
    fi

    if [[ -n "${FAKE_CONTAINER_STATUS:-}" ]]; then
        printf '%s\n' 'synthetic Docker container run failure' >&2
        exit "${FAKE_CONTAINER_STATUS}"
    fi

    if [[ -n "${FAKE_INNER_STATUS:-}" ]]; then
        if [[ "${FAKE_WRITE_CRASH_MARKER:-}" == '1' ]]; then
            marker=''
            nonce=''

            for argument in "$@"; do
                case "${argument}" in
                    FIGHT_COMMON_RELEASE_CRASH_MARKER=*) marker="${argument#*=}" ;;
                    FIGHT_COMMON_RELEASE_CRASH_NONCE=*) nonce="${argument#*=}" ;;
                esac
            done

            if [[ -n "${marker}" && -n "${nonce}" ]]; then
                printf '%s\n' "${nonce}" > "${marker}"
            fi
        fi

        if [[ "${FAKE_AUTHENTICATE_RESULT:-}" == '1' ]]; then
            result_evidence=''
            result_output=''
            result_nonce=''

            for argument in "$@"; do
                case "${argument}" in
                    FIGHT_COMMON_RELEASE_RESULT_EVIDENCE=*) result_evidence="${argument#*=}" ;;
                    FIGHT_COMMON_RELEASE_RESULT_OUTPUT=*) result_output="${argument#*=}" ;;
                    FIGHT_COMMON_RELEASE_RESULT_NONCE=*) result_nonce="${argument#*=}" ;;
                esac
            done

            printf '%s' "${FAKE_RESULT_COPY:-${FAKE_INNER_STDOUT:-}}" > "${result_output}"
            result_digest="$(sha256sum "${result_output}")"
            printf '%s\n%s\n%s\n%s' "${result_nonce}" "${FAKE_EVIDENCE_STATUS:-${FAKE_INNER_STATUS}}" \
                "${FAKE_EVIDENCE_DIGEST:-${result_digest%% *}}" "${FAKE_EVIDENCE_EXTRA:-}" \
                > "${result_evidence}"
        fi

        printf '%s' "${FAKE_INNER_STDOUT:-}"
        printf '%s' "${FAKE_INNER_DIAGNOSTIC:-}" >&2
        exit "${FAKE_INNER_STATUS}"
    fi

    while [[ "${1:-}" != "/usr/bin/env" ]]; do
        shift
    done

    arguments=("$@")
    for ((argument = 0; argument < ${#arguments[@]}; argument++)); do
        if [[ "${arguments[${argument}]}" == '/usr/local/bin/php' ]]; then
            arguments[${argument}]="${host_php}"
        fi
    done

    exec "${arguments[@]}"
fi
BASH
        ;
        file_put_contents(
            $this->directory.'/docker',
            str_replace('__HOST_PHP_BINARY__', escapeshellarg(PHP_BINARY), $fakeDocker)
        );
        chmod($this->directory.'/docker', 0755);
    }

    /**
     * Removes isolated wrapper fixtures
     */
    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory($this->directory, 'fight-common-release-runtime-');

        parent::tearDown();
    }

    /**
     * Proves fixture cleanup never follows a directory symbolic link
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_fixture_cleanup_unlinks_directory_symlinks_without_removing_their_targets(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $outsideDirectory = sys_get_temp_dir().'/fight-common-release-runtime-outside-'.$suffix;
        $outsideSentinel = $outsideDirectory.'/sentinel';
        mkdir($outsideDirectory);
        file_put_contents($outsideSentinel, 'must survive cleanup');
        symlink($outsideDirectory, $this->directory.'/outside');

        try {
            $this->removeTemporaryDirectory($this->directory, 'fight-common-release-runtime-');

            self::assertDirectoryDoesNotExist($this->directory);
            self::assertFileExists($outsideSentinel);
            self::assertSame('must survive cleanup', file_get_contents($outsideSentinel));
        } finally {
            mkdir($this->directory);
            $this->removeTemporaryDirectory(
                $outsideDirectory,
                'fight-common-release-runtime-outside-'
            );
        }
    }

    /**
     * Covers fail-closed provisioning outside the private canonical runtime
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_wrapper_outside_the_image_owned_project_root_provisions_the_canonical_runtime(): void
    {
        foreach ([false, 'generic-container', self::CANONICAL_RUNTIME] as $sentinel) {
            $log = $this->directory.'/docker-'.($sentinel === false ? 'absent' : $sentinel).'.log';
            $process = new Process(
                ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
                $this->directory,
                [
                    'DOCKER_BIN'                            => $this->directory.'/docker',
                    'FAKE_CONTAINER_STATUS'                 => '43',
                    'FAKE_DOCKER_LOG'                       => $log,
                    'FIGHT_COMMON_RELEASE_INTERNAL_RUNTIME' => $sentinel
                ],
                null,
                20
            );
            $process->run();

            $this->assertRuntimeBootstrapFailure($process);
            self::assertStringContainsString('synthetic Docker container run failure', $process->getErrorOutput());
            $dockerLog = (string) file_get_contents($log);
            self::assertStringContainsString('build -t fight-common '.$this->directory.'/etc/docker/', $dockerLog);
            self::assertStringContainsString('container run --cidfile', $dockerLog);
            self::assertStringContainsString(' --rm ', $dockerLog);
            self::assertStringNotContainsString('FIGHT_COMMON_RELEASE_INTERNAL_RUNTIME', $dockerLog);
            self::assertStringContainsString('-v '.$this->directory.':'.$this->directory.':delegated', $dockerLog);
            self::assertStringContainsString('-w '.$this->directory, $dockerLog);
            self::assertStringContainsString('/usr/bin/env -i', $dockerLog);
            self::assertStringContainsString('HOME=/tmp/fight-common-release/home', $dockerLog);
            self::assertStringContainsString('COMPOSER_HOME=/tmp/fight-common-release/composer', $dockerLog);
            self::assertStringContainsString('GNUPGHOME=/tmp/fight-common-release/gnupg', $dockerLog);
            self::assertStringContainsString('GH_CONFIG_DIR=/tmp/fight-common-release/gh', $dockerLog);
            self::assertStringContainsString('GIT_CONFIG_GLOBAL=/dev/null', $dockerLog);
            self::assertStringContainsString('GIT_CONFIG_NOSYSTEM=1', $dockerLog);

            self::assertStringContainsString(
                sprintf(
                    '/usr/local/bin/php -d display_errors=stderr %s/%s',
                    $this->directory,
                    'scripts/release.php inspect --fixture=/fixture.json'
                ),
                $dockerLog
            );
        }
    }

    /**
     * Covers deterministic machine reporting when the canonical image cannot be built
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_docker_build_failure_returns_one_machine_result_and_preserves_diagnostics(): void
    {
        $log = $this->directory.'/docker-build-failure.log';
        $process = new Process(
            ['bash', 'bin/release', 'plan', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'        => $this->directory.'/docker',
                'FAKE_BUILD_STATUS' => '17',
                'FAKE_DOCKER_LOG'   => $log
            ],
            null,
            20
        );
        $process->run();

        $this->assertRuntimeBootstrapFailure($process, 'plan');
        self::assertSame("synthetic Docker build failure\n", $process->getErrorOutput());
        self::assertSame(
            'build -t fight-common '.$this->directory."/etc/docker/\n",
            file_get_contents($log)
        );
    }

    /**
     * Covers fail-closed bootstrap reporting when private run allocation fails
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_runtime_directory_allocation_failure_returns_one_bootstrap_result(): void
    {
        $this->installTool('mktemp', "#!/usr/bin/env bash\nexit 73\n");
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'      => $this->directory.'/docker',
                'FAKE_DOCKER_LOG' => $this->directory.'/docker-mktemp-failure.log',
                'PATH'            => $this->directory.'/tooling:/usr/bin:/bin'
            ],
            null,
            20
        );
        $process->run();

        $this->assertRuntimeBootstrapFailure($process);
        self::assertSame('', $process->getErrorOutput());
    }

    /**
     * Covers cleanup of a partially-created private run after entropy failure
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_entropy_failure_returns_one_bootstrap_result_without_leaking_the_runtime_directory(): void
    {
        $runtimeDirectory = sys_get_temp_dir().'/fight-common-release-run.'.bin2hex(random_bytes(8));
        $this->installTool(
            'mktemp',
            "#!/usr/bin/env bash\nmkdir \"\${FAKE_RUNTIME_DIRECTORY}\"\nprintf '%s\\n' \"\${FAKE_RUNTIME_DIRECTORY}\"\n"
        );
        $this->installTool('od', "#!/usr/bin/env bash\nexit 74\n");
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'             => $this->directory.'/docker',
                'FAKE_DOCKER_LOG'        => $this->directory.'/docker-entropy-failure.log',
                'FAKE_RUNTIME_DIRECTORY' => $runtimeDirectory,
                'PATH'                   => $this->directory.'/tooling:/usr/bin:/bin'
            ],
            null,
            20
        );
        $process->run();

        $this->assertRuntimeBootstrapFailure($process);
        self::assertSame('', $process->getErrorOutput());
        self::assertDirectoryDoesNotExist($runtimeDirectory);
    }

    /**
     * Covers exact exit preservation when best-effort EXIT cleanup reports failures
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_cleanup_failures_preserve_the_intended_machine_result_status(): void
    {
        $this->installTool(
            'rm',
            "#!/usr/bin/env bash\n/bin/rm \"\$@\"\nexit 92\n"
        );
        $this->installTool(
            'rmdir',
            "#!/usr/bin/env bash\n/bin/rmdir \"\$@\"\nexit 93\n"
        );
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'                => $this->directory.'/docker',
                'FAKE_CONTAINER_STATUS'     => '125',
                'FAKE_DOCKER_LOG'           => $this->directory.'/docker-cleanup-failure.log',
                'FAKE_WRITE_CID_ON_FAILURE' => '1',
                'PATH'                      => $this->directory.'/tooling:/usr/bin:/bin'
            ],
            null,
            20
        );
        $process->run();

        $this->assertRuntimeTermination($process);
        self::assertStringContainsString('synthetic Docker container run failure', $process->getErrorOutput());
        self::assertStringContainsString('could not remove runtime files', $process->getErrorOutput());
        self::assertStringContainsString('could not remove runtime directory', $process->getErrorOutput());
    }

    /**
     * Covers CID-authoritative classification for Docker reserved statuses
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_docker_reserved_statuses_use_a_valid_cid_as_runtime_start_proof(): void
    {
        foreach ([125, 126, 127] as $status) {
            foreach ([false, true] as $writeCid) {
                $process = new Process(
                    ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
                    $this->directory,
                    [
                        'DOCKER_BIN'                => $this->directory.'/docker',
                        'FAKE_CONTAINER_STATUS'     => (string) $status,
                        'FAKE_DOCKER_LOG'           => sprintf(
                            '%s/docker-cid-%d-%d.log',
                            $this->directory,
                            $status,
                            $writeCid
                        ),
                        'FAKE_WRITE_CID_ON_FAILURE' => $writeCid ? '1' : '0'
                    ],
                    null,
                    20
                );
                $process->run();

                if ($writeCid) {
                    $this->assertRuntimeTermination($process);
                } else {
                    $this->assertRuntimeBootstrapFailure($process);
                }
                self::assertStringContainsString(
                    'synthetic Docker container run failure',
                    $process->getErrorOutput()
                );
            }
        }
    }

    /**
     * Covers preservation of an inner nonzero machine result after the container starts
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_an_inner_nonzero_machine_result_is_not_wrapped_as_a_runtime_failure(): void
    {
        $machineResult = json_encode($this->governedFailure(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'               => $this->directory.'/docker',
                'FAKE_DOCKER_LOG'          => $this->directory.'/docker-inner-result.log',
                'FAKE_INNER_STATUS'        => '4',
                'FAKE_INNER_STDOUT'        => $machineResult,
                'FAKE_AUTHENTICATE_RESULT' => '1'
            ],
            null,
            20
        );
        $process->run();

        self::assertSame(4, $process->getExitCode());
        self::assertSame($machineResult, $process->getOutput());
        self::assertSame('', $process->getErrorOutput());
    }

    /**
     * Covers preservation of a deliberate inner crash as a non-JSON interruption
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_an_authenticated_configured_crash_is_not_wrapped_as_a_runtime_failure(): void
    {
        $process = new Process(
            ['bash', 'bin/release', 'plan', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'              => $this->directory.'/docker',
                'FAKE_DOCKER_LOG'         => $this->directory.'/docker-inner-crash.log',
                'FAKE_INNER_STATUS'       => (string) self::CONFIGURED_CRASH_STATUS,
                'FAKE_INNER_DIAGNOSTIC'   => "synthetic configured release crash\n",
                'FAKE_WRITE_CRASH_MARKER' => '1'
            ],
            null,
            20
        );
        $process->run();

        self::assertSame(self::CONFIGURED_CRASH_STATUS, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        self::assertSame("synthetic configured release crash\n", $process->getErrorOutput());
    }

    /**
     * Covers fail-closed attribution for ordinary inner-process failures.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_unattributed_inner_failures_return_one_post_start_termination_result(): void
    {
        foreach ([255, 137] as $status) {
            $process = new Process(
                ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
                $this->directory,
                [
                    'DOCKER_BIN'            => $this->directory.'/docker',
                    'FAKE_DOCKER_LOG'       => $this->directory.'/docker-inner-'.$status.'.log',
                    'FAKE_INNER_STATUS'     => (string) $status,
                    'FAKE_INNER_DIAGNOSTIC' => "synthetic inner failure {$status}\n"
                ],
                null,
                20
            );
            $process->run();

            $this->assertRuntimeTermination($process);
            self::assertSame("synthetic inner failure {$status}\n", $process->getErrorOutput());
        }
    }

    /**
     * Covers a PHP bootstrap fatal before the configured-crash boundary exists.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_missing_autoloader_returns_one_runtime_machine_result(): void
    {
        file_put_contents(
            $this->directory.'/scripts/release.php',
            "<?php\n\ndeclare(strict_types=1);\n\nrequire __DIR__.'/missing-autoload.php';\n"
        );
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'      => $this->directory.'/docker',
                'FAKE_DOCKER_LOG' => $this->directory.'/docker-missing-autoload.log'
            ],
            null,
            20
        );
        $process->run();

        $this->assertRuntimeTermination($process);
        self::assertStringContainsString('missing-autoload.php', $process->getErrorOutput());
    }

    /**
     * Covers rejection of caller-forged crash-channel inputs.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_caller_forged_crash_channel_values_cannot_authorize_an_inner_failure(): void
    {
        $forgedMarker = $this->directory.'/forged-crash-marker';
        file_put_contents($forgedMarker, "forged-nonce\n");
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'                        => $this->directory.'/docker',
                'FAKE_DOCKER_LOG'                   => $this->directory.'/docker-forged-crash.log',
                'FAKE_INNER_STATUS'                 => (string) self::CONFIGURED_CRASH_STATUS,
                'FIGHT_COMMON_RELEASE_CRASH_MARKER' => $forgedMarker,
                'FIGHT_COMMON_RELEASE_CRASH_NONCE'  => 'forged-nonce',
                'PHP_BIN'                           => 'true'
            ],
            null,
            20
        );
        $process->run();

        $this->assertRuntimeTermination($process);
        self::assertSame("forged-nonce\n", file_get_contents($forgedMarker));
    }

    /**
     * Covers rejection of malformed and non-versioned inner output.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_invalid_inner_output_returns_one_runtime_machine_result(): void
    {
        foreach (
            [
            "not-json\n",
            "{\"status\":\"policy_blocked\"}\n",
            json_encode($this->governedFailure(), JSON_THROW_ON_ERROR)."forged-suffix\n",
            "forged-prefix".json_encode($this->governedFailure(), JSON_THROW_ON_ERROR)."\n"
            ] as $output
        ) {
            $process = new Process(
                ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
                $this->directory,
                [
                    'DOCKER_BIN'        => $this->directory.'/docker',
                    'FAKE_DOCKER_LOG'   => $this->directory.'/docker-invalid-output.log',
                    'FAKE_INNER_STATUS' => '4',
                    'FAKE_INNER_STDOUT' => $output
                ],
                null,
                20
            );
            $process->run();

            $this->assertRuntimeTermination($process);
            self::assertStringContainsString(trim($output), $process->getErrorOutput());
        }
    }

    /**
     * Covers exact byte, status, and digest binding for authenticated normal results.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_inconsistent_normal_result_evidence_fails_closed(): void
    {
        $machineResult = json_encode($this->governedFailure(), JSON_THROW_ON_ERROR)."\n";
        $mutations = [
            ['FAKE_RESULT_COPY' => "different-output\n"],
            ['FAKE_EVIDENCE_STATUS' => '3'],
            ['FAKE_EVIDENCE_DIGEST' => str_repeat('0', 64)],
            ['FAKE_EVIDENCE_EXTRA' => "unexpected\n"]
        ];

        foreach ($mutations as $index => $mutation) {
            $process = new Process(
                ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
                $this->directory,
                [
                    'DOCKER_BIN'               => $this->directory.'/docker',
                    'FAKE_DOCKER_LOG'          => $this->directory.'/docker-evidence-'.$index.'.log',
                    'FAKE_INNER_STATUS'        => '4',
                    'FAKE_INNER_STDOUT'        => $machineResult,
                    'FAKE_AUTHENTICATE_RESULT' => '1',
                    ...$mutation
                ],
                null,
                20
            );
            $process->run();

            $this->assertRuntimeTermination($process);
            self::assertSame($machineResult, $process->getErrorOutput());
        }
    }

    /**
     * Covers exact environment isolation at the outer branch's PHP process boundary
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_outer_runtime_php_process_receives_only_the_release_allowlist(): void
    {
        $log = $this->directory.'/docker-environment.log';
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'        => $this->directory.'/docker',
                'FAKE_DOCKER_LOG'   => $log,
                'GH_TOKEN'          => 'synthetic-github-token-never-use',
                'GITHUB_TOKEN'      => 'synthetic-github-token-never-use',
                'COMPOSER_AUTH'     => 'synthetic-packagist-token-never-use',
                'PACKAGIST_TOKEN'   => 'synthetic-packagist-token-never-use',
                'SSH_AUTH_SOCK'     => '/synthetic/ssh-agent.sock',
                'GIT_ASKPASS'       => '/synthetic/git-askpass',
                'GPG_PASSPHRASE'    => 'synthetic-signing-passphrase-never-use',
                'SIGNING_KEY_FILE'  => '/synthetic/signing-key',
                'UNRELATED_AMBIENT' => 'synthetic-unrelated-value'
            ],
            null,
            20
        );
        $process->mustRun();

        $observation = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $environment = $observation['environment'];
        self::assertIsArray($environment);
        self::assertMatchesRegularExpression(
            '#\A/tmp/fight-common-release-run\.[A-Za-z0-9]+/configured-crash\z#',
            $environment['FIGHT_COMMON_RELEASE_CRASH_MARKER']
        );
        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{64}\z/',
            $environment['FIGHT_COMMON_RELEASE_CRASH_NONCE']
        );
        self::assertMatchesRegularExpression(
            '#\A/tmp/fight-common-release-run\.[A-Za-z0-9]+/result-evidence\z#',
            $environment['FIGHT_COMMON_RELEASE_RESULT_EVIDENCE']
        );
        self::assertMatchesRegularExpression(
            '#\A/tmp/fight-common-release-run\.[A-Za-z0-9]+/result-output\z#',
            $environment['FIGHT_COMMON_RELEASE_RESULT_OUTPUT']
        );
        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{64}\z/',
            $environment['FIGHT_COMMON_RELEASE_RESULT_NONCE']
        );
        unset(
            $environment['FIGHT_COMMON_RELEASE_CRASH_MARKER'],
            $environment['FIGHT_COMMON_RELEASE_CRASH_NONCE'],
            $environment['FIGHT_COMMON_RELEASE_RESULT_EVIDENCE'],
            $environment['FIGHT_COMMON_RELEASE_RESULT_OUTPUT'],
            $environment['FIGHT_COMMON_RELEASE_RESULT_NONCE']
        );

        self::assertSame([
            'HOME'                => '/tmp/fight-common-release/home',
            'COMPOSER_HOME'       => '/tmp/fight-common-release/composer',
            'GNUPGHOME'           => '/tmp/fight-common-release/gnupg',
            'GH_CONFIG_DIR'       => '/tmp/fight-common-release/gh',
            'GIT_CONFIG_GLOBAL'   => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'LANG'                => 'C.UTF-8',
            'LC_ALL'              => 'C.UTF-8',
            'PATH'                => '/usr/local/bin:/usr/bin:/bin'
        ], $environment);
        self::assertSame(['inspect', '--fixture=/fixture.json'], $observation['arguments']);
    }

    /**
     * Covers rejection of the former caller-forgeable runtime handoff
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_a_forged_sentinel_and_php_binary_cannot_bypass_canonical_provisioning(): void
    {
        $process = new Process(
            ['bash', 'bin/release', 'publish', '--fixture=/fixture.json'],
            $this->directory,
            [
                'DOCKER_BIN'                            => $this->directory.'/missing-docker',
                'FIGHT_COMMON_RELEASE_INTERNAL_RUNTIME' => self::CANONICAL_RUNTIME,
                'PHP_BIN'                               => 'true'
            ],
            null,
            20
        );
        $process->run();

        $this->assertRuntimeBootstrapFailure($process, 'publish');
        self::assertStringContainsString('missing-docker', $process->getErrorOutput());
    }

    /**
     * Covers the guarded canonical-runtime handoff
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_canonical_image_marker_is_root_owned_and_read_only(): void
    {
        $dockerfile = (string) file_get_contents(dirname(__DIR__, 2).'/etc/docker/Dockerfile');

        self::assertStringContainsString(self::RUNTIME_MARKER, $dockerfile);
        self::assertStringContainsString(self::CANONICAL_RUNTIME, $dockerfile);
        self::assertMatchesRegularExpression('/chmod 0444[^\n]*release-runtime-v1/', $dockerfile);
        self::assertMatchesRegularExpression('/chown root:root[^\n]*release-runtime-v1/', $dockerfile);
    }

    /**
     * Covers rejection before loading Composer on an incompatible runtime
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_the_image_marker_uses_fixed_php_and_ignores_caller_runtime_values(): void
    {
        $log = $this->directory.'/docker-fixed-php.log';
        $process = new Process(
            ['bash', 'bin/release', 'inspect', '--fixture=/fixture.json'],
            $this->directory,
            env: [
                'DOCKER_BIN'                            => $this->directory.'/docker',
                'FAKE_DOCKER_LOG'                       => $log,
                'FIGHT_COMMON_RELEASE_INTERNAL_RUNTIME' => self::CANONICAL_RUNTIME,
                'PHP_BIN'                               => 'true'
            ]
        );
        $process->mustRun();

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('fight-common.release-result/v1', $result['schema_version']);
        self::assertSame('succeeded', $result['status']);
        self::assertStringContainsString('/usr/local/bin/php', (string) file_get_contents($log));
        self::assertStringNotContainsString(' true ', (string) file_get_contents($log));
    }

    /**
     * Asserts the stable outer-runtime failure contract
     */
    private function assertRuntimeBootstrapFailure(Process $process, string $requestedCommand = 'inspect'): void
    {
        $exitCode = $process->getExitCode();
        $payload = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(70, $exitCode);
        self::assertIsArray($payload);
        self::assertSame(
            new ReleaseResultFactory()->runtimeFailure($requestedCommand)->payload,
            $payload
        );
        self::assertTrue(MachineResult::isValidPayload($payload, $exitCode));
    }

    /**
     * Asserts the stable result for an unauthenticated termination after runtime startup
     */
    private function assertRuntimeTermination(Process $process, string $requestedCommand = 'inspect'): void
    {
        $exitCode = $process->getExitCode();
        $payload = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(71, $exitCode);
        self::assertIsArray($payload);
        self::assertSame(
            new ReleaseResultFactory()->runtimeTermination($requestedCommand)->payload,
            $payload
        );
        self::assertTrue(MachineResult::isValidPayload($payload, $exitCode));
    }

    /** @return array<string, mixed> */
    private function governedFailure(): array
    {
        return [
            'schema_version'          => 'fight-common.release-result/v1',
            'command'                 => 'inspect',
            'capability'              => 'release_inspection',
            'status'                  => 'policy_blocked',
            'exit_class'              => 'failed',
            'exit_code'               => 4,
            'findings'                => [[
                'id'      => 'release.test.governed_failure',
                'message' => 'Synthetic governed failure.'
            ]],
            'verified_postconditions' => [],
            'performed_effects'       => [],
            'proposed_effects'        => [],
            'next_action'             => ['action' => 'repair_test_failure']
        ];
    }

    /**
     * Installs one executable fixture used to control a host tooling boundary
     */
    private function installTool(string $name, string $contents): void
    {
        $path = $this->directory.'/tooling/'.$name;
        file_put_contents($path, $contents);
        chmod($path, 0755);
    }
}

// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
