<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\DateTime;

use Fight\Common\Domain\Value\DateTime\WeekDay;
use Fight\Common\Domain\Value\Value;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;
use ValueError;

#[CoversClass(WeekDay::class)]
class WeekDayTest extends UnitTestCase
{
    public function test_that_native_cases_use_sunday_zero_backing_and_json_instead_of_value_object_semantics(): void
    {
        $expected = [
            'SUNDAY' => 0, 'MONDAY' => 1, 'TUESDAY' => 2, 'WEDNESDAY' => 3,
            'THURSDAY' => 4, 'FRIDAY' => 5, 'SATURDAY' => 6
        ];
        self::assertCount(7, WeekDay::cases());
        foreach (WeekDay::cases() as $case) {
            self::assertSame($expected[$case->name], $case->value);
            self::assertSame($case, WeekDay::from($expected[$case->name]));
            self::assertSame($case, WeekDay::tryFrom($expected[$case->name]));
            self::assertSame((string) $expected[$case->name], json_encode($case, JSON_THROW_ON_ERROR));
            self::assertFalse($case instanceof Value);
        }
    }

    #[DataProvider('invalidValues')]
    public function test_that_invalid_backing_values_have_native_from_and_try_from_behavior(int $value): void
    {
        self::assertNull(WeekDay::tryFrom($value));
        $this->expectException(ValueError::class);

        WeekDay::from($value);
    }

    /**
     * @return iterable<array{int}>
     */
    public static function invalidValues(): iterable
    {
        yield [7];
        yield [-1];
        yield [8];
        yield [PHP_INT_MIN];
        yield [PHP_INT_MAX];
    }

    public function test_that_strict_native_enum_typing_is_not_domain_validation(): void
    {
        $this->expectException(TypeError::class);

        WeekDay::from('1');
    }
}
