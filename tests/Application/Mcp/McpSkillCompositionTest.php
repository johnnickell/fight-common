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
