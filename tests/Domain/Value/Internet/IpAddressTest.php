<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Internet\IpAddress;
use Fight\Common\Domain\Value\Internet\IpV4Address;
use Fight\Common\Domain\Value\Internet\IpV6Address;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IpAddress::class)]
class IpAddressTest extends UnitTestCase
{
    #[DataProvider('addresses')]
    public function test_that_generic_factory_selects_family_and_reconstructs_canonical_value(
        string $input,
        string $family,
        string $canonical
    ): void {
        $address = IpAddress::fromString($input);

        self::assertInstanceOf($family, $address);
        self::assertSame($canonical, $address->toString());
        self::assertSame($canonical, (string) $address);
        self::assertSame($canonical, $address->jsonSerialize());
        self::assertSame(json_encode($canonical), json_encode($address));
        self::assertTrue($address->equals(IpAddress::fromString($canonical)));
        self::assertTrue($address->equals($family::fromString(json_decode(json_encode($address), true))));
        self::assertSame($canonical, $address->hashValue());
    }

    /**
     * @return iterable<string, array{string, class-string<IpAddress>, string}>
     */
    public static function addresses(): iterable
    {
        yield 'IPv4' => ['192.0.2.1', IpV4Address::class, '192.0.2.1'];
        yield 'IPv6' => ['2001:0DB8:0000:0000:0000:0000:0000:0001', IpV6Address::class, '2001:db8::1'];
        yield 'dotted mapped IPv6' => ['::FFFF:192.0.2.1', IpV6Address::class, '::ffff:c000:201'];
        yield 'hex mapped IPv6' => ['0:0:0:0:0:ffff:c000:0201', IpV6Address::class, '::ffff:c000:201'];
        yield 'bare final hextet is not a port' => ['2001:db8::80', IpV6Address::class, '2001:db8::80'];
    }

    #[DataProvider('invalidAddresses')]
    public function test_that_generic_factory_rejects_malformed_or_contextual_literals(string $input): void
    {
        $this->expectException(DomainException::class);

        IpAddress::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'hostname' => ['example.com'];
        yield 'IPv4 leading zero' => ['192.168.001.1'];
        yield 'IPv4 port' => ['192.0.2.1:80'];
        yield 'IPv4 CIDR' => ['192.0.2.1/24'];
        yield 'IPv6 CIDR' => ['2001:db8::/32'];
        yield 'IPv6 URI host' => ['[::1]'];
        yield 'IPv6 URI host and port' => ['[::1]:80'];
        yield 'zone name' => ['fe80::1%eth0'];
        yield 'zone index' => ['fe80::1%1'];
        yield 'leading space' => [' 192.0.2.1'];
        yield 'trailing space' => ['::1 '];
        yield 'newline' => ["::1\n"];
        yield 'embedded NUL' => ["192.0.2.1\0"];
    }

    public function test_that_values_compare_by_concrete_family_and_canonical_content(): void
    {
        $first = IpAddress::fromString('::ffff:192.0.2.1');
        $equal = IpV6Address::fromString('::FFFF:C000:0201');
        $different = IpV6Address::fromString('::ffff:192.0.2.2');
        $ipv4 = IpV4Address::fromString('192.0.2.1');

        self::assertTrue($first->equals($first));
        self::assertNotSame($first, $equal);
        self::assertTrue($first->equals($equal));
        self::assertTrue($equal->equals($first));
        self::assertSame($first->hashValue(), $equal->hashValue());
        self::assertFalse($first->equals($different));
        self::assertNotSame($first->hashValue(), $different->hashValue());
        self::assertFalse($first->equals($ipv4));
        self::assertFalse($ipv4->equals($first));
        self::assertFalse($first->equals(StringObject::fromString('::ffff:c000:201')));
        self::assertFalse($first->equals('::ffff:c000:201'));
        self::assertFalse($first->equals(null));
    }

    public function test_that_hash_set_deduplicates_equivalent_values_and_keeps_families_distinct(): void
    {
        $set = HashSet::of(IpAddress::class);
        $set->add(IpAddress::fromString('::ffff:192.0.2.1'));
        $set->add(IpV6Address::fromString('0:0:0:0:0:ffff:c000:201'));
        $set->add(IpV4Address::fromString('192.0.2.1'));

        self::assertSame(2, $set->count());
        self::assertTrue($set->contains(IpV6Address::fromString('::FFFF:C000:0201')));
        self::assertTrue($set->contains(IpAddress::fromString('192.0.2.1')));
        self::assertFalse($set->contains(IpV6Address::fromString('::ffff:192.0.2.2')));

        $set->remove(IpAddress::fromString('::FFFF:C000:201'));
        self::assertSame(1, $set->count());
        self::assertFalse($set->contains(IpV6Address::fromString('::ffff:192.0.2.1')));
        self::assertTrue($set->contains(IpV4Address::fromString('192.0.2.1')));

        $set->remove(IpV4Address::fromString('192.0.2.1'));
        self::assertTrue($set->isEmpty());
    }

    #[DataProvider('addresses')]
    public function test_that_address_values_cannot_acquire_mutable_properties(
        string $input,
        string $family,
        string $canonical
    ): void {
        $address = IpAddress::fromString($input);

        try {
            $address->context = 'changed';
            self::fail('Readonly values must reject mutable properties');
        } catch (Error) {
            self::assertInstanceOf($family, $address);
            self::assertSame($canonical, $address->toString());
        }
    }
}
