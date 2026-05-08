<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Messaging;

use stdClass;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Meta::class)]
class MetaTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_that_create_with_no_arguments_returns_empty_instance(): void
    {
        $meta = Meta::create();

        self::assertTrue($meta->isEmpty());
        self::assertSame(0, $meta->count());
    }

    public function test_that_create_with_array_pre_populates_values(): void
    {
        $meta = Meta::create(['key' => 'value', 'num' => 42]);

        self::assertSame('value', $meta->get('key'));
        self::assertSame(42, $meta->get('num'));
        self::assertSame(2, $meta->count());
    }

    // -------------------------------------------------------------------------
    // Set / Get
    // -------------------------------------------------------------------------

    public function test_that_set_adds_a_value_and_get_returns_it(): void
    {
        $meta = Meta::create();
        $meta->set('foo', 'bar');

        self::assertSame('bar', $meta->get('foo'));
    }

    public function test_that_get_returns_null_for_missing_key(): void
    {
        $meta = Meta::create();

        self::assertNull($meta->get('missing'));
    }

    public function test_that_get_returns_provided_default_for_missing_key(): void
    {
        $meta = Meta::create();

        self::assertSame('fallback', $meta->get('missing', 'fallback'));
    }

    public function test_that_get_returns_stored_null_not_default_when_key_exists_with_null(): void
    {
        $meta = Meta::create(['key' => null]);

        self::assertNull($meta->get('key', 'default'));
    }

    // -------------------------------------------------------------------------
    // Has
    // -------------------------------------------------------------------------

    public function test_that_has_returns_true_for_existing_key(): void
    {
        $meta = Meta::create(['key' => 'value']);

        self::assertTrue($meta->has('key'));
    }

    public function test_that_has_returns_true_for_key_with_null_value(): void
    {
        $meta = Meta::create(['key' => null]);

        self::assertTrue($meta->has('key'));
    }

    public function test_that_has_returns_false_for_missing_key(): void
    {
        $meta = Meta::create();

        self::assertFalse($meta->has('missing'));
    }

    // -------------------------------------------------------------------------
    // Remove
    // -------------------------------------------------------------------------

    public function test_that_remove_deletes_a_key(): void
    {
        $meta = Meta::create(['key' => 'value']);
        $meta->remove('key');

        self::assertFalse($meta->has('key'));
        self::assertSame(0, $meta->count());
    }

    // -------------------------------------------------------------------------
    // Merge
    // -------------------------------------------------------------------------

    public function test_that_merge_combines_two_meta_instances(): void
    {
        $meta1 = Meta::create(['a' => 1]);
        $meta2 = Meta::create(['b' => 2]);
        $meta1->merge($meta2);

        self::assertSame(1, $meta1->get('a'));
        self::assertSame(2, $meta1->get('b'));
    }

    public function test_that_merge_overwrites_existing_keys(): void
    {
        $meta1 = Meta::create(['key' => 'original']);
        $meta2 = Meta::create(['key' => 'updated']);
        $meta1->merge($meta2);

        self::assertSame('updated', $meta1->get('key'));
    }

    // -------------------------------------------------------------------------
    // Guard value / isValid
    // -------------------------------------------------------------------------

    public function test_that_set_accepts_string_value(): void
    {
        $meta = Meta::create();
        $meta->set('k', 'hello');

        self::assertSame('hello', $meta->get('k'));
    }

    public function test_that_set_accepts_integer_value(): void
    {
        $meta = Meta::create();
        $meta->set('k', 7);

        self::assertSame(7, $meta->get('k'));
    }

    public function test_that_set_accepts_float_value(): void
    {
        $meta = Meta::create();
        $meta->set('k', 3.14);

        self::assertSame(3.14, $meta->get('k'));
    }

    public function test_that_set_accepts_boolean_value(): void
    {
        $meta = Meta::create();
        $meta->set('k', true);

        self::assertTrue($meta->get('k'));
    }

    public function test_that_set_accepts_null_value(): void
    {
        $meta = Meta::create();
        $meta->set('k', null);

        self::assertNull($meta->get('k'));
    }

    public function test_that_guard_value_throws_for_object_values(): void
    {
        $meta = Meta::create();

        $this->expectException(DomainException::class);
        $meta->set('key', new stdClass());
    }

    public function test_that_guard_value_throws_for_array_containing_object(): void
    {
        $meta = Meta::create();

        $this->expectException(DomainException::class);
        $meta->set('key', ['nested' => new stdClass()]);
    }

    public function test_that_guard_value_allows_nested_arrays(): void
    {
        $meta = Meta::create();
        $meta->set('key', ['nested' => ['value' => 42]]);

        self::assertSame(['nested' => ['value' => 42]], $meta->get('key'));
    }

    public function test_that_create_throws_when_array_contains_object(): void
    {
        $this->expectException(DomainException::class);
        Meta::create(['key' => new stdClass()]);
    }

    // -------------------------------------------------------------------------
    // Keys
    // -------------------------------------------------------------------------

    public function test_that_keys_returns_all_keys(): void
    {
        $meta = Meta::create(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertSame(['a', 'b', 'c'], $meta->keys());
    }

    public function test_that_keys_returns_empty_array_when_empty(): void
    {
        $meta = Meta::create();

        self::assertSame([], $meta->keys());
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    public function test_that_to_array_returns_stored_data(): void
    {
        $meta = Meta::create(['x' => 1, 'y' => 'two']);

        self::assertSame(['x' => 1, 'y' => 'two'], $meta->toArray());
    }

    public function test_that_to_string_returns_json_encoded_data(): void
    {
        $meta = Meta::create(['key' => 'value']);

        self::assertSame('{"key":"value"}', $meta->toString());
    }

    public function test_that_cast_to_string_returns_json_encoded_data(): void
    {
        $meta = Meta::create(['n' => 42]);

        self::assertSame('{"n":42}', (string) $meta);
    }

    public function test_that_json_serialize_returns_stored_data(): void
    {
        $meta = Meta::create(['a' => true]);

        self::assertSame(['a' => true], $meta->jsonSerialize());
    }

    // -------------------------------------------------------------------------
    // Iteration
    // -------------------------------------------------------------------------

    public function test_that_iteration_visits_all_key_value_pairs(): void
    {
        $meta = Meta::create(['x' => 10, 'y' => 20]);
        $pairs = [];

        foreach ($meta as $key => $value) {
            $pairs[$key] = $value;
        }

        self::assertSame(['x' => 10, 'y' => 20], $pairs);
    }
}
