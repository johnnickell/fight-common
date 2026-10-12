<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsIpAddress;
use Fight\Common\Application\Validation\Rule\IsIpV4Address;
use Fight\Common\Application\Validation\Rule\IsIpV6Address;
use Fight\Common\Domain\Utility\Validate;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(IsIpAddress::class)]
#[CoversClass(IsIpV4Address::class)]
#[CoversClass(IsIpV6Address::class)]
#[CoversClass(Validate::class)]
class IpAddressRuleContractTest extends UnitTestCase
{
    #[DataProvider('addressFamilies')]
    public function test_that_existing_predicates_and_rules_retain_lexical_family_acceptance(
        string $input,
        bool $ipv4,
        bool $ipv6
    ): void {
        self::assertSame($ipv4 || $ipv6, Validate::isIpAddress($input));
        self::assertSame($ipv4, Validate::isIpV4Address($input));
        self::assertSame($ipv6, Validate::isIpV6Address($input));
        self::assertSame($ipv4 || $ipv6, new IsIpAddress()->isSatisfiedBy($input));
        self::assertSame($ipv4, new IsIpV4Address()->isSatisfiedBy($input));
        self::assertSame($ipv6, new IsIpV6Address()->isSatisfiedBy($input));
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function addressFamilies(): iterable
    {
        yield 'IPv4' => ['192.0.2.1', true, false];
        yield 'private IPv4' => ['192.168.1.1', true, false];
        yield 'loopback IPv4' => ['127.0.0.1', true, false];
        yield 'broadcast IPv4' => ['255.255.255.255', true, false];
        yield 'expanded IPv6' => ['2001:0DB8:0000:0000:0000:0000:0000:0001', false, true];
        yield 'compressed IPv6' => ['2001:db8::1', false, true];
        yield 'mapped dotted IPv6' => ['::ffff:192.0.2.1', false, true];
        yield 'mapped hexadecimal IPv6' => ['::FFFF:C000:0201', false, true];
        yield 'bare IPv6 terminal hextet' => ['2001:db8::80', false, true];
        yield 'leading zero IPv4' => ['192.168.001.1', false, false];
        yield 'malformed IPv4' => ['256.0.0.1', false, false];
        yield 'malformed IPv6' => ['2001::db8::1', false, false];
        yield 'IPv6 zone' => ['fe80::1%eth0', false, false];
        yield 'bracketed IPv6' => ['[::1]', false, false];
        yield 'IPv4 CIDR' => ['192.0.2.1/24', false, false];
        yield 'IPv6 CIDR' => ['2001:db8::/32', false, false];
        yield 'IPv4 port' => ['192.0.2.1:80', false, false];
        yield 'leading whitespace' => [' 192.0.2.1', false, false];
        yield 'trailing whitespace' => ['::1 ', false, false];
        yield 'hostname' => ['localhost', false, false];
    }
}
