<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsIpAddress;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsIpAddress::class)]
class IsIpAddressTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_ipv4_address(): void
    {
        self::assertTrue((new IsIpAddress())->isSatisfiedBy('192.168.1.1'));
    }

    public function test_that_is_satisfied_by_returns_true_for_valid_ipv6_address(): void
    {
        self::assertTrue((new IsIpAddress())->isSatisfiedBy('2001:db8::1'));
    }

    public function test_that_is_satisfied_by_returns_false_for_invalid_ip_address(): void
    {
        self::assertFalse((new IsIpAddress())->isSatisfiedBy('999.999.999.999'));
    }
}
