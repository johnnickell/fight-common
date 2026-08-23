<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class StructuralApiComparison
 *
 * Authenticates structural checker evidence against intentional manifest policy.
 */
final readonly class StructuralApiComparison implements StructuralCompatibilityAuthority
{
    private const string INVENTORY_SCHEMA = 'fight-common.structural-inventory/v1';
    private const string CHECKER_SCHEMA = 'fight-common.structural-checker/v1';
    private const string BASELINE_OID = 'fdd48065c5527f4968943db7d61d6f1ad17619e7';
    private const array OPERATIONS = ['callable', 'constructible', 'extensible', 'implementable'];
    private const array SUPPORTED_CODES = [
        'declaration-added'      => 'minor',
        'declaration-compatible' => 'patch',
        'declaration-breaking'   => 'major',
        'operation-compatible'   => 'patch',
        'operation-breaking'     => 'major',
        'member-added'           => 'minor',
        'member-compatible'      => 'patch',
        'member-breaking'        => 'major'
    ];
    private const array RANKS = ['patch' => 0, 'minor' => 1, 'major' => 2];

    /**
     * Creates closed structural checker evidence from two authenticated inventories
     *
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $candidate
     *
     * @return array<string, mixed>
     */
    public function checker(array $baseline, array $candidate): array
    {
        $baselineEntries = array_column([...$baseline['declarations'], ...$baseline['functions']], null, 'name');
        $candidateEntries = array_column([...$candidate['declarations'], ...$candidate['functions']], null, 'name');
        $findings = [];

        foreach ($baselineEntries as $name => $entry) {
            $candidateEntry = $candidateEntries[$name] ?? null;
            $code = 'declaration-breaking';
            if (is_array($candidateEntry) && $candidateEntry['kind'] === $entry['kind']) {
                $code = 'declaration-compatible';
            }

            $findings[] = ['code' => $code, 'declaration' => $name, 'operation' => null];

            if ($code !== 'declaration-compatible') {
                continue;
            }

            foreach (self::OPERATIONS as $operation) {
                $baselineShape = $entry['operations'][$operation] ?? null;
                $candidateShape = $candidateEntry['operations'][$operation] ?? null;
                $operationCode = 'operation-indeterminate';

                if (is_array($baselineShape) && is_array($candidateShape)) {
                    $compatible = match ($operation) {
                        'callable', 'constructible' => array_diff($baselineShape, $candidateShape) === [],
                        'extensible', 'implementable' => $baselineShape === $candidateShape
                    };
                    $operationCode = $compatible ? 'operation-compatible' : 'operation-breaking';
                }

                $findings[] = [
                    'code'        => $operationCode,
                    'declaration' => $name,
                    'operation'   => $operation
                ];
            }

            $baselineMembers = array_column($entry['members'], 'signature', 'name');
            $candidateMembers = array_column($candidateEntry['members'], 'signature', 'name');
            foreach ($baselineMembers as $member => $signature) {
                $memberCode = 'member-breaking';
                if (($candidateMembers[$member] ?? null) === $signature) {
                    $memberCode = 'member-compatible';
                }

                $findings[] = [
                    'code'        => $memberCode,
                    'declaration' => $name,
                    'operation'   => null,
                    'member'      => $member
                ];
            }

            foreach (array_diff_key($candidateMembers, $baselineMembers) as $member => $_signature) {
                $findings[] = [
                    'code' => 'member-added', 'declaration' => $name, 'operation' => null, 'member' => $member
                ];
            }
        }

        foreach (array_diff_key($candidateEntries, $baselineEntries) as $name => $_entry) {
            $findings[] = ['code' => 'declaration-added', 'declaration' => $name, 'operation' => null];
        }

        usort($findings, static fn (array $left, array $right): int => $left['declaration'] <=> $right['declaration']);

        return [
            'schema_version'             => self::CHECKER_SCHEMA,
            'baseline_inventory_sha256'  => $this->digest($baseline),
            'candidate_inventory_sha256' => $this->digest($candidate),
            'findings'                   => $findings
        ];
    }

    /**
     * Returns authenticated checker evidence without allowing it to define manifest policy
     *
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $checker
     *
     * @return array<string, mixed>
     */
    public function compare(array $manifest, array $baseline, array $candidate, array $checker): array
    {
        if (!$this->isInventory($baseline) || $baseline['source_oid'] !== self::BASELINE_OID) {
            return $this->rejected('baseline-drift', 'baseline-inventory');
        }

        if (!$this->isInventory($candidate)) {
            return $this->rejected('candidate-drift', 'candidate-inventory');
        }

        if (!$this->isCheckerEnvelope($checker)) {
            return $this->rejected('unsupported-checker-output', 'structural-checker');
        }

        if (($checker['baseline_inventory_sha256'] ?? null) !== $this->digest($baseline)) {
            return $this->rejected('baseline-drift', 'baseline-inventory');
        }

        if (($checker['candidate_inventory_sha256'] ?? null) !== $this->digest($candidate)) {
            return $this->rejected('candidate-drift', 'candidate-inventory');
        }

        $classifications = $this->classifications($manifest);
        foreach (
            [
                ...$baseline['declarations'],
                ...$baseline['functions'],
                ...$candidate['declarations'],
                ...$candidate['functions']
            ] as $entry
        ) {
            if (!isset($classifications[$entry['name']])) {
                return $this->rejected(
                    'missing-classification',
                    'compatibility-manifest',
                    $entry['name']
                );
            }
        }

        foreach ([...$baseline['declarations'], ...$candidate['declarations']] as $entry) {
            if (($classifications[$entry['name']]['classification'] ?? null) === 'internal') {
                continue;
            }

            foreach ($entry['members'] as $member) {
                if (!in_array($member['name'], $classifications[$entry['name']]['members'] ?? [], true)) {
                    return $this->rejected(
                        'missing-member-classification',
                        'compatibility-manifest',
                        $entry['name'],
                        null,
                        $member['name']
                    );
                }
            }
        }

        $subjects = array_fill_keys(array_column([
            ...$baseline['declarations'],
            ...$baseline['functions'],
            ...$candidate['declarations'],
            ...$candidate['functions']
        ], 'name'), true);
        $canonicalChecker = $this->checker($baseline, $candidate);
        /** @var list<array{code: string, declaration: string, operation: string|null}> $canonicalFindings */
        $canonicalFindings = $canonicalChecker['findings'];
        $mismatch = $this->checkerMismatch($checker['findings'], $canonicalFindings, $subjects);
        if ($mismatch !== null) {
            return $this->rejected(
                'unsupported-checker-output',
                'structural-checker',
                $mismatch['subject'],
                $mismatch['operation']
            );
        }

        $classification = 'patch';
        $findings = [];

        foreach ($checker['findings'] as $finding) {
            /** @var array{code: string, declaration: string, operation: string|null, member?: string} $finding */
            $subject = $finding['declaration'];
            $operation = $finding['operation'];
            $code = $finding['code'];
            $member = $finding['member'] ?? null;

            if ($classifications[$subject]['classification'] === 'internal') {
                continue;
            }

            if ($code === 'operation-indeterminate') {
                if (!($classifications[$subject]['operations'][$operation] ?? false)) {
                    continue;
                }

                return $this->rejected(
                    'operation-promise-indeterminate',
                    'compatibility-manifest',
                    $subject,
                    $operation
                );
            }

            if (
                $operation !== null
                && !($classifications[$subject]['operations'][$operation] ?? false)
            ) {
                continue;
            }

            /** @var string $code */
            $findingClassification = self::SUPPORTED_CODES[$code];
            if (self::RANKS[$findingClassification] > self::RANKS[$classification]) {
                $classification = $findingClassification;
            }

            $findings[] = [
                'finding_id'  => 'release.compatibility.structural-api.'.$code,
                'attribution' => 'structural-checker',
                'subject'     => $subject,
                'operation'   => $operation
            ];
            if (is_string($member)) {
                $findings[array_key_last($findings)]['member'] = $member;
            }
        }

        return ['status' => 'valid', 'classification' => $classification, 'findings' => $findings];
    }

    /**
     * Returns the first difference from the independently derived canonical checker evidence
     *
     * @param array<mixed>                                                              $actual
     * @param list<array{code: string, declaration: string, operation: string|null}>     $expected
     * @param array<string, bool>                                                        $subjects
     *
     * @phpstan-param list<mixed> $actual
     *
     * @return array{subject: string|null, operation: string|null}|null
     */
    private function checkerMismatch(array $actual, array $expected, array $subjects): ?array
    {
        foreach ($actual as $finding) {
            if (!$this->isFinding($finding, $subjects)) {
                $subject = null;
                $operation = null;
                if (is_array($finding)) {
                    $subject = is_string($finding['declaration'] ?? null) ? $finding['declaration'] : null;
                    $operation = is_string($finding['operation'] ?? null) ? $finding['operation'] : null;
                }

                return ['subject' => $subject, 'operation' => $operation];
            }
        }

        if ($actual === $expected) {
            return null;
        }

        $actualCounts = array_count_values(array_map($this->findingIdentity(...), $actual));
        foreach ($expected as $finding) {
            $identity = $this->findingIdentity($finding);
            if (($actualCounts[$identity] ?? 0) === 0) {
                return ['subject' => $finding['declaration'], 'operation' => $finding['operation']];
            }

            --$actualCounts[$identity];
        }

        $mismatchIndex = array_find_key(
            $actual,
            static fn (mixed $finding, int $index): bool => ($expected[$index] ?? null) !== $finding
        );
        assert(is_int($mismatchIndex));
        /** @var array{declaration: string, operation: string|null} $finding */
        $finding = $actual[$mismatchIndex];

        return ['subject' => $finding['declaration'], 'operation' => $finding['operation']];
    }

    /**
     * Returns an exact identity for one structurally valid checker finding
     *
     * @param array{code: string, declaration: string, operation: string|null} $finding
     */
    private function findingIdentity(array $finding): string
    {
        return json_encode($finding, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Reports whether one inventory has the closed authenticated shape
     *
     * @param array<string, mixed> $inventory
     */
    private function isInventory(array $inventory): bool
    {
        if (
            array_keys($inventory) !== ['schema_version', 'source_oid', 'declarations', 'functions']
            || $inventory['schema_version'] !== self::INVENTORY_SCHEMA
            || !is_string($inventory['source_oid'])
            || preg_match('/\A[0-9a-f]{40,64}\z/D', $inventory['source_oid']) !== 1
            || !is_array($inventory['declarations'])
            || !array_is_list($inventory['declarations'])
            || !is_array($inventory['functions'])
            || !array_is_list($inventory['functions'])
        ) {
            return false;
        }

        return array_all(
            [...$inventory['declarations'], ...$inventory['functions']],
            fn (mixed $entry): bool => $this->isInventoryEntry($entry)
        );
    }

    /**
     * Reports whether one generated structural inventory entry has the closed v1 shape
     */
    private function isInventoryEntry(mixed $entry): bool
    {
        return is_array($entry)
            && array_keys($entry) === ['name', 'source', 'kind', 'members', 'operations']
            && is_string($entry['name'])
            && $entry['name'] !== ''
            && is_string($entry['source'])
            && preg_match(
                '/\Asrc\/(?:Domain|Application|Adapter)\/(?:[A-Za-z0-9_.-]+\/)*[A-Za-z0-9_.-]+\.php\z/D',
                $entry['source']
            ) === 1
            && is_string($entry['kind'])
            && in_array($entry['kind'], ['class', 'interface', 'trait', 'enum', 'function'], true)
            && $this->isMemberShape($entry['members'])
            && is_array($entry['operations'])
            && array_keys($entry['operations']) === self::OPERATIONS
            && array_all($entry['operations'], $this->isOperationShape(...));
    }

    /**
     * Reports whether declaration members have a closed deterministic shape
     */
    private function isMemberShape(mixed $members): bool
    {
        if (!is_array($members) || !array_is_list($members)) {
            return false;
        }

        $names = [];
        foreach ($members as $member) {
            if (
                !is_array($member)
                || array_keys($member) !== ['name', 'signature']
                || !is_string($member['name'])
                || preg_match('/\A(?:constant|case) [A-Za-z_][A-Za-z0-9_]*\z/D', $member['name']) !== 1
                || !is_string($member['signature'])
                || $member['signature'] === ''
            ) {
                return false;
            }

            $names[] = $member['name'];
        }

        $sorted = $names;
        sort($sorted, SORT_STRING);

        return $names === $sorted && count($names) === count(array_unique($names));
    }

    /**
     * Reports whether one operation shape is deterministic or explicitly indeterminate
     */
    private function isOperationShape(mixed $shape): bool
    {
        if ($shape === null) {
            return true;
        }

        return is_array($shape)
            && array_is_list($shape)
            && array_all($shape, static fn (mixed $signature): bool => is_string($signature) && $signature !== '')
            && array_values(array_unique($shape)) === $shape
            && $shape === $this->sorted($shape);
    }

    /**
     * Returns one deterministically sorted operation shape
     *
     * @param array $shape Operation signatures.
     *
     * @phpstan-param list<string> $shape
     *
     * @return list<string>
     */
    private function sorted(array $shape): array
    {
        sort($shape, SORT_STRING);

        return $shape;
    }

    /**
     * Returns complete intentional classifications indexed by structural subject
     *
     * @param array<string, mixed> $manifest
     *
     * @return array<string, array{classification: string, operations: array<string, bool>, members: list<string>}>
     */
    private function classifications(array $manifest): array
    {
        $classifications = [];

        foreach ([...($manifest['declarations'] ?? []), ...($manifest['functions'] ?? [])] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (!is_string($entry['name'] ?? null)) {
                continue;
            }

            if (!in_array($entry['classification'] ?? null, ['public', 'internal'], true)) {
                continue;
            }

            if (!is_array($entry['operations'] ?? null)) {
                continue;
            }

            if (array_keys($entry['operations']) !== self::OPERATIONS) {
                continue;
            }

            $operations = [];
            foreach ($entry['operations'] as $name => $operation) {
                if (!is_array($operation)) {
                    continue 2;
                }

                if (!is_bool($operation['promised'] ?? null)) {
                    continue 2;
                }

                $operations[$name] = $operation['promised'];
            }

            $classifications[$entry['name']] = [
                'classification' => $entry['classification'],
                'operations'     => $operations,
                'members'        => is_array($entry['members'] ?? null) ? array_values(array_filter(array_map(
                    static fn (mixed $member): mixed => is_array($member) ? ($member['name'] ?? null) : $member,
                    $entry['members']
                ), is_string(...))) : []
            ];
        }

        return $classifications;
    }

    /**
     * Reports whether checker evidence uses the supported envelope
     *
     * @param array<string, mixed> $checker
     */
    private function isCheckerEnvelope(array $checker): bool
    {
        return array_keys($checker) === [
            'schema_version',
            'baseline_inventory_sha256',
            'candidate_inventory_sha256',
            'findings'
        ]
            && $checker['schema_version'] === self::CHECKER_SCHEMA
            && is_string($checker['baseline_inventory_sha256'])
            && preg_match('/\A[0-9a-f]{64}\z/D', $checker['baseline_inventory_sha256']) === 1
            && is_string($checker['candidate_inventory_sha256'])
            && preg_match('/\A[0-9a-f]{64}\z/D', $checker['candidate_inventory_sha256']) === 1
            && is_array($checker['findings'])
            && array_is_list($checker['findings']);
    }

    /**
     * Reports whether one checker finding uses a supported exact vocabulary
     *
     * @param mixed               $finding
     * @param array<string, bool> $subjects
     */
    private function isFinding(mixed $finding, array $subjects): bool
    {
        if (
            !is_array($finding)
            || !in_array(array_keys($finding), [
                ['code', 'declaration', 'operation'],
                ['code', 'declaration', 'operation', 'member']
            ], true)
            || !is_string($finding['code'])
            || (!isset(self::SUPPORTED_CODES[$finding['code']]) && $finding['code'] !== 'operation-indeterminate')
            || !is_string($finding['declaration'])
            || !isset($subjects[$finding['declaration']])
            || (!is_null($finding['operation']) && !in_array($finding['operation'], self::OPERATIONS, true))
        ) {
            return false;
        }

        $operationFinding = str_starts_with($finding['code'], 'operation-');
        $memberFinding = str_starts_with($finding['code'], 'member-');

        return $operationFinding === is_string($finding['operation'])
            && $memberFinding === is_string($finding['member'] ?? null);
    }

    /**
     * Returns the exact structural inventory content digest
     *
     * @param array<string, mixed> $inventory
     */
    private function digest(array $inventory): string
    {
        return hash('sha256', json_encode($inventory, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * Returns one stable attributed fail-closed finding
     *
     * @return array<string, mixed>
     */
    private function rejected(
        string $finding,
        string $attribution,
        ?string $subject = null,
        ?string $operation = null,
        ?string $member = null
    ): array {
        $result = [
            'status'         => 'rejected',
            'classification' => 'indeterminate',
            'findings'       => [[
                'finding_id'  => 'release.compatibility.structural-api.'.$finding,
                'attribution' => $attribution,
                'subject'     => $subject,
                'operation'   => $operation
            ]]
        ];
        if ($member !== null) {
            $result['findings'][0]['member'] = $member;
        }

        return $result;
    }
}
