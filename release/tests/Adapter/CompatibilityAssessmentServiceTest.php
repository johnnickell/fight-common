<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Adapter\DisposablePublicConsumer;
use Fight\Release\Adapter\GitBaselineStructuralInventory;
use Fight\Release\Adapter\LocalCompatibilityInput;
use Fight\Release\Adapter\LocalCompatibilityWorkspace;
use Fight\Release\Adapter\LocalGitPort;
use Fight\Release\Adapter\PhpParserStructuralInventory;
use Fight\Release\Application\Boundary\BaselineStructuralInventoryPort;
use Fight\Release\Application\Boundary\BaselineTagResolutionResult;
use Fight\Release\Application\Boundary\CompatibilityInputPort;
use Fight\Release\Application\Boundary\CompatibilityWorkspacePort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\PublicConsumerPort;
use Fight\Release\Application\Boundary\PublicConsumerProbeRejected;
use Fight\Release\Application\Boundary\ReleaseBoundaryOperationResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\Boundary\StructuralInventoryPort;
use Fight\Release\Application\CompatibilityAssessmentService;
use Fight\Release\Application\CompatibilityFinding;
use Fight\Release\Application\CompatibilityManifestAuthority;
use Fight\Release\Application\CompatibilityManifestRejected;
use Fight\Release\Application\PublicApiManifestAuthority;
use Fight\Release\Application\SchedulerEvidenceAuthority;
use Fight\Release\Application\StructuralApiComparison;
use Fight\Release\Application\StructuralCompatibilityAuthority;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use UnexpectedValueException;

/**
 * Class CompatibilityAssessmentServiceTest
 */
#[CoversClass(CompatibilityAssessmentService::class)]
#[CoversClass(CompatibilityFinding::class)]
#[CoversClass(CompatibilityManifestRejected::class)]
#[CoversClass(PublicConsumerProbeRejected::class)]
#[CoversClass(PublicApiManifestAuthority::class)]
#[CoversClass(SchedulerEvidenceAuthority::class)]
#[CoversClass(StructuralApiComparison::class)]
#[CoversClass(GitBaselineStructuralInventory::class)]
#[CoversClass(LocalCompatibilityInput::class)]
#[CoversClass(LocalCompatibilityWorkspace::class)]
#[CoversClass(PhpParserStructuralInventory::class)]
#[CoversClass(DisposablePublicConsumer::class)]
final class CompatibilityAssessmentServiceTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves the Application service composes the real read-only adapters and cleans its workspace.
     */
    public function test_that_the_application_service_owns_compatibility_orchestration(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $workspace = new LocalCompatibilityWorkspace();
        $inventory = new PhpParserStructuralInventory($input);
        $git = new LocalGitPort($root, $effects);
        $result = new CompatibilityAssessmentService(
            $input,
            $workspace,
            $inventory,
            new GitBaselineStructuralInventory($root, $workspace, $inventory),
            $git,
            new DisposablePublicConsumer()
        )->assess($root);

        self::assertSame(0, $result->exitCode);
        self::assertSame('succeeded', $result->payload['status']);
        self::assertSame([], $result->payload['performed_effects']);
        self::assertSame([], $result->payload['proposed_effects']);

        $missing = sys_get_temp_dir().'/fight-common-compatibility-missing-'.bin2hex(random_bytes(8));
        self::assertFalse(is_dir($missing));
        $workspace->remove($missing);

        $existing = sys_get_temp_dir().'/fight-common-compatibility-existing-'.bin2hex(random_bytes(8));
        mkdir($existing);
        try {
            $this->expectException(RuntimeException::class);
            $workspace->createDirectory($existing);
        } finally {
            rmdir($existing);
        }
    }

    /**
     * Proves parser shape evidence for intersections, ordinary statics, and public constants.
     */
    public function test_that_structural_inventory_reports_only_consumer_relevant_parser_shapes(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-parser-shapes-'.bin2hex(random_bytes(8));
        mkdir($fixture.'/src/Domain/Coverage', 0777, true);
        mkdir($fixture.'/src/Application', 0777, true);
        mkdir($fixture.'/src/Adapter', 0777, true);
        file_put_contents(
            $fixture.'/src/Domain/Coverage/ParserShape.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Coverage;

class ParserShape
{
    public const IGNORED_IMPLEMENTATION_VALUE = 'not-an-extension-contract';

    public static function utility()
    {
    }

    public function accept(\Countable&\Iterator $value): void
    {
    }
}
PHP
        );
        file_put_contents(
            $fixture.'/src/Domain/Coverage/State.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Coverage;

enum State: string
{
    case READY = 'ready';

    public function label(): string
    {
        return $this->value;
    }
}
PHP
        );
        file_put_contents(
            $fixture.'/src/Domain/functions.php',
            '<?php declare(strict_types=1); namespace Fight\Common\Domain;'
        );

        try {
            $result = new PhpParserStructuralInventory(new LocalCompatibilityInput())->structuralInventory(
                $fixture,
                str_repeat('1', 40)
            );

            self::assertSame([
                'schema_version' => 'fight-common.structural-inventory/v1',
                'source_oid'     => str_repeat('1', 40),
                'declarations'   => [[
                    'name'       => 'Fight\\Common\\Domain\\Coverage\\ParserShape',
                    'source'     => 'src/Domain/Coverage/ParserShape.php',
                    'kind'       => 'class',
                    'members'    => [[
                        'name'      => 'constant IGNORED_IMPLEMENTATION_VALUE',
                        'signature' => "public const IGNORED_IMPLEMENTATION_VALUE = 'not-an-extension-contract'"
                    ]],
                    'operations' => [
                        'callable'      => [
                            'accept(\\Countable&\\Iterator $value): void',
                            'parent none',
                            'static utility()'
                        ],
                        'constructible' => ['implicit __construct()'],
                        'extensible'    => [
                            'public accept(\\Countable&\\Iterator $value): void',
                            'public static utility()'
                        ],
                        'implementable' => []
                    ]
                ], [
                    'name'       => 'Fight\\Common\\Domain\\Coverage\\State',
                    'source'     => 'src/Domain/Coverage/State.php',
                    'kind'       => 'enum',
                    'members'    => [[
                        'name'      => 'case READY',
                        'signature' => "case READY = 'ready'"
                    ]],
                    'operations' => [
                        'callable'      => ['label(): string'],
                        'constructible' => [],
                        'extensible'    => [],
                        'implementable' => []
                    ]
                ]],
                'functions'      => []
            ], $result);
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Proves direct-parent and abstract-hook changes remain operation-level breaking evidence.
     */
    public function test_that_structural_inventory_authenticates_parent_and_abstract_hook_changes(): void
    {
        $fixture = sys_get_temp_dir().'/fight-common-parser-contracts-'.bin2hex(random_bytes(8));
        $baselineRoot = $fixture.'/baseline';
        $candidateRoot = $fixture.'/candidate';

        foreach ([$baselineRoot, $candidateRoot] as $root) {
            mkdir($root.'/src/Domain/Contract', 0777, true);
            mkdir($root.'/src/Application', 0777, true);
            mkdir($root.'/src/Adapter', 0777, true);
            file_put_contents(
                $root.'/src/Domain/Contract/ParentOne.php',
                '<?php namespace Fight\\Common\\Domain\\Contract; class ParentOne {}'
            );
            file_put_contents(
                $root.'/src/Domain/Contract/ParentTwo.php',
                '<?php namespace Fight\\Common\\Domain\\Contract; class ParentTwo {}'
            );
            file_put_contents(
                $root.'/src/Domain/functions.php',
                '<?php declare(strict_types=1); namespace Fight\\Common\\Domain;'
            );
        }

        file_put_contents(
            $baselineRoot.'/src/Domain/Contract/FinalCallable.php',
            implode('', [
                '<?php namespace Fight\\Common\\Domain\\Contract; final class FinalCallable extends ParentOne {',
                ' public function run(): void {} }'
            ])
        );
        file_put_contents(
            $candidateRoot.'/src/Domain/Contract/FinalCallable.php',
            implode('', [
                '<?php namespace Fight\\Common\\Domain\\Contract; final class FinalCallable extends ParentTwo {',
                ' public function run(): void {} }'
            ])
        );
        file_put_contents(
            $baselineRoot.'/src/Domain/Contract/ExtensionApi.php',
            implode('', [
                '<?php namespace Fight\\Common\\Domain\\Contract; abstract class ExtensionApi {',
                ' protected function transform(string $value): string { return $value; } }'
            ])
        );
        file_put_contents(
            $candidateRoot.'/src/Domain/Contract/ExtensionApi.php',
            implode('', [
                '<?php namespace Fight\\Common\\Domain\\Contract; abstract class ExtensionApi {',
                ' abstract protected function transform(string $value): string; }'
            ])
        );

        try {
            $inventory = new PhpParserStructuralInventory(new LocalCompatibilityInput());
            $baseline = $inventory->structuralInventory(
                $baselineRoot,
                'fdd48065c5527f4968943db7d61d6f1ad17619e7'
            );
            $candidate = $inventory->structuralInventory($candidateRoot, str_repeat('1', 40));
            $baselineByName = array_column($baseline['declarations'], null, 'name');
            $candidateByName = array_column($candidate['declarations'], null, 'name');
            $finalCallable = 'Fight\\Common\\Domain\\Contract\\FinalCallable';
            $extensionApi = 'Fight\\Common\\Domain\\Contract\\ExtensionApi';

            self::assertSame(
                ['parent \\Fight\\Common\\Domain\\Contract\\ParentOne', 'run(): void'],
                $baselineByName[$finalCallable]['operations']['callable']
            );
            self::assertSame(
                ['parent \\Fight\\Common\\Domain\\Contract\\ParentTwo', 'run(): void'],
                $candidateByName[$finalCallable]['operations']['callable']
            );
            self::assertSame(
                ['abstract class', 'protected transform(string $value): string'],
                $baselineByName[$extensionApi]['operations']['extensible']
            );
            self::assertSame(
                ['abstract class', 'protected abstract transform(string $value): string'],
                $candidateByName[$extensionApi]['operations']['extensible']
            );

            $manifest = ['declarations' => [], 'functions' => []];
            foreach (array_keys($baselineByName) as $name) {
                $manifest['declarations'][] = [
                    'name'           => $name,
                    'classification' => 'public',
                    'operations'     => [
                        'callable'      => ['promised' => $name === $finalCallable],
                        'constructible' => ['promised' => false],
                        'extensible'    => ['promised' => $name === $extensionApi],
                        'implementable' => ['promised' => false]
                    ]
                ];
            }

            $comparison = new StructuralApiComparison();
            $result = $comparison->compare(
                $manifest,
                $baseline,
                $candidate,
                $comparison->checker($baseline, $candidate)
            );
            $breaking = array_values(array_filter(
                $result['findings'],
                static fn (array $finding): bool => (
                    $finding['finding_id'] === 'release.compatibility.structural-api.operation-breaking'
                )
            ));

            self::assertSame('major', $result['classification']);
            self::assertSame([
                $extensionApi  => 'extensible',
                $finalCallable => 'callable'
            ], array_column($breaking, 'operation', 'subject'));
        } finally {
            new Filesystem()->remove($fixture);
        }
    }

    /**
     * Proves unreadable source and unavailable workspace operations fail closed.
     */
    public function test_that_local_and_application_boundary_failures_remain_indeterminate(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $inventory = new PhpParserStructuralInventory($input);
        $invalid = sys_get_temp_dir().'/fight-common-invalid-inventory-'.bin2hex(random_bytes(8));
        mkdir($invalid.'/src/Domain', 0777, true);
        mkdir($invalid.'/src/Application', 0777, true);
        mkdir($invalid.'/src/Adapter', 0777, true);
        file_put_contents($invalid.'/src/Domain/Invalid.php', '<?php declare(strict_types=1);');
        file_put_contents($invalid.'/src/Domain/functions.php', '<?php namespace Invalid;');

        try {
            $inventory->structuralInventory($invalid, str_repeat('0', 64));
            self::fail('Unreadable structural source must fail closed.');
        } catch (UnexpectedValueException $unexpectedValueException) {
            self::assertSame('A structural declaration is unreadable.', $unexpectedValueException->getMessage());
        } finally {
            new Filesystem()->remove($invalid);
        }

        $capabilities = new class implements
            CompatibilityInputPort,
            CompatibilityWorkspacePort,
            StructuralInventoryPort,
            BaselineStructuralInventoryPort,
            GitPort
        {
            /**
             * Returns no boundary input
             */
            public function read(string $_path): string
            {
                throw new RuntimeException('not reached');
            }

            /**
             * Returns a missing-file predicate
             */
            public function isFile(string $_path): bool
            {
                return false;
            }

            /**
             * Returns no candidate inventory
             */
            public function structuralInventory(string $_sourceRoot, string $_sourceOid): array
            {
                return [];
            }

            /**
             * Returns no baseline inventory
             */
            public function baselineStructuralInventory(string $_commitOid, string $_workspace): array
            {
                return [];
            }

            /**
             * Rejects exact-tag resolution
             */
            public function resolveExactAnnotatedTag(string $_tagName): BaselineTagResolutionResult
            {
                throw new RuntimeException('not reached');
            }

            /**
             * Rejects unused repository inspection
             */
            public function inspectRepository(): ReleaseBoundaryOperationResult
            {
                throw new RuntimeException('not reached');
            }

            /**
             * Rejects unused baseline resolution
             */
            public function resolveBaselineTag(
                string $_tagName,
                string $_candidateOid
            ): BaselineTagResolutionResult {
                throw new RuntimeException('not reached');
            }

            /**
             * Rejects workspace creation
             */
            public function createWorkspace(): string
            {
                throw new RuntimeException('workspace unavailable');
            }

            /**
             * Accepts an unused directory request
             */
            public function createDirectory(string $_path): void
            {
            }

            /**
             * Accepts an unused cleanup request
             */
            public function remove(string $_path): void
            {
            }
        };
        $consumer = new class implements PublicConsumerPort {
            /**
             * Returns no consumer evidence
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                return [];
            }
        };
        $result = new CompatibilityAssessmentService(
            $capabilities,
            $capabilities,
            $capabilities,
            $capabilities,
            $capabilities,
            $consumer
        )->assess($root);

        self::assertSame(5, $result->exitCode);
        self::assertSame('evidence_indeterminate', $result->payload['status']);
        self::assertSame('release.compatibility.manifest-evidence-unavailable', $result->payload['findings'][0]['id']);
        self::assertSame([], $result->payload['performed_effects']);
        self::assertSame([], $result->payload['proposed_effects']);
    }

    /**
     * Proves exact fail-closed authority findings survive the public machine-result seam.
     */
    public function test_that_structural_authority_findings_are_preserved_by_the_machine_result(): void
    {
        $cases = [
            ['baseline-drift', 'baseline-inventory', null, null],
            ['candidate-drift', 'candidate-inventory', null, null],
            ['missing-classification', 'compatibility-manifest', 'Fight\\Common\\Added', null],
            ['unsupported-checker-output', 'structural-checker', 'Fight\\Common\\Changed', 'callable'],
            [
                'operation-promise-indeterminate',
                'compatibility-manifest',
                'Fight\\Common\\Promised',
                'implementable'
            ]
        ];
        $actual = [];

        foreach ($cases as [$suffix, $attribution, $subject, $operation]) {
            $capabilities = $this->compatibilityCapabilities();
            $consumer = new class implements PublicConsumerPort {
                /**
                 * Rejects consumer execution after structural authority has rejected evidence
                 */
                public function run(string $_repository, string $_fixture, string $_consumer): array
                {
                    throw new RuntimeException('Consumer must not run after structural rejection.');
                }
            };
            $manifestAuthority = new class implements CompatibilityManifestAuthority {
                /**
                 * Returns controlled valid manifest authority
                 */
                public function validate(
                    string $_manifestPath,
                    string $_repository,
                    CompatibilityInputPort $_input,
                    StructuralInventoryPort $_inventory,
                    GitPort $_git
                ): array {
                    return ['status' => 'valid', 'baseline' => ['peeled_commit_oid' => str_repeat('a', 40)]];
                }
            };
            $structuralAuthority = new readonly class (
                $suffix,
                $attribution,
                $subject,
                $operation
            ) implements StructuralCompatibilityAuthority {
                /**
                 * Constructs one controlled structural rejection
                 */
                public function __construct(
                    private string $suffix,
                    private string $attribution,
                    private ?string $subject,
                    private ?string $operation
                ) {
                }

                /**
                 * Returns controlled checker evidence
                 */
                public function checker(array $_baseline, array $_candidate): array
                {
                    return [];
                }

                /**
                 * Returns the controlled exact structural authority finding
                 */
                public function compare(
                    array $_manifest,
                    array $_baseline,
                    array $_candidate,
                    array $_checker
                ): array {
                    return [
                        'status'         => 'rejected',
                        'classification' => 'indeterminate',
                        'findings'       => [[
                            'finding_id'  => 'release.compatibility.structural-api.'.$this->suffix,
                            'attribution' => $this->attribution,
                            'subject'     => $this->subject,
                            'operation'   => $this->operation
                        ]]
                    ];
                }
            };
            $result = new CompatibilityAssessmentService(...[
                ...$capabilities,
                $consumer,
                $manifestAuthority,
                $structuralAuthority
            ])->assess('/repository');

            $actual[] = [
                'exit_code'         => $result->exitCode,
                'status'            => $result->payload['status'],
                'findings'          => $result->payload['findings'],
                'performed_effects' => $result->payload['performed_effects'],
                'proposed_effects'  => $result->payload['proposed_effects'],
                'next_action'       => $result->payload['next_action']
            ];
        }

        $expected = array_map(
            static fn (array $case): array => [
                'exit_code'         => 5,
                'status'            => 'evidence_indeterminate',
                'findings'          => [[
                    'id'          => 'release.compatibility.structural-api.'.$case[0],
                    'message'     => 'Structural compatibility authority rejected the evidence.',
                    'attribution' => $case[1],
                    'subject'     => $case[2],
                    'operation'   => $case[3]
                ]],
                'performed_effects' => [],
                'proposed_effects'  => [],
                'next_action'       => ['action' => 'restore_structural_evidence_and_retry']
            ],
            $cases
        );

        self::assertSame($expected, $actual);
    }

    /**
     * Proves authenticated candidate Scheduler divergence stops for an explicit 2.0.0 replan.
     */
    public function test_that_proven_candidate_scheduler_incompatibility_requires_an_explicit_major_replan(): void
    {
        $scheduler = [
            'construction_styles'      => ['two_argument', 'positional_optional', 'named_arguments'],
            'callable_output'          => "scheduler callable\n",
            'command_output'           => "scheduler command\nscheduler command\n",
            'default_process_commands' => ['default-command'],
            'factory_process_commands' => ['factory-command', 'false', 'false'],
            'non_zero_failure'         => SchedulerEvidenceAuthority::nonZeroFailureObservation()
        ];
        $receipt = (static fn (string $tree, array $observation): array => [
            'schema_version'   => 'fight-common.disposable-public-consumer/v1',
            'status'           => 'valid',
            'findings'         => SchedulerEvidenceAuthority::findings(
                isset($observation['portable_process_runner'])
            ),
            'candidate'        => ['production_tree_sha256' => $tree],
            'resolved_package' => [
                'installed_as'           => 'copy',
                'production_tree_sha256' => $tree
            ],
            'lock'             => ['sha256' => str_repeat('d', 64)],
            'probe'            => [
                'sha256'       => str_repeat('a', 64),
                'observations' => [
                    'uuid'                 => '00000000-0000-0000-0000-000000000000',
                    'meta'                 => ['consumer' => 'disposable'],
                    'collection'           => ['alpha', 'beta'],
                    'runtime_deprecations' => [],
                    'scheduler'            => $observation
                ]
            ]
        ]);
        $baseline = $receipt(str_repeat('b', 64), $scheduler);
        $candidateScheduler = $scheduler;
        $candidateScheduler['portable_process_runner'] = SchedulerEvidenceAuthority::portableObservation();
        $candidate = $receipt(str_repeat('c', 64), $candidateScheduler);
        $candidate['probe']['observations']['scheduler']['portable_process_runner'] = [
            'commands' => ['candidate-only-command'],
            'output'   => "candidate-only portable output\n"
        ];
        $consumer = new class ($baseline, $candidate) implements PublicConsumerPort {
            private int $calls = 0;

            /**
             * Constructs the controlled dual-package consumer
             *
             * @param array<string, mixed> $baseline
             * @param array<string, mixed> $candidate
             */
            public function __construct(private readonly array $baseline, private readonly array $candidate)
            {
            }

            /**
             * Returns the baseline receipt followed by the incompatible candidate receipt
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                return $this->calls++ === 0 ? $this->baseline : $this->candidate;
            }
        };
        $manifestAuthority = new class implements CompatibilityManifestAuthority {
            /**
             * Returns controlled valid manifest authority
             */
            public function validate(
                string $_manifestPath,
                string $_repository,
                CompatibilityInputPort $_input,
                StructuralInventoryPort $_inventory,
                GitPort $_git
            ): array {
                return [
                    'status'   => 'valid',
                    'baseline' => [
                        'version'           => '1.1.0',
                        'peeled_commit_oid' => str_repeat('a', 40)
                    ]
                ];
            }
        };
        $structuralAuthority = new class implements StructuralCompatibilityAuthority {
            /**
             * Returns controlled checker evidence
             */
            public function checker(array $_baseline, array $_candidate): array
            {
                return [];
            }

            /**
             * Returns controlled valid structural evidence
             */
            public function compare(
                array $_manifest,
                array $_baseline,
                array $_candidate,
                array $_checker
            ): array {
                return ['status' => 'valid', 'classification' => 'minor', 'findings' => []];
            }
        };
        $result = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $consumer,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');

        self::assertSame(4, $result->exitCode);
        self::assertSame('policy_blocked', $result->payload['status']);
        self::assertSame('failed', $result->payload['exit_class']);
        self::assertSame([[
            'id'      => 'release.compatibility.consumer.scheduler-1x-incompatible',
            'message' => 'The candidate cannot reproduce the published Scheduler 1.1.0 behavior.'
        ]], $result->payload['findings']);
        self::assertSame([], $result->payload['verified_postconditions']);
        self::assertSame([], $result->payload['performed_effects']);
        self::assertSame([], $result->payload['proposed_effects']);
        self::assertSame([
            'action'  => 'replan_scheduler_compatibility',
            'version' => '2.0.0'
        ], $result->payload['next_action']);

        $legacyOnlyCandidate = $receipt(str_repeat('c', 64), $scheduler);
        $divergentLog = &$legacyOnlyCandidate['probe']['observations']['scheduler']['non_zero_failure']['logs'][0];
        $divergentLog['message'] = 'candidate-only failure report';
        $legacyOnlyConsumer = new class ($baseline, $legacyOnlyCandidate) implements PublicConsumerPort {
            private int $calls = 0;

            /**
             * Constructs a valid baseline followed by an authenticated divergent legacy-only candidate
             *
             * @param array<string, mixed> $baseline
             * @param array<string, mixed> $candidate
             */
            public function __construct(private readonly array $baseline, private readonly array $candidate)
            {
            }

            /**
             * Returns baseline followed by a divergent candidate missing the portable Scheduler contract
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                return $this->calls++ === 0 ? $this->baseline : $this->candidate;
            }
        };
        $legacyOnlyResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $legacyOnlyConsumer,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(4, $legacyOnlyResult->exitCode);
        self::assertSame(SchedulerEvidenceAuthority::incompatibilityResult(), $legacyOnlyResult->payload);

        $malformedPortableCandidate = $candidate;
        $malformedPortableCandidate['probe']['observations']['scheduler']['portable_process_runner'] = [
            'commands' => 'portable-command',
            'output'   => "scheduler portable command\n"
        ];
        $malformedPortableConsumer = new class ($baseline, $malformedPortableCandidate) implements PublicConsumerPort {
            private int $calls = 0;

            /**
             * Constructs a valid baseline followed by malformed portable candidate evidence
             *
             * @param array<string, mixed> $baseline
             * @param array<string, mixed> $candidate
             */
            public function __construct(private readonly array $baseline, private readonly array $candidate)
            {
            }

            /**
             * Returns the baseline followed by malformed portable candidate evidence
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                return $this->calls++ === 0 ? $this->baseline : $this->candidate;
            }
        };
        $malformedPortableResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $malformedPortableConsumer,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(5, $malformedPortableResult->exitCode);
        self::assertSame('evidence_indeterminate', $malformedPortableResult->payload['status']);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $malformedPortableResult->payload['findings'][0]['id']
        );
        self::assertSame(
            ['action' => 'restore_consumer_evidence_and_retry'],
            $malformedPortableResult->payload['next_action']
        );

        $baselineFailure = new class implements PublicConsumerPort {
            /**
             * Reports unavailable baseline consumer infrastructure
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                throw new RuntimeException('Baseline consumer infrastructure is unavailable.');
            }
        };
        $baselineFailureResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $baselineFailure,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(5, $baselineFailureResult->exitCode);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $baselineFailureResult->payload['findings'][0]['id']
        );
        self::assertSame(
            ['action' => 'restore_consumer_evidence_and_retry'],
            $baselineFailureResult->payload['next_action']
        );

        $candidateProbeFailure = new class ($baseline) implements PublicConsumerPort {
            private int $calls = 0;

            /**
             * Constructs a baseline receipt followed by a designated candidate probe rejection
             *
             * @param array<string, mixed> $baseline
             */
            public function __construct(private readonly array $baseline)
            {
            }

            /**
             * Returns valid baseline evidence before rejecting the candidate probe
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                if ($this->calls++ === 0) {
                    return $this->baseline;
                }

                throw new PublicConsumerProbeRejected('Designated public consumer probe was rejected.');
            }
        };
        $candidateProbeFailureResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $candidateProbeFailure,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(4, $candidateProbeFailureResult->exitCode);
        self::assertSame('policy_blocked', $candidateProbeFailureResult->payload['status']);
        self::assertSame('failed', $candidateProbeFailureResult->payload['exit_class']);
        self::assertSame([[
            'id'      => 'release.compatibility.consumer.scheduler-1x-incompatible',
            'message' => 'The candidate cannot reproduce the published Scheduler 1.1.0 behavior.'
        ]], $candidateProbeFailureResult->payload['findings']);
        self::assertSame([], $candidateProbeFailureResult->payload['verified_postconditions']);
        self::assertSame([], $candidateProbeFailureResult->payload['performed_effects']);
        self::assertSame([], $candidateProbeFailureResult->payload['proposed_effects']);
        self::assertSame([
            'action'  => 'replan_scheduler_compatibility',
            'version' => '2.0.0'
        ], $candidateProbeFailureResult->payload['next_action']);

        $portableBaseline = $receipt(str_repeat('b', 64), $candidateScheduler);
        $portableBaselineConsumer = new class ($portableBaseline) implements PublicConsumerPort {
            public int $calls = 0;

            /**
             * Constructs portable baseline evidence before a candidate Scheduler rejection
             *
             * @param array<string, mixed> $baseline
             */
            public function __construct(private readonly array $baseline)
            {
            }

            /**
             * Returns portable baseline evidence and rejects if candidate execution is incorrectly attempted
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                if ($this->calls++ === 0) {
                    return $this->baseline;
                }

                throw new PublicConsumerProbeRejected('Candidate Scheduler probe must not be interpreted.');
            }
        };
        $portableBaselineResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $portableBaselineConsumer,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(1, $portableBaselineConsumer->calls);
        self::assertSame(5, $portableBaselineResult->exitCode);
        self::assertSame('evidence_indeterminate', $portableBaselineResult->payload['status']);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $portableBaselineResult->payload['findings'][0]['id']
        );
        self::assertSame(
            ['action' => 'restore_consumer_evidence_and_retry'],
            $portableBaselineResult->payload['next_action']
        );

        $malformedBaseline = $baseline;
        $malformedBaseline['probe']['observations']['scheduler']['command_output'] = 'untrusted baseline';
        $candidateMustNotRun = new class ($malformedBaseline) implements PublicConsumerPort {
            public int $calls = 0;

            /**
             * Constructs malformed baseline evidence before a candidate Scheduler rejection
             *
             * @param array<string, mixed> $baseline
             */
            public function __construct(private readonly array $baseline)
            {
            }

            /**
             * Returns malformed baseline evidence and rejects if candidate execution is incorrectly attempted
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                if ($this->calls++ === 0) {
                    return $this->baseline;
                }

                throw new PublicConsumerProbeRejected('Candidate Scheduler probe must not be interpreted.');
            }
        };
        $malformedBaselineResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $candidateMustNotRun,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(1, $candidateMustNotRun->calls);
        self::assertSame(5, $malformedBaselineResult->exitCode);
        self::assertSame('evidence_indeterminate', $malformedBaselineResult->payload['status']);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $malformedBaselineResult->payload['findings'][0]['id']
        );
        self::assertSame(
            ['action' => 'restore_consumer_evidence_and_retry'],
            $malformedBaselineResult->payload['next_action']
        );

        $candidateSchedulerEnvelopeFailure = new class ($baseline) implements PublicConsumerPort {
            private int $calls = 0;

            /**
             * Constructs a baseline receipt followed by invalid raw Scheduler-probe evidence
             *
             * @param array<string, mixed> $baseline
             */
            public function __construct(private readonly array $baseline)
            {
            }

            /**
             * Returns valid baseline evidence before rejecting the candidate Scheduler envelope
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                if ($this->calls++ === 0) {
                    return $this->baseline;
                }

                throw new RuntimeException('The Scheduler probe evidence is invalid.');
            }
        };
        $candidateSchedulerEnvelopeFailureResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $candidateSchedulerEnvelopeFailure,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(5, $candidateSchedulerEnvelopeFailureResult->exitCode);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $candidateSchedulerEnvelopeFailureResult->payload['findings'][0]['id']
        );
        self::assertSame(
            ['action' => 'restore_consumer_evidence_and_retry'],
            $candidateSchedulerEnvelopeFailureResult->payload['next_action']
        );

        $malformedCandidate = $candidate;
        $malformedCandidate['resolved_package']['production_tree_sha256'] = str_repeat('d', 64);
        $unclassifiedConsumer = new class ($baseline, $malformedCandidate) implements PublicConsumerPort {
            private int $calls = 0;

            /**
             * Constructs controlled unclassified consumer evidence
             *
             * @param array<string, mixed> $baseline
             * @param array<string, mixed> $candidate
             */
            public function __construct(private readonly array $baseline, private readonly array $candidate)
            {
            }

            /**
             * Returns valid baseline then malformed candidate evidence
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                return $this->calls++ === 0 ? $this->baseline : $this->candidate;
            }
        };
        $unclassifiedResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $unclassifiedConsumer,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(5, $unclassifiedResult->exitCode);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $unclassifiedResult->payload['findings'][0]['id']
        );
        self::assertSame(
            ['action' => 'restore_consumer_evidence_and_retry'],
            $unclassifiedResult->payload['next_action']
        );

        $malformedGenericBaseline = $baseline;
        $malformedGenericBaseline['probe']['observations']['uuid'] = 'candidate-only-uuid';
        $genericBaselineConsumer = new class ($malformedGenericBaseline) implements PublicConsumerPort {
            public int $calls = 0;

            /** @param array<string, mixed> $baseline */
            public function __construct(private readonly array $baseline)
            {
            }

            /**
             * Returns malformed generic baseline evidence and rejects any candidate call
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                if ($this->calls++ === 0) {
                    return $this->baseline;
                }

                throw new PublicConsumerProbeRejected('Candidate Scheduler probe must not be interpreted.');
            }
        };
        $malformedGenericBaselineResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $genericBaselineConsumer,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(1, $genericBaselineConsumer->calls);
        self::assertSame(5, $malformedGenericBaselineResult->exitCode);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $malformedGenericBaselineResult->payload['findings'][0]['id']
        );

        $malformedGenericCandidate = $candidate;
        $malformedGenericCandidate['probe']['observations']['collection'] = ['candidate-only'];
        $genericCandidateConsumer = new class ($baseline, $malformedGenericCandidate) implements PublicConsumerPort {
            private int $calls = 0;

            /**
             * @param array<string, mixed> $baseline
             * @param array<string, mixed> $candidate
             */
            public function __construct(private readonly array $baseline, private readonly array $candidate)
            {
            }

            /**
             * Returns valid baseline evidence followed by malformed generic candidate evidence
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                return $this->calls++ === 0 ? $this->baseline : $this->candidate;
            }
        };
        $malformedGenericCandidateResult = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $genericCandidateConsumer,
            $manifestAuthority,
            $structuralAuthority
        ])->assess('/repository');
        self::assertSame(5, $malformedGenericCandidateResult->exitCode);
        self::assertSame('evidence_indeterminate', $malformedGenericCandidateResult->payload['status']);
        self::assertSame(
            'release.compatibility.consumer-evidence-unavailable',
            $malformedGenericCandidateResult->payload['findings'][0]['id']
        );
        self::assertSame(
            ['action' => 'restore_consumer_evidence_and_retry'],
            $malformedGenericCandidateResult->payload['next_action']
        );
    }

    /**
     * Proves only closed, exactly attributed authority findings enter the machine-result seam.
     */
    public function test_that_unauthenticated_structural_findings_are_rejected(): void
    {
        $outerInvalid = [];
        $findingShapeInvalid = [
            'status'         => 'rejected',
            'classification' => 'indeterminate',
            'findings'       => [['finding_id' => 'release.compatibility.structural-api.baseline-drift']]
        ];
        $findingContractInvalid = [
            'status'         => 'rejected',
            'classification' => 'indeterminate',
            'findings'       => [[
                'finding_id'  => 'release.compatibility.structural-api.unknown',
                'attribution' => 'baseline-inventory',
                'subject'     => null,
                'operation'   => null
            ]]
        ];

        self::assertNull(CompatibilityFinding::fromStructuralResult($outerInvalid));
        self::assertNull(CompatibilityFinding::fromStructuralResult($findingShapeInvalid));
        self::assertNull(CompatibilityFinding::fromStructuralResult($findingContractInvalid));
        self::assertFalse(CompatibilityFinding::isMachineFinding(['id' => 'untrusted']));
    }

    /**
     * Proves governed non-method member evidence is preserved and malformed attribution is rejected.
     */
    public function test_that_governed_member_findings_are_authenticated_for_machine_results(): void
    {
        $structuralFinding = [
            'status'         => 'rejected',
            'classification' => 'indeterminate',
            'findings'       => [[
                'finding_id'  => 'release.compatibility.structural-api.missing-member-classification',
                'attribution' => 'compatibility-manifest',
                'subject'     => 'Fight\\Common\\Domain\\Status',
                'operation'   => null,
                'member'      => 'case READY'
            ]]
        ];
        $machineFinding = [
            'id'          => 'release.compatibility.structural-api.missing-member-classification',
            'message'     => 'Structural compatibility authority rejected the evidence.',
            'attribution' => 'compatibility-manifest',
            'subject'     => 'Fight\\Common\\Domain\\Status',
            'operation'   => null,
            'member'      => 'case READY'
        ];

        $finding = CompatibilityFinding::fromStructuralResult($structuralFinding);

        self::assertInstanceOf(CompatibilityFinding::class, $finding);
        self::assertSame($machineFinding, $finding->machineFinding());
        self::assertTrue(CompatibilityFinding::isMachineFinding($machineFinding));

        $malformedMember = $structuralFinding;
        $malformedMember['findings'][0]['member'] = 'method ready()';
        $unknownAttribution = $structuralFinding;
        $unknownAttribution['findings'][0]['attribution'] = 'structural-checker';
        self::assertNull(CompatibilityFinding::fromStructuralResult($malformedMember));
        self::assertNull(CompatibilityFinding::fromStructuralResult($unknownAttribution));

        $malformedMachineMember = $machineFinding;
        $malformedMachineMember['member'] = 'method ready()';
        $unknownMachineAttribution = $machineFinding;
        $unknownMachineAttribution['attribution'] = 'structural-checker';
        self::assertFalse(CompatibilityFinding::isMachineFinding($malformedMachineMember));
        self::assertFalse(CompatibilityFinding::isMachineFinding($unknownMachineAttribution));
    }

    /**
     * Proves typed manifest authority rejection uses the exact public finding contract.
     */
    public function test_that_typed_manifest_rejection_preserves_all_missing_classifications(): void
    {
        $subjects = ['Fight\\Common\\MissingOne', 'Fight\\Common\\MissingTwo'];
        $manifestAuthority = new readonly class ($subjects) implements CompatibilityManifestAuthority {
            /**
             * Constructs the controlled manifest authority
             *
             * @param array $subjects Missing subjects.
             *
             * @phpstan-param list<string> $subjects
             */
            public function __construct(private array $subjects)
            {
            }

            /**
             * Rejects exact missing classifications
             */
            public function validate(
                string $_manifestPath,
                string $_repository,
                CompatibilityInputPort $_input,
                StructuralInventoryPort $_inventory,
                GitPort $_git
            ): array {
                throw new CompatibilityManifestRejected(
                    ...array_map(CompatibilityFinding::missingClassification(...), $this->subjects)
                );
            }
        };
        $consumer = new class implements PublicConsumerPort {
            /**
             * Rejects consumer execution after manifest authority has rejected evidence
             */
            public function run(string $_repository, string $_fixture, string $_consumer): array
            {
                throw new RuntimeException('Consumer must not run after manifest rejection.');
            }
        };
        $result = new CompatibilityAssessmentService(...[
            ...$this->compatibilityCapabilities(),
            $consumer,
            $manifestAuthority
        ])->assess('/repository');

        self::assertSame(5, $result->exitCode);
        self::assertSame(
            $subjects,
            array_column($result->payload['findings'], 'subject')
        );
        self::assertSame(
            [
                'release.compatibility.structural-api.missing-classification',
                'release.compatibility.structural-api.missing-classification'
            ],
            array_column($result->payload['findings'], 'id')
        );
        self::assertSame([], $result->payload['performed_effects']);
        self::assertSame([], $result->payload['proposed_effects']);
        self::assertSame(
            ['action' => 'restore_manifest_evidence_and_retry'],
            $result->payload['next_action']
        );
    }

    /**
     * Returns controlled focused compatibility capabilities
     *
     * @return array{
     *     CompatibilityInputPort,
     *     CompatibilityWorkspacePort,
     *     StructuralInventoryPort,
     *     BaselineStructuralInventoryPort,
     *     GitPort
     * }
     */
    private function compatibilityCapabilities(): array
    {
        $capabilities = new class implements
            CompatibilityInputPort,
            CompatibilityWorkspacePort,
            StructuralInventoryPort,
            BaselineStructuralInventoryPort,
            GitPort
        {
            /**
             * Returns minimal committed manifest policy
             */
            public function read(string $_path): string
            {
                return '{"declarations":[],"functions":[]}';
            }

            /**
             * Reports controlled evidence exists
             */
            public function isFile(string $_path): bool
            {
                return true;
            }

            /**
             * Returns minimal candidate inventory
             */
            public function structuralInventory(string $_sourceRoot, string $_sourceOid): array
            {
                return ['declarations' => [], 'functions' => []];
            }

            /**
             * Returns minimal baseline inventory
             */
            public function baselineStructuralInventory(string $_commitOid, string $_workspace): array
            {
                return [];
            }

            /**
             * Rejects unused tag resolution
             */
            public function resolveExactAnnotatedTag(string $_tagName): BaselineTagResolutionResult
            {
                throw new RuntimeException('Not reached.');
            }

            /**
             * Rejects unused repository inspection
             */
            public function inspectRepository(): ReleaseBoundaryOperationResult
            {
                throw new RuntimeException('Not reached.');
            }

            /**
             * Rejects unused baseline resolution
             */
            public function resolveBaselineTag(
                string $_tagName,
                string $_candidateOid
            ): BaselineTagResolutionResult {
                throw new RuntimeException('Not reached.');
            }

            /**
             * Returns one controlled disposable workspace
             */
            public function createWorkspace(): string
            {
                return '/disposable-workspace';
            }

            /**
             * Accepts an unused directory request
             */
            public function createDirectory(string $_path): void
            {
            }

            /**
             * Accepts controlled workspace cleanup
             */
            public function remove(string $_path): void
            {
            }
        };

        return [$capabilities, $capabilities, $capabilities, $capabilities, $capabilities];
    }
}
