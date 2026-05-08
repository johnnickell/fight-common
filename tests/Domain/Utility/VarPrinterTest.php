<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Utility;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Utility\VarPrinter;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Stringable;

enum VarPrinterTestEnum
{
    case Alpha;
    case Beta;
}

#[CoversClass(VarPrinter::class)]
class VarPrinterTest extends UnitTestCase
{
    public function test_that_to_string_returns_null_string_for_null(): void
    {
        self::assertSame('NULL', VarPrinter::toString(null));
    }

    public function test_that_to_string_returns_true_string_for_true(): void
    {
        self::assertSame('TRUE', VarPrinter::toString(true));
    }

    public function test_that_to_string_returns_false_string_for_false(): void
    {
        self::assertSame('FALSE', VarPrinter::toString(false));
    }

    public function test_that_to_string_returns_enum_representation(): void
    {
        $result = VarPrinter::toString(VarPrinterTestEnum::Alpha);

        self::assertSame('Enum(VarPrinterTestEnum::Alpha)', $result);
    }

    public function test_that_to_string_returns_enum_representation_for_different_case(): void
    {
        $result = VarPrinter::toString(VarPrinterTestEnum::Beta);

        self::assertSame('Enum(VarPrinterTestEnum::Beta)', $result);
    }

    public function test_that_to_string_returns_function_string_for_closure(): void
    {
        $closure = function (): void {};

        self::assertSame('Function', VarPrinter::toString($closure));
    }

    public function test_that_to_string_returns_datetime_representation(): void
    {
        $date = new DateTime('2024-06-15 12:30:00', new DateTimeZone('UTC'));
        $expected = sprintf('DateTime(%s)', $date->format('Y-m-d\TH:i:sP'));

        self::assertSame($expected, VarPrinter::toString($date));
    }

    public function test_that_to_string_returns_datetime_immutable_representation(): void
    {
        $date = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $expected = sprintf('DateTimeImmutable(%s)', $date->format('Y-m-d\TH:i:sP'));

        self::assertSame($expected, VarPrinter::toString($date));
    }

    public function test_that_to_string_returns_throwable_representation(): void
    {
        $exception = new RuntimeException('Something failed', 42);
        $result = VarPrinter::toString($exception);

        self::assertStringStartsWith('RuntimeException(', $result);
        self::assertStringContainsString('"message":"Something failed"', $result);
        self::assertStringContainsString('"code":42', $result);
    }

    public function test_that_to_string_uses_to_string_method_when_available(): void
    {
        $object = new class {
            public function toString(): string
            {
                return 'custom-to-string';
            }
        };

        self::assertSame('custom-to-string', VarPrinter::toString($object));
    }

    public function test_that_to_string_uses_stringable_interface(): void
    {
        $object = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };

        self::assertSame('stringable-value', VarPrinter::toString($object));
    }

    public function test_that_to_string_returns_object_representation_for_plain_object(): void
    {
        $object = new stdClass();
        $result = VarPrinter::toString($object);

        self::assertStringStartsWith('Object(', $result);
        self::assertStringContainsString('stdClass', $result);
    }

    public function test_that_to_string_returns_array_representation(): void
    {
        $array = ['key' => 'value', 'num' => 42];
        $result = VarPrinter::toString($array);

        self::assertSame('Array(key => value, num => 42)', $result);
    }

    public function test_that_to_string_returns_nested_array_representation(): void
    {
        $array = ['outer' => ['inner' => 'value']];
        $result = VarPrinter::toString($array);

        self::assertStringStartsWith('Array(outer => Array(', $result);
    }

    public function test_that_to_string_returns_empty_array_representation(): void
    {
        self::assertSame('Array()', VarPrinter::toString([]));
    }

    public function test_that_to_string_returns_integer_as_string(): void
    {
        self::assertSame('42', VarPrinter::toString(42));
    }

    public function test_that_to_string_returns_float_as_string(): void
    {
        self::assertSame('3.14', VarPrinter::toString(3.14));
    }

    public function test_that_to_string_returns_nan_string_for_nan(): void
    {
        self::assertSame('NAN', VarPrinter::toString(NAN));
    }

    public function test_that_to_string_returns_inf_string_for_positive_infinity(): void
    {
        self::assertSame('INF', VarPrinter::toString(INF));
    }

    public function test_that_to_string_returns_negative_inf_string_for_negative_infinity(): void
    {
        self::assertSame('-INF', VarPrinter::toString(-INF));
    }

    public function test_that_to_string_returns_string_value_unchanged(): void
    {
        self::assertSame('hello world', VarPrinter::toString('hello world'));
    }

    public function test_that_to_string_returns_resource_representation(): void
    {
        $resource = fopen('php://memory', 'r');
        $result = VarPrinter::toString($resource);
        fclose($resource);

        self::assertMatchesRegularExpression('/\AResource\(\d+:stream\)\z/', $result);
    }
}
