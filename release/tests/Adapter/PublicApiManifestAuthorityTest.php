<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Common\Adapter\Observability\Metrics\UdpMetricSender;
use Fight\Common\Adapter\Repository\DoctrineRepository;
use Fight\Common\Application\HttpClient\Exception\Exception;
use Fight\Common\Application\HttpFoundation\HttpMethod;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\MessageType;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\Internet\Uri;
use Fight\Release\Adapter\LocalCompatibilityInput;
use Fight\Release\Adapter\LocalGitPort;
use Fight\Release\Adapter\PhpParserStructuralInventory;
use Fight\Release\Application\Boundary\CompatibilityInputPort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Release\Application\Boundary\ReleaseEffect;
use Fight\Release\Application\Boundary\StructuralInventoryPort;
use Fight\Release\Application\CompatibilityFinding;
use Fight\Release\Application\CompatibilityManifestRejected;
use Fight\Release\Application\ManifestClassificationAuthority;
use Fight\Release\Application\OperationEvidenceAuthority;
use Fight\Release\Application\PublicApiManifestAuthority;
use Fight\Release\Application\ReferencedContractAuthority;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use UnexpectedValueException;

/**
 * Class PublicApiManifestAuthorityTest
 */
#[CoversClass(PublicApiManifestAuthority::class)]
#[CoversClass(ManifestClassificationAuthority::class)]
#[CoversClass(OperationEvidenceAuthority::class)]
#[CoversClass(ReferencedContractAuthority::class)]
#[CoversClass(CompatibilityManifestRejected::class)]
#[CoversClass(LocalCompatibilityInput::class)]
#[CoversClass(PhpParserStructuralInventory::class)]
#[CoversClass(LocalGitPort::class)]
final class PublicApiManifestAuthorityTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves that a generic whole-file authority cannot stand in for declaration-bound evidence.
     */
    public function test_that_unbound_or_non_reviewable_evidence_fails_validation(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = [];
        $git = new LocalGitPort(
            $root,
            static function (ReleaseEffect $effect, ReleaseBoundaryOutcome $outcome) use (&$effects): void {
                $effects[] = [$effect->value, $outcome->value];
            }
        );
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [$input, $this->cachedInventory($facts), $git];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $genericWholeFileAuthority = $manifest;
        $genericWholeFileAuthority['evidence_authorities'][
            'fight-common.classification.baseline-grandfathered'
        ] = ['path' => 'planning/adr/0009-public-api-manifest-baseline.md'];
        $this->assertInvalidManifest($genericWholeFileAuthority, $root, $operations);

        $wrongSubject = $manifest;
        $wrongSubject['declarations'][0]['evidence_binding']['subject'] = Uuid::class;
        $this->assertInvalidManifest($wrongSubject, $root, $operations);

        $wrongSourceLocator = $manifest;
        $wrongSourceLocator['declarations'][0]['evidence_binding']['source_locator'] = 'working-tree:wrong.php';
        $this->assertInvalidManifest($wrongSourceLocator, $root, $operations);

        $malformedBinding = $manifest;
        unset($malformedBinding['declarations'][0]['evidence_binding']['source_locator']);
        $this->assertInvalidManifest($malformedBinding, $root, $operations);

        $swappedBindings = $manifest;
        $firstSource = $swappedBindings['declarations'][0]['source'];
        $secondSource = $swappedBindings['declarations'][1]['source'];
        foreach ([0 => $secondSource, 1 => $firstSource] as $index => $falseSource) {
            $entry = &$swappedBindings['declarations'][$index];
            $entry['source'] = $falseSource;
            $entry['evidence_binding']['source_locator'] = sprintf(
                '%s:%s#%s',
                $manifest['baseline']['peeled_commit_oid'],
                $falseSource,
                $entry['name']
            );
            $entry['classification_evidence']['rationale'] = str_replace(
                $index === 0 ? $firstSource : $secondSource,
                $falseSource,
                $entry['classification_evidence']['rationale']
            );
            unset($entry);
        }

        $this->assertInvalidManifest($swappedBindings, $root, $operations);

        $falseFunctionBinding = $manifest;
        $function = &$falseFunctionBinding['functions'][0];
        $actualFunctionSource = $function['source'];
        $falseFunctionSource = 'src/Domain/ArrayList.php';
        $function['source'] = $falseFunctionSource;
        $function['evidence_binding']['source_locator'] = sprintf(
            '%s:%s#%s',
            $manifest['baseline']['peeled_commit_oid'],
            $falseFunctionSource,
            $function['name']
        );
        $function['classification_evidence']['rationale'] = str_replace(
            $actualFunctionSource,
            $falseFunctionSource,
            $function['classification_evidence']['rationale']
        );
        unset($function);
        $this->assertInvalidManifest($falseFunctionBinding, $root, $operations);

        $inaccurateRationale = $manifest;
        $inaccurateRationale['declarations'][0]['classification_evidence']['rationale'] = sprintf(
            '%s Not %s.',
            $inaccurateRationale['declarations'][0]['classification_evidence']['rationale'],
            $inaccurateRationale['declarations'][1]['source']
        );
        $this->assertInvalidManifest($inaccurateRationale, $root, $operations);

        $missingClassificationRationale = $manifest;
        $missingClassificationRationale['declarations'][0]['classification_evidence']['rationale'] = '';
        $this->assertInvalidManifest($missingClassificationRationale, $root, $operations);

        $malformedClassificationEvidence = $manifest;
        unset($malformedClassificationEvidence['declarations'][0]['classification_evidence']['rationale']);
        $this->assertInvalidManifest($malformedClassificationEvidence, $root, $operations);

        $unknownClassificationAuthority = $manifest;
        $unknownClassificationAuthority['declarations'][0]['classification_evidence']['authority'] = 'unknown';
        $entry = $unknownClassificationAuthority['declarations'][0];
        $unknownClassificationAuthority['declarations'][0]['evidence_binding']['source_locator']
            = 'working-tree:'.$entry['source'].'#'.$entry['name'];
        $this->assertInvalidManifest($unknownClassificationAuthority, $root, $operations);

        $missingOperationRationale = $manifest;
        $missingOperationRationale['declarations'][0]['operations']['callable']['evidence']['rationale'] = '';
        $this->assertInvalidManifest($missingOperationRationale, $root, $operations);

        $malformedOperationEvidence = $manifest;
        $malformedOperationEvidence['declarations'][0]['operations']['callable']['evidence'] = 'unknown';
        $this->assertInvalidManifest($malformedOperationEvidence, $root, $operations);

        $nonAffirmativeExtension = $manifest;
        $extensible = array_find_key(
            $nonAffirmativeExtension['declarations'],
            static fn (array $entry): bool => $entry['operations']['extensible']['promised']
        );
        self::assertIsInt($extensible);
        $nonAffirmativeExtension['declarations'][$extensible]['operations']['extensible']['evidence']['authority']
            = 'fight-common.operation.not-promised';
        $this->assertInvalidManifest($nonAffirmativeExtension, $root, $operations);
    }

    /**
     * Proves affirmative operation authority must agree with independently scanned declaration facts.
     */
    public function test_that_self_consistent_false_operation_authority_fails_validation(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [
            $input,
            $this->cachedInventory($facts),
            new LocalGitPort($root, $effects)
        ];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $exceptionInterfaceClaim = $manifest;
        $exceptionInterface = array_find_key(
            $exceptionInterfaceClaim['declarations'],
            static fn (array $entry): bool => $entry['name'] === Exception::class
        );
        self::assertIsInt($exceptionInterface);
        $exceptionInterfaceClaim['declarations'][$exceptionInterface]['operations']['extensible'] = [
            'promised' => true,
            'evidence' => [
                'authority' => 'fight-common.operation.exception-subtyping',
                'rationale' => sprintf(
                    '%s is an exception contract intended to support consumer subtypes.',
                    Exception::class
                ),
                'binding'   => $manifest['declarations'][$exceptionInterface]['operations']['extensible'][
                    'evidence'
                ]['binding']
            ]
        ];
        $this->assertInvalidManifest($exceptionInterfaceClaim, $root, $operations);

        $concreteAbstractBaseClaim = $manifest;
        $uuid = array_find_key(
            $concreteAbstractBaseClaim['declarations'],
            static fn (array $entry): bool => $entry['name'] === Uuid::class
        );
        self::assertIsInt($uuid);
        $concreteAbstractBaseClaim['declarations'][$uuid]['operations']['extensible'] = [
            'promised' => true,
            'evidence' => [
                'authority' => 'fight-common.operation.abstract-extension-base',
                'rationale' => Uuid::class.' is an abstract base designed for consumer specialization.',
                'binding'   => $manifest['declarations'][$uuid]['operations']['extensible']['evidence']['binding']
            ]
        ];
        $this->assertInvalidManifest($concreteAbstractBaseClaim, $root, $operations);
    }

    /**
     * Proves each operation decision is bound to its exact subject, source, and operation axis.
     */
    public function test_that_swapped_operation_evidence_binding_fails_validation(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [
            $input,
            $this->cachedInventory($facts),
            new LocalGitPort($root, $effects)
        ];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $manifest['declarations'][0]['operations']['callable']['evidence']['binding']
            = $manifest['declarations'][0]['operations']['constructible']['evidence']['binding'];

        $this->assertInvalidManifest($manifest, $root, $operations);

        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $manifest['declarations'][0]['operations']['callable']['evidence']['binding']
            = $manifest['declarations'][1]['operations']['callable']['evidence']['binding'];

        $this->assertInvalidManifest($manifest, $root, $operations);
    }

    /**
     * Proves an affirmative call or construction promise requires a non-empty matching scanner fact.
     */
    public function test_that_empty_callable_or_constructible_fact_cannot_authenticate_a_promise(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [
            $input,
            $this->cachedInventory($facts),
            new LocalGitPort($root, $effects)
        ];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $intentionalManifest = $manifest;
        $byName = array_column($facts['declarations'], null, 'name');
        self::assertSame(['parent none'], $byName[HttpMethod::class]['operations']['callable']);
        self::assertContains(
            ['name' => 'constant GET', 'signature' => "public const string GET = 'GET'"],
            $byName[HttpMethod::class]['members']
        );
        self::assertContains(
            ['name' => 'case COMMAND', 'signature' => "case COMMAND = 'command'"],
            $byName[MessageType::class]['members']
        );
        $httpMethod = array_find_key(
            $manifest['declarations'],
            static fn (array $entry): bool => $entry['name'] === HttpMethod::class
        );
        self::assertIsInt($httpMethod);
        $manifest['declarations'][$httpMethod]['operations']['callable']['promised'] = true;
        $manifest['declarations'][$httpMethod]['operations']['callable']['evidence']['authority']
            = 'fight-common.operation.public-call';
        $manifest['declarations'][$httpMethod]['operations']['callable']['evidence']['rationale']
            = HttpMethod::class.' promises its inventoried public callable surface to consumers.';
        $this->assertInvalidManifest($manifest, $root, $operations);

        $manifest = $intentionalManifest;
        $command = array_find_key(
            $manifest['declarations'],
            static fn (array $entry): bool => $entry['name'] === Command::class
        );
        self::assertIsInt($command);
        self::assertSame([], $byName[Command::class]['operations']['constructible']);
        $manifest['declarations'][$command]['operations']['constructible']['promised'] = true;
        $manifest['declarations'][$command]['operations']['constructible']['evidence']['authority']
            = 'fight-common.operation.public-construction';
        $manifest['declarations'][$command]['operations']['constructible']['evidence']['rationale']
            = Command::class.' promises construction through its inventoried public constructor or named factory.';
        $this->assertInvalidManifest($manifest, $root, $operations);
    }

    /**
     * Proves every scanner-derived public constant and enum case needs exact member-bound policy.
     */
    public function test_that_public_member_policy_is_complete_and_exactly_bound(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [$input, $this->cachedInventory($facts), new LocalGitPort($root, $effects)];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $httpMethod = array_find_key(
            $manifest['declarations'],
            static fn (array $entry): bool => $entry['name'] === HttpMethod::class
        );
        $messageType = array_find_key(
            $manifest['declarations'],
            static fn (array $entry): bool => $entry['name'] === MessageType::class
        );
        self::assertIsInt($httpMethod);
        self::assertIsInt($messageType);

        $missing = $manifest;
        array_shift($missing['declarations'][$httpMethod]['members']);
        $this->assertInvalidManifest($missing, $root, $operations);

        $swapped = $manifest;
        $swapped['declarations'][$messageType]['members'][0]['evidence']['binding']['member'] = 'case QUERY';
        $this->assertInvalidManifest($swapped, $root, $operations);

        $nonList = $manifest;
        $nonList['declarations'][$httpMethod]['members'] = 'not-a-member-list';
        $this->assertInvalidManifest($nonList, $root, $operations);

        $missingFacts = $facts;
        $factIndex = array_find_key(
            $missingFacts['declarations'],
            static fn (array $entry): bool => $entry['name'] === HttpMethod::class
        );
        self::assertIsInt($factIndex);
        $missingFacts['declarations'][$factIndex]['members'] = 'not-scanner-member-facts';
        $this->assertInvalidManifest(
            $manifest,
            $root,
            [$input, $this->cachedInventory($missingFacts), new LocalGitPort($root, $effects)]
        );

        $malformedMember = $manifest;
        $malformedMember['declarations'][$messageType]['members'][0] = 'not-a-member-policy';
        $this->assertInvalidManifest($malformedMember, $root, $operations);
    }

    /**
     * Proves malformed or cyclic inherited operation facts cannot authenticate an affirmative promise.
     */
    public function test_that_malformed_or_cyclic_inherited_operation_facts_fail_closed(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $manifestByName = array_flip(array_column($manifest['declarations'], 'name'));
        $factByName = array_flip(array_column($facts['declarations'], 'name'));
        $httpMethod = $manifestByName[HttpMethod::class];
        $httpMethodFact = $factByName[HttpMethod::class];
        $composite = $manifestByName[CompositeSpecification::class];
        $assertInvalidFacts = function (array $mutation, array $candidate) use ($input, $root, $effects): void {
            $this->assertInvalidManifest(
                $candidate,
                $root,
                [$input, $this->cachedInventory($mutation), new LocalGitPort($root, $effects)]
            );
        };
        $callableClaim = $manifest;
        $callableClaim['declarations'][$httpMethod]['operations']['callable']['promised'] = true;
        $callableClaim['declarations'][$httpMethod]['operations']['callable']['evidence']['authority']
            = 'fight-common.operation.public-call';
        $callableClaim['declarations'][$httpMethod]['operations']['callable']['evidence']['rationale']
            = HttpMethod::class.' promises its inventoried public callable surface to consumers.';

        $malformedCallable = $facts;
        $malformedCallable['declarations'][$httpMethodFact]['operations']['callable'] = null;
        $assertInvalidFacts($malformedCallable, $callableClaim);

        $cyclicCallable = $facts;
        $cyclicCallable['declarations'][$httpMethodFact]['operations']['callable']
            = ['parent \\'.HttpMethod::class];
        $assertInvalidFacts($cyclicCallable, $callableClaim);

        $missingCallableParent = $facts;
        $missingCallableParent['declarations'][$httpMethodFact]['operations']['callable']
            = ['parent \\Fight\\Common\\MissingCallableParent'];
        $assertInvalidFacts($missingCallableParent, $callableClaim);

        $abstractConstruction = $manifest;
        $abstractConstruction['declarations'][$composite]['operations']['constructible']['promised'] = true;
        $abstractConstruction['declarations'][$composite]['operations']['constructible']['evidence']['authority']
            = 'fight-common.operation.public-construction';
        $abstractConstruction['declarations'][$composite]['operations']['constructible']['evidence']['rationale']
            = sprintf(
                '%s promises construction through its inventoried public constructor or named factory.',
                CompositeSpecification::class
            );
        $assertInvalidFacts($facts, $abstractConstruction);

        $malformedConstruction = $facts;
        $malformedConstruction['declarations'][$httpMethodFact]['operations']['constructible'] = null;
        $assertInvalidFacts($malformedConstruction, $manifest);

        $cyclicConstruction = $facts;
        $cyclicConstruction['declarations'][$httpMethodFact]['operations']['constructible']
            = ['inherits constructor \\'.HttpMethod::class];
        $assertInvalidFacts($cyclicConstruction, $manifest);

        $missingConstructorParent = $facts;
        $missingConstructorParent['declarations'][$httpMethodFact]['operations']['constructible']
            = ['inherits constructor \\Fight\\Common\\MissingConstructorParent'];
        $assertInvalidFacts($missingConstructorParent, $manifest);
    }

    /**
     * Proves every intentional Composer package-surface promise is exact closed authority.
     */
    public function test_that_omitted_composer_package_surfaces_fail_validation(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [
            $input,
            $this->cachedInventory($facts),
            new LocalGitPort($root, $effects)
        ];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $promiseIndexes = array_flip(array_column($manifest['package_promises'], 'id'));

        foreach (
            [
                'fight-common.package.production-autoload',
                'fight-common.package.runtime-requirements',
                'fight-common.package.conflict',
                'fight-common.package.provide',
                'fight-common.package.replace',
                'fight-common.package.composer-plugin-metadata',
                'fight-common.package.exported-archive-boundary'
            ] as $promiseId
        ) {
            $mutation = $manifest;
            $mutation['package_promises'][$promiseIndexes[$promiseId]]['value'] = ['unexpected' => true];
            $this->assertInvalidManifest($mutation, $root, $operations);
        }

        $missingIntentionalEmpty = $manifest;
        unset($missingIntentionalEmpty['package_promises'][$promiseIndexes['fight-common.package.conflict']]['value']);
        $this->assertInvalidManifest($missingIntentionalEmpty, $root, $operations);
    }

    /**
     * Proves each behavioral contract and package promise retains its designated references.
     */
    public function test_that_swapped_contract_paths_sections_and_fixtures_fail_validation(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [
            $input,
            $this->cachedInventory($facts),
            new LocalGitPort($root, $effects)
        ];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $swappedBehaviorPath = $manifest;
        $swappedBehaviorPath['behavioral_contracts'][0]['normative']
            = $manifest['behavioral_contracts'][2]['normative'];
        $this->assertInvalidManifest($swappedBehaviorPath, $root, $operations);

        $swappedBehaviorOrder = $manifest;
        [$swappedBehaviorOrder['behavioral_contracts'][0], $swappedBehaviorOrder['behavioral_contracts'][1]] = [
            $swappedBehaviorOrder['behavioral_contracts'][1],
            $swappedBehaviorOrder['behavioral_contracts'][0]
        ];
        $this->assertInvalidManifest($swappedBehaviorOrder, $root, $operations);

        $wrongExistingSection = $manifest;
        $wrongExistingSection['behavioral_contracts'][0]['normative']['section'] = 'Architectural language';
        $this->assertInvalidManifest($wrongExistingSection, $root, $operations);

        $missingSection = $manifest;
        unset($missingSection['behavioral_contracts'][0]['normative']['section']);
        $this->assertInvalidManifest($missingSection, $root, $operations);

        $swappedBehaviorFixture = $manifest;
        $swappedBehaviorFixture['behavioral_contracts'][0]['fixture']
            = $manifest['behavioral_contracts'][1]['fixture'];
        $this->assertInvalidManifest($swappedBehaviorFixture, $root, $operations);

        $wrongPackagePath = $manifest;
        $wrongPackagePath['package_promises'][0]['normative']['path'] = 'CONTEXT.md';
        $this->assertInvalidManifest($wrongPackagePath, $root, $operations);

        $wrongPackageFixture = $manifest;
        $wrongPackageFixture['package_promises'][0]['fixture']['path']
            = 'tests/Domain/EventSourcing/StoredEventTest.php';
        $this->assertInvalidManifest($wrongPackageFixture, $root, $operations);

        $wrongPackageSection = $manifest;
        $wrongPackageSection['package_promises'][7]['normative']['section'] = 'Layout';
        $this->assertInvalidManifest($wrongPackageSection, $root, $operations);

        $missingPackageSection = $manifest;
        unset($missingPackageSection['package_promises'][7]['normative']['section']);
        $this->assertInvalidManifest($missingPackageSection, $root, $operations);
    }

    /**
     * Proves a designated normative section resolves to one actual Markdown heading.
     */
    public function test_that_nonexistent_normative_section_fails_validation(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $operations = [
            $input,
            $this->cachedInventory($facts),
            new LocalGitPort($root, $effects)
        ];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $normative = (string) file_get_contents($root.'/docs/event-sourcing.md');
        self::assertStringContainsString('## Migrate durable names and event schemas', $normative);
        self::assertStringNotContainsString('## Stable event identity and schema evolution', $normative);
        self::assertSame(
            'Migrate durable names and event schemas',
            $manifest['behavioral_contracts'][2]['normative']['section']
        );

        $nonexistentSection = $manifest;
        $nonexistentSection['behavioral_contracts'][2]['normative']['section']
            = 'Stable event identity and schema evolution';
        $this->assertInvalidManifest($nonexistentSection, $root, $operations);

        $malformedSection = $manifest;
        $malformedSection['behavioral_contracts'][2]['normative']['section'] = [
            'Migrate durable names and event schemas'
        ];
        $this->assertInvalidManifest($malformedSection, $root, $operations);

        $duplicateHeadingInput = new readonly class ($input) implements CompatibilityInputPort
        {
            /**
             * Constructs the duplicate-heading compatibility input
             */
            public function __construct(private CompatibilityInputPort $input)
            {
            }

            /**
             * Reads repository input with one duplicated designated heading
             */
            public function read(string $path): string
            {
                $contents = $this->input->read($path);
                if (str_ends_with($path, '/docs/event-sourcing.md')) {
                    return $contents."\n## Migrate durable names and event schemas\n";
                }

                return $contents;
            }

            /**
             * Checks whether the delegated repository input is a regular file
             */
            public function isFile(string $path): bool
            {
                return $this->input->isFile($path);
            }
        };
        $this->assertInvalidManifest(
            $manifest,
            $root,
            [$duplicateHeadingInput, $this->cachedInventory($facts), new LocalGitPort($root, $effects)]
        );
    }

    /**
     * Proves duplicate subjects and parentless exception claims remain generic manifest rejection.
     */
    public function test_that_invalid_subject_sets_and_parentless_exception_claims_fail_validation(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = static function (ReleaseEffect $_effect, ReleaseBoundaryOutcome $_outcome): void {
        };
        $input = new LocalCompatibilityInput();
        $facts = new PhpParserStructuralInventory($input)->structuralInventory($root, str_repeat('0', 64));
        $byName = array_column($facts['declarations'], null, 'name');
        self::assertSame(['parent none'], $byName[HttpMethod::class]['operations']['callable']);
        $operations = [
            $input,
            $this->cachedInventory($facts),
            new LocalGitPort($root, $effects)
        ];
        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $duplicateSubject = $manifest;
        $duplicateSubject['declarations'][1] = $duplicateSubject['declarations'][0];
        $this->assertInvalidManifest($duplicateSubject, $root, $operations);

        $parentlessException = $manifest;
        $httpMethod = array_find_key(
            $parentlessException['declarations'],
            static fn (array $entry): bool => $entry['name'] === HttpMethod::class
        );
        self::assertIsInt($httpMethod);
        $parentlessException['declarations'][$httpMethod]['operations']['extensible'] = [
            'promised' => true,
            'evidence' => [
                'authority' => 'fight-common.operation.exception-subtyping',
                'rationale' => HttpMethod::class.' is an exception contract intended to support consumer subtypes.',
                'binding'   => $manifest['declarations'][$httpMethod]['operations']['extensible']['evidence'][
                    'binding'
                ]
            ]
        ];
        $this->assertInvalidManifest($parentlessException, $root, $operations);
    }

    /**
     * Proves the intentional manifest against exact repository, source, contract, and package authority.
     */
    public function test_that_the_committed_manifest_is_the_complete_intentional_public_api_authority(): void
    {
        $root = dirname(__DIR__, 3);
        $effects = [];
        $git = new LocalGitPort(
            $root,
            static function (ReleaseEffect $effect, ReleaseBoundaryOutcome $outcome) use (&$effects): void {
                $effects[] = [$effect->value, $outcome->value];
            }
        );
        $input = new LocalCompatibilityInput();
        $inventory = new PhpParserStructuralInventory($input);
        $candidate = $inventory->structuralInventory($root, str_repeat('0', 64));
        $operations = [$input, $this->cachedInventory($candidate), $git];
        $declarations = array_column($candidate['declarations'], null, 'name');

        self::assertContains(
            'static random(): static',
            $declarations[Uuid::class]['operations']['constructible']
        );
        self::assertContains(
            'public abstract isSatisfiedBy(mixed $candidate): bool',
            $declarations[CompositeSpecification::class]['operations']['extensible']
        );
        self::assertContains(
            'extends \\Fight\\Common\\Domain\\Messaging\\Payload',
            $declarations[Command::class]['operations']['implementable']
        );
        self::assertContains(
            'protected const DEFAULT_PORTS = []',
            $declarations[Uri::class]['operations']['extensible']
        );
        self::assertContains(
            'protected property string $scheme',
            $declarations[Uri::class]['operations']['extensible']
        );
        self::assertContains(
            'protected readonly property \\Doctrine\\ORM\\EntityManagerInterface $entityManager',
            $declarations[DoctrineRepository::class]['operations']['extensible']
        );

        $result = new PublicApiManifestAuthority()->validate(
            $root.'/compatibility/manifest.json',
            $root,
            ...$operations
        );

        self::assertSame([
            'status'                  => 'valid',
            'schema_version'          => 'fight-common.compatibility-manifest/v1',
            'baseline'                => [
                'version'           => '1.1.0',
                'tag_name'          => '1.1.0',
                'tag_object_oid'    => '5f1c2f2a4a78741836003b0d6acd229569beb454',
                'peeled_commit_oid' => 'fdd48065c5527f4968943db7d61d6f1ad17619e7'
            ],
            'inventory'               => [
                'Domain'      => ['declarations' => 131, 'functions' => 13],
                'Application' => ['declarations' => 170, 'functions' => 0],
                'Adapter'     => ['declarations' => 108, 'functions' => 0]
            ],
            'classifications'         => ['public' => 408, 'internal' => 1],
            'operation_examples'      => [
                Command::class                => [
                    'callable'      => true,
                    'constructible' => false,
                    'extensible'    => false,
                    'implementable' => true
                ],
                CompositeSpecification::class => [
                    'callable'      => true,
                    'constructible' => false,
                    'extensible'    => true,
                    'implementable' => false
                ],
                Uuid::class                   => [
                    'callable'      => true,
                    'constructible' => true,
                    'extensible'    => false,
                    'implementable' => false
                ],
                UdpMetricSender::class        => [
                    'callable'      => false,
                    'constructible' => false,
                    'extensible'    => false,
                    'implementable' => false
                ]
            ],
            'behavioral_contract_ids' => [
                'fight-common.behavior.event-dispatch-complete-fanout',
                'fight-common.behavior.message-meta-isolation',
                'fight-common.behavior.stored-event-stable-identity',
                'fight-common.behavior.scheduler-legacy-construction',
                'fight-common.behavior.scheduler-legacy-command',
                'fight-common.behavior.scheduler-portable-runner',
                'fight-common.behavior.jsend-legacy-response',
                'fight-common.behavior.jsend-typed-response'
            ],
            'package_promise_ids'     => [
                'fight-common.package.name',
                'fight-common.package.production-autoload',
                'fight-common.package.runtime-requirements',
                'fight-common.package.conflict',
                'fight-common.package.provide',
                'fight-common.package.replace',
                'fight-common.package.composer-plugin-metadata',
                'fight-common.package.exported-archive-boundary'
            ]
        ], $result);
        self::assertSame([['git.resolve_ref', 'success']], $effects);

        $manifest = json_decode(
            (string) file_get_contents($root.'/compatibility/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $missingDeclaration = $manifest;
        $missing = array_pop($missingDeclaration['declarations']);
        self::assertIsArray($missing);
        $this->assertMissingClassifications($missingDeclaration, [$missing['name']], $root, $operations);

        $multipleMissingDeclarations = $manifest;
        $multipleMissing = [
            array_pop($multipleMissingDeclarations['declarations']),
            array_pop($multipleMissingDeclarations['declarations'])
        ];
        self::assertIsArray($multipleMissing[0]);
        self::assertIsArray($multipleMissing[1]);
        $multipleMissingSubjects = array_column($multipleMissing, 'name');
        sort($multipleMissingSubjects, SORT_STRING);
        $this->assertMissingClassifications(
            $multipleMissingDeclarations,
            $multipleMissingSubjects,
            $root,
            $operations
        );

        $extraEntryMember = $manifest;
        $extraEntryMember['declarations'][0]['unexpected'] = true;
        $this->assertInvalidManifest($extraEntryMember, $root, $operations);

        $unknownClassification = $manifest;
        $unknownClassification['declarations'][0]['classification'] = 'unknown';
        $this->assertMissingClassifications(
            $unknownClassification,
            [$unknownClassification['declarations'][0]['name']],
            $root,
            $operations
        );

        $missingClassification = $manifest;
        unset($missingClassification['declarations'][0]['classification']);
        $this->assertMissingClassifications(
            $missingClassification,
            [$missingClassification['declarations'][0]['name']],
            $root,
            $operations
        );

        $multipleInvalidClassifications = $manifest;
        $multipleInvalidClassifications['declarations'][0]['classification'] = 'unknown';
        unset($multipleInvalidClassifications['functions'][0]['classification']);
        $multipleInvalidSubjects = [
            $multipleInvalidClassifications['declarations'][0]['name'],
            $multipleInvalidClassifications['functions'][0]['name']
        ];
        sort($multipleInvalidSubjects, SORT_STRING);
        $this->assertMissingClassifications(
            $multipleInvalidClassifications,
            $multipleInvalidSubjects,
            $root,
            $operations
        );

        $invalidClassificationWithMalformedName = $manifest;
        $invalidClassificationWithMalformedName['declarations'][0]['classification'] = 'unknown';
        $invalidClassificationWithMalformedName['declarations'][0]['name'] = '';
        $this->assertInvalidManifest($invalidClassificationWithMalformedName, $root, $operations);

        $invalidClassificationWithMalformedSource = $manifest;
        $invalidClassificationWithMalformedSource['declarations'][0]['classification'] = 'unknown';
        $invalidClassificationWithMalformedSource['declarations'][0]['source'] = 'src/Unknown.php';
        $this->assertInvalidManifest($invalidClassificationWithMalformedSource, $root, $operations);

        $invalidClassificationWithMalformedEvidence = $manifest;
        $invalidClassificationWithMalformedEvidence['declarations'][0]['classification'] = 'unknown';
        $invalidClassificationWithMalformedEvidence['declarations'][0]['classification_evidence']['rationale']
            = 'Unbound rationale.';
        $this->assertInvalidManifest($invalidClassificationWithMalformedEvidence, $root, $operations);

        $invalidClassificationWithUnknownEvidence = $manifest;
        $invalidClassificationWithUnknownEvidence['declarations'][0]['classification'] = 'unknown';
        $invalidClassificationWithUnknownEvidence['declarations'][0]['classification_evidence']['authority']
            = 'unknown';
        $this->assertInvalidManifest($invalidClassificationWithUnknownEvidence, $root, $operations);

        $internalClassificationEvidence = $manifest;
        $additionAuthority = 'fight-common.classification.prd-00014-addition';
        $additionIndex = array_find_key(
            $internalClassificationEvidence['declarations'],
            static fn (array $entry): bool => $entry['classification_evidence']['authority'] === $additionAuthority
        );
        self::assertIsInt($additionIndex);
        $internalClassificationEvidence['declarations'][$additionIndex]['classification'] = 'unknown';
        $internalClassificationEvidence['declarations'][$additionIndex]['classification_evidence']['authority']
            = 'fight-common.classification.explicit-internal-annotation';
        $this->assertMissingClassifications(
            $internalClassificationEvidence,
            [$internalClassificationEvidence['declarations'][$additionIndex]['name']],
            $root,
            $operations
        );

        $invalidClassificationWithDuplicate = $manifest;
        $invalidClassificationWithDuplicate['declarations'][1]
            = $invalidClassificationWithDuplicate['declarations'][0];
        $invalidClassificationWithDuplicate['declarations'][1]['classification'] = 'unknown';
        $this->assertInvalidManifest($invalidClassificationWithDuplicate, $root, $operations);

        $invalidClassificationWithBroadenedEntry = $manifest;
        $broadenedEntry = $invalidClassificationWithBroadenedEntry['declarations'][0];
        $broadenedEntry['classification'] = 'unknown';
        $broadenedEntry['name'] = 'Fight\\Common\\Unknown';
        $invalidClassificationWithBroadenedEntry['declarations'][] = $broadenedEntry;
        $this->assertInvalidManifest($invalidClassificationWithBroadenedEntry, $root, $operations);

        $unknownClassificationEvidence = $manifest;
        $unknownClassificationEvidence['declarations'][0]['classification_evidence'] = 'unknown';
        $this->assertInvalidManifest($unknownClassificationEvidence, $root, $operations);

        $permutedOperations = $manifest;
        $permutedOperations['declarations'][0]['operations'] = array_reverse(
            $permutedOperations['declarations'][0]['operations'],
            true
        );
        $this->assertInvalidManifest($permutedOperations, $root, $operations);

        $extraOperationMember = $manifest;
        $extraOperationMember['declarations'][0]['operations']['callable']['unexpected'] = true;
        $this->assertInvalidManifest($extraOperationMember, $root, $operations);

        $nonBooleanPromise = $manifest;
        $nonBooleanPromise['declarations'][0]['operations']['callable']['promised'] = 'yes';
        $this->assertInvalidManifest($nonBooleanPromise, $root, $operations);

        $invalidEvidenceReference = $manifest;
        $invalidEvidenceReference['evidence_authorities']['fight-common.operation.public-call'] = [
            'unexpected' => 'CONTEXT.md'
        ];
        $this->assertInvalidManifest($invalidEvidenceReference, $root, $operations);

        $missingNormativeAuthority = $manifest;
        $missingNormativeAuthority['behavioral_contracts'][0]['normative']['path'] = 'missing.md';
        $this->assertInvalidManifest($missingNormativeAuthority, $root, $operations);

        $missingFixture = $manifest;
        $missingFixture['behavioral_contracts'][0]['fixture']['path'] = 'missing.php';
        $this->assertInvalidManifest($missingFixture, $root, $operations);
    }

    /**
     * Asserts that one policy mutation cannot validate as committed authority
     *
     * @param array<string, mixed>                                            $manifest   Manifest mutation.
     * @param string                                                          $root       Repository root.
     * @param array{CompatibilityInputPort, StructuralInventoryPort, GitPort} $operations Focused capabilities.
     */
    private function assertInvalidManifest(
        array $manifest,
        string $root,
        array $operations
    ): void {
        $invalidPath = tempnam(sys_get_temp_dir(), 'fight-common-manifest-');
        self::assertIsString($invalidPath);

        try {
            file_put_contents(
                $invalidPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
            new PublicApiManifestAuthority()->validate($invalidPath, $root, ...$operations);
            self::fail('An incomplete or broadened manifest validated as intentional authority.');
        } catch (UnexpectedValueException $unexpectedValueException) {
            self::assertSame(
                'The committed compatibility manifest is not complete intentional authority.',
                $unexpectedValueException->getMessage()
            );
        } finally {
            unlink($invalidPath);
        }
    }

    /**
     * Returns deterministic scanner facts without rescanning the repository for each manifest mutation.
     *
     * @param array<string, mixed> $facts
     */
    private function cachedInventory(array $facts): StructuralInventoryPort
    {
        return new readonly class ($facts) implements StructuralInventoryPort
        {
            /** @param array<string, mixed> $facts */
            public function __construct(private array $facts)
            {
            }

            /** @return array<string, mixed> */
            public function structuralInventory(string $_sourceRoot, string $_sourceOid): array
            {
                return $this->facts;
            }
        };
    }

    /**
     * Asserts otherwise intentional omitted subjects produce typed stable findings
     *
     * @param array<string, mixed>                                            $manifest   Manifest mutation.
     * @param array                                                           $subjects   Missing subjects.
     * @param string                                                          $root       Repository root.
     * @param array{CompatibilityInputPort, StructuralInventoryPort, GitPort} $operations Focused capabilities.
     *
     * @phpstan-param list<string> $subjects
     */
    private function assertMissingClassifications(
        array $manifest,
        array $subjects,
        string $root,
        array $operations
    ): void {
        $invalidPath = tempnam(sys_get_temp_dir(), 'fight-common-manifest-');
        self::assertIsString($invalidPath);

        try {
            file_put_contents(
                $invalidPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
            new PublicApiManifestAuthority()->validate($invalidPath, $root, ...$operations);
            self::fail('A missing classification did not reject manifest authority.');
        } catch (CompatibilityManifestRejected $compatibilityManifestRejected) {
            $findings = array_map(
                static fn (CompatibilityFinding $finding): array => $finding->machineFinding(),
                $compatibilityManifestRejected->findings
            );
            self::assertSame($subjects, array_column($findings, 'subject'));
            self::assertSame(
                array_fill(
                    0,
                    count($subjects),
                    'release.compatibility.structural-api.missing-classification'
                ),
                array_column($findings, 'id')
            );
        } finally {
            unlink($invalidPath);
        }
    }
}
