<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Application\StructuralApiComparison;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class StructuralApiComparisonTest
 */
#[CoversClass(StructuralApiComparison::class)]
final class StructuralApiComparisonTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves non-method public members are governed independently from consumer operations.
     */
    public function test_that_public_constant_and_enum_case_drift_is_attributed_to_the_member(): void
    {
        $name = 'Fight\\Common\\Domain\\Example\\State';
        $baseline = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => 'fdd48065c5527f4968943db7d61d6f1ad17619e7',
            'declarations'   => [$this->inventoryEntry(
                $name,
                'enum',
                members: [['name' => 'case READY', 'signature' => "case READY = 'ready'"]]
            )],
            'functions'      => []
        ];
        $candidate = $baseline;
        $candidate['source_oid'] = str_repeat('1', 40);
        $candidate['declarations'][0]['members'][0]['signature'] = "case READY = 'changed'";
        $manifest = ['declarations' => [$this->manifestEntry($name, ['case READY'])], 'functions' => []];
        $comparison = new StructuralApiComparison();

        $changed = $comparison->compare(
            $manifest,
            $baseline,
            $candidate,
            $comparison->checker($baseline, $candidate)
        );
        self::assertSame('major', $changed['classification']);
        $breaking = array_values(array_filter(
            $changed['findings'],
            static fn (array $finding): bool => ($finding['member'] ?? null) === 'case READY'
        ));
        self::assertSame('release.compatibility.structural-api.member-breaking', $breaking[0]['finding_id']);

        $removed = $candidate;
        $removed['declarations'][0]['members'] = [];
        $removedResult = $comparison->compare(
            $manifest,
            $baseline,
            $removed,
            $comparison->checker($baseline, $removed)
        );
        self::assertSame('major', $removedResult['classification']);
        $memberBreaking = 'release.compatibility.structural-api.member-breaking';
        self::assertSame('case READY', array_find(
            $removedResult['findings'],
            static fn (array $finding): bool => $finding['finding_id'] === $memberBreaking
        )['member']);

        $renamed = $candidate;
        $renamed['declarations'][0]['members'] = [[
            'name' => 'case RENAMED', 'signature' => "case RENAMED = 'ready'"
        ]];
        $renameManifest = [
            'declarations' => [$this->manifestEntry($name, ['case READY', 'case RENAMED'])],
            'functions'    => []
        ];
        $renameResult = $comparison->compare(
            $renameManifest,
            $baseline,
            $renamed,
            $comparison->checker($baseline, $renamed)
        );
        self::assertSame('major', $renameResult['classification']);

        $candidate['declarations'][0]['members'][] = [
            'name'      => 'case EXTRA',
            'signature' => "case EXTRA = 'extra'"
        ];
        usort(
            $candidate['declarations'][0]['members'],
            static fn (array $left, array $right): int => $left['name'] <=> $right['name']
        );
        $result = $comparison->compare(
            $manifest,
            $baseline,
            $candidate,
            $comparison->checker($baseline, $candidate)
        );
        self::assertSame(
            'release.compatibility.structural-api.missing-member-classification',
            $result['findings'][0]['finding_id']
        );
        self::assertSame('case EXTRA', $result['findings'][0]['member']);
    }

    /**
     * Proves authenticated structural evidence and every required fail-closed stop.
     */
    public function test_that_structural_comparison_emits_stable_attributed_findings_and_fails_closed(): void
    {
        $baseline = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => 'fdd48065c5527f4968943db7d61d6f1ad17619e7',
            'declarations'   => [
                $this->inventoryEntry('Fight\\Common\\Domain\\Example\\LegacyContract', 'interface')
            ],
            'functions'      => []
        ];
        $candidate = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => '1111111111111111111111111111111111111111',
            'declarations'   => [
                $this->inventoryEntry('Fight\\Common\\Domain\\Example\\LegacyContract', 'interface'),
                $this->inventoryEntry('Fight\\Common\\Domain\\Example\\NewValue', 'class')
            ],
            'functions'      => [
                $this->inventoryEntry('Fight\\Common\\Domain\\example_value', 'function')
            ]
        ];
        $manifest = [
            'declarations' => [
                $this->manifestEntry('Fight\\Common\\Domain\\Example\\LegacyContract'),
                $this->manifestEntry('Fight\\Common\\Domain\\Example\\NewValue')
            ],
            'functions'    => [
                $this->manifestEntry('Fight\\Common\\Domain\\example_value')
            ]
        ];
        $comparison = new StructuralApiComparison();
        $checker = $comparison->checker($baseline, $candidate);
        self::assertSame([
            'status'         => 'valid',
            'classification' => 'minor',
            'findings'       => [
                [
                    'finding_id'  => 'release.compatibility.structural-api.declaration-compatible',
                    'attribution' => 'structural-checker',
                    'subject'     => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                    'operation'   => null
                ],
                [
                    'finding_id'  => 'release.compatibility.structural-api.operation-compatible',
                    'attribution' => 'structural-checker',
                    'subject'     => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                    'operation'   => 'callable'
                ],
                [
                    'finding_id'  => 'release.compatibility.structural-api.operation-compatible',
                    'attribution' => 'structural-checker',
                    'subject'     => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                    'operation'   => 'implementable'
                ],
                [
                    'finding_id'  => 'release.compatibility.structural-api.declaration-added',
                    'attribution' => 'structural-checker',
                    'subject'     => 'Fight\\Common\\Domain\\Example\\NewValue',
                    'operation'   => null
                ],
                [
                    'finding_id'  => 'release.compatibility.structural-api.declaration-added',
                    'attribution' => 'structural-checker',
                    'subject'     => 'Fight\\Common\\Domain\\example_value',
                    'operation'   => null
                ]
            ]
        ], $comparison->compare($manifest, $baseline, $candidate, $checker));

        $baselineDrift = $checker;
        $baselineDrift['baseline_inventory_sha256'] = str_repeat('0', 64);
        $this->assertRejected(
            'release.compatibility.structural-api.baseline-drift',
            'baseline-inventory',
            $comparison->compare($manifest, $baseline, $candidate, $baselineDrift)
        );

        $candidateDrift = $checker;
        $candidateDrift['candidate_inventory_sha256'] = str_repeat('0', 64);
        $this->assertRejected(
            'release.compatibility.structural-api.candidate-drift',
            'candidate-inventory',
            $comparison->compare($manifest, $baseline, $candidate, $candidateDrift)
        );

        $unclassified = $manifest;
        array_pop($unclassified['declarations']);
        $this->assertRejected(
            'release.compatibility.structural-api.missing-classification',
            'compatibility-manifest',
            $comparison->compare($unclassified, $baseline, $candidate, $checker),
            'Fight\\Common\\Domain\\Example\\NewValue'
        );

        $unsupported = $checker;
        $unsupported['findings'][5]['code'] = 'tool-specific-unknown';
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $candidate, $unsupported),
            'Fight\\Common\\Domain\\Example\\NewValue'
        );

        $indeterminateBaseline = $baseline;
        $indeterminateBaseline['declarations'][0]['operations']['implementable'] = null;
        $indeterminateCandidate = $candidate;
        $indeterminateCandidate['declarations'][0]['operations']['implementable'] = null;
        $indeterminate = $comparison->checker($indeterminateBaseline, $indeterminateCandidate);
        $this->assertRejected(
            'release.compatibility.structural-api.operation-promise-indeterminate',
            'compatibility-manifest',
            $comparison->compare($manifest, $indeterminateBaseline, $indeterminateCandidate, $indeterminate),
            'Fight\\Common\\Domain\\Example\\LegacyContract',
            'implementable'
        );

        $wrongBaseline = $baseline;
        $wrongBaseline['source_oid'] = str_repeat('2', 40);
        $this->assertRejected(
            'release.compatibility.structural-api.baseline-drift',
            'baseline-inventory',
            $comparison->compare($manifest, $wrongBaseline, $candidate, $checker)
        );

        $invalidCandidate = $candidate;
        $invalidCandidate['declarations'][0]['kind'] = 'unknown';
        $this->assertRejected(
            'release.compatibility.structural-api.candidate-drift',
            'candidate-inventory',
            $comparison->compare($manifest, $baseline, $invalidCandidate, $checker)
        );

        $nonListMembers = $candidate;
        $nonListMembers['declarations'][0]['members'] = 'not-a-member-list';
        $this->assertRejected(
            'release.compatibility.structural-api.candidate-drift',
            'candidate-inventory',
            $comparison->compare($manifest, $baseline, $nonListMembers, $checker)
        );

        $malformedMember = $candidate;
        $malformedMember['declarations'][0]['members'] = ['not-a-member-record'];
        $this->assertRejected(
            'release.compatibility.structural-api.candidate-drift',
            'candidate-inventory',
            $comparison->compare($manifest, $baseline, $malformedMember, $checker)
        );

        $unsupportedCandidateSchema = $candidate;
        $unsupportedCandidateSchema['schema_version'] = 'tool-specific-schema';
        $this->assertRejected(
            'release.compatibility.structural-api.candidate-drift',
            'candidate-inventory',
            $comparison->compare($manifest, $baseline, $unsupportedCandidateSchema, $checker)
        );

        $unsupportedEnvelope = $checker;
        $unsupportedEnvelope['schema_version'] = 'tool-specific-schema';
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $candidate, $unsupportedEnvelope)
        );

        $unknownManifestClassification = $manifest;
        $unknownManifestClassification['declarations'][0]['classification'] = 'unknown';
        $this->assertRejected(
            'release.compatibility.structural-api.missing-classification',
            'compatibility-manifest',
            $comparison->compare($unknownManifestClassification, $baseline, $candidate, $checker),
            'Fight\\Common\\Domain\\Example\\LegacyContract'
        );

        $malformedManifestOperation = $manifest;
        $malformedManifestOperation['declarations'][0]['operations']['callable']['promised'] = 'yes';
        $this->assertRejected(
            'release.compatibility.structural-api.missing-classification',
            'compatibility-manifest',
            $comparison->compare($malformedManifestOperation, $baseline, $candidate, $checker),
            'Fight\\Common\\Domain\\Example\\LegacyContract'
        );

        foreach (
            [
                'non-array-entry'       => null,
                'missing-name'          => [],
                'operations-not-array'  => [
                    'name'           => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                    'classification' => 'public',
                    'operations'     => 'unknown'
                ],
                'operations-incomplete' => [
                    'name'           => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                    'classification' => 'public',
                    'operations'     => []
                ],
                'operation-not-array'   => [
                    'name'           => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                    'classification' => 'public',
                    'operations'     => [
                        'callable'      => 'unknown',
                        'constructible' => ['promised' => false],
                        'extensible'    => ['promised' => false],
                        'implementable' => ['promised' => true]
                    ]
                ]
            ] as $malformed
        ) {
            $malformedManifest = $manifest;
            $malformedManifest['declarations'][0] = $malformed;
            $this->assertRejected(
                'release.compatibility.structural-api.missing-classification',
                'compatibility-manifest',
                $comparison->compare($malformedManifest, $baseline, $candidate, $checker),
                'Fight\\Common\\Domain\\Example\\LegacyContract'
            );
        }

        $unpromisedBaseline = $baseline;
        $unpromisedBaseline['declarations'][0]['operations']['constructible'] = null;
        $unpromisedCandidate = $candidate;
        $unpromisedCandidate['declarations'][0]['operations']['constructible'] = null;
        $unpromisedIndeterminate = $comparison->checker($unpromisedBaseline, $unpromisedCandidate);
        self::assertSame(
            [
                'status'         => 'valid',
                'classification' => 'minor',
                'findings'       => [
                    [
                        'finding_id'  => 'release.compatibility.structural-api.declaration-compatible',
                        'attribution' => 'structural-checker',
                        'subject'     => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                        'operation'   => null
                    ],
                    [
                        'finding_id'  => 'release.compatibility.structural-api.operation-compatible',
                        'attribution' => 'structural-checker',
                        'subject'     => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                        'operation'   => 'callable'
                    ],
                    [
                        'finding_id'  => 'release.compatibility.structural-api.operation-compatible',
                        'attribution' => 'structural-checker',
                        'subject'     => 'Fight\\Common\\Domain\\Example\\LegacyContract',
                        'operation'   => 'implementable'
                    ],
                    [
                        'finding_id'  => 'release.compatibility.structural-api.declaration-added',
                        'attribution' => 'structural-checker',
                        'subject'     => 'Fight\\Common\\Domain\\Example\\NewValue',
                        'operation'   => null
                    ],
                    [
                        'finding_id'  => 'release.compatibility.structural-api.declaration-added',
                        'attribution' => 'structural-checker',
                        'subject'     => 'Fight\\Common\\Domain\\example_value',
                        'operation'   => null
                    ]
                ]
            ],
            $comparison->compare(
                $manifest,
                $unpromisedBaseline,
                $unpromisedCandidate,
                $unpromisedIndeterminate
            )
        );

        $breakingCandidate = $candidate;
        $breakingCandidate['declarations'][0]['operations']['implementable'] = ['run(): void'];
        $breaking = $comparison->checker($baseline, $breakingCandidate);
        self::assertSame(
            'major',
            $comparison->compare($manifest, $baseline, $breakingCandidate, $breaking)['classification']
        );

        $malformedFinding = $checker;
        $malformedFinding['findings'][5]['operation'] = 'callable';
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $candidate, $malformedFinding),
            'Fight\\Common\\Domain\\Example\\NewValue',
            'callable'
        );
    }

    /**
     * Proves checker digests cannot authenticate incomplete or contradictory findings.
     */
    public function test_that_checker_findings_must_be_the_exact_complete_inventory_comparison(): void
    {
        $baseline = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => 'fdd48065c5527f4968943db7d61d6f1ad17619e7',
            'declarations'   => [
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\ChangedApi',
                    'class',
                    callable: ['run(string $value): void']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\StableApi',
                    'interface',
                    implementable: ['run(string $value): void']
                )
            ],
            'functions'      => []
        ];
        $candidate = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => str_repeat('1', 40),
            'declarations'   => [
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\ChangedApi',
                    'class',
                    callable: ['run(int $value): void']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\StableApi',
                    'interface',
                    implementable: ['run(string $value): void']
                )
            ],
            'functions'      => []
        ];
        $manifest = [
            'declarations' => [
                $this->manifestEntryWithPromise('Fight\\Common\\Domain\\Example\\ChangedApi', 'callable'),
                $this->manifestEntryWithPromise('Fight\\Common\\Domain\\Example\\StableApi', 'implementable')
            ],
            'functions'    => []
        ];
        $comparison = new StructuralApiComparison();

        $removedCandidate = $candidate;
        $removedCandidate['declarations'] = [];
        $empty = $comparison->checker($baseline, $removedCandidate);
        $empty['findings'] = [];
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $removedCandidate, $empty),
            'Fight\\Common\\Domain\\Example\\ChangedApi'
        );

        $missingDeclaration = $comparison->checker($baseline, $candidate);
        $missingDeclaration['findings'] = array_values(array_filter(
            $missingDeclaration['findings'],
            static fn (array $finding): bool => !(
                $finding['declaration'] === 'Fight\\Common\\Domain\\Example\\StableApi'
                && $finding['operation'] === null
            )
        ));
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $candidate, $missingDeclaration),
            'Fight\\Common\\Domain\\Example\\StableApi'
        );

        $missingPromisedOperation = $comparison->checker($baseline, $candidate);
        $missingPromisedOperation['findings'] = array_values(array_filter(
            $missingPromisedOperation['findings'],
            static fn (array $finding): bool => !(
                $finding['declaration'] === 'Fight\\Common\\Domain\\Example\\ChangedApi'
                && $finding['operation'] === 'callable'
            )
        ));
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $candidate, $missingPromisedOperation),
            'Fight\\Common\\Domain\\Example\\ChangedApi',
            'callable'
        );

        $duplicate = $comparison->checker($baseline, $candidate);
        $duplicate['findings'][] = $duplicate['findings'][0];
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $candidate, $duplicate),
            'Fight\\Common\\Domain\\Example\\ChangedApi'
        );

        $contradictory = $comparison->checker($baseline, $candidate);
        foreach ($contradictory['findings'] as &$finding) {
            if (
                $finding['declaration'] === 'Fight\\Common\\Domain\\Example\\ChangedApi'
                && $finding['operation'] === 'callable'
            ) {
                $finding['code'] = 'operation-compatible';
            }
        }

        unset($finding);
        $this->assertRejected(
            'release.compatibility.structural-api.unsupported-checker-output',
            'structural-checker',
            $comparison->compare($manifest, $baseline, $candidate, $contradictory),
            'Fight\\Common\\Domain\\Example\\ChangedApi',
            'callable'
        );
    }

    /**
     * Proves internal inventory changes cannot broaden intentional compatibility policy.
     */
    public function test_that_internal_additions_removals_and_changes_do_not_affect_release_rank(): void
    {
        $removed = 'Fight\\Common\\Domain\\Internal\\RemovedHelper';
        $changed = 'Fight\\Common\\Domain\\Internal\\ChangedHelper';
        $added = 'Fight\\Common\\Domain\\Internal\\AddedHelper';
        $addedFunction = 'Fight\\Common\\Domain\\internal_added_helper';
        $baseline = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => 'fdd48065c5527f4968943db7d61d6f1ad17619e7',
            'declarations'   => [
                $this->inventoryEntry($removed, 'class', callable: ['run(string $value): void']),
                $this->inventoryEntry($changed, 'class', callable: ['run(string $value): void'])
            ],
            'functions'      => []
        ];
        $candidate = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => str_repeat('1', 40),
            'declarations'   => [
                $this->inventoryEntry($changed, 'class', callable: ['run(int $value): void']),
                $this->inventoryEntry($added, 'class', callable: ['run(): void'])
            ],
            'functions'      => [
                $this->inventoryEntry($addedFunction, 'function', callable: [$addedFunction.'(): void'])
            ]
        ];
        $manifestEntries = array_map(
            function (string $name): array {
                $entry = $this->manifestEntryWithPromise($name, 'callable');
                $entry['classification'] = 'internal';

                return $entry;
            },
            [$removed, $changed, $added]
        );
        $internalFunction = $this->manifestEntryWithPromise($addedFunction, 'callable');
        $internalFunction['classification'] = 'internal';
        $manifest = ['declarations' => $manifestEntries, 'functions' => [$internalFunction]];
        $comparison = new StructuralApiComparison();
        $checker = $comparison->checker($baseline, $candidate);

        self::assertSame(
            ['status' => 'valid', 'classification' => 'patch', 'findings' => []],
            $comparison->compare($manifest, $baseline, $candidate, $checker)
        );

        $missingClassification = $manifest;
        array_shift($missingClassification['declarations']);
        $this->assertRejected(
            'release.compatibility.structural-api.missing-classification',
            'compatibility-manifest',
            $comparison->compare($missingClassification, $baseline, $candidate, $checker),
            $removed
        );
    }

    /**
     * Proves each independently promised consumer operation is structurally compared.
     */
    public function test_that_checker_compares_each_promised_operation_axis_independently(): void
    {
        $baseline = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => 'fdd48065c5527f4968943db7d61d6f1ad17619e7',
            'declarations'   => [
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\CallableApi',
                    'class',
                    callable: ['run(string $value): void']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\FactoryApi',
                    'class',
                    constructible: ['static create(string $value): static']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\ExtensionApi',
                    'class',
                    extensible: ['protected transform(string $value): string']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\ImplementationApi',
                    'interface',
                    implementable: ['execute(string $value): void']
                )
            ],
            'functions'      => []
        ];
        $candidate = [
            'schema_version' => 'fight-common.structural-inventory/v1',
            'source_oid'     => str_repeat('1', 40),
            'declarations'   => [
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\CallableApi',
                    'class',
                    callable: ['run(int $value): void']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\FactoryApi',
                    'class',
                    constructible: ['static create(int $value): static']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\ExtensionApi',
                    'class',
                    extensible: ['protected transform(int $value): string']
                ),
                $this->inventoryEntry(
                    'Fight\\Common\\Domain\\Example\\ImplementationApi',
                    'interface',
                    implementable: ['execute(int $value): void']
                )
            ],
            'functions'      => []
        ];
        $manifest = [
            'declarations' => [
                $this->manifestEntryWithPromise('Fight\\Common\\Domain\\Example\\CallableApi', 'callable'),
                $this->manifestEntryWithPromise('Fight\\Common\\Domain\\Example\\FactoryApi', 'constructible'),
                $this->manifestEntryWithPromise('Fight\\Common\\Domain\\Example\\ExtensionApi', 'extensible'),
                $this->manifestEntryWithPromise('Fight\\Common\\Domain\\Example\\ImplementationApi', 'implementable')
            ],
            'functions'    => []
        ];
        $comparison = new StructuralApiComparison();

        $result = $comparison->compare(
            $manifest,
            $baseline,
            $candidate,
            $comparison->checker($baseline, $candidate)
        );

        self::assertSame('valid', $result['status']);
        self::assertSame('major', $result['classification']);
        $breakingFinding = 'release.compatibility.structural-api.operation-breaking';
        $breaking = array_values(array_filter(
            $result['findings'],
            static fn (array $finding): bool => $finding['finding_id'] === $breakingFinding
        ));
        self::assertSame([
            'Fight\\Common\\Domain\\Example\\CallableApi'       => 'callable',
            'Fight\\Common\\Domain\\Example\\ExtensionApi'      => 'extensible',
            'Fight\\Common\\Domain\\Example\\FactoryApi'        => 'constructible',
            'Fight\\Common\\Domain\\Example\\ImplementationApi' => 'implementable'
        ], array_column($breaking, 'operation', 'subject'));
    }

    /**
     * @param string $name    Structural subject name.
     * @param array  $members Governed member names.
     *
     * @phpstan-param list<string> $members
     * @return array<string, mixed>
     */
    private function manifestEntry(string $name, array $members = []): array
    {
        return [
            'name'           => $name,
            'classification' => 'public',
            'members'        => $members,
            'operations'     => [
                'callable'      => ['promised' => true],
                'constructible' => ['promised' => false],
                'extensible'    => ['promised' => false],
                'implementable' => ['promised' => true]
            ]
        ];
    }

    /** @return array<string, mixed> */
    private function manifestEntryWithPromise(string $name, string $promise): array
    {
        $entry = $this->manifestEntry($name);
        foreach ($entry['operations'] as $operation => $_policy) {
            $entry['operations'][$operation]['promised'] = $operation === $promise;
        }

        return $entry;
    }

    /**
     * Returns one generated inventory entry fixture
     *
     * @param string $name          Structural subject name.
     * @param string $kind          Structural declaration kind.
     * @param array $callable      Callable operation shape.
     * @param array $constructible Constructible operation shape.
     * @param array $extensible    Extensible operation shape.
     * @param array $implementable Implementable operation shape.
     * @param array $members       Non-method declaration members.
     *
     * @phpstan-param list<string> $callable
     * @phpstan-param list<string> $constructible
     * @phpstan-param list<string> $extensible
     * @phpstan-param list<string> $implementable
     * @phpstan-param list<array{name: string, signature: string}> $members
     *
     * @return array<string, mixed>
     */
    private function inventoryEntry(
        string $name,
        string $kind,
        array $callable = [],
        array $constructible = [],
        array $extensible = [],
        array $implementable = [],
        array $members = []
    ): array {
        $source = match ($kind) {
            'function' => 'src/Domain/functions.php',
            default => sprintf(
                'src/Domain/%s.php',
                str_replace('\\', '/', substr($name, strlen('Fight\\Common\\Domain\\')))
            )
        };

        return [
            'name'       => $name,
            'source'     => $source,
            'kind'       => $kind,
            'members'    => $members,
            'operations' => [
                'callable'      => $callable,
                'constructible' => $constructible,
                'extensible'    => $extensible,
                'implementable' => $implementable
            ]
        ];
    }

    /**
     * Asserts one exact governed structural stop
     *
     * @param string               $findingId  Stable finding identifier.
     * @param string               $attribution Evidence authority.
     * @param array<string, mixed> $result
     * @param string|null          $subject     Attributed structural subject.
     * @param string|null          $operation   Attributed consumer operation.
     */
    private function assertRejected(
        string $findingId,
        string $attribution,
        array $result,
        ?string $subject = null,
        ?string $operation = null
    ): void {
        self::assertSame([
            'status'         => 'rejected',
            'classification' => 'indeterminate',
            'findings'       => [[
                'finding_id'  => $findingId,
                'attribution' => $attribution,
                'subject'     => $subject,
                'operation'   => $operation
            ]]
        ], $result);
    }
}
