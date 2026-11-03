<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Protocol\Exception;

use Fight\Common\Application\Mcp as ReleasedMcp;
use Fight\Common\Application\Mcp\Protocol\Exception\McpProtocolException;
use Fight\Common\Application\Mcp\Protocol\McpProtocolError;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReleasedMcp\McpProtocolException::class)]
final class McpProtocolExceptionTest extends UnitTestCase
{
    public function test_that_protocol_rejections_retain_the_error_and_usable_or_absent_request_identity(): void
    {
        foreach ([null, 0, '', 'request-é'] as $requestId) {
            $error = McpProtocolError::invalidParams();

            $exception = new McpProtocolException($error, $requestId);

            self::assertSame($error, $exception->protocolError());
            self::assertSame($requestId, $exception->requestId());
            self::assertSame('Invalid params.', $exception->getMessage());
        }
    }
}
