<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Hmac;

use Fight\Common\Adapter\Auth\Hmac\HmacKeyGenerator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HmacKeyGenerator::class)]
class HmacKeyGeneratorTest extends UnitTestCase
{
    public function test_that_generate_secure_random_returns_non_empty_string(): void
    {
        $key = HmacKeyGenerator::generateSecureRandom();

        self::assertIsString($key);
        self::assertNotEmpty($key);
    }

    public function test_that_generate_secure_random_returns_different_value_on_each_call(): void
    {
        $key1 = HmacKeyGenerator::generateSecureRandom();
        $key2 = HmacKeyGenerator::generateSecureRandom();

        self::assertNotSame($key1, $key2);
    }
}
