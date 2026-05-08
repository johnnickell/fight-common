<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsIpV4Address;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsIpV4Address::class)]
class IsIpV4AddressTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_ipv4_address(): void
    {
        self::assertTrue((new IsIpV4Address())->isSatisfiedBy('192.168.1.1'));
    }

    public function test_that_is_satisfied_by_returns_false_for_ipv6_address(): void
    {
        self::assertFalse((new IsIpV4Address())->isSatisfiedBy('2001:db8::1'));
    }
}
