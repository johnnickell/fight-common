<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\Protocol\McpRequestDecoder;
use Fight\Common\Application\Mcp\Protocol\McpResult;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use __PHP_Incomplete_Class;

#[CoversClass(McpCapabilityRegistry::class)]
#[CoversClass(McpRequestDecoder::class)]
#[CoversClass(McpResponder::class)]
#[CoversClass(McpResult::class)]
#[CoversClass(McpServerInfo::class)]
final class McpFoundationCompatibilityTest extends UnitTestCase
{
    public function test_that_consumer_container_can_wire_canonical_mcp_dependencies(): void
    {
        $registry = new McpCapabilityRegistry(
            serverInfo: new McpServerInfo(name: 'Canonical consumer', version: '2'),
            capabilities: [new EndpointCapability()]
        );
        $services = [McpCapabilityRegistry::class => $registry, McpRequestDecoder::class => new McpRequestDecoder()];
        $responder = new ReflectionClass(McpResponder::class);
        $constructor = $responder->getConstructor();

        self::assertSame($registry, $services[$constructor->getParameters()[0]->getType()->getName()]);
        self::assertInstanceOf(McpRequestDecoder::class, $services[$constructor->getParameters()[1]->getType()->getName()]);
    }

    public function test_that_a_tagged_root_result_frame_requires_pre_upgrade_conversion(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 2).'/Fixture/Mcp/serialized-foundation-v1.4.0.json'), true, flags: JSON_THROW_ON_ERROR);
        $frame = base64_decode($fixture['values']['result'], true);

        self::assertInstanceOf(__PHP_Incomplete_Class::class, unserialize($frame, ['allowed_classes' => [McpResult::class]]));
    }

    public function test_that_canonical_result_retains_its_payload_and_limit_when_restored(): void
    {
        $result = McpResult::boundedComplete(['answer' => 42], 100)->withResponseLimit(500);

        $restored = unserialize(serialize($result));

        self::assertSame(McpResult::class, $restored::class);
        self::assertSame(500, $restored->maxEncodedResponseBytes());
        self::assertSame(['resultType' => 'complete', 'answer' => 42], $restored->toArray());
    }
}
