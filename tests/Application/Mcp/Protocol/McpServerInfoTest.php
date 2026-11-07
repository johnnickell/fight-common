<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp as ReleasedMcp;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReleasedMcp\McpServerInfo::class)]
final class McpServerInfoTest extends UnitTestCase
{
    public function test_that_server_info_accepts_schema_permitted_empty_identity_strings(): void
    {
        $serverInfo = new McpServerInfo('', '');

        self::assertSame('', $serverInfo->name());
        self::assertSame('', $serverInfo->version());
    }

    public function test_that_server_info_retains_the_configured_identity(): void
    {
        $serverInfo = new McpServerInfo('Example', '1.0.0');

        self::assertSame('Example', $serverInfo->name());
        self::assertSame('1.0.0', $serverInfo->version());
    }

    public function test_that_server_info_rejects_non_json_unicode_strings(): void
    {
        foreach (["\xB1", "\xB1\x31"] as $value) {
            try {
                new McpServerInfo($value, '1.0.0');
                self::fail('Expected a malformed UTF-8 server identity to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

        $serverInfo = new McpServerInfo('Exämple', '1.0.0');
        self::assertSame('Exämple', $serverInfo->name());
    }
}
