<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\DateTime;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\DateTime\Timezone;
use Fight\Common\Domain\Value\ValueObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Timezone::class)]
class TimezoneTest extends UnitTestCase
{
    public function test_that_timezone_is_created_from_valid_name(): void
    {
        $tz = new Timezone('America/New_York');

        self::assertInstanceOf(ValueObject::class, $tz);
    }

    public function test_that_from_string_creates_instance(): void
    {
        $tz = Timezone::fromString('Europe/London');

        self::assertSame('Europe/London', $tz->value());
    }

    public function test_that_value_returns_timezone_name(): void
    {
        $tz = new Timezone('UTC');

        self::assertSame('UTC', $tz->value());
    }

    public function test_that_to_string_returns_timezone_name(): void
    {
        $tz = new Timezone('Asia/Tokyo');

        self::assertSame('Asia/Tokyo', (string) $tz);
    }

    public function test_that_to_string_via_to_string_method(): void
    {
        $tz = new Timezone('America/Chicago');

        self::assertSame('America/Chicago', $tz->toString());
    }

    public function test_that_invalid_timezone_throws_domain_exception(): void
    {
        self::expectException(DomainException::class);

        new Timezone('Not/A/Timezone');
    }

    public function test_that_empty_string_throws_domain_exception(): void
    {
        self::expectException(DomainException::class);

        new Timezone('');
    }
}
