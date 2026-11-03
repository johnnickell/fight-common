<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\Composition\McpCompositionValidator;
use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\FixtureCapability;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(McpCapabilityRegistry::class)]
#[CoversClass(McpCompositionValidator::class)]
final class McpCapabilityRegistryTest extends UnitTestCase
{
    public function test_that_registry_rejects_missing_server_identity(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(null, []);
    }

    public function test_that_registry_rejects_duplicate_method_ownership(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [new FixtureCapability(), new FixtureCapability()]);
    }

    public function test_that_registry_rejects_contradictory_capability_metadata(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(), new FixtureCapability(method: 'example/second', definition: ['enabled' => false])],
        );
    }

    public function test_that_registry_rejects_capabilities_without_a_handler_method(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(methods: [])],
        );
    }

    public function test_that_registry_rejects_dispatchable_capabilities_without_advertisement(): void
    {
        $this->expectException(DomainException::class);

        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(capabilities: [])],
        );
    }

    public function test_that_registry_rejects_nonempty_list_capability_definitions(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(definition: ['not', 'an', 'object'])],
        );
    }

    public function test_that_registry_requires_truthful_standard_capability_declarations(): void
    {
        $registry = new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [new FixtureCapability(
            methods: ['tools/list'],
            capabilities: ['tools' => []],
        )]);
        self::assertInstanceOf(McpCapability::class, $registry->capabilityFor('tools/list'));

        foreach ([
            new FixtureCapability(methods: ['tools/list'], capabilities: ['prompts' => []]),
            new FixtureCapability(methods: ['tools/list'], capabilities: ['tools' => ['listChanged' => 'yes']]),
            new FixtureCapability(methods: ['example/echo'], capabilities: ['extensions' => ['unprefixed' => []]]),
        ] as $capability) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
                self::fail('Expected invalid MCP capability composition to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

        $extensionRegistry = new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(
                methods: ['rpc.example'],
                capabilities: ['extensions' => ['com.example/feature' => new stdClass()]],
            )],
        );
        self::assertInstanceOf(McpCapability::class, $extensionRegistry->capabilityFor('rpc.example'));

        foreach ([
            new FixtureCapability(methods: ['example/echo'], capabilities: ['tools' => []]),
            new FixtureCapability(methods: ['tools/list'], capabilities: ['tools' => [], 'prompts' => []]),
        ] as $capability) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
                self::fail('Expected unimplemented standard discovery capability to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_registry_requires_standard_capability_mandatory_anchor_methods(): void
    {
        foreach ([
            ['prompts/get', 'prompts'],
            ['resources/read', 'resources'],
            ['resources/templates/list', 'resources'],
            ['tools/call', 'tools'],
        ] as [$method, $capabilityName]) {
            try {
                new McpCapabilityRegistry(
                    new McpServerInfo('Example', '1.0.0'),
                    [new FixtureCapability(methods: [$method], capabilities: [$capabilityName => []])],
                );
                self::fail('Expected a standard capability without its mandatory anchor to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_registry_rejects_subscription_capability_flags_without_a_subscription_contract(): void
    {
        foreach ([
            ['prompts/list', 'prompts', ['listChanged' => true]],
            ['resources/list', 'resources', ['subscribe' => true]],
            ['resources/list', 'resources', ['listChanged' => true]],
            ['tools/list', 'tools', ['listChanged' => true]],
        ] as [$method, $capabilityName, $definition]) {
            try {
                new McpCapabilityRegistry(
                    new McpServerInfo('Example', '1.0.0'),
                    [new FixtureCapability(methods: [$method], capabilities: [$capabilityName => $definition])],
                );
                self::fail('Expected an unsupported subscription capability to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

        try {
            new McpCapabilityRegistry(
                new McpServerInfo('Example', '1.0.0'),
                [new FixtureCapability(
                    methods: ['tools/list', 'subscriptions/listen'],
                    capabilities: ['tools' => ['listChanged' => true]],
                )],
            );
            self::fail('Expected a generic listener to be rejected without a subscription contract.');
        } catch (DomainException) {
            self::addToAssertionCount(1);
        }
    }

    public function test_that_registry_validates_all_defined_standard_capability_shapes(): void
    {
        $registry = new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(
                methods: ['completion/complete', 'resources/list', 'rpc.example', 'subscriptions/listen'],
                capabilities: [
                    'completions' => ['values' => [null, true, 1, 'one', 1.0, (object) ['nested' => []]]],
                    'logging' => [],
                    'resources' => ['subscribe' => false, 'listChanged' => false],
                    'experimental' => ['feature' => (object) ['nested' => []]],
                    'extensions' => ['com.example/feature' => ['nested' => []]],
                ],
            )],
        );

        self::assertInstanceOf(McpCapability::class, $registry->capabilityFor('completion/complete'));
        self::assertInstanceOf(McpCapability::class, $registry->capabilityFor('resources/list'));

        foreach ([
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['completions' => ['list']]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['experimental' => ['feature' => []]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['logging' => ['value' => NAN]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['logging' => ['nested' => [NAN]]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['logging' => ['nested' => (object) ['value' => NAN]]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ["\xB1" => []]),
        ] as $capability) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
                self::fail('Expected invalid standard capability body to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_registry_rejects_non_json_custom_capability_definitions(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            foreach ([
                ['nested' => ['number' => NAN]],
                ['nested' => ['resource' => $resource]],
                ['nested' => ['object' => new \DateTimeImmutable()]],
                ["\xB1" => 'invalid UTF-8 key'],
                ['value' => "\xB1"],
            ] as $definition) {
                try {
                    new McpCapabilityRegistry(
                        new McpServerInfo('Example', '1.0.0'),
                        [new FixtureCapability(definition: $definition)],
                    );
                    self::fail('Expected a custom capability with a non-JSON value to be rejected.');
                } catch (DomainException) {
                    self::addToAssertionCount(1);
                }
            }
        } finally {
            fclose($resource);
        }

        $registry = new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(definition: [
                'nested' => [
                    'null'   => null,
                    'list'   => [true, 1, 'value', 1.5],
                    'object' => (object) ['value' => false],
                ],
            ])],
        );

        self::assertEquals(
            [
                'example' => [
                    'nested' => StrictJson::fromObject([
                        'null'   => null,
                        'list'   => [true, 1, 'value', 1.5],
                        'object' => ['value' => false],
                    ]),
                ],
            ],
            $registry->advertisedCapabilities(),
        );
    }

    public function test_that_registry_rejects_invalid_capability_method_and_mirror_values(): void
    {
        foreach ([
            [new stdClass()],
            [new FixtureCapability(methods: [''])],
            [new FixtureCapability(methods: ['server/discover'])],
            [new FixtureCapability(mirrors: [new stdClass()])],
        ] as $invalidCapabilities) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), $invalidCapabilities);
                self::fail('Expected invalid capability registration to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_registry_returns_only_the_selected_methods_registered_mirrors(): void
    {
        $serverInfo = new McpServerInfo('Example', '1.0.0');
        $declaration = new McpMirrorDeclaration('example/echo', ['region'], 'Region');
        $registry = new McpCapabilityRegistry(
            $serverInfo,
            [new FixtureCapability(mirrors: [$declaration])],
        );

        self::assertSame([$declaration], $registry->mirrorDeclarationsFor('example/echo'));
        self::assertSame([], $registry->mirrorDeclarationsFor('unknown/method'));
    }

    public function test_that_registry_rejects_mirrors_for_unowned_methods(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(mirrors: [new McpMirrorDeclaration('other/method', ['region'], 'Region')])],
        );
    }

    public function test_that_registry_rejects_case_insensitive_duplicate_mirror_headers(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(mirrors: [
                new McpMirrorDeclaration('example/echo', ['region'], 'Region'),
                new McpMirrorDeclaration('example/echo', ['zone'], 'region'),
            ])],
        );
    }
}
