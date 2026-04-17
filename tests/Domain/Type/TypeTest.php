<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Type;

use Exception;
use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(Type::class)]
class TypeTest extends UnitTestCase
{
    public function test_that_create_from_object_returns_expected_class_name(): void
    {
        $exceptionClass = RuntimeException::class;
        $exception = new RuntimeException('Test exception');
        $type = Type::create($exception);

        self::assertSame($exceptionClass, $type->toClassName());
    }

    public function test_that_create_from_string_returns_expected_class_name(): void
    {
        $exceptionClass = RuntimeException::class;
        $type = Type::create($exceptionClass);

        self::assertSame($exceptionClass, $type->toClassName());
    }

    public function test_that_to_string_returns_expected_value(): void
    {
        $type = Type::create(RuntimeException::class);

        self::assertSame(ClassName::canonical(RuntimeException::class), $type->toString());
    }

    public function test_that_cast_to_string_returns_expected_value(): void
    {
        $type = Type::create(RuntimeException::class);

        self::assertSame(ClassName::canonical(RuntimeException::class), (string) $type);
    }

    public function test_that_json_serialize_returns_expected_value(): void
    {
        $type = Type::create(RuntimeException::class);

        self::assertSame(json_encode(ClassName::canonical(RuntimeException::class)), json_encode($type));
    }

    public function test_that_serialize_and_unserialize_preserve_value(): void
    {
        $type = Type::create(RuntimeException::class);
        $serialized = serialize($type);
        $unserialized = unserialize($serialized);

        self::assertTrue($type->equals($unserialized));
    }

    public function test_that_equals_returns_true_for_same_instance(): void
    {
        $type = Type::create(RuntimeException::class);

        self::assertTrue($type->equals($type));
    }

    public function test_that_equals_returns_true_for_identical_type(): void
    {
        $type1 = Type::create(RuntimeException::class);
        $type2 = Type::create(RuntimeException::class);

        self::assertTrue($type1->equals($type2));
    }

    public function test_that_equals_returns_false_for_different_type(): void
    {
        $type1 = Type::create(RuntimeException::class);
        $type2 = Type::create(Exception::class);

        self::assertFalse($type1->equals($type2));
    }

    public function test_that_equals_returns_false_for_non_type_object(): void
    {
        $type = Type::create(RuntimeException::class);
        $nonType = new stdClass();

        self::assertFalse($type->equals($nonType));
    }

    public function test_that_hash_value_returns_expected_value(): void
    {
        $type = Type::create(RuntimeException::class);

        self::assertSame(ClassName::canonical(RuntimeException::class), $type->hashValue());
    }
}
