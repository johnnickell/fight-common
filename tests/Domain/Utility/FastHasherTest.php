<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Utility;

use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\FastHasher;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(FastHasher::class)]
class FastHasherTest extends UnitTestCase
{
    public function test_that_hash_returns_string_hash_for_string_value(): void
    {
        $expected = hash('fnv1a32', 's_hello');

        self::assertSame($expected, FastHasher::hash('hello'));
    }

    public function test_that_hash_returns_integer_hash_for_integer_value(): void
    {
        $expected = hash('fnv1a32', 'i_42');

        self::assertSame($expected, FastHasher::hash(42));
    }

    public function test_that_hash_returns_float_hash_for_float_value(): void
    {
        $expected = hash('fnv1a32', sprintf('f_%.14F', 3.14));

        self::assertSame($expected, FastHasher::hash(3.14));
    }

    public function test_that_hash_returns_bool_hash_for_true(): void
    {
        $expected = hash('fnv1a32', 'b_1');

        self::assertSame($expected, FastHasher::hash(true));
    }

    public function test_that_hash_returns_bool_hash_for_false(): void
    {
        $expected = hash('fnv1a32', 'b_0');

        self::assertSame($expected, FastHasher::hash(false));
    }

    public function test_that_hash_returns_null_hash_for_null(): void
    {
        $expected = hash('fnv1a32', '0');

        self::assertSame($expected, FastHasher::hash(null));
    }

    public function test_that_hash_returns_array_hash_for_array(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $expected = hash('fnv1a32', 'a_' . serialize($array));

        self::assertSame($expected, FastHasher::hash($array));
    }

    public function test_that_hash_returns_equatable_hash_for_equatable_object(): void
    {
        $type = Type::create(RuntimeException::class);
        $expected = hash('fnv1a32', 'e_' . $type->hashValue());

        self::assertSame($expected, FastHasher::hash($type));
    }

    public function test_that_hash_returns_object_hash_for_non_equatable_object(): void
    {
        $object = new stdClass();
        $expected = hash('fnv1a32', 'o_' . spl_object_hash($object));

        self::assertSame($expected, FastHasher::hash($object));
    }

    public function test_that_hash_returns_resource_hash_for_resource(): void
    {
        $resource = fopen('php://memory', 'r');
        $expected = hash('fnv1a32', sprintf('r_%d', get_resource_id($resource)));
        $result = FastHasher::hash($resource);
        fclose($resource);

        self::assertSame($expected, $result);
    }

    public function test_that_hash_uses_custom_algorithm(): void
    {
        $expected = hash('md5', 's_hello');

        self::assertSame($expected, FastHasher::hash('hello', 'md5'));
    }

    public function test_that_hash_returns_same_result_for_same_value(): void
    {
        $hash1 = FastHasher::hash('consistent');
        $hash2 = FastHasher::hash('consistent');

        self::assertSame($hash1, $hash2);
    }

    public function test_that_hash_returns_different_results_for_different_values(): void
    {
        $hash1 = FastHasher::hash('value-one');
        $hash2 = FastHasher::hash('value-two');

        self::assertNotSame($hash1, $hash2);
    }

    public function test_that_hash_returns_different_results_for_same_value_of_different_type(): void
    {
        $hashString = FastHasher::hash('1');
        $hashInt = FastHasher::hash(1);

        self::assertNotSame($hashString, $hashInt);
    }
}
