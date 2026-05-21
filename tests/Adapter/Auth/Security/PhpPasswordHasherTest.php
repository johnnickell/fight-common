<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Security;

use Fight\Common\Adapter\Auth\Security\PhpPasswordHasher;
use Fight\Common\Application\Auth\Exception\PasswordException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PhpPasswordHasher::class)]
class PhpPasswordHasherTest extends UnitTestCase
{
    public function test_that_hash_returns_hashed_string_not_equal_to_original_password(): void
    {
        $hasher = new PhpPasswordHasher(PASSWORD_BCRYPT);
        $hash = $hasher->hash('secret');

        self::assertIsString($hash);
        self::assertNotSame('secret', $hash);
    }

    public function test_that_hash_returns_different_hash_on_each_call_for_same_password(): void
    {
        $hasher = new PhpPasswordHasher(PASSWORD_BCRYPT);

        $hash1 = $hasher->hash('secret');
        $hash2 = $hasher->hash('secret');

        self::assertNotSame($hash1, $hash2);
    }

    public function test_that_hash_throws_for_null_byte_in_password(): void
    {
        $hasher = new PhpPasswordHasher(PASSWORD_BCRYPT);

        $this->expectException(PasswordException::class);

        $hasher->hash("foo\0bar");
    }

    public function test_that_hash_with_options_produces_hash_that_needs_rehash_at_higher_cost(): void
    {
        $hasher = new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
        $hash = $hasher->hash('secret');

        self::assertTrue(password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]));
    }
}
