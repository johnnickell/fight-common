<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\IpV6Address;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IpV6Address::class)]
class IpV6AddressTest extends UnitTestCase
{
    #[DataProvider('canonicalAddresses')]
    public function test_that_factory_renders_deterministic_lowercase_compressed_hexadecimal(
        string $input,
        string $canonical
    ): void {
        $address = IpV6Address::fromString($input);
        $roundTrip = IpV6Address::fromString($canonical);

        self::assertSame($canonical, $address->toString());
        self::assertSame($canonical, (string) $address);
        self::assertSame($canonical, $address->jsonSerialize());
        self::assertSame(json_encode($canonical), json_encode($address));
        self::assertTrue($address->equals($roundTrip));
        self::assertSame($address->hashValue(), $roundTrip->hashValue());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalAddresses(): iterable
    {
        yield 'unspecified' => ['0:0:0:0:0:0:0:0', '::'];
        yield 'loopback' => ['0000:0000:0000:0000:0000:0000:0000:0001', '::1'];
        yield 'upper case and leading zeros' => ['2001:0DB8:0000:0000:0000:0000:0000:0001', '2001:db8::1'];
        yield 'no zero groups' => ['2001:0DB8:0001:0002:0003:0004:0005:0006', '2001:db8:1:2:3:4:5:6'];
        yield 'isolated zero' => ['2001:db8:1:0:2:3:4:5', '2001:db8:1:0:2:3:4:5'];
        yield 'first equal longest run' => ['2001:0:0:1:0:0:1:1', '2001::1:0:0:1:1'];
        yield 'later longer run' => ['2001:0:0:1:0:0:0:1', '2001:0:0:1::1'];
        yield 'trailing longest run' => ['2001:db8:1:2:0:0:0:0', '2001:db8:1:2::'];
        yield 'leading longest run' => ['0:0:0:0:1:2:3:4', '::1:2:3:4'];
        yield 'two zeros are compressed' => ['1:2:3:4:5:6:0:0', '1:2:3:4:5:6::'];
        yield 'already compressed' => ['2001:db8::1', '2001:db8::1'];
        yield 'maximum' => ['FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'];
        yield 'link local' => ['FE80:0:0:0:0:0:0:1', 'fe80::1'];
        yield 'unique local' => ['FD00:0:0:0:0:0:0:1', 'fd00::1'];
        yield 'multicast' => ['FF02:0:0:0:0:0:0:1', 'ff02::1'];
        yield 'mapped dotted suffix' => ['::FFFF:192.0.2.1', '::ffff:c000:201'];
        yield 'mapped hex suffix' => ['0:0:0:0:0:ffff:C000:0201', '::ffff:c000:201'];
        yield 'mapped zero address' => ['::ffff:0.0.0.0', '::ffff:0:0'];
        yield 'mapped broadcast' => ['::ffff:255.255.255.255', '::ffff:ffff:ffff'];
        yield 'other embedded IPv4' => ['2001:db8::192.0.2.1', '2001:db8::c000:201'];
        yield 'compatible IPv4' => ['::192.0.2.1', '::c000:201'];
        yield 'bare terminal hextet' => ['2001:db8::443', '2001:db8::443'];
    }

    #[DataProvider('invalidAddresses')]
    public function test_that_factory_rejects_malformed_contextual_and_wrong_family_inputs(string $input): void
    {
        $this->expectException(DomainException::class);

        IpV6Address::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'IPv4' => ['192.0.2.1'];
        yield 'triple colon' => [':::1'];
        yield 'duplicate compression' => ['2001::db8::1'];
        yield 'too few groups' => ['1:2:3:4:5:6:7'];
        yield 'too many groups' => ['1:2:3:4:5:6:7:8:9'];
        yield 'compression with eight groups' => ['1:2:3:4:5:6:7::8'];
        yield 'group too long' => ['12345::1'];
        yield 'nonhex group' => ['2001:db8::g'];
        yield 'leading single colon' => [':1:2:3:4:5:6:7'];
        yield 'trailing single colon' => ['1:2:3:4:5:6:7:'];
        yield 'hostname' => ['example.com'];
        yield 'URI host' => ['[::1]'];
        yield 'URI port' => ['[::1]:443'];
        yield 'CIDR' => ['2001:db8::/32'];
        yield 'zone' => ['fe80::1%eth0'];
        yield 'encoded zone' => ['fe80::1%25eth0'];
        yield 'leading whitespace' => ["\t::1"];
        yield 'trailing whitespace' => ['::1 '];
        yield 'embedded NUL' => ["::1\0"];
        yield 'invalid dotted suffix' => ['::ffff:192.0.2.256'];
        yield 'leading zero dotted suffix' => ['::ffff:192.0.02.1'];
        yield 'too few dotted components' => ['::ffff:192.0.1'];
        yield 'too many mixed groups' => ['1:2:3:4:5:6:7:192.0.2.1'];
    }
}
