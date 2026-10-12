<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\IpV4Address;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IpV4Address::class)]
class IpV4AddressTest extends UnitTestCase
{
    #[DataProvider('validAddresses')]
    public function test_that_factory_accepts_bare_ipv4_without_category_policy(string $input): void
    {
        $address = IpV4Address::fromString($input);

        self::assertSame($input, $address->toString());
        self::assertSame($input, (string) $address);
        self::assertSame(json_encode($input), json_encode($address));
        self::assertTrue($address->equals(IpV4Address::fromString($address->toString())));
        self::assertSame($input, $address->hashValue());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validAddresses(): iterable
    {
        yield 'unspecified' => ['0.0.0.0'];
        yield 'broadcast' => ['255.255.255.255'];
        yield 'loopback' => ['127.0.0.1'];
        yield 'private class A' => ['10.0.0.1'];
        yield 'private class B' => ['172.16.0.1'];
        yield 'private class C' => ['192.168.1.1'];
        yield 'link local' => ['169.254.1.1'];
        yield 'multicast' => ['224.0.0.1'];
        yield 'documentation' => ['192.0.2.1'];
    }

    #[DataProvider('invalidAddresses')]
    public function test_that_factory_rejects_malformed_contextual_and_wrong_family_inputs(string $input): void
    {
        $this->expectException(DomainException::class);

        IpV4Address::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'out of range' => ['256.0.0.1'];
        yield 'negative' => ['-1.0.0.1'];
        yield 'leading zero' => ['192.168.01.1'];
        yield 'all leading zeros' => ['000.000.000.000'];
        yield 'missing component' => ['192.0.1'];
        yield 'extra component' => ['192.0.2.1.1'];
        yield 'trailing separator' => ['192.0.2.1.'];
        yield 'hex component' => ['0xc0.0.2.1'];
        yield 'single integer' => ['3221225985'];
        yield 'hostname' => ['localhost'];
        yield 'port' => ['192.0.2.1:80'];
        yield 'CIDR' => ['192.0.2.1/32'];
        yield 'brackets' => ['[192.0.2.1]'];
        yield 'leading space' => [' 192.0.2.1'];
        yield 'trailing newline' => ["192.0.2.1\n"];
        yield 'Unicode digit' => ['１９２.0.2.1'];
        yield 'IPv6' => ['::1'];
        yield 'dotted mapped IPv6' => ['::ffff:192.0.2.1'];
        yield 'hex mapped IPv6' => ['::ffff:c000:201'];
    }

    public function test_that_independent_ipv4_values_have_equal_hashes_and_real_set_semantics(): void
    {
        $first = IpV4Address::fromString('192.0.2.1');
        $equal = IpV4Address::fromString('192.0.2.1');
        $different = IpV4Address::fromString('192.0.2.2');
        $set = HashSet::of(IpV4Address::class);
        $set->add($first);
        $set->add($equal);

        self::assertNotSame($first, $equal);
        self::assertTrue($first->equals($equal));
        self::assertSame($first->hashValue(), $equal->hashValue());
        self::assertFalse($first->equals($different));
        self::assertNotSame($first->hashValue(), $different->hashValue());
        self::assertSame(1, $set->count());
        self::assertTrue($set->contains(IpV4Address::fromString('192.0.2.1')));
        self::assertFalse($set->contains($different));

        $set->remove(IpV4Address::fromString('192.0.2.1'));
        self::assertTrue($set->isEmpty());
    }
}
