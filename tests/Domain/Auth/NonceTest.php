<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Auth;

use DateTimeImmutable;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Nonce::class)]
class NonceTest extends UnitTestCase
{
    public function test_that_generate_creates_nonce_with_hex_value_and_expiry(): void
    {
        $before = new DateTimeImmutable();
        $nonce = Nonce::generate(8, 300);
        $after = new DateTimeImmutable('+300 seconds');

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $nonce->value());
        self::assertGreaterThanOrEqual($before->getTimestamp() + 299, $nonce->expiresAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp() + 1, $nonce->expiresAt()->getTimestamp());
    }

    public function test_that_generate_with_different_byte_count_produces_correct_length(): void
    {
        $nonce = Nonce::generate(16);
        // 16 bytes hex-encoded = 32 hex chars
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $nonce->value());
    }

    public function test_that_is_expired_returns_false_for_future_expiry(): void
    {
        $nonce = new Nonce('abc', new DateTimeImmutable('+1 hour'));
        self::assertFalse($nonce->isExpired());
    }

    public function test_that_is_expired_returns_true_for_past_expiry(): void
    {
        $nonce = new Nonce('abc', new DateTimeImmutable('-1 second'));
        self::assertTrue($nonce->isExpired());
    }

    public function test_that_constructor_sets_value_and_expiry(): void
    {
        $expiry = new DateTimeImmutable('+5 minutes');
        $nonce = new Nonce('my-nonce', $expiry);

        self::assertSame('my-nonce', $nonce->value());
        self::assertSame($expiry, $nonce->expiresAt());
    }
}
