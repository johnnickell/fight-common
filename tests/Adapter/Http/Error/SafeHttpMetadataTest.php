<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Error;

use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SafeHttpMetadata::class)]
class SafeHttpMetadataTest extends UnitTestCase
{
    public function test_that_constructor_accepts_valid_challenge_allow_and_retry_values(): void
    {
        $metadata = new SafeHttpMetadata('Basic realm="api"', 'GET, POST', PHP_INT_MAX);
        self::assertSame('Basic realm="api"', $metadata->challenge);
        self::assertSame('GET, POST', $metadata->allow);
        self::assertSame('M-SEARCH, post', (new SafeHttpMetadata(allow: 'M-SEARCH, post'))->allow);
        self::assertSame(PHP_INT_MAX, $metadata->retryAfterSeconds);
        self::assertSame(0, (new SafeHttpMetadata(retryAfterSeconds: 0))->retryAfterSeconds);
        self::assertSame('Bearer', (new SafeHttpMetadata(challenge: 'Bearer'))->challenge);
    }

    public function test_that_constructor_rejects_injected_or_malformed_protocol_metadata(): void
    {
        foreach (["Basic realm=\"api\"\r\nSet-Cookie: secret", '', ' bad', 'Bearer' . "\x7f", 'Basic realm="unterminated', 'Bearer realm="bad" extra'] as $challenge) {
            self::assertInvalid(fn () => new SafeHttpMetadata(challenge: $challenge));
        }

        foreach(["GET\r\nX-Leak: yes", '', 'GET, DELETE,', 'GET, '] as $allow) {
            self::assertInvalid(fn () => new SafeHttpMetadata(allow: $allow));
        }

        self::assertInvalid(fn () => new SafeHttpMetadata(retryAfterSeconds: -1));
    }

    private static function assertInvalid(callable $action): void
    {
        try {
            $action();
            self::fail('Expected invalid metadata to be rejected.');
        } catch (InvalidArgumentException $expected) {
            self::assertNotSame('', $expected->getMessage());
        }
    }
}
