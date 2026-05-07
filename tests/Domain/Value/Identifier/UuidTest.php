<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Identifier;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Uuid::class)]
class UuidTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_that_random_creates_a_version_4_uuid(): void
    {
        $uuid = Uuid::random();

        self::assertSame(Uuid::VERSION_RANDOM, $uuid->version());
        self::assertSame(Uuid::VARIANT_RFC_4122, $uuid->variant());
        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    public function test_that_comb_creates_a_valid_uuid_with_msb_timestamp(): void
    {
        $uuid = Uuid::comb(true);

        self::assertSame(Uuid::VERSION_RANDOM, $uuid->version());
        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    public function test_that_comb_creates_a_valid_uuid_with_lsb_timestamp(): void
    {
        $uuid = Uuid::comb(false);

        self::assertSame(Uuid::VERSION_RANDOM, $uuid->version());
        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    public function test_that_time_creates_a_version_1_uuid(): void
    {
        $uuid = Uuid::time();

        self::assertSame(Uuid::VERSION_TIME, $uuid->version());
        self::assertSame(Uuid::VARIANT_RFC_4122, $uuid->variant());
        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    public function test_that_named_creates_a_version_5_uuid(): void
    {
        $uuid = Uuid::named(Uuid::NAMESPACE_DNS, 'example.com');

        self::assertSame(Uuid::VERSION_SHA1, $uuid->version());
        self::assertSame(Uuid::VARIANT_RFC_4122, $uuid->variant());
        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    public function test_that_named_accepts_a_uuid_instance_as_namespace(): void
    {
        $ns = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid = Uuid::named($ns, 'example.com');

        self::assertSame(Uuid::VERSION_SHA1, $uuid->version());
    }

    public function test_that_md5_creates_a_version_3_uuid(): void
    {
        $uuid = Uuid::md5(Uuid::NAMESPACE_DNS, 'example.com');

        self::assertSame(Uuid::VERSION_MD5, $uuid->version());
        self::assertSame(Uuid::VARIANT_RFC_4122, $uuid->variant());
        self::assertTrue(Uuid::isValid($uuid->toString()));
    }

    public function test_that_md5_accepts_a_uuid_instance_as_namespace(): void
    {
        $ns = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid = Uuid::md5($ns, 'example.com');

        self::assertSame(Uuid::VERSION_MD5, $uuid->version());
    }

    public function test_that_from_string_creates_instance_from_valid_uuid(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);

        self::assertSame(Uuid::NAMESPACE_DNS, $uuid->toString());
    }

    public function test_that_from_string_throws_for_invalid_uuid_string(): void
    {
        $this->expectException(DomainException::class);
        Uuid::fromString('not-a-uuid');
    }

    public function test_that_from_hex_creates_instance_from_valid_hex_string(): void
    {
        $hex = '6ba7b8109dad11d180b400c04fd430c8';
        $uuid = Uuid::fromHex($hex);

        self::assertSame($hex, $uuid->toHex());
        self::assertSame(Uuid::NAMESPACE_DNS, $uuid->toString());
    }

    public function test_that_from_hex_throws_for_invalid_hex_string(): void
    {
        $this->expectException(DomainException::class);
        Uuid::fromHex('not-valid-hex');
    }

    public function test_that_from_bytes_creates_instance_from_valid_16_byte_string(): void
    {
        $known = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid = Uuid::fromBytes($known->toBytes());

        self::assertSame(Uuid::NAMESPACE_DNS, $uuid->toString());
    }

    public function test_that_from_bytes_throws_when_byte_string_is_not_16_bytes(): void
    {
        $this->expectException(DomainException::class);
        Uuid::fromBytes('tooshort');
    }
}
