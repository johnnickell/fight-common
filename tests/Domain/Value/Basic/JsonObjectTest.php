<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Basic;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonObject::class)]
class JsonObjectTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_that_from_data_creates_instance_from_array(): void
    {
        $json = JsonObject::fromData(['key' => 'value']);

        self::assertSame(['key' => 'value'], $json->toData());
    }

    public function test_that_from_data_creates_instance_from_scalar(): void
    {
        $json = JsonObject::fromData('hello');

        self::assertSame('hello', $json->toData());
    }

    public function test_that_from_data_creates_instance_from_null(): void
    {
        $json = JsonObject::fromData(null);

        self::assertNull($json->toData());
    }

    public function test_that_from_string_creates_instance_from_valid_json(): void
    {
        $json = JsonObject::fromString('{"key":"value"}');

        self::assertSame(['key' => 'value'], $json->toData());
    }

    public function test_that_from_string_creates_instance_from_json_array(): void
    {
        $json = JsonObject::fromString('[1,2,3]');

        self::assertSame([1, 2, 3], $json->toData());
    }

    public function test_that_from_string_creates_instance_from_null_json(): void
    {
        $json = JsonObject::fromString('null');

        self::assertNull($json->toData());
    }

    public function test_that_from_string_throws_for_invalid_json(): void
    {
        $this->expectException(DomainException::class);
        JsonObject::fromString('{invalid}');
    }

    public function test_that_from_data_throws_for_non_encodable_value(): void
    {
        $this->expectException(DomainException::class);
        JsonObject::fromData(NAN);
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    public function test_that_to_string_returns_json_encoded_value(): void
    {
        $json = JsonObject::fromData(['key' => 'value/slash']);

        self::assertSame('{"key":"value/slash"}', $json->toString());
    }

    public function test_that_to_string_respects_encoding_options(): void
    {
        $json = JsonObject::fromData(['url' => 'http://example.com/path'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        self::assertSame('{"url":"http://example.com/path"}', $json->toString());
    }

    public function test_that_cast_to_string_returns_json_encoded_value(): void
    {
        $json = JsonObject::fromData(['key' => 'value']);

        self::assertSame('{"key":"value"}', (string) $json);
    }

    public function test_that_encode_returns_json_with_given_options(): void
    {
        $json = JsonObject::fromData(['url' => 'http://example.com/path']);
        $result = $json->encode(JSON_UNESCAPED_SLASHES);

        self::assertSame('{"url":"http://example.com/path"}', $result);
    }

    public function test_that_pretty_print_returns_formatted_json(): void
    {
        $json = JsonObject::fromData(['key' => 'value']);
        $result = $json->prettyPrint();

        self::assertStringContainsString("\n", $result);
        self::assertStringContainsString('    "key": "value"', $result);
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function test_that_json_serialize_returns_raw_data_not_string(): void
    {
        $data = ['key' => 'value'];
        $json = JsonObject::fromData($data);

        self::assertSame($data, $json->jsonSerialize());
    }

    public function test_that_json_encode_wraps_data_correctly(): void
    {
        $json = JsonObject::fromData(['key' => 'value']);

        self::assertSame('{"key":"value"}', json_encode($json));
    }

    // -------------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------------

    public function test_that_equals_returns_true_for_same_data(): void
    {
        $json1 = JsonObject::fromData(['key' => 'value']);
        $json2 = JsonObject::fromData(['key' => 'value']);

        self::assertTrue($json1->equals($json2));
    }

    public function test_that_equals_returns_false_for_different_data(): void
    {
        $json1 = JsonObject::fromData(['key' => 'value1']);
        $json2 = JsonObject::fromData(['key' => 'value2']);

        self::assertFalse($json1->equals($json2));
    }

    public function test_that_equals_returns_false_for_different_type(): void
    {
        $json = JsonObject::fromData(['key' => 'value']);

        self::assertFalse($json->equals('{"key":"value"}'));
    }

    public function test_that_hash_value_returns_json_string(): void
    {
        $json = JsonObject::fromData(['key' => 'value']);

        self::assertSame($json->toString(), $json->hashValue());
    }
}
