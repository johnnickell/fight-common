<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\CompatibilityInputPort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\StructuralInventoryPort;
use UnexpectedValueException;

/**
 * Class PublicApiManifestAuthority
 *
 * Orchestrates committed compatibility policy validation against repository authorities.
 */
final readonly class PublicApiManifestAuthority implements CompatibilityManifestAuthority
{
    /**
     * Constructs PublicApiManifestAuthority
     */
    public function __construct(
        private ManifestClassificationAuthority $classificationAuthority = new ManifestClassificationAuthority(),
        private ReferencedContractAuthority $referencedContractAuthority = new ReferencedContractAuthority()
    ) {
    }

    /**
     * Validates one committed policy against exact current repository authority
     *
     * @return array<string, mixed>
     */
    public function validate(
        string $manifestPath,
        string $repository,
        CompatibilityInputPort $input,
        StructuralInventoryPort $inventory,
        GitPort $git
    ): array {
        $manifestBytes = $input->read($manifestPath);
        $manifest = json_decode($manifestBytes, true, flags: JSON_THROW_ON_ERROR);
        is_array($manifest) || throw new UnexpectedValueException('The compatibility manifest must be an object.');

        $structuralInventory = $inventory->structuralInventory($repository, str_repeat('0', 64));
        $declarations = array_map(
            static fn (array $entry): array => [
                'name'   => $entry['name'],
                'source' => $entry['source'],
                'layer'  => explode('\\', $entry['name'])[2]
            ],
            $structuralInventory['declarations']
        );
        $functions = array_map(
            static fn (array $entry): array => [
                'name'   => $entry['name'],
                'source' => $entry['source'],
                'layer'  => 'Domain'
            ],
            $structuralInventory['functions']
        );
        $sourceFacts = array_column([...$declarations, ...$functions], 'source', 'name');
        $structuralFacts = array_column(
            [...$structuralInventory['declarations'], ...$structuralInventory['functions']],
            null,
            'name'
        );
        $composerBytes = $input->read($repository.'/composer.json');
        $composer = json_decode($composerBytes, true, flags: JSON_THROW_ON_ERROR);
        is_array($composer) || throw new UnexpectedValueException('Composer authority must be an object.');

        $classificationsAreIntentional = $this->classificationAuthority->isIntentional(
            $manifest,
            $declarations,
            $functions,
            $sourceFacts,
            $structuralFacts
        );
        $referencedContractsAreIntentional = $this->referencedContractAuthority->isIntentional(
            $manifest,
            $repository,
            $input,
            $composer
        );

        if (!$classificationsAreIntentional || !$referencedContractsAreIntentional) {
            $missingClassifications = $this->classificationAuthority->missingClassifications(
                $manifest,
                $declarations,
                $functions,
                $sourceFacts,
                $structuralFacts,
                $referencedContractsAreIntentional
            );
            if (is_array($missingClassifications)) {
                $findings = array_map(
                    CompatibilityFinding::missingClassification(...),
                    $missingClassifications
                );
                throw new CompatibilityManifestRejected(...$findings);
            }

            throw new UnexpectedValueException(
                'The committed compatibility manifest is not complete intentional authority.'
            );
        }

        $baselineAuthority = $this->classificationAuthority->baseline();
        $baseline = $git->resolveExactAnnotatedTag($baselineAuthority['tag_name']);
        ($baseline->tagObjectOid === $baselineAuthority['tag_object_oid']
            && $baseline->peeledCommitOid === $baselineAuthority['peeled_commit_oid'])
            || throw new UnexpectedValueException('The authoritative baseline identity is not resolved.');

        $manifestDeclarations = $manifest['declarations'];
        $byName = array_column($manifestDeclarations, null, 'name');

        return [
            'status'                  => 'valid',
            'schema_version'          => $this->classificationAuthority->schemaVersion(),
            'baseline'                => $baselineAuthority,
            'inventory'               => $this->classificationAuthority->inventory(),
            'classifications'         => $this->classificationAuthority->classificationCounts(
                $manifestDeclarations
            ),
            'operation_examples'      => [
                'Fight\Common\Domain\Messaging\Command\Command'              => $this->operationPromises(
                    $byName['Fight\Common\Domain\Messaging\Command\Command']
                ),
                'Fight\Common\Domain\Specification\CompositeSpecification'   => $this->operationPromises(
                    $byName['Fight\Common\Domain\Specification\CompositeSpecification']
                ),
                'Fight\Common\Domain\Value\Identifier\Uuid'                  => $this->operationPromises(
                    $byName['Fight\Common\Domain\Value\Identifier\Uuid']
                ),
                'Fight\Common\Adapter\Observability\Metrics\UdpMetricSender' => $this->operationPromises(
                    $byName['Fight\Common\Adapter\Observability\Metrics\UdpMetricSender']
                )
            ],
            'behavioral_contract_ids' => $this->referencedContractAuthority->behavioralContractIds(),
            'package_promise_ids'     => $this->referencedContractAuthority->packagePromiseIds()
        ];
    }

    /**
     * Extracts independent operation decisions from one declaration entry
     *
     * @param array<string, mixed> $declaration
     *
     * @return array<string, bool>
     */
    private function operationPromises(array $declaration): array
    {
        return array_map(
            static fn (array $operation): bool => $operation['promised'],
            $declaration['operations']
        );
    }
}
