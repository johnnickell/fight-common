<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class ManifestClassificationAuthority
 *
 * Owns manifest schema, inventory completeness, classification, and evidence binding policy.
 */
final readonly class ManifestClassificationAuthority
{
    private const string SCHEMA_VERSION = 'fight-common.compatibility-manifest/v1';
    private const array BASELINE = [
        'version'           => '1.1.0',
        'tag_name'          => '1.1.0',
        'tag_object_oid'    => '5f1c2f2a4a78741836003b0d6acd229569beb454',
        'peeled_commit_oid' => 'fdd48065c5527f4968943db7d61d6f1ad17619e7'
    ];
    private const array INVENTORY = [
        'Domain'      => ['declarations' => 131, 'functions' => 13],
        'Application' => ['declarations' => 170, 'functions' => 0],
        'Adapter'     => ['declarations' => 114, 'functions' => 0]
    ];
    private const array OPERATIONS = ['callable', 'constructible', 'extensible', 'implementable'];
    private const array EVIDENCE_AUTHORITIES = [
        'fight-common.classification.baseline-grandfathered'       => [
            'path'    => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator' => 'Decision paragraphs 1-2: authoritative 1.1.0 grandfathering'
        ],
        'fight-common.classification.explicit-internal-annotation' => [
            'path'    => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator' => 'Decision paragraph 3: explicit internal classification'
        ],
        'fight-common.classification.prd-00014-addition'           => [
            'path'    => 'planning/specs/00014-PRD.md',
            'locator' => 'Implementation Decisions: 46 post-1.1.0 classifications'
        ],
        'fight-common.operation.abstract-extension-base'           => [
            'path'     => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator'  => 'Decision: abstract base designed for consumer specialization',
            'category' => 'abstract-base'
        ],
        'fight-common.operation.exception-subtyping'               => [
            'path'     => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator'  => 'Decision: exception type intended to support consumer subtypes',
            'category' => 'exception-subtype'
        ],
        'fight-common.operation.interface-implementation'          => [
            'path'    => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator' => 'Decision: implementable interfaces may be implemented by consumers'
        ],
        'fight-common.operation.not-promised'                      => [
            'path'    => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator' => 'Decision: operations are independent and visibility alone is insufficient'
        ],
        'fight-common.operation.public-call'                       => [
            'path'    => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator' => 'Decision: callable public methods and function contracts'
        ],
        'fight-common.operation.public-construction'               => [
            'path'    => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator' => 'Decision: constructible documented constructors or factories'
        ],
        'fight-common.operation.published-subtype'                 => [
            'path'     => 'src/Domain/Value/Internet/Url.php',
            'locator'  => 'class Url extends Uri',
            'category' => 'published-subtype'
        ],
        'fight-common.member.public-declaration'                   => [
            'path'    => 'planning/adr/0009-public-api-manifest-baseline.md',
            'locator' => 'Consequences: deterministic grandfathered public declaration surface'
        ]
    ];
    private const array CLASSIFICATION_SUBJECT_DIGESTS = [
        'baseline_declarations' => '68268346a810048e882b47685736e51d7d747cd19115c8dff67faf7f6d691ee8',
        'baseline_functions'    => '519444e4d23f8b4813df8ab5677f6d1aeab0b0c8c46ec1a8aed49e60daec9168',
        'added_declarations'    => 'a5fbba8fecc7c302e002813ee054893fe6e03c7e1e9fc47880885177a921b273'
    ];

    /**
     * Constructs ManifestClassificationAuthority
     */
    public function __construct(
        private OperationEvidenceAuthority $operationEvidenceAuthority = new OperationEvidenceAuthority()
    ) {
    }

    /**
     * Returns the governed manifest schema version
     */
    public function schemaVersion(): string
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Returns the authenticated baseline identity
     *
     * @return array<string, string>
     */
    public function baseline(): array
    {
        return self::BASELINE;
    }

    /**
     * Returns exact inventory expectations
     *
     * @return array<string, array<string, int>>
     */
    public function inventory(): array
    {
        return self::INVENTORY;
    }

    /**
     * Reports whether manifest classification authority is complete and intentional
     *
     * @param array<string, mixed>                     $manifest
     * @param list<array<string, mixed>>               $declarations
     * @param list<array<string, mixed>>               $functions
     * @param array<string, string>                    $sourceFacts
     * @param array<string, array<string, mixed>>      $structuralFacts
     */
    public function isIntentional(
        array $manifest,
        array $declarations,
        array $functions,
        array $sourceFacts,
        array $structuralFacts
    ): bool {
        $declaredNames = array_column($manifest['declarations'] ?? [], 'name');
        $functionNames = array_column($manifest['functions'] ?? [], 'name');
        sort($declaredNames, SORT_STRING);
        sort($functionNames, SORT_STRING);
        $evidence = $manifest['evidence_authorities'] ?? [];
        $valid = array_keys($manifest) === $this->manifestKeys();
        $valid = $valid && ($manifest['schema_version'] ?? null) === self::SCHEMA_VERSION;
        $valid = $valid && ($manifest['baseline'] ?? null) === self::BASELINE;
        $valid = $valid && ($manifest['inventory_expectations'] ?? null) === self::INVENTORY;
        $valid = $valid && $evidence === self::EVIDENCE_AUTHORITIES;
        $valid = $valid && $declaredNames === array_column($declarations, 'name');
        $valid = $valid && $functionNames === array_column($functions, 'name');
        $valid = $valid && $this->inventoryCounts(
            $manifest['declarations'] ?? [],
            $manifest['functions'] ?? []
        ) === self::INVENTORY;
        $valid = $valid && $this->entriesAreIntentional(
            $manifest['declarations'] ?? [],
            $evidence,
            $sourceFacts,
            $structuralFacts,
            true
        );
        $valid = $valid && $this->entriesAreIntentional(
            $manifest['functions'] ?? [],
            $evidence,
            $sourceFacts,
            $structuralFacts,
            false
        );
        $valid = $valid && $this->classificationEvidenceCounts(
            $manifest['declarations'] ?? [],
            $manifest['functions'] ?? []
        ) === [
            'declarations' => [
                'fight-common.classification.baseline-grandfathered'       => 363,
                'fight-common.classification.prd-00014-addition'           => 51,
                'fight-common.classification.explicit-internal-annotation' => 1
            ],
            'functions'    => ['fight-common.classification.baseline-grandfathered' => 13]
        ];

        return $valid && $this->classificationSubjectDigests(
            $manifest['declarations'] ?? [],
            $manifest['functions'] ?? []
        ) === self::CLASSIFICATION_SUBJECT_DIGESTS;
    }

    /**
     * Returns every runtime subject omitted from an otherwise intentional manifest
     *
     * @param array<string, mixed>                     $manifest
     * @param list<array<string, mixed>>               $declarations
     * @param list<array<string, mixed>>               $functions
     * @param array<string, string>                    $sourceFacts
     * @param array<string, array<string, mixed>>      $structuralFacts
     *
     * @return non-empty-list<string>|null
     */
    public function missingClassifications(
        array $manifest,
        array $declarations,
        array $functions,
        array $sourceFacts,
        array $structuralFacts,
        bool $referencedContractsAreIntentional
    ): ?array {
        $manifestDeclarations = $manifest['declarations'] ?? null;
        $manifestFunctions = $manifest['functions'] ?? null;
        $evidence = $manifest['evidence_authorities'] ?? null;
        $behavioralContracts = $manifest['behavioral_contracts'] ?? null;
        $packagePromises = $manifest['package_promises'] ?? null;
        if (
            array_keys($manifest) !== $this->manifestKeys()
            || ($manifest['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ($manifest['baseline'] ?? null) !== self::BASELINE
            || ($manifest['inventory_expectations'] ?? null) !== self::INVENTORY
            || $evidence !== self::EVIDENCE_AUTHORITIES
            || !is_array($manifestDeclarations)
            || !array_is_list($manifestDeclarations)
            || !array_all($manifestDeclarations, static fn (mixed $entry): bool => is_array($entry))
            || !is_array($manifestFunctions)
            || !array_is_list($manifestFunctions)
            || !array_all($manifestFunctions, static fn (mixed $entry): bool => is_array($entry))
            || !is_array($behavioralContracts)
            || !array_is_list($behavioralContracts)
            || !is_array($packagePromises)
            || !array_is_list($packagePromises)
            || !$referencedContractsAreIntentional
        ) {
            return null;
        }

        /** @var list<array<string, mixed>> $manifestDeclarations */
        /** @var list<array<string, mixed>> $manifestFunctions */
        $normalizedDeclarations = $this->normalizeMissingClassifications($manifestDeclarations, true);
        $normalizedFunctions = $this->normalizeMissingClassifications($manifestFunctions, false);
        if (
            $normalizedDeclarations === null
            || $normalizedFunctions === null
            || !$this->entriesAreIntentional(
                $normalizedDeclarations['entries'],
                $evidence,
                $sourceFacts,
                $structuralFacts,
                true
            )
            || !$this->entriesAreIntentional(
                $normalizedFunctions['entries'],
                $evidence,
                $sourceFacts,
                $structuralFacts,
                false
            )
        ) {
            return null;
        }

        $runtimeNames = array_column([...$declarations, ...$functions], 'name');
        $manifestNames = array_column([
            ...$normalizedDeclarations['entries'],
            ...$normalizedFunctions['entries']
        ], 'name');
        if (
            count(array_unique($manifestNames)) !== count($manifestNames)
            || array_diff($manifestNames, $runtimeNames) !== []
        ) {
            return null;
        }

        $missing = array_values(array_unique([
            ...array_diff($runtimeNames, $manifestNames),
            ...$normalizedDeclarations['subjects'],
            ...$normalizedFunctions['subjects']
        ]));
        sort($missing, SORT_STRING);

        return $missing === [] ? null : $missing;
    }

    /**
     * Counts public and internal declaration classifications
     *
     * @param list<array<string, mixed>> $declarations
     *
     * @return array{public: int, internal: int}
     */
    public function classificationCounts(array $declarations): array
    {
        $classifications = array_count_values(array_column($declarations, 'classification'));

        return [
            'public'   => $classifications['public'] ?? 0,
            'internal' => $classifications['internal'] ?? 0
        ];
    }

    /**
     * Returns the closed manifest key order
     *
     * @return list<string>
     */
    private function manifestKeys(): array
    {
        return [
            'schema_version',
            'baseline',
            'inventory_expectations',
            'evidence_authorities',
            'declarations',
            'functions',
            'behavioral_contracts',
            'package_promises'
        ];
    }

    /**
     * Counts declarations and functions assigned to each runtime layer
     *
     * @param list<array<string, mixed>> $declarations
     * @param list<array<string, mixed>> $functions
     *
     * @return array<string, array<string, int>>
     */
    private function inventoryCounts(array $declarations, array $functions): array
    {
        $counts = [
            'Domain'      => ['declarations' => 0, 'functions' => 0],
            'Application' => ['declarations' => 0, 'functions' => 0],
            'Adapter'     => ['declarations' => 0, 'functions' => 0]
        ];

        foreach ($declarations as $entry) {
            ++$counts[$entry['layer']]['declarations'];
        }

        foreach ($functions as $entry) {
            ++$counts[$entry['layer']]['functions'];
        }

        return $counts;
    }

    /**
     * Normalizes absent classifications without changing any other entry evidence
     *
     * @param list<array<string, mixed>> $entries
     *
     * @return array{entries: list<array<string, mixed>>, subjects: list<string>}|null
     */
    private function normalizeMissingClassifications(array $entries, bool $declarations): ?array
    {
        $expectedKeys = $this->entryKeys($declarations);
        $keysWithoutClassification = array_values(array_diff($expectedKeys, ['classification']));
        $normalized = [];
        $subjects = [];

        foreach ($entries as $entry) {
            if (!in_array(array_keys($entry), [$expectedKeys, $keysWithoutClassification], true)) {
                return null;
            }

            $classification = $entry['classification'] ?? null;
            if (!in_array($classification, ['public', 'internal'], true)) {
                $classificationEvidence = $entry['classification_evidence'] ?? null;
                $authority = is_array($classificationEvidence) ? ($classificationEvidence['authority'] ?? null) : null;
                $classification = match ($authority) {
                    'fight-common.classification.baseline-grandfathered',
                    'fight-common.classification.prd-00014-addition' => 'public',
                    'fight-common.classification.explicit-internal-annotation' => 'internal',
                    default => null
                };
                $subject = $entry['name'] ?? null;
                if ($classification === null || !is_string($subject) || $subject === '') {
                    return null;
                }

                $subjects[] = $subject;
            }

            $normalizedEntry = [];
            foreach ($expectedKeys as $key) {
                $normalizedEntry[$key] = $key === 'classification' ? $classification : $entry[$key];
            }

            $normalized[] = $normalizedEntry;
        }

        return ['entries' => $normalized, 'subjects' => $subjects];
    }

    /**
     * Returns the closed key order for a declaration or function entry
     *
     * @return list<string>
     */
    private function entryKeys(bool $declarations): array
    {
        return match ($declarations) {
            true => [
                'name',
                'source',
                'layer',
                'kind',
                'classification',
                'evidence_binding',
                'classification_evidence',
                'members',
                'operations'
            ],
            false => [
                'name',
                'source',
                'layer',
                'classification',
                'evidence_binding',
                'classification_evidence',
                'operations'
            ]
        };
    }

    /**
     * Checks every policy entry against classification and operation evidence
     *
     * @param list<array<string, mixed>>              $entries
     * @param array<string, array<mixed>>             $evidence
     * @param array<string, string>                   $sourceFacts
     * @param array<string, array<string, mixed>>     $structuralFacts
     */
    private function entriesAreIntentional(
        array $entries,
        array $evidence,
        array $sourceFacts,
        array $structuralFacts,
        bool $declarations
    ): bool {
        foreach ($entries as $entry) {
            if (array_keys($entry) !== $this->entryKeys($declarations)) {
                return false;
            }

            if (!in_array($entry['classification'], ['public', 'internal'], true)) {
                return false;
            }

            if (($sourceFacts[$entry['name']] ?? null) !== $entry['source']) {
                return false;
            }

            if (!$this->evidenceBindingIsExact($entry)) {
                return false;
            }

            if (
                !$this->classificationEvidenceIsIntentional(
                    $entry,
                    $entry['classification_evidence'],
                    $evidence
                )
            ) {
                return false;
            }

            if ($declarations && !$this->membersAreIntentional($entry, $evidence, $structuralFacts)) {
                return false;
            }

            if (array_keys($entry['operations']) !== self::OPERATIONS) {
                return false;
            }

            foreach ($entry['operations'] as $operationName => $operation) {
                if (array_keys($operation) !== ['promised', 'evidence']) {
                    return false;
                }

                if (
                    !is_bool($operation['promised'])
                    || !$this->operationEvidenceAuthority->isIntentional(
                        $entry,
                        $operationName,
                        $operation,
                        $evidence,
                        $structuralFacts
                    )
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Checks the closed public non-method member policy against scanner-derived facts
     *
     * @param array<string, mixed> $entry
     * @param array<string, array<mixed>> $evidence
     * @param array<string, array<string, mixed>> $structuralFacts
     */
    private function membersAreIntentional(array $entry, array $evidence, array $structuralFacts): bool
    {
        $members = $entry['members'] ?? null;
        if (!is_array($members) || !array_is_list($members)) {
            return false;
        }

        if ($entry['classification'] === 'internal') {
            return $members === [];
        }

        $facts = $structuralFacts[$entry['name']]['members'] ?? null;
        if (!is_array($facts) || !array_is_list($facts)) {
            return false;
        }

        $policies = [];
        foreach ($members as $member) {
            if (!is_array($member) || array_keys($member) !== ['name', 'signature', 'evidence']) {
                return false;
            }

            $decision = $member['evidence'];
            $binding = is_array($decision) ? ($decision['binding'] ?? null) : null;
            if (
                !is_string($member['name'])
                || !is_string($member['signature'])
                || !is_array($decision)
                || array_keys($decision) !== ['authority', 'rationale', 'binding']
                || $decision['authority'] !== 'fight-common.member.public-declaration'
                || !isset($evidence[$decision['authority']])
                || !is_string($decision['rationale'])
                || !str_contains($decision['rationale'], $entry['name'])
                || !str_contains($decision['rationale'], $entry['source'])
                || !str_contains($decision['rationale'], $member['name'])
                || !is_array($binding)
                || array_keys($binding) !== ['subject', 'source_locator', 'member']
                || $binding !== [
                    'subject'        => $entry['name'],
                    'source_locator' => $entry['evidence_binding']['source_locator'],
                    'member'         => $member['name']
                ]
            ) {
                return false;
            }

            $policies[] = ['name' => $member['name'], 'signature' => $member['signature']];
        }

        return $policies === $facts;
    }

    /**
     * Checks the exact declaration or function identity shared by its evidence
     *
     * @param array<string, mixed> $entry
     */
    private function evidenceBindingIsExact(array $entry): bool
    {
        $binding = $entry['evidence_binding'] ?? null;
        if (!is_array($binding) || array_keys($binding) !== ['subject', 'source_locator']) {
            return false;
        }

        $authority = $entry['classification_evidence']['authority'] ?? null;
        $sourceIdentity = match ($authority) {
            'fight-common.classification.baseline-grandfathered' => self::BASELINE['peeled_commit_oid'],
            default => 'working-tree'
        };

        return $binding['subject'] === $entry['name']
            && $binding['source_locator'] === $sourceIdentity.':'.$entry['source'].'#'.$entry['name'];
    }

    /**
     * Checks one classification decision against its subject and authority
     *
     * @param array<string, mixed>        $entry
     * @param mixed                       $decision
     * @param array<string, array<mixed>> $authorities
     */
    private function classificationEvidenceIsIntentional(array $entry, mixed $decision, array $authorities): bool
    {
        if (!is_array($decision) || array_keys($decision) !== ['authority', 'rationale']) {
            return false;
        }

        $authority = $decision['authority'];
        $classification = $entry['classification'];
        $validPair = match ($authority) {
            'fight-common.classification.baseline-grandfathered',
            'fight-common.classification.prd-00014-addition' => $classification === 'public',
            'fight-common.classification.explicit-internal-annotation' => $classification === 'internal',
            default => false
        };

        return $validPair
            && isset($authorities[$authority])
            && $this->rationaleNamesSubjectAndSource($decision['rationale'], $entry);
    }

    /**
     * Counts the deliberate classification authorities for declarations and functions
     *
     * @param list<array<string, mixed>> $declarations
     * @param list<array<string, mixed>> $functions
     *
     * @return array<string, array<string, int>>
     */
    private function classificationEvidenceCounts(array $declarations, array $functions): array
    {
        $count = static function (array $entries): array {
            $authorities = array_map(
                static fn (array $entry): mixed => $entry['classification_evidence']['authority'] ?? null,
                $entries
            );

            return array_count_values($authorities);
        };

        return ['declarations' => $count($declarations), 'functions' => $count($functions)];
    }

    /**
     * Returns exact baseline and post-baseline subject-set digests
     *
     * @param list<array<string, mixed>> $declarations
     * @param list<array<string, mixed>> $functions
     *
     * @return array<string, string>
     */
    private function classificationSubjectDigests(array $declarations, array $functions): array
    {
        $baselineAuthority = 'fight-common.classification.baseline-grandfathered';
        $names = static function (array $entries, bool $baseline) use ($baselineAuthority): array {
            $subjects = array_map(
                static fn (array $entry): string => $entry['name'],
                array_filter(
                    $entries,
                    static fn (array $entry): bool => (
                        $entry['classification_evidence']['authority'] === $baselineAuthority
                    ) === $baseline
                )
            );
            sort($subjects, SORT_STRING);

            return $subjects;
        };

        return [
            'baseline_declarations' => hash('sha256', implode("\n", $names($declarations, true))),
            'baseline_functions'    => hash('sha256', implode("\n", $names($functions, true))),
            'added_declarations'    => hash('sha256', implode("\n", $names($declarations, false)))
        ];
    }

    /**
     * Checks that classification rationale identifies both subject and source
     *
     * @param mixed                $rationale
     * @param array<string, mixed> $entry
     */
    private function rationaleNamesSubjectAndSource(mixed $rationale, array $entry): bool
    {
        if (!is_string($rationale) || $rationale === '' || !str_contains($rationale, $entry['name'])) {
            return false;
        }

        preg_match_all(
            implode('', [
                '/(?<![A-Za-z0-9_\/-])src\/(?:Domain|Application|Adapter)\/(?:[A-Za-z0-9_.-]+\/)*',
                '[A-Za-z0-9_.-]+\.php(?![A-Za-z0-9_\/-])/'
            ]),
            $rationale,
            $matches
        );

        return array_values(array_unique($matches[0])) === [$entry['source']];
    }
}
