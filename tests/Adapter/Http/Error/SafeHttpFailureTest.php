<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Error;

use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(SafeHttpFailure::class)]
class SafeHttpFailureTest extends UnitTestCase
{
    public function test_that_factories_capture_only_supported_transport_outcomes_and_private_diagnostics(): void
    {
        $cause = new RuntimeException('private cause');
        $bad = SafeHttpFailure::badRequest('private message', $cause);
        self::assertSame(400, $bad->status);
        self::assertSame('private message', $bad->getMessage());
        self::assertSame($cause, $bad->getPrevious());
        self::assertNull($bad->metadata);

        self::assertSame(401, SafeHttpFailure::authenticationRequired('Basic')->status);
        self::assertSame(405, SafeHttpFailure::methodNotAllowed('GET')->status);
        self::assertSame(406, SafeHttpFailure::notAcceptable()->status);
        self::assertSame(413, SafeHttpFailure::payloadTooLarge()->status);
        self::assertSame(415, SafeHttpFailure::unsupportedMediaType()->status);
        self::assertSame(429, SafeHttpFailure::tooManyRequests()->status);
        self::assertSame(503, SafeHttpFailure::unavailable()->status);
    }
}
