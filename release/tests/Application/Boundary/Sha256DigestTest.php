<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application\Boundary;

use Fight\Release\Application\Boundary\Sha256Digest;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers validated SHA-256 release identities. */
#[CoversClass(Sha256Digest::class)]
class Sha256DigestTest extends UnitTestCase
{
    /**
     * Covers exact lowercase hexadecimal SHA-256 validation.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_only_exact_lowercase_sha256_values_are_accepted(): void
    {
        $valid = str_repeat('a', 64);

        self::assertSame($valid, Sha256Digest::tryFrom($valid)?->value);
        self::assertNull(Sha256Digest::tryFrom(str_repeat('a', 63)));
        self::assertNull(Sha256Digest::tryFrom(str_repeat('A', 64)));
        self::assertNull(Sha256Digest::tryFrom(str_repeat('g', 64)));
    }
}
