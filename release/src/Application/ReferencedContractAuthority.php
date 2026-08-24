<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\CompatibilityInputPort;

/**
 * Class ReferencedContractAuthority
 *
 * Owns referenced evidence, behavioral contracts, and Composer package-surface promises.
 */
final readonly class ReferencedContractAuthority
{
    private const array BEHAVIORAL_CONTRACT_REFERENCES = [
        'fight-common.behavior.event-dispatch-complete-fanout' => [
            'normative' => ['path' => 'CONTEXT.md', 'section' => 'Messaging and CQRS'],
            'fixture'   => ['path' => 'tests/Adapter/Messaging/Event/Sync/SimpleEventDispatcherTest.php']
        ],
        'fight-common.behavior.message-meta-isolation'         => [
            'normative' => ['path' => 'CONTEXT.md', 'section' => 'Messaging and CQRS'],
            'fixture'   => ['path' => 'tests/Domain/Messaging/Event/EventMessageTest.php']
        ],
        'fight-common.behavior.stored-event-stable-identity'   => [
            'normative' => [
                'path'    => 'docs/event-sourcing.md',
                'section' => 'Migrate durable names and event schemas'
            ],
            'fixture'   => ['path' => 'tests/Domain/EventSourcing/StoredEventTest.php']
        ],
        'fight-common.behavior.scheduler-legacy-construction'  => [
            'normative' => [
                'path'    => 'docs/scheduler.md',
                'section' => 'Legacy 1.x Construction Compatibility'
            ],
            'fixture'   => ['path' => 'release/fixtures/PublicApiConsumer/probe.php']
        ],
        'fight-common.behavior.scheduler-legacy-command'       => [
            'normative' => [
                'path'    => 'docs/scheduler.md',
                'section' => 'Legacy Command Compatibility Bridge'
            ],
            'fixture'   => ['path' => 'release/fixtures/PublicApiConsumer/probe.php']
        ],
        'fight-common.behavior.scheduler-portable-runner'      => [
            'normative' => [
                'path'    => 'docs/scheduler.md',
                'section' => 'Portable ProcessRunner Composition'
            ],
            'fixture'   => ['path' => 'tests/Application/Scheduler/SchedulerTest.php']
        ]
    ];
    private const array PACKAGE_PROMISE_REFERENCES = [
        'fight-common.package.name'                      => [
            'normative' => ['path' => 'composer.json'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ],
        'fight-common.package.production-autoload'       => [
            'normative' => ['path' => 'composer.json'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ],
        'fight-common.package.runtime-requirements'      => [
            'normative' => ['path' => 'composer.json'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ],
        'fight-common.package.conflict'                  => [
            'normative' => ['path' => 'composer.json'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ],
        'fight-common.package.provide'                   => [
            'normative' => ['path' => 'composer.json'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ],
        'fight-common.package.replace'                   => [
            'normative' => ['path' => 'composer.json'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ],
        'fight-common.package.composer-plugin-metadata'  => [
            'normative' => ['path' => 'composer.json'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ],
        'fight-common.package.exported-archive-boundary' => [
            'normative' => ['path' => 'release/README.md', 'section' => 'Loading and distribution'],
            'fixture'   => ['path' => 'release/tests/Tooling/PackageSurfaceAuthorityTest.php']
        ]
    ];
    private const array EXPORTED_ARCHIVE_BOUNDARY = [
        'archive'                           => ['name' => null, 'exclude' => []],
        'production_content_roots'          => ['src', 'tests/TestCase'],
        'maintainer_module_may_be_exported' => true
    ];

    /**
     * Reports whether every referenced contract and package promise is intentional
     *
     * @param array<string, mixed> $manifest   Manifest policy candidate.
     * @param string               $repository Repository root.
     * @param CompatibilityInputPort $input   Repository evidence boundary.
     * @param array<string, mixed> $composer   Composer authority.
     */
    public function isIntentional(
        array $manifest,
        string $repository,
        CompatibilityInputPort $input,
        array $composer
    ): bool {
        $evidence = $manifest['evidence_authorities'] ?? [];
        $behavioralContracts = $manifest['behavioral_contracts'] ?? [];
        $packagePromises = $manifest['package_promises'] ?? [];

        return $this->referencesExist($repository, array_values($evidence), $input)
            && $this->contractReferencesMatch(
                $repository,
                $behavioralContracts,
                self::BEHAVIORAL_CONTRACT_REFERENCES,
                false,
                $input
            )
            && $this->contractReferencesMatch(
                $repository,
                $packagePromises,
                self::PACKAGE_PROMISE_REFERENCES,
                true,
                $input
            )
            && $this->packagePromisesMatch($packagePromises, $composer);
    }

    /**
     * Returns the closed behavioral contract identifiers
     *
     * @return list<string>
     */
    public function behavioralContractIds(): array
    {
        return array_keys(self::BEHAVIORAL_CONTRACT_REFERENCES);
    }

    /**
     * Returns the closed package promise identifiers
     *
     * @return list<string>
     */
    public function packagePromiseIds(): array
    {
        return array_keys(self::PACKAGE_PROMISE_REFERENCES);
    }

    /**
     * Checks that classification and operation evidence resolves inside the repository
     *
     * @param string                 $repository Repository root.
     * @param list<array<mixed>> $references
     * @param CompatibilityInputPort $input      Repository evidence boundary.
     */
    private function referencesExist(
        string $repository,
        array $references,
        CompatibilityInputPort $input
    ): bool {
        return array_all(
            $references,
            static fn (array $reference): bool => (
                isset($reference['path']) && $input->isFile($repository.'/'.$reference['path'])
            )
        );
    }

    /**
     * Checks each ID against its closed normative and designated-fixture binding
     *
     * @param string                                    $repository Repository root.
     * @param list<array<string, mixed>>                 $contracts  Manifest contracts.
     * @param array<string, array<string, array<string>>> $expected   Closed per-ID references.
     * @param boolean                                      $withValue  Whether entries carry package values.
     * @param CompatibilityInputPort                    $input      Repository evidence boundary.
     */
    private function contractReferencesMatch(
        string $repository,
        array $contracts,
        array $expected,
        bool $withValue,
        CompatibilityInputPort $input
    ): bool {
        if (array_column($contracts, 'id') !== array_keys($expected)) {
            return false;
        }

        foreach ($contracts as $contract) {
            $keys = $withValue ? ['id', 'value', 'normative', 'fixture'] : ['id', 'normative', 'fixture'];
            if (array_keys($contract) !== $keys) {
                return false;
            }

            $reference = $expected[$contract['id']];
            if (
                $contract['normative'] !== $reference['normative']
                || $contract['fixture'] !== $reference['fixture']
                || !$input->isFile($repository.'/'.$reference['normative']['path'])
                || !$input->isFile($repository.'/'.$reference['fixture']['path'])
                || !$this->sectionResolves($repository, $reference['normative'], $input)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reports whether an optional section locator names exactly one actual ATX Markdown heading
     *
     * @param string                $repository Repository root.
     * @param array<string, string> $reference  Closed normative reference.
     * @param CompatibilityInputPort $input     Repository evidence boundary.
     */
    private function sectionResolves(
        string $repository,
        array $reference,
        CompatibilityInputPort $input
    ): bool {
        if (!isset($reference['section'])) {
            return true;
        }

        $matches = 0;
        $fence = null;
        $lines = preg_split('/\R/', $input->read($repository.'/'.$reference['path']));
        assert(is_array($lines));

        foreach ($lines as $line) {
            if (preg_match('/^[ ]{0,3}(`{3,}|~{3,})/', $line, $fenceMatch) === 1) {
                $marker = $fenceMatch[1][0];
                if ($fence === null) {
                    $fence = $marker;
                } elseif ($fence === $marker) {
                    $fence = null;
                }

                continue;
            }

            if ($fence !== null) {
                continue;
            }

            if (
                preg_match('/^[ ]{0,3}#{1,6}[\t ]+(.+?)[\t ]*#*[\t ]*$/', $line, $heading) === 1
                && $heading[1] === $reference['section']
            ) {
                ++$matches;
            }
        }

        return $matches === 1;
    }

    /**
     * Checks committed package promises against Composer authority
     *
     * @param list<array<string, mixed>> $promises
     * @param array<string, mixed>       $composer
     */
    private function packagePromisesMatch(array $promises, array $composer): bool
    {
        $byId = array_column($promises, null, 'id');
        $requirements = $composer['require'];
        $php = $requirements['php'];
        unset($requirements['php']);
        $extensions = array_filter(
            $requirements,
            static fn (string $package): bool => str_starts_with($package, 'ext-'),
            ARRAY_FILTER_USE_KEY
        );
        $dependencies = array_diff_key($requirements, $extensions);

        return $byId['fight-common.package.name']['value'] === $composer['name']
            && $byId['fight-common.package.production-autoload']['value'] === $composer['autoload']
            && $byId['fight-common.package.runtime-requirements']['value'] === [
                'php'          => $php,
                'extensions'   => $extensions,
                'dependencies' => $dependencies
            ]
            && $byId['fight-common.package.conflict']['value'] === ($composer['conflict'] ?? [])
            && $byId['fight-common.package.provide']['value'] === ($composer['provide'] ?? [])
            && $byId['fight-common.package.replace']['value'] === ($composer['replace'] ?? [])
            && $byId['fight-common.package.composer-plugin-metadata']['value'] === [
                'type'          => $composer['type'] ?? 'library',
                'extra'         => $composer['extra'] ?? [],
                'allow-plugins' => $composer['config']['allow-plugins'] ?? []
            ]
            && $byId['fight-common.package.exported-archive-boundary']['value'] === self::EXPORTED_ARCHIVE_BOUNDARY;
    }
}
