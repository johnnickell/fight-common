<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use RuntimeException;

/**
 * Class PackageSurfaceInspector
 */
final readonly class PackageSurfaceInspector
{
    /**
     * Constructs PackageSurfaceInspector
     *
     * @param PhpParserStructuralInventory $inventory Structural inventory adapter
     */
    public function __construct(private PhpParserStructuralInventory $inventory = new PhpParserStructuralInventory())
    {
    }

    /**
     * Validates an exported package surface against the compatibility manifest
     *
     * @return array<string, mixed>
     */
    public function inspect(string $packageRoot, string $manifestPath, string $commit): array
    {
        $manifestBytes = file_get_contents($manifestPath);
        is_string($manifestBytes) || throw new RuntimeException('The compatibility manifest is unreadable.');
        $manifest = json_decode($manifestBytes, true, flags: JSON_THROW_ON_ERROR);
        is_array($manifest) || throw new RuntimeException('The compatibility manifest must be an object.');

        $inventory = $this->inventory->structuralInventory($packageRoot, $commit);
        $expected = [...($manifest['declarations'] ?? []), ...($manifest['functions'] ?? [])];
        $actual = [...$inventory['declarations'], ...$inventory['functions']];
        $expectedByName = array_column($expected, null, 'name');
        $actualByName = array_column($actual, null, 'name');

        foreach ($expectedByName as $name => $entry) {
            $candidate = $actualByName[$name] ?? null;
            if (!is_array($candidate)) {
                throw new RuntimeException('Manifest declaration is absent from the package: '.$name);
            }

            if (($candidate['source'] ?? null) !== ($entry['source'] ?? null)) {
                throw new RuntimeException('Manifest source differs from the package: '.$name);
            }

            if (isset($entry['kind']) && ($candidate['kind'] ?? null) !== $entry['kind']) {
                throw new RuntimeException('Manifest declaration kind differs from the package: '.$name);
            }

            $expectedMembers = array_column($entry['members'] ?? [], 'signature', 'name');
            $actualMembers = array_column($candidate['members'] ?? [], 'signature', 'name');
            ksort($expectedMembers, SORT_STRING);
            ksort($actualMembers, SORT_STRING);
            if (($entry['classification'] ?? null) === 'public' && $actualMembers !== $expectedMembers) {
                throw new RuntimeException('Manifest members differ from the package: '.$name);
            }

            foreach (['callable', 'constructible', 'extensible', 'implementable'] as $operation) {
                $promise = $entry['operations'][$operation]['promised'] ?? null;
                is_bool($promise)
                    || throw new RuntimeException('Manifest operation promise is invalid: '.$name.' '.$operation);
                $shapes = $candidate['operations'][$operation] ?? null;
                is_array($shapes)
                    || throw new RuntimeException('Package operation inventory is invalid: '.$name.' '.$operation);
            }
        }

        $unclassified = array_diff_key($actualByName, $expectedByName);
        if ($unclassified !== []) {
            throw new RuntimeException(
                'Package declaration is absent from the manifest: '.array_key_first($unclassified)
            );
        }

        $counts = ['Domain' => 0, 'Application' => 0, 'Adapter' => 0];
        foreach ($actual as $entry) {
            $layer = explode('\\', $entry['name'])[2] ?? null;
            if (is_string($layer) && array_key_exists($layer, $counts)) {
                ++$counts[$layer];
            }
        }

        foreach ($counts as $layer => $count) {
            $expectation = $manifest['inventory_expectations'][$layer] ?? null;
            $expectedCount = null;
            if (is_array($expectation)) {
                $expectedCount = ($expectation['declarations'] ?? 0) + ($expectation['functions'] ?? 0);
            }

            if ($expectedCount !== $count) {
                throw new RuntimeException('Package inventory count differs for '.$layer.'.');
            }
        }

        $canonicalInventory = $this->canonicalJson($inventory);
        $operationShapesSha256 = hash('sha256', $this->canonicalJson(array_map(
            static fn (array $entry): array => [
                'name'       => $entry['name'],
                'operations' => $entry['operations']
            ],
            $actual
        )));
        $operationShapesSha256 === ($manifest['operation_shapes_sha256'] ?? null)
            || throw new RuntimeException(
                'Package operation shapes differ from the manifest: '.$operationShapesSha256
            );
        $packagePromises = $this->inspectPackagePromises($packageRoot, $manifest);

        return [
            'status'                  => 'passed',
            'manifest_schema_version' => $manifest['schema_version'] ?? null,
            'manifest_sha256'         => hash('sha256', $manifestBytes),
            'inventory_sha256'        => hash('sha256', $canonicalInventory),
            'operation_shapes_sha256' => $operationShapesSha256,
            'package_promises_sha256' => $packagePromises['sha256'],
            'package_promises'        => $packagePromises['count'],
            'inventory_counts'        => $counts,
            'classified_declarations' => count($expectedByName),
            'unexpected_declarations' => 0
        ];
    }

    /**
     * Returns installed Composer metadata and archive-boundary comparison evidence
     *
     * @param string               $packageRoot Installed package root
     * @param array<string, mixed> $manifest    Compatibility manifest
     *
     * @return array{count: int, sha256: string}
     */
    private function inspectPackagePromises(string $packageRoot, array $manifest): array
    {
        $composerBytes = file_get_contents($packageRoot.'/composer.json');
        is_string($composerBytes) || throw new RuntimeException('The installed Composer manifest is unreadable.');
        $composer = json_decode($composerBytes, true, flags: JSON_THROW_ON_ERROR);
        is_array($composer) || throw new RuntimeException('The installed Composer manifest must be an object.');
        $requirements = $composer['require'] ?? [];
        is_array($requirements) || throw new RuntimeException('The installed runtime requirements are invalid.');
        $extensions = [];
        $dependencies = [];
        foreach ($requirements as $name => $constraint) {
            if ($name === 'php') {
                continue;
            }

            if (str_starts_with((string) $name, 'ext-')) {
                $extensions[$name] = $constraint;
                continue;
            }

            $dependencies[$name] = $constraint;
        }

        ksort($extensions, SORT_STRING);
        ksort($dependencies, SORT_STRING);

        $autoload = $composer['autoload'] ?? [];
        is_array($autoload) || throw new RuntimeException('The installed production autoload is invalid.');
        $productionRoots = array_values(array_unique(array_values($autoload['psr-4'] ?? [])));
        sort($productionRoots, SORT_STRING);
        $archive = $composer['archive'] ?? [];
        is_array($archive) || throw new RuntimeException('The installed archive configuration is invalid.');
        $promises = $manifest['package_promises'] ?? null;
        is_array($promises) && $promises !== []
            || throw new RuntimeException('Manifest package promises are absent.');
        $manifestValues = [];
        foreach ($promises as $promise) {
            is_array($promise) && is_string($promise['id'] ?? null) && array_key_exists('value', $promise)
                || throw new RuntimeException('A manifest package promise is invalid.');
            !array_key_exists($promise['id'], $manifestValues)
                || throw new RuntimeException('A manifest package promise is duplicated.');
            $manifestValues[$promise['id']] = $promise['value'];
        }

        $expected = [
            'fight-common.package.name'                      => $composer['name'] ?? null,
            'fight-common.package.production-autoload'       => $autoload,
            'fight-common.package.runtime-requirements'      => [
                'php'          => $requirements['php'] ?? null,
                'extensions'   => $extensions,
                'dependencies' => $dependencies
            ],
            'fight-common.package.conflict'                  => $composer['conflict'] ?? [],
            'fight-common.package.provide'                   => $composer['provide'] ?? [],
            'fight-common.package.replace'                   => $composer['replace'] ?? [],
            'fight-common.package.composer-plugin-metadata'  => [
                'type'          => $composer['type'] ?? 'library',
                'extra'         => $composer['extra'] ?? [],
                'allow-plugins' => $composer['config']['allow-plugins'] ?? []
            ],
            'fight-common.package.exported-archive-boundary' => [
                'archive'                           => [
                    'name'    => $archive['name'] ?? null,
                    'exclude' => $archive['exclude'] ?? []
                ],
                'production_content_roots'          => $productionRoots,
                'maintainer_module_may_be_exported' => is_dir($packageRoot.'/release')
            ]
        ];

        array_keys($manifestValues) === array_keys($expected)
            || throw new RuntimeException('Manifest package promise identities are incomplete or out of order.');

        foreach ($manifestValues as $id => $value) {
            $candidate = $expected[$id] ?? null;
            $this->canonicalJson(['value' => $candidate]) === $this->canonicalJson(['value' => $value])
                || throw new RuntimeException('Package promise differs from the installed archive: '.$id);
        }

        return [
            'count'  => count($manifestValues),
            'sha256' => hash('sha256', $this->canonicalJson($manifestValues))
        ];
    }

    /**
     * Encodes recursively key-sorted JSON
     *
     * @param array<mixed> $value Value to encode
     */
    private function canonicalJson(array $value): string
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Sorts every object-like array by key
     *
     * @param array<mixed> $value Value to sort
     *
     * @return array<mixed>
     */
    private function sortKeys(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        return $value;
    }
}
