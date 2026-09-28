<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpTool;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Fixture\Mcp\DiscoveryTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(McpToolRegistry::class)]
final class McpToolRegistryTest extends UnitTestCase
{
    public function test_that_only_supplied_tools_are_registered_without_invocation_or_service_discovery(): void
    {
        $supplied = new DiscoveryTool();
        $notSupplied = new DiscoveryTool();
        $registry = new McpToolRegistry([$supplied]);
        self::assertSame($supplied, $registry->find('alpha.find'));
        self::assertNotSame($notSupplied, $registry->find('alpha.find'));
        self::assertNull($registry->find('ALPHA.find'));
        self::assertNull($registry->find('unknown'));
        self::assertNull((new McpToolRegistry([]))->find('alpha.find'));
    }

    public function test_that_availability_receives_metadata_only_and_empty_registry_is_supported(): void
    {
        $seen = [];
        $availability = $this->mock(McpToolAvailability::class);
        $availability->shouldReceive('isAvailable')->once()->withArgs(function (McpToolInfo $info) use (&$seen): bool {
            $seen[] = $info->toArray();
            return $info->name() === 'alpha.find';
        })->andReturn(false);
        self::assertSame([], (new McpToolRegistry([new DiscoveryTool()]))->available($availability));
        self::assertSame([], (new McpToolRegistry([]))->available($availability));
        self::assertSame(['name', 'description', 'inputSchema', 'outputSchema'], array_keys($seen[0]));
    }

    public function test_that_duplicate_canonical_names_fail_composition(): void
    {
        $this->expectException(DomainException::class);
        new McpToolRegistry([new DiscoveryTool(), new DiscoveryTool()]);
    }

    public function test_that_non_opted_in_objects_and_actions_cannot_be_registered(): void
    {
        $action = new class {
            #[McpToolInfo('not-a-tool', 'An ordinary Action', ['type' => 'object'], [])]
            public function handle(): void {}
        };
        foreach ([$action, new stdClass(), 'alpha.find', null] as $notTool) {
            try {
                new McpToolRegistry([$notTool]);
                self::fail('A non-Tool was accepted.');
            } catch (DomainException $exception) {
                self::assertStringContainsString('explicitly supplied', $exception->getMessage());
            }
        }
    }

    public function test_that_an_attribute_on_another_method_does_not_opt_in_the_handle_method(): void
    {
        $tool = new class implements McpTool {
            #[McpToolInfo('wrong-method', 'Not handle', ['type' => 'object'], [])]
            public function another(): void {}
            public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
            {
                throw new LogicException('Must not execute');
            }
        };
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exactly one');
        new McpToolRegistry([$tool]);
    }

    public function test_that_duplicate_method_attributes_fail_composition(): void
    {
        $tool = new class implements McpTool {
            #[McpToolInfo('one', 'One', ['type' => 'object'], [])]
            #[McpToolInfo('two', 'Two', ['type' => 'object'], [])]
            public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
            {
                throw new LogicException('Must not execute');
            }
        };
        $this->expectException(DomainException::class);
        new McpToolRegistry([$tool]);
    }

    public function test_that_malformed_attribute_arguments_fail_during_composition(): void
    {
        $badName = new class implements McpTool {
            #[McpToolInfo('bad name', 'Description', ['type' => 'object'], [])]
            public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
            {
                throw new LogicException('Must not execute');
            }
        };
        $missingSchema = new class implements McpTool {
            #[McpToolInfo('valid', 'Description', ['type' => 'object'])]
            public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
            {
                throw new LogicException('Must not execute');
            }
        };
        foreach ([$badName, $missingSchema] as $tool) {
            try {
                new McpToolRegistry([$tool]);
                self::fail('Malformed metadata was accepted.');
            } catch (DomainException $exception) {
                self::assertSame('A Tool has invalid McpToolInfo metadata.', $exception->getMessage());
                self::assertNotNull($exception->getPrevious());
            }
        }
    }
}
