<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(McpProtocolError::class)]
final class McpProtocolErrorTest extends UnitTestCase
{
    public function test_that_fixed_errors_expose_only_their_public_code_and_message(): void
    {
        foreach ([
            [McpProtocolError::parseError(), -32700, 'Parse error.'],
            [McpProtocolError::invalidRequest(), -32600, 'Invalid request.'],
            [McpProtocolError::methodNotFound(), -32601, 'Method not found.'],
            [McpProtocolError::invalidParams(), -32602, 'Invalid params.'],
            [McpProtocolError::unknownTool(), -32602, 'Unknown or unavailable tool.'],
            [McpProtocolError::internalError(), -32603, 'Internal error.'],
            [McpProtocolError::headerMismatch(), -32020, 'Header mismatch.'],
            [McpProtocolError::invocationLimit(), 429, 'Invocation limit exceeded.'],
        ] as [$error, $code, $message]) {
            self::assertSame(['code' => $code, 'message' => $message], $error->toArray());
        }
    }

    public function test_that_unsupported_versions_report_the_requested_and_supported_versions(): void
    {
        self::assertSame(
            [
                'code' => -32022,
                'message' => 'Unsupported protocol version.',
                'data' => ['supported' => ['2026-07-28'], 'requested' => '2025-11-25'],
            ],
            McpProtocolError::unsupportedProtocolVersion(['2026-07-28'], '2025-11-25')->toArray(),
        );
    }

    public function test_that_missing_capabilities_preserve_empty_object_requirements(): void
    {
        self::assertEquals(
            [
                'code' => -32021,
                'message' => 'Missing required client capability.',
                'data' => ['requiredCapabilities' => ['elicitation' => ['form' => StrictJson::fromObject()]]],
            ],
            McpProtocolError::missingFormElicitation()->toArray(),
        );
        self::assertEquals(
            [
                'code' => -32021,
                'message' => 'Missing required client capability.',
                'data' => ['requiredCapabilities' => [
                    'extensions' => ['io.modelcontextprotocol/tasks' => StrictJson::fromObject()],
                ]],
            ],
            McpProtocolError::missingTasks()->toArray(),
        );
    }
}
