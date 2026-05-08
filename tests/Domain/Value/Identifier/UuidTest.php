<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Identifier;

use stdClass;
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

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_that_is_valid_returns_false_for_an_invalid_uuid_string(): void
    {
        self::assertFalse(Uuid::isValid('not-a-uuid'));
    }

    // -------------------------------------------------------------------------
    // Field accessors
    // -------------------------------------------------------------------------

    public function test_that_field_accessors_return_the_correct_components(): void
    {
        // NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);

        self::assertSame('6ba7b810', $uuid->timeLow());
        self::assertSame('9dad', $uuid->timeMid());
        self::assertSame('11d1', $uuid->timeHiAndVersion());
        self::assertSame('80', $uuid->clockSeqHiAndReserved());
        self::assertSame('b4', $uuid->clockSeqLow());
        self::assertSame('00c04fd430c8', $uuid->node());
    }

    public function test_that_most_significant_bits_returns_first_64_bits_as_hex(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame('6ba7b8109dad11d1', $uuid->mostSignificantBits());
    }

    public function test_that_least_significant_bits_returns_last_64_bits_as_hex(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame('80b400c04fd430c8', $uuid->leastSignificantBits());
    }

    // -------------------------------------------------------------------------
    // Version
    // -------------------------------------------------------------------------

    public function test_that_version_returns_version_time_for_a_time_based_uuid(): void
    {
        self::assertSame(Uuid::VERSION_TIME, Uuid::time()->version());
    }

    public function test_that_version_returns_version_md5_for_an_md5_named_uuid(): void
    {
        self::assertSame(Uuid::VERSION_MD5, Uuid::md5(Uuid::NAMESPACE_DNS, 'test')->version());
    }

    public function test_that_version_returns_version_random_for_a_random_uuid(): void
    {
        self::assertSame(Uuid::VERSION_RANDOM, Uuid::random()->version());
    }

    public function test_that_version_returns_version_sha1_for_a_named_uuid(): void
    {
        self::assertSame(Uuid::VERSION_SHA1, Uuid::named(Uuid::NAMESPACE_DNS, 'test')->version());
    }

    public function test_that_version_returns_version_unknown_when_version_bits_do_not_match(): void
    {
        // Nil UUID has timeHiAndVersion '0000'; first hex digit 0 is not in [1..5]
        $uuid = Uuid::parse(Uuid::NIL);
        self::assertSame(Uuid::VERSION_UNKNOWN, $uuid->version());
    }

    // -------------------------------------------------------------------------
    // Variant
    // -------------------------------------------------------------------------

    public function test_that_variant_returns_rfc_4122_for_a_standard_generated_uuid(): void
    {
        self::assertSame(Uuid::VARIANT_RFC_4122, Uuid::random()->variant());
    }

    public function test_that_variant_returns_reserved_ncs_when_most_significant_bit_is_zero(): void
    {
        // clockSeqHiAndReserved = 7f (0111 1111) — MSB is 0
        $uuid = Uuid::parse('00000000-0000-0000-7fff-000000000000');
        self::assertSame(Uuid::VARIANT_RESERVED_NCS, $uuid->variant());
    }

    public function test_that_variant_returns_reserved_microsoft_for_110_bit_prefix(): void
    {
        // clockSeqHiAndReserved = c0 (1100 0000) — MSBs are 110
        $uuid = Uuid::parse('00000000-0000-0000-c0ff-000000000000');
        self::assertSame(Uuid::VARIANT_RESERVED_MICROSOFT, $uuid->variant());
    }

    public function test_that_variant_returns_reserved_future_for_111_bit_prefix(): void
    {
        // clockSeqHiAndReserved = e0 (1110 0000) — MSBs are 111
        $uuid = Uuid::parse('00000000-0000-0000-e0ff-000000000000');
        self::assertSame(Uuid::VARIANT_RESERVED_FUTURE, $uuid->variant());
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function test_that_compare_to_returns_zero_for_same_instance(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame(0, $uuid->compareTo($uuid));
    }

    public function test_that_compare_to_returns_zero_for_equal_value_different_instances(): void
    {
        $uuid1 = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid2 = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame(0, $uuid1->compareTo($uuid2));
    }

    public function test_that_compare_to_returns_negative_when_less_than_other(): void
    {
        // NAMESPACE_DNS ends in ...b810... NAMESPACE_URL ends in ...b811...
        $lesser  = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $greater = Uuid::fromString(Uuid::NAMESPACE_URL);
        self::assertLessThan(0, $lesser->compareTo($greater));
    }

    public function test_that_compare_to_returns_positive_when_greater_than_other(): void
    {
        $lesser  = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $greater = Uuid::fromString(Uuid::NAMESPACE_URL);
        self::assertGreaterThan(0, $greater->compareTo($lesser));
    }

    // -------------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------------

    public function test_that_equals_returns_true_for_the_same_instance(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertTrue($uuid->equals($uuid));
    }

    public function test_that_equals_returns_true_for_same_uuid_string(): void
    {
        $uuid1 = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid2 = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertTrue($uuid1->equals($uuid2));
    }

    public function test_that_equals_returns_false_for_different_uuid(): void
    {
        $uuid1 = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid2 = Uuid::fromString(Uuid::NAMESPACE_URL);
        self::assertFalse($uuid1->equals($uuid2));
    }

    public function test_that_equals_returns_false_for_a_non_uuid_value(): void
    {
        self::assertFalse(Uuid::fromString(Uuid::NAMESPACE_DNS)->equals(new stdClass()));
    }

    public function test_that_hash_value_returns_same_hex_for_two_uuids_from_the_same_string(): void
    {
        $uuid1 = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid2 = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame($uuid1->hashValue(), $uuid2->hashValue());
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    public function test_that_to_urn_returns_string_beginning_with_urn_uuid(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame('urn:uuid:' . Uuid::NAMESPACE_DNS, $uuid->toUrn());
    }

    public function test_that_to_hex_returns_32_character_lowercase_hex_string(): void
    {
        $hex = Uuid::random()->toHex();
        self::assertSame(32, strlen($hex));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $hex);
    }

    public function test_that_to_bytes_returns_16_byte_string(): void
    {
        self::assertSame(16, strlen(Uuid::random()->toBytes()));
    }

    public function test_that_to_array_returns_array_with_the_six_expected_keys(): void
    {
        $array = Uuid::fromString(Uuid::NAMESPACE_DNS)->toArray();

        self::assertArrayHasKey('time_low', $array);
        self::assertArrayHasKey('time_mid', $array);
        self::assertArrayHasKey('time_hi_and_version', $array);
        self::assertArrayHasKey('clock_seq_hi_and_reserved', $array);
        self::assertArrayHasKey('clock_seq_low', $array);
        self::assertArrayHasKey('node', $array);
        self::assertCount(6, $array);
    }

    public function test_that_to_string_returns_canonical_hyphenated_uuid_string(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame(Uuid::NAMESPACE_DNS, $uuid->toString());
        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/',
            $uuid->toString()
        );
    }

    public function test_that_cast_to_string_returns_canonical_hyphenated_uuid_string(): void
    {
        $uuid = Uuid::fromString(Uuid::NAMESPACE_DNS);
        self::assertSame(Uuid::NAMESPACE_DNS, (string) $uuid);
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    public function test_that_time_throws_for_invalid_timestamp(): void
    {
        $this->expectException(DomainException::class);
        Uuid::time(timestamp: 'invalid');
    }

    public function test_that_time_throws_for_clock_sequence_out_of_range(): void
    {
        $this->expectException(DomainException::class);
        Uuid::time(clockSeq: 99999);
    }

    public function test_that_time_throws_for_invalid_node(): void
    {
        $this->expectException(DomainException::class);
        // after stripping hyphens: 'badnode' — 7 chars, not valid 12-char hex
        Uuid::time(node: 'bad-node');
    }
}
