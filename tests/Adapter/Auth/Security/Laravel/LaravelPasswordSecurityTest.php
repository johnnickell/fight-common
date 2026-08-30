<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Security\Laravel;

use Fight\Common\Adapter\Auth\Security\Laravel\LaravelPasswordHasher;
use Fight\Common\Adapter\Auth\Security\Laravel\LaravelPasswordValidator;
use Fight\Common\Application\Auth\Exception\PasswordException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(LaravelPasswordHasher::class)]
#[CoversClass(LaravelPasswordValidator::class)]
final class LaravelPasswordSecurityTest extends UnitTestCase
{
    public function test_that_configured_laravel_hasher_hashes_validates_and_identifies_rehashes(): void
    {
        $hasher = new BcryptHasher(['rounds' => 4]);
        $passwordHasher = new LaravelPasswordHasher($hasher);
        $passwordValidator = new LaravelPasswordValidator($hasher);
        $hash = $passwordHasher->hash('correct horse battery staple');

        self::assertNotSame('correct horse battery staple', $hash);
        self::assertTrue($passwordValidator->validate('correct horse battery staple', $hash));
        self::assertFalse($passwordValidator->validate('incorrect password', $hash));
        self::assertFalse($passwordValidator->needsRehash($hash));
        self::assertTrue(
            (new LaravelPasswordValidator(new BcryptHasher(['rounds' => 12])))->needsRehash($hash)
        );
    }

    public function test_that_hash_rejects_null_bytes_with_the_port_exception(): void
    {
        $hasher = new LaravelPasswordHasher(new BcryptHasher(['rounds' => 4]));

        $this->expectException(PasswordException::class);

        $hasher->hash("valid\0invalid");
    }

    public function test_that_hash_translates_a_laravel_hasher_failure_to_the_port_exception(): void
    {
        $hasher = $this->mock(Hasher::class);
        $hasher->shouldReceive('make')
            ->once()
            ->with('correct horse battery staple')
            ->andThrow(new RuntimeException('hashing service unavailable'));

        $passwordHasher = new LaravelPasswordHasher($hasher);

        $this->expectException(PasswordException::class);

        $passwordHasher->hash('correct horse battery staple');
    }
}
