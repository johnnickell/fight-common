<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Test\Common\TestCase\UnitTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(McpJsonResponse::class)]
final class McpJsonResponseTest extends UnitTestCase
{
    public function test_that_success_and_error_responses_encode_their_semantic_envelopes(): void
    {
        self::assertSame(
            '{"jsonrpc":"2.0","id":"id-1","result":{"resultType":"complete","message":"hello"}}',
            McpJsonResponse::success('id-1', McpResult::complete(['message' => 'hello']))->toJson(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid request.']],
            McpJsonResponse::error(null, McpProtocolError::invalidRequest())->toArray(),
        );
    }

    public function test_that_response_encoding_surfaces_json_errors(): void
    {
        $this->expectException(JsonException::class);
        McpJsonResponse::success(1, McpResult::complete(['value' => NAN]))->toJson();
    }
}
