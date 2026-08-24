<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Common\Application\Scheduler\Exception\SchedulerException;
use Fight\Release\Adapter\DisposablePublicConsumer;
use Fight\Release\Application\Boundary\PublicConsumerProbeRejected;
use Fight\Release\Application\JSendEvidenceAuthority;
use Fight\Test\Common\TestCase\UnitTestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DisposablePublicConsumerTest
 */
#[CoversClass(DisposablePublicConsumer::class)]
#[CoversClass(PublicConsumerProbeRejected::class)]
#[CoversClass(JSendEvidenceAuthority::class)]
final class DisposablePublicConsumerTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves installed-package JSend evidence is authenticated before receipt composition.
     */
    public function test_that_the_installed_candidate_emits_authenticated_jsend_evidence(): void
    {
        $root = dirname(__DIR__, 3);
        $consumer = sys_get_temp_dir().'/fight-common-jsend-evidence-'.bin2hex(random_bytes(8));
        mkdir($consumer, 0777, true);

        try {
            $receipt = new DisposablePublicConsumer()->run(
                $root,
                $root.'/release/fixtures/PublicApiConsumer',
                $consumer
            );

            self::assertSame(
                [
                    'legacy' => [
                        'success'                      => [
                            'body'    => ['status' => 'success', 'data' => ['id' => 42]],
                            'status'  => 202,
                            'headers' => ['x-jsend' => ['legacy-success']],
                            'options' => 79,
                            'type'    => 'success'
                        ],
                        'success_null'                 => ['status' => 'success', 'data' => null],
                        'fail'                         => [
                            'body'    => ['status' => 'fail', 'data' => ['email' => 'invalid']],
                            'status'  => 422,
                            'headers' => ['x-jsend' => ['legacy-fail']],
                            'options' => 79,
                            'type'    => 'fail'
                        ],
                        'error'                        => [
                            'body'    => [
                                'status'  => 'error',
                                'message' => 'The bridge is out',
                                'data'    => ['request_id' => 'request-42'],
                                'code'    => 4102
                            ],
                            'status'  => 502,
                            'headers' => ['retry-after' => ['30']],
                            'options' => 79,
                            'type'    => 'error'
                        ],
                        'error_optional_fields_absent' => [
                            'status'  => 'error',
                            'message' => 'Optional fields are absent'
                        ],
                        'caller_selected_encoding'     => '{"status":"success","data":{"url":"https:\\/\\/example.com\\/path"}}',
                        'runtime_deprecations'         => []
                    ],
                    'typed'  => [
                        'available'        => true,
                        'single'           => ['status' => 'success', 'data' => ['id' => 42]],
                        'fail'             => ['status' => 'fail', 'data' => ['email' => 'invalid']],
                        'paginated'        => [
                            'status' => 'success',
                            'data'   => [
                                'page'          => 2,
                                'per_page'      => 2,
                                'total_pages'   => 3,
                                'total_records' => 5,
                                'records'       => [
                                    ['id' => 42, 'name' => 'Frodo'],
                                    ['id' => 43, 'name' => 'Samwise']
                                ]
                            ]
                        ],
                        'response'         => [
                            'body'         => '{"status":"error","message":"The bridge is out","data":{"request_id":"request-42"},"code":4102}',
                            'status'       => 502,
                            'headers'      => ['retry-after' => ['30']],
                            'content_type' => 'application/json'
                        ],
                        'encoding_option_79' => implode('', [
                            '{"status":"success","data":{"url":"https://example.com/path",',
                            '"tag":"\u003Csafe\u003E","quote":"\u0022","apostrophe":"\u0027",',
                            '"ampersand":"\u0026"}}'
                        ]),
                        'invalid_encoding' => 'JsonException'
                    ]
                ],
                $receipt['probe']['observations']['jsend']
            );
        } finally {
            new Filesystem()->remove($consumer);
        }
    }

    /**
     * Proves an attributed public probe through a copied Composer package outside the repository
     */
    public function test_that_a_disposable_external_consumer_receives_exact_package_and_probe_receipts(): void
    {
        $root = dirname(__DIR__, 3);
        $fixture = $root.'/release/fixtures/PublicApiConsumer';
        $consumer = sys_get_temp_dir().'/fight-common-public-consumer-'.bin2hex(random_bytes(8));
        mkdir($consumer, 0777, true);

        try {
            $receipt = new DisposablePublicConsumer()->run($root, $fixture, $consumer);
            $installedPackage = $consumer.'/vendor/johnnickell/fight-common';
            $lockBytes = file_get_contents($consumer.'/composer.lock');
            $probeBytes = file_get_contents($consumer.'/probe-receipt.json');

            self::assertIsString($lockBytes);
            self::assertIsString($probeBytes);
            $lock = json_decode($lockBytes, true, flags: JSON_THROW_ON_ERROR);
            $packages = array_column($lock['packages'], null, 'name');
            $resolved = $packages['johnnickell/fight-common'];
            self::assertFalse(str_starts_with(realpath($consumer) ?: '', realpath($root) ?: ''));
            self::assertFileExists($installedPackage.'/composer.json');
            self::assertFalse(is_link($installedPackage));
            self::assertSame(
                [
                    'schema_version'   => 'fight-common.disposable-public-consumer/v1',
                    'status'           => 'valid',
                    'classification'   => 'patch',
                    'findings'         => [
                        [
                            'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
                            'evidence_id' => 'fight-common.consumer.public-api-representative',
                            'attribution' => 'release/fixtures/PublicApiConsumer/public-api-probe.php',
                            'status'      => 'passed'
                        ],
                        [
                            'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-construction-passed',
                            'evidence_id' => 'fight-common.behavior.scheduler-legacy-construction',
                            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                            'status'      => 'passed'
                        ],
                        [
                            'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-command-passed',
                            'evidence_id' => 'fight-common.behavior.scheduler-legacy-command',
                            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                            'status'      => 'passed'
                        ],
                        [
                            'finding_id'  => 'release.compatibility.consumer.scheduler-portable-runner-passed',
                            'evidence_id' => 'fight-common.behavior.scheduler-portable-runner',
                            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                            'status'      => 'passed'
                        ],
                        [
                            'finding_id'  => 'release.compatibility.consumer.jsend-legacy-passed',
                            'evidence_id' => 'fight-common.behavior.jsend-legacy-response',
                            'attribution' => 'release/fixtures/PublicApiConsumer/jsend-probe.php',
                            'status'      => 'passed'
                        ],
                        [
                            'finding_id'  => 'release.compatibility.consumer.jsend-typed-passed',
                            'evidence_id' => 'fight-common.behavior.jsend-typed-response',
                            'attribution' => 'release/fixtures/PublicApiConsumer/jsend-probe.php',
                            'status'      => 'passed'
                        ]
                    ],
                    'candidate'        => [
                        'package'                => 'johnnickell/fight-common',
                        'composer_sha256'        => hash_file('sha256', $root.'/composer.json'),
                        'production_tree_sha256' => $this->productionTreeDigest($root)
                    ],
                    'resolved_package' => [
                        'package'                => 'johnnickell/fight-common',
                        'version'                => $resolved['version'],
                        'reference'              => $resolved['dist']['reference'],
                        'composer_sha256'        => hash_file('sha256', $installedPackage.'/composer.json'),
                        'production_tree_sha256' => $this->productionTreeDigest($installedPackage),
                        'installed_as'           => 'copy'
                    ],
                    'lock'             => [
                        'sha256'       => hash('sha256', $lockBytes),
                        'content_hash' => json_decode($lockBytes, true, flags: JSON_THROW_ON_ERROR)['content-hash']
                    ],
                    'probe'            => [
                        'sha256'       => hash('sha256', $probeBytes),
                        'observations' => [
                            'uuid'                 => '00000000-0000-0000-0000-000000000000',
                            'meta'                 => ['consumer' => 'disposable'],
                            'collection'           => ['alpha', 'beta'],
                            'runtime_deprecations' => [],
                            'scheduler'            => [
                                'construction_styles'      => [
                                    'two_argument',
                                    'positional_optional',
                                    'named_arguments'
                                ],
                                'callable_output'          => "scheduler callable\n",
                                'command_output'           => "scheduler command\nscheduler command\n",
                                'default_process_commands' => ['default-command'],
                                'factory_process_commands' => ['factory-command', 'false', 'false'],
                                'non_zero_failure'         => $this->schedulerNonZeroFailureObservation(),
                                'portable_process_runner'  => [
                                    'commands' => ['portable-command'],
                                    'output'   => "scheduler portable command\n"
                                ]
                            ],
                            'jsend'                => JSendEvidenceAuthority::observation(true)
                        ]
                    ]
                ],
                $receipt
            );
        } finally {
            new Filesystem()->remove($consumer);
        }
    }

    /**
     * Proves the installed probe observes normalized runtime deprecations raised during package autoload
     */
    public function test_that_the_installed_probe_captures_normalized_runtime_deprecations(): void
    {
        $root = dirname(__DIR__, 3);
        $workspace = sys_get_temp_dir().'/fight-common-runtime-deprecation-'.bin2hex(random_bytes(8));
        $candidate = $workspace.'/candidate';
        $consumer = $workspace.'/consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$candidate, $consumer]);
        $filesystem->copy($root.'/composer.json', $candidate.'/composer.json');
        $filesystem->mirror($root.'/src', $candidate.'/src');
        $filesystem->mirror($root.'/tests/TestCase', $candidate.'/tests/TestCase');

        $composer = json_decode(
            (string) file_get_contents($candidate.'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $composer['autoload']['files'][] = 'runtime-deprecation.php';
        $filesystem->dumpFile(
            $candidate.'/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
        );
        $filesystem->dumpFile(
            $candidate.'/runtime-deprecation.php',
            implode('', [
                "<?php\n\ndeclare(strict_types=1);\n\ntrigger_error(\n",
                "    'controlled public probe deprecation',\n    E_USER_DEPRECATED\n);\n"
            ])
        );

        try {
            $receipt = new DisposablePublicConsumer()->run(
                $candidate,
                $root.'/release/fixtures/PublicApiConsumer',
                $consumer
            );

            self::assertSame([[
                'severity' => 'E_USER_DEPRECATED',
                'message'  => 'controlled public probe deprecation'
            ]], $receipt['probe']['observations']['runtime_deprecations']);
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves the installed candidate receipt links each Scheduler behavior to one stable finding.
     */
    public function test_that_the_installed_candidate_emits_stable_scheduler_behavior_findings(): void
    {
        $root = dirname(__DIR__, 3);
        $consumer = sys_get_temp_dir().'/fight-common-scheduler-findings-'.bin2hex(random_bytes(8));
        mkdir($consumer, 0777, true);

        try {
            $receipt = new DisposablePublicConsumer()->run(
                $root,
                $root.'/release/fixtures/PublicApiConsumer',
                $consumer
            );

            self::assertSame(
                [
                    [
                        'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
                        'evidence_id' => 'fight-common.consumer.public-api-representative',
                        'attribution' => 'release/fixtures/PublicApiConsumer/public-api-probe.php',
                        'status'      => 'passed'
                    ],
                    [
                        'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-construction-passed',
                        'evidence_id' => 'fight-common.behavior.scheduler-legacy-construction',
                        'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                        'status'      => 'passed'
                    ],
                    [
                        'finding_id'  => 'release.compatibility.consumer.scheduler-legacy-command-passed',
                        'evidence_id' => 'fight-common.behavior.scheduler-legacy-command',
                        'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                        'status'      => 'passed'
                    ],
                    [
                        'finding_id'  => 'release.compatibility.consumer.scheduler-portable-runner-passed',
                        'evidence_id' => 'fight-common.behavior.scheduler-portable-runner',
                        'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
                        'status'      => 'passed'
                    ],
                    ...JSendEvidenceAuthority::findings(true)
                ],
                $receipt['findings']
            );
        } finally {
            new Filesystem()->remove($consumer);
        }
    }

    /**
     * Proves run refuses to describe a Composer path symlink as an installed copy.
     */
    public function test_that_run_rejects_a_symlinked_installed_candidate_before_emitting_a_copy_receipt(): void
    {
        $root = dirname(__DIR__, 3);
        $workspace = sys_get_temp_dir().'/fight-common-symlinked-consumer-'.bin2hex(random_bytes(8));
        $fixture = $workspace.'/fixture';
        $consumer = $workspace.'/consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$fixture, $consumer]);
        $filesystem->copy($root.'/release/fixtures/PublicApiConsumer/probe.php', $fixture.'/probe.php');
        $filesystem->copy(
            $root.'/release/fixtures/PublicApiConsumer/public-api-probe.php',
            $fixture.'/public-api-probe.php'
        );
        $this->copyJSendFixture($root, $fixture, $filesystem);

        $composer = json_decode(
            (string) file_get_contents($root.'/release/fixtures/PublicApiConsumer/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $composer['repositories'][0]['options']['symlink'] = true;
        $filesystem->dumpFile(
            $fixture.'/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
        );

        try {
            new DisposablePublicConsumer()->run($root, $fixture, $consumer);
            self::fail('A symlinked candidate package was described as an installed copy.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(
                'Installed candidate package is not an authenticated copy.',
                $runtimeException->getMessage()
            );
            self::assertTrue(is_link($consumer.'/vendor/johnnickell/fight-common'));
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves copied-package receipts include every production PSR-4 root and fail when one is absent.
     */
    public function test_that_production_digest_includes_and_requires_the_test_case_autoload_root(): void
    {
        $root = dirname(__DIR__, 3);
        $fixture = $root.'/release/fixtures/PublicApiConsumer';
        $workspace = sys_get_temp_dir().'/fight-common-production-digest-'.bin2hex(random_bytes(8));
        $candidate = $workspace.'/candidate';
        $mutatedConsumer = $workspace.'/mutated-consumer';
        $missingConsumer = $workspace.'/missing-consumer';
        $invalidConsumer = $workspace.'/invalid-consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$candidate, $mutatedConsumer, $missingConsumer, $invalidConsumer]);
        $filesystem->copy($root.'/composer.json', $candidate.'/composer.json');
        $filesystem->mirror($root.'/src', $candidate.'/src');
        $filesystem->mirror($root.'/tests/TestCase', $candidate.'/tests/TestCase');

        try {
            $mutation = $candidate.'/tests/TestCase/UnitTestCase.php';
            self::assertFileExists($mutation);
            file_put_contents($mutation, "\n// receipt mutation\n", FILE_APPEND);
            $receipt = new DisposablePublicConsumer()->run($candidate, $fixture, $mutatedConsumer);
            $expectedMutated = $this->productionTreeDigest($candidate);

            self::assertSame($expectedMutated, $receipt['candidate']['production_tree_sha256']);
            self::assertSame($expectedMutated, $receipt['resolved_package']['production_tree_sha256']);
            self::assertNotSame($this->productionTreeDigest($root), $expectedMutated);

            $filesystem->remove($candidate.'/tests/TestCase');
            try {
                new DisposablePublicConsumer()->run($candidate, $fixture, $missingConsumer);
                self::fail('A missing production PSR-4 root did not fail the copied-package receipt.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(
                    'Production autoload path is unavailable: tests/TestCase',
                    $runtimeException->getMessage()
                );
            }

            $composer = json_decode(
                (string) file_get_contents($candidate.'/composer.json'),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $composer['autoload']['psr-4']["Fight\\Test\\Common\\TestCase\\"] = '../outside';
            file_put_contents(
                $candidate.'/composer.json',
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
            );
            try {
                new DisposablePublicConsumer()->run($candidate, $fixture, $invalidConsumer);
                self::fail('A non-canonical production PSR-4 root did not fail the copied-package receipt.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(
                    'Production autoload path is not canonical: ../outside',
                    $runtimeException->getMessage()
                );
            }
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves malformed successful generic evidence fails before the Scheduler probe can run
     */
    public function test_that_malformed_generic_probe_evidence_stops_before_scheduler_execution(): void
    {
        $root = dirname(__DIR__, 3);
        $workspace = sys_get_temp_dir().'/fight-common-generic-evidence-'.bin2hex(random_bytes(8));
        $fixture = $workspace.'/fixture';
        $consumer = $workspace.'/consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$fixture, $consumer]);
        $filesystem->copy($root.'/release/fixtures/PublicApiConsumer/composer.json', $fixture.'/composer.json');
        $filesystem->dumpFile(
            $fixture.'/public-api-probe.php',
            "<?php\n\ndeclare(strict_types=1);\n\necho '{\"schema_version\":\"malformed/v1\"}';\n"
        );
        $filesystem->dumpFile(
            $fixture.'/probe.php',
            "<?php\n\ndeclare(strict_types=1);\n\nfile_put_contents(__DIR__.'/scheduler-ran', 'yes');\n"
        );
        $this->copyJSendFixture($root, $fixture, $filesystem);

        try {
            new DisposablePublicConsumer()->run($root, $fixture, $consumer);
            self::fail('Malformed representative public API evidence was accepted.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(
                'The representative public API probe evidence is invalid.',
                $runtimeException->getMessage()
            );
            self::assertFileDoesNotExist($consumer.'/scheduler-ran');
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves malformed successful JSend evidence fails before Scheduler execution and receipt composition.
     */
    public function test_that_malformed_jsend_probe_evidence_stops_before_scheduler_execution(): void
    {
        self::assertFalse(JSendEvidenceAuthority::isProbeReceipt([]));

        $root = dirname(__DIR__, 3);
        $workspace = sys_get_temp_dir().'/fight-common-jsend-envelope-'.bin2hex(random_bytes(8));
        $fixture = $workspace.'/fixture';
        $consumer = $workspace.'/consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$fixture, $consumer]);
        $filesystem->copy($root.'/release/fixtures/PublicApiConsumer/composer.json', $fixture.'/composer.json');
        $filesystem->copy(
            $root.'/release/fixtures/PublicApiConsumer/public-api-probe.php',
            $fixture.'/public-api-probe.php'
        );
        $filesystem->dumpFile(
            $fixture.'/probe.php',
            "<?php\n\ndeclare(strict_types=1);\n\nfile_put_contents(__DIR__.'/scheduler-ran', 'yes');\n"
        );
        $this->copyJSendFixture($root, $fixture, $filesystem);
        $jsendProbe = (string) file_get_contents($fixture.'/jsend-probe.php');
        $filesystem->dumpFile(
            $fixture.'/jsend-probe.php',
            str_replace('fight-common.jsend-probe/v1', 'fight-common.jsend-probe/v999', $jsendProbe)
        );

        try {
            new DisposablePublicConsumer()->run($root, $fixture, $consumer);
            self::fail('Malformed JSend evidence was accepted.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The JSend probe evidence is invalid.', $runtimeException->getMessage());
            self::assertFileDoesNotExist($consumer.'/scheduler-ran');
            self::assertFileDoesNotExist($consumer.'/probe-receipt.json');
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves zero-exit Scheduler evidence with missing or mutated schema is rejected before aggregation
     */
    public function test_that_zero_exit_scheduler_probe_requires_its_exact_schema_before_aggregation(): void
    {
        $root = dirname(__DIR__, 3);
        $workspace = sys_get_temp_dir().'/fight-common-scheduler-envelope-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $probe = (string) file_get_contents($root.'/release/fixtures/PublicApiConsumer/probe.php');
        $schema = "'schema_version' => 'fight-common.scheduler-probe/v1'";
        $mutations = [
            'missing' => "'untrusted_schema' => 'fight-common.scheduler-probe/v1'",
            'v999'    => "'schema_version' => 'fight-common.scheduler-probe/v999'"
        ];

        try {
            foreach ($mutations as $name => $replacement) {
                $fixture = $workspace.'/'.$name.'-fixture';
                $consumer = $workspace.'/'.$name.'-consumer';
                $filesystem->mkdir([$fixture, $consumer]);
                $filesystem->copy(
                    $root.'/release/fixtures/PublicApiConsumer/composer.json',
                    $fixture.'/composer.json'
                );
                $filesystem->copy(
                    $root.'/release/fixtures/PublicApiConsumer/public-api-probe.php',
                    $fixture.'/public-api-probe.php'
                );
                $filesystem->dumpFile($fixture.'/probe.php', str_replace($schema, $replacement, $probe));
                $this->copyJSendFixture($root, $fixture, $filesystem);

                try {
                    new DisposablePublicConsumer()->run($root, $fixture, $consumer);
                    self::fail('A zero-exit Scheduler probe with '.$name.' schema was aggregated.');
                } catch (RuntimeException $runtimeException) {
                    self::assertSame(
                        'The Scheduler probe evidence is invalid.',
                        $runtimeException->getMessage()
                    );
                    self::assertFileDoesNotExist($consumer.'/probe-receipt.json');
                }
            }
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves only failure of the Scheduler-specific installed probe receives the typed rejection.
     */
    public function test_that_only_the_designated_php_probe_maps_to_a_typed_rejection(): void
    {
        $root = dirname(__DIR__, 3);
        $workspace = sys_get_temp_dir().'/fight-common-probe-rejection-'.bin2hex(random_bytes(8));
        $publicApiFixture = $workspace.'/public-api-fixture';
        $schedulerFixture = $workspace.'/scheduler-fixture';
        $composerFixture = $workspace.'/composer-fixture';
        $publicApiConsumer = $workspace.'/public-api-consumer';
        $schedulerConsumer = $workspace.'/scheduler-consumer';
        $composerConsumer = $workspace.'/composer-consumer';
        $filesystem = new Filesystem();
        $filesystem->mkdir([
            $publicApiFixture,
            $schedulerFixture,
            $composerFixture,
            $publicApiConsumer,
            $schedulerConsumer,
            $composerConsumer
        ]);
        foreach ([$publicApiFixture, $schedulerFixture] as $fixture) {
            $filesystem->copy($root.'/release/fixtures/PublicApiConsumer/composer.json', $fixture.'/composer.json');
            $filesystem->copy($root.'/release/fixtures/PublicApiConsumer/probe.php', $fixture.'/probe.php');
            $filesystem->copy(
                $root.'/release/fixtures/PublicApiConsumer/public-api-probe.php',
                $fixture.'/public-api-probe.php'
            );
            $this->copyJSendFixture($root, $fixture, $filesystem);
        }

        $failedProbe = "<?php\n\ndeclare(strict_types=1);\n\nfwrite(STDERR, 'private probe diagnostics');\nexit(19);\n";
        $filesystem->dumpFile($publicApiFixture.'/public-api-probe.php', $failedProbe);
        $filesystem->dumpFile($schedulerFixture.'/probe.php', $failedProbe);

        $composer = json_decode(
            (string) file_get_contents($root.'/release/fixtures/PublicApiConsumer/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $composer['require']['fight-common/missing-package'] = 'dev-main';
        $filesystem->dumpFile(
            $composerFixture.'/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
        );
        $filesystem->copy(
            $root.'/release/fixtures/PublicApiConsumer/probe.php',
            $composerFixture.'/probe.php'
        );
        $filesystem->copy(
            $root.'/release/fixtures/PublicApiConsumer/public-api-probe.php',
            $composerFixture.'/public-api-probe.php'
        );
        $this->copyJSendFixture($root, $composerFixture, $filesystem);

        try {
            try {
                new DisposablePublicConsumer()->run($root, $publicApiFixture, $publicApiConsumer);
                self::fail('A failed representative public API probe did not reject evidence.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(RuntimeException::class, $runtimeException::class);
                self::assertSame(
                    'The representative public API probe failed to compile or execute.',
                    $runtimeException->getMessage()
                );
                self::assertStringNotContainsString('private probe diagnostics', $runtimeException->getMessage());
                self::assertNull($runtimeException->getPrevious());
            }

            try {
                new DisposablePublicConsumer()->run($root, $schedulerFixture, $schedulerConsumer);
                self::fail('A failed Scheduler probe did not receive the typed rejection.');
            } catch (PublicConsumerProbeRejected $rejected) {
                self::assertSame(
                    'The installed Scheduler probe failed to compile or execute.',
                    $rejected->getMessage()
                );
                self::assertStringNotContainsString('private probe diagnostics', $rejected->getMessage());
                self::assertNull($rejected->getPrevious());
            }

            try {
                new DisposablePublicConsumer()->run($root, $composerFixture, $composerConsumer);
                self::fail('A failed Composer install did not reject consumer evidence.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(RuntimeException::class, $runtimeException::class);
            }
        } finally {
            $filesystem->remove($workspace);
        }
    }

    /**
     * Proves process-launch infrastructure failure is not typed as an observed probe rejection.
     */
    public function test_that_probe_launch_infrastructure_failure_remains_generic(): void
    {
        $method = new ReflectionMethod(DisposablePublicConsumer::class, 'runProbe');

        try {
            $method->invoke(
                new DisposablePublicConsumer(),
                [PHP_BINARY, '-r', 'exit(19);'],
                '/definitely/unavailable/fight-common-consumer',
                ['PATH' => '/usr/local/bin:/usr/bin:/bin']
            );
            self::fail('Unavailable process-launch infrastructure did not reject probe execution.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(RuntimeException::class, $runtimeException::class);
            self::assertSame('The public consumer process is unavailable.', $runtimeException->getMessage());
        }
    }

    /**
     * Copies the optional HttpFoundation boundary fake and the closed JSend probe into a custom fixture.
     */
    private function copyJSendFixture(string $root, string $fixture, Filesystem $filesystem): void
    {
        $filesystem->copy(
            $root.'/release/fixtures/PublicApiConsumer/jsend-probe.php',
            $fixture.'/jsend-probe.php'
        );
        $filesystem->copy(
            $root.'/release/fixtures/PublicApiConsumer/http-foundation-boundary-fake.php',
            $fixture.'/http-foundation-boundary-fake.php'
        );
    }

    /**
     * Independently calculates the specified production-tree framing used by the receipt
     */
    private function productionTreeDigest(string $package): string
    {
        $files = ['composer.json'];
        $composer = json_decode(
            (string) file_get_contents($package.'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $roots = array_values($composer['autoload']['psr-4']);

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($package.'/'.$root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $files[] = substr($file->getPathname(), strlen($package) + 1);
                }
            }
        }

        $files = [...$files, ...$composer['autoload']['files']];
        $files = array_values(array_unique($files));

        sort($files, SORT_STRING);
        $context = hash_init('sha256');

        foreach ($files as $relativePath) {
            $bytes = file_get_contents($package.'/'.$relativePath);
            is_string($bytes) || throw new RuntimeException('Production package input is unreadable.');
            hash_update($context, $relativePath."\0".strlen($bytes)."\0".$bytes);
        }

        return hash_final($context);
    }

    /** @return array<string, mixed> */
    private function schedulerNonZeroFailureObservation(): array
    {
        $log = [
            'level'   => 'error',
            'message' => 'Command exited with non-zero status 1',
            'context' => [
                'keys'      => ['exception'],
                'exception' => [
                    'class'   => SchedulerException::class,
                    'message' => 'Command exited with non-zero status 1',
                    'code'    => 0
                ]
            ]
        ];
        $notification = [
            'subject' => '[Scheduler] Job "consumer-failing-command" failed',
            'from'    => [['address' => 'scheduler@example.com', 'name' => null]],
            'to'      => [['address' => 'operator@example.com', 'name' => null]],
            'content' => [
                'environment'  => 'Environment: consumer',
                'error'        => 'Error: Command exited with non-zero status 1',
                'code'         => 'Code: 0',
                'content_type' => 'text/plain',
                'charset'      => 'utf-8'
            ]
        ];

        return [
            'attempts'                       => 2,
            'reported_exit_codes'            => [1, 1],
            'logs'                           => [$log, $log],
            'notification_count'             => 2,
            'notifications'                  => [$notification, $notification],
            'lock_reacquired_after_attempts' => [true, true]
        ];
    }
}
