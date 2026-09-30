<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceAvailability;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillAvailability;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscovery;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpCapabilityRegistry::class)]
final class McpSkillCompositionTest extends UnitTestCase
{
    public function test_that_distinct_extension_ids_compose_and_equal_same_id_settings_coalesce(): void
    {
        $a = new ExtensionCapability(['example/a'], ['extensions' => ['com.example/a' => ['value' => ['enabled' => true]]]]);
        $b = new ExtensionCapability(['example/b'], ['extensions' => ['com.example/b' => (object) [], 'com.example/a' => ['value' => ['enabled' => true]]]]);
        foreach ([[$a, $b], [$b, $a]] as $capabilities) {
            $registry = $this->registry($capabilities);
            self::assertSame($a, $registry->capabilityFor('example/a'));
            self::assertSame($b, $registry->capabilityFor('example/b'));
            $extensions = $registry->advertisedCapabilities()['extensions'];
            self::assertCount(2, $extensions);
            self::assertSame('{"value":{"enabled":true}}', $extensions['com.example/a']->toString());
            self::assertSame('{}', $extensions['com.example/b']->toString());
        }
    }

    #[DataProvider('equivalentExtensionSettings')]
    public function test_that_extension_object_member_order_does_not_prevent_coalescing(string $left, string $right): void
    {
        $a = new ExtensionCapability(['example/a'], ['extensions' => ['com.example/shared' => StrictJson::fromString($left)]]);
        $b = new ExtensionCapability(['example/b'], ['extensions' => ['com.example/shared' => StrictJson::fromString($right)]]);
        foreach ([[$a, $b], [$b, $a]] as $capabilities) {
            $registry = $this->registry($capabilities);
            self::assertSame($a, $registry->capabilityFor('example/a'));
            self::assertSame($b, $registry->capabilityFor('example/b'));
            $extensions = $registry->advertisedCapabilities()['extensions'];
            self::assertCount(1, $extensions);
            self::assertContains($extensions['com.example/shared']->toString(), [$left, $right]);
        }
    }

    public static function equivalentExtensionSettings(): iterable
    {
        yield 'top-level object' => ['{"a":1,"b":2}', '{"b":2,"a":1}'];
        yield 'nested object' => ['{"nested":{"a":1,"b":2}}', '{"nested":{"b":2,"a":1}}'];
        yield 'objects within ordered lists' => [
            '{"items":[{"a":1,"b":2},[null,{"c":true,"d":"x"}]]}',
            '{"items":[{"b":2,"a":1},[null,{"d":"x","c":true}]]}'
        ];
        yield 'numeric object names' => ['{"0":null,"1":false}', '{"1":false,"0":null}'];
        yield 'numeric-looking names stay distinct' => ['{"1":true,"01":false}', '{"01":false,"1":true}'];
        yield 'empty containers and scalars' => [
            '{"object":{},"list":[],"value":1.5,"text":"x","flag":false,"null":null}',
            '{"null":null,"flag":false,"text":"x","value":1.5,"list":[],"object":{}}'
        ];
    }

    #[DataProvider('conflictingExtensionSettings')]
    public function test_that_extension_setting_conflicts_preserve_types_members_and_list_order(string $left, string $right): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('contradictory settings');
        $this->registry([
            new ExtensionCapability(['example/a'], ['extensions' => ['com.example/shared' => StrictJson::fromString($left)]]),
            new ExtensionCapability(['example/b'], ['extensions' => ['com.example/shared' => StrictJson::fromString($right)]])
        ]);
    }

    public static function conflictingExtensionSettings(): iterable
    {
        $cases = [
            'scalar value' => ['{"a":1}', '{"a":2}'],
            'string versus number' => ['{"a":1}', '{"a":"1"}'],
            'boolean versus number' => ['{"a":0}', '{"a":false}'],
            'null versus absent' => ['{"a":null}', '{}'],
            'different null member' => ['{"a":null}', '{"b":null}'],
            'empty object versus list' => ['{"a":{}}', '{"a":[]}'],
            'numeric object versus list' => ['{"a":{"0":"x"}}', '{"a":["x"]}'],
            'list order' => ['{"a":[1,2]}', '{"a":[2,1]}'],
            'list length' => ['{"a":[1]}', '{"a":[1,2]}'],
            'list versus scalar' => ['{"a":[]}', '{"a":null}'],
            'nested value' => ['{"a":{"b":1,"c":2}}', '{"a":{"c":3,"b":1}}'],
            'ordered objects' => ['{"a":[{"x":1},{"x":2}]}', '{"a":[{"x":2},{"x":1}]}']
        ];
        foreach ($cases as $name => [$left, $right]) {
            yield $name => [$left, $right];
            yield $name.' reversed' => [$right, $left];
        }
    }

    public function test_that_non_extension_capability_equality_remains_unchanged(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('contradictory advertised metadata');
        $this->registry([
            new ExtensionCapability(['example/a'], ['custom' => ['a' => 1, 'b' => 2]]),
            new ExtensionCapability(['example/b'], ['custom' => ['b' => 2, 'a' => 1]])
        ]);
    }

    public function test_that_conflicting_same_id_settings_are_not_recursively_merged(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('contradictory settings');
        $this->registry([
            new ExtensionCapability(['example/a'], ['extensions' => ['com.example/shared' => ['a' => true]]]),
            new ExtensionCapability(['example/b'], ['extensions' => ['com.example/shared' => ['b' => true]]])
        ]);
    }

    public function test_that_duplicate_method_ownership_is_still_rejected_across_different_extensions(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('more than one capability owner');
        $this->registry([
            new ExtensionCapability(['example/shared'], ['extensions' => ['com.example/a' => (object) []]]),
            new ExtensionCapability(['example/shared'], ['extensions' => ['com.example/b' => (object) []]])
        ]);
    }

    #[DataProvider('incompleteSkills')]
    public function test_that_incomplete_or_false_skills_advertisement_is_rejected(array $methods, array $capabilities): void
    {
        $this->expectException(DomainException::class);
        $this->registry([new ExtensionCapability($methods, $capabilities)]);
    }

    public static function incompleteSkills(): iterable
    {
        $complete = ['extensions' => [McpSkillDiscovery::EXTENSION => (object) []], 'resources' => []];
        $methods = ['skills/list', 'skills/get', 'resources/list', 'resources/read'];
        foreach (['skills/list', 'skills/get', 'resources/read'] as $missing) {
            yield [array_values(array_diff($methods, [$missing])), $complete];
        }
        yield [['skills/list'], ['extensions' => [McpSkillDiscovery::EXTENSION => (object) []]]];
        yield [['skills/get'], ['other' => []]];
        yield [['skills/list'], ['other' => []]];
        foreach ([['directoryRead' => true], ['directoryRead' => 'false'], ['listChanged' => true], ['unknown' => false]] as $settings) {
            yield [$methods, ['extensions' => [McpSkillDiscovery::EXTENSION => $settings], 'resources' => []]];
        }
    }

    public function test_that_complete_custom_skills_composition_can_explicitly_decline_directory_support(): void
    {
        $capability = new ExtensionCapability(['skills/list', 'skills/get', 'resources/list', 'resources/read'], ['resources' => [], 'extensions' => [McpSkillDiscovery::EXTENSION => ['directoryRead' => false]]]);
        self::assertSame($capability, $this->registry([$capability])->capabilityFor('skills/get'));
    }

    public function test_that_builtin_skills_requires_its_exact_shared_resource_capability_in_either_registration_order(): void
    {
        $policy = new class implements McpSkillAvailability { public function isAvailable(StrictJson $entry): bool { return true; } };
        $general = new class implements McpResourceAvailability { public function isAvailable(McpResourceInfo $resource): bool { return true; } };
        $provider = new McpSkillResources([], $policy, new HttpFactory());
        $resources = new McpResourceDiscovery([$provider], $general, str_repeat('k', 32), 'scope', readLimits: new McpResourceReadLimits());
        $skills = new McpSkillDiscovery($provider, $resources, str_repeat('k', 32), 'scope');
        $extension = new ExtensionCapability(['example/other'], ['extensions' => ['com.example/other' => (object) []]]);
        foreach ([[$skills, $resources, $extension], [$extension, $resources, $skills]] as $capabilities) {
            $registry = $this->registry($capabilities);
            self::assertSame($skills, $registry->capabilityFor('skills/get'));
            self::assertSame($resources, $registry->capabilityFor('resources/read'));
            self::assertCount(2, $registry->advertisedCapabilities()['extensions']);
        }
        self::assertArrayNotHasKey('extensions', $this->registry([$resources])->advertisedCapabilities());
        $other = new McpResourceDiscovery([$provider], $general, str_repeat('k', 32), 'scope', readLimits: new McpResourceReadLimits());
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('share the configured Resources');
        $this->registry([$skills, $other]);
    }

    private function registry(array $capabilities): McpCapabilityRegistry
    {
        return new McpCapabilityRegistry(new McpServerInfo('server', '1'), $capabilities);
    }
}

final readonly class ExtensionCapability implements McpCapability
{
    public function __construct(private array $methods, private array $capabilities) {}
    public function methods(): array { return $this->methods; }
    public function capabilities(): array { return $this->capabilities; }
    public function mirrorDeclarations(): array { return []; }
    public function validate(McpRequest $request): void {}
    public function handle(McpRequest $request): McpResult { return McpResult::complete([]); }
}
