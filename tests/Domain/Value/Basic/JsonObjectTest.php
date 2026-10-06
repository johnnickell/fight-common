<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Basic;

use Error;
use Fight\Common\Domain\Exception\Catchable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use JsonException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Throwable;
use WeakReference;

#[CoversClass(JsonObject::class)]
class JsonObjectTest extends UnitTestCase
{
    public function test_that_snapshots_detach_nested_input_references_and_every_mutable_output(): void
    {
        $child = (object) ['value' => 'original'];
        $text = 'original';
        $items = [&$text, $child];
        $input = (object) ['child' => $child, 'items' => &$items];
        $snapshot = JsonObject::fromSnapshot($input);
        $comparison = JsonObject::fromSnapshotString('{"child":{"value":"original"},"items":["original",{"value":"original"}]}');
        $expected = $comparison->toString();

        $child->value = 'changed input';
        $text = 'changed reference';
        $items[] = 'extra';
        unset($input->child);
        $data = $snapshot->toData();
        $data->child->value = 'changed output';
        $data->items[1]->value = 'another output change';
        $serialized = $snapshot->jsonSerialize();
        $serialized->items[] = 'extra';
        $serialized->child->value = 'changed serialization';

        self::assertSame($expected, $snapshot->toString());
        self::assertSame($expected, $snapshot->hashValue());
        self::assertTrue($snapshot->equals($comparison));
        self::assertTrue($comparison->equals($snapshot));
        self::assertNotSame($snapshot->toData(), $snapshot->toData());
        self::assertSame('original', $snapshot->jsonSerialize()->items[1]->value);
        self::assertSame($expected, json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_that_snapshot_reads_never_reinvoke_consumer_serializers(): void
    {
        $serializer = new class implements JsonSerializable {
            public int $calls = 0;
            public object $child;

            public function __construct()
            {
                $this->child = (object) ['value' => 'captured'];
            }

            public function jsonSerialize(): mixed
            {
                ++$this->calls;

                return ['child' => $this->child];
            }
        };
        $reference = WeakReference::create($serializer);
        $snapshot = JsonObject::fromSnapshot($serializer);
        $serializer->child->value = 'changed';
        $snapshot->toData()->child->value = 'changed output';
        $snapshot->jsonSerialize()->child->value = 'changed output';
        self::assertSame('{"child":{"value":"captured"}}', $snapshot->encode());
        self::assertStringContainsString('"captured"', $snapshot->prettyPrint());
        self::assertSame('{"child":{"value":"captured"}}', json_encode($snapshot));
        self::assertSame(1, $serializer->calls);
        unset($serializer);
        self::assertNull($reference->get());
    }

    public function test_that_snapshot_serializer_output_is_captured_but_legacy_serializers_remain_live(): void
    {
        $serializer = new class implements JsonSerializable {
            public int $calls = 0;

            public function jsonSerialize(): mixed
            {
                return ['calls' => ++$this->calls];
            }
        };
        $legacy = JsonObject::fromData($serializer);
        self::assertSame(1, $serializer->calls);
        self::assertSame('{"calls":2}', $legacy->toString());
        $snapshot = JsonObject::fromSnapshot([$serializer, $serializer]);
        $calls = $serializer->calls;
        self::assertSame('[{"calls":3},{"calls":4}]', $snapshot->toString());
        self::assertSame(3, $snapshot->toData()[0]->calls);
        self::assertSame($calls, $serializer->calls);
    }

    public function test_that_snapshot_validates_the_actual_json_serializable_output(): void
    {
        $serializer = new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return INF;
            }
        };
        try {
            JsonObject::fromSnapshot($serializer);
            self::fail('Expected invalid serializer output rejection');
        } catch (DomainException $exception) {
            self::assertSame(JSON_ERROR_INF_OR_NAN, $exception->getPrevious()->getCode());
        }
    }

    public function test_that_snapshot_associative_arrays_become_fresh_objects_without_retaining_references(): void
    {
        $value = 'original';
        $input = ['value' => &$value, 'child' => (object) ['value' => 'original']];
        $snapshot = JsonObject::fromSnapshot($input);
        $value = 'changed';
        $input['child']->value = 'changed';
        self::assertInstanceOf(stdClass::class, $snapshot->toData());
        self::assertSame('{"value":"original","child":{"value":"original"}}', $snapshot->toString());
    }

    #[DataProvider('snapshotValues')]
    public function test_that_snapshot_factories_preserve_json_kinds(mixed $input, string $expected): void
    {
        $snapshot = JsonObject::fromSnapshot($input);
        $restored = JsonObject::fromSnapshotString($expected);
        self::assertSame($expected, $snapshot->toString());
        self::assertSame($expected, $restored->toString());
        self::assertSame(get_debug_type($input), get_debug_type($snapshot->toData()));
        self::assertSame(get_debug_type($input), get_debug_type($restored->jsonSerialize()));
        self::assertSame($expected, json_encode($snapshot, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES));
        self::assertTrue($snapshot->equals($restored));
        self::assertSame($snapshot->hashValue(), $restored->hashValue());
    }

    public static function snapshotValues(): iterable
    {
        yield 'empty object' => [new stdClass(), '{}'];
        yield 'numeric object property' => [(object) ['0' => 'zero'], '{"0":"zero"}'];
        yield 'empty list' => [[], '[]'];
        yield 'list' => [[true, 1, 1.0, null], '[true,1,1.0,null]'];
        yield 'null' => [null, 'null'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'string' => ['hello/世界', '"hello/\u4e16\u754c"'];
        yield 'integer' => [1, '1'];
        yield 'zero fraction' => [1.0, '1.0'];
        yield 'negative zero float' => [-0.0, '-0.0'];
        yield 'max integer' => [PHP_INT_MAX, (string) PHP_INT_MAX];
        yield 'min integer' => [PHP_INT_MIN, (string) PHP_INT_MIN];
        yield 'max float' => [PHP_FLOAT_MAX, '1.7976931348623157e+308'];
        yield 'min normal float' => [PHP_FLOAT_MIN, '2.2250738585072014e-308'];
        yield 'subnormal float' => [5e-324, '5.0e-324'];
    }

    public function test_that_snapshot_precision_is_independent_of_ambient_formatting_and_restored(): void
    {
        $precision = ini_get('serialize_precision');
        try {
            ini_set('serialize_precision', '3');
            $snapshot = JsonObject::fromSnapshot([1.2345678901234567, 1.0]);
            self::assertSame('3', ini_get('serialize_precision'));
            ini_set('serialize_precision', '5');
            $restored = JsonObject::fromSnapshotString(' [ 1.2345678901234567000, 1e0 ] ');
            self::assertSame('[1.2345678901234567,1.0]', $snapshot->toString());
            self::assertSame($snapshot->toString(), $snapshot->encode());
            self::assertSame([1.2345678901234567, 1.0], $snapshot->toData());
            self::assertTrue($snapshot->equals($restored));
            self::assertSame($snapshot->hashValue(), $restored->hashValue());
            self::assertSame('5', ini_get('serialize_precision'));
            self::assertSame('[1.2346,1]', json_encode($snapshot));
            self::assertSame('[1.2345678901234567,1.0]', $snapshot->toString());
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }

    public function test_that_snapshot_text_normalizes_native_float_lexemes_without_claiming_decimal_precision(): void
    {
        self::assertSame('0', JsonObject::fromSnapshotString('-0')->toString());
        self::assertSame('0.0', JsonObject::fromSnapshotString('1e-9999')->toString());
        self::assertSame('1.0', JsonObject::fromSnapshotString('1.00000000000000000001')->toString());
        self::assertSame('1.0e+20', JsonObject::fromSnapshotString('1e20')->toString());
        $text = <<<'JSON'
            {"999999999999999999999":"escaped \" 999999999999999999999 \\ tail", "n":1}
            JSON;
        self::assertSame(1, JsonObject::fromSnapshotString($text)->toData()->n);
    }

    #[DataProvider('invalidSnapshotText')]
    public function test_that_snapshot_text_rejects_invalid_or_unrepresentable_input(string $text, ?int $cause): void
    {
        try {
            JsonObject::fromSnapshotString($text);
            self::fail('Expected snapshot text rejection');
        } catch (DomainException $exception) {
            self::assertInstanceOf(Catchable::class, $exception);
            if ($cause === null) {
                self::assertSame('Unsupported JSON snapshot representation.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            } else {
                self::assertInstanceOf(JsonException::class, $exception->getPrevious());
                self::assertSame($cause, $exception->getPrevious()->getCode());
            }
        }
    }

    public static function invalidSnapshotText(): iterable
    {
        yield 'malformed' => ['{secret}', JSON_ERROR_SYNTAX];
        yield 'invalid unicode' => ["\"\xFF\"", JSON_ERROR_UTF8];
        yield 'lone surrogate' => ['"\ud800"', JSON_ERROR_UTF16];
        yield 'leading null property' => ['{"\u0000bad":1}', JSON_ERROR_INVALID_PROPERTY_NAME];
        yield 'max plus one' => [substr((string) PHP_INT_MAX, 0, -1).'8', null];
        yield 'min minus one' => [substr((string) PHP_INT_MIN, 0, -1).'9', null];
        yield 'positive integer overflow' => [(string) PHP_INT_MAX.'0', null];
        yield 'negative integer overflow' => [(string) PHP_INT_MIN.'0', null];
        yield 'overwritten integer overflow' => ['{"n":'.PHP_INT_MAX.'0,"n":1}', null];
        yield 'nested integer overflow' => ['[{"n":'.PHP_INT_MIN.'0}]', null];
        yield 'float overflow' => ['1e9999', JSON_ERROR_INF_OR_NAN];
    }

    #[DataProvider('overflowingSnapshotFloats')]
    public function test_that_snapshot_text_rejects_every_overflowing_float_before_duplicate_selection(string $text): void
    {
        $precision = ini_get('serialize_precision');
        try {
            JsonObject::fromSnapshotString($text);
            self::fail('Expected overflowing float rejection');
        } catch (DomainException $exception) {
            self::assertSame('Unable to encode JSON snapshot.', $exception->getMessage());
            self::assertInstanceOf(Catchable::class, $exception);
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
            self::assertSame(JSON_ERROR_INF_OR_NAN, $exception->getPrevious()->getCode());
            self::assertSame($precision, ini_get('serialize_precision'));
        }
    }

    public static function overflowingSnapshotFloats(): iterable
    {
        yield 'retained positive' => ['1e9999'];
        yield 'retained negative' => ['-1e9999'];
        yield 'overwritten positive' => ['{"n":1e9999,"n":1}'];
        yield 'overwritten negative' => ['{"n":-1E+9999,"n":1}'];
        yield 'overwritten nested subtree' => ['{"n":{"items":[1,-1e9999]},"n":null}'];
        yield 'escaped duplicate name' => ['{"n":1e9999,"\u006e":1}'];
        yield 'overwritten decimal without exponent' => ['{"n":'.str_repeat('9', 309).'.0,"n":1}'];
    }

    #[DataProvider('finiteSnapshotDuplicates')]
    public function test_that_snapshot_text_keeps_last_duplicate_values_with_valid_numeric_literals(string $text, string $expected): void
    {
        self::assertSame($expected, JsonObject::fromSnapshotString($text)->toString());
    }

    public static function finiteSnapshotDuplicates(): iterable
    {
        yield 'finite extrema' => ['{"n":1.7976931348623157e308,"n":-1.7976931348623157e308}', '{"n":-1.7976931348623157e+308}'];
        yield 'rounding' => ['{"n":1.0,"n":1.00000000000000000001}', '{"n":1.0}'];
        yield 'underflow' => ['{"n":1e-9999,"n":0.0}', '{"n":0.0}'];
        yield 'escaped name and discarded subtree' => ['{"n":{"items":[1.5,-1e-9999]},"\u006e":1}', '{"n":1}'];
        yield 'numeric strings and escaped quotes' => ['{"1e9999":"-1e9999","n":"escaped \" 1e9999","n":1}', '{"1e9999":"-1e9999","n":1}'];
    }

    #[DataProvider('invalidSnapshotData')]
    public function test_that_snapshot_codec_failures_keep_fixed_messages_and_causes(mixed $data, int $code): void
    {
        $precision = ini_get('serialize_precision');
        try {
            JsonObject::fromSnapshot($data);
            self::fail('Expected snapshot encoding rejection');
        } catch (DomainException $exception) {
            self::assertSame('Unable to encode JSON snapshot.', $exception->getMessage());
            self::assertInstanceOf(Catchable::class, $exception);
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
            self::assertSame($code, $exception->getPrevious()->getCode());
            self::assertSame($precision, ini_get('serialize_precision'));
        }
    }

    public static function invalidSnapshotData(): iterable
    {
        yield 'unicode string' => ["secret\xFF", JSON_ERROR_UTF8];
        yield 'unicode key' => [["secret\xFF" => 'value'], JSON_ERROR_UTF8];
        yield 'infinity' => [INF, JSON_ERROR_INF_OR_NAN];
        yield 'negative infinity' => [-INF, JSON_ERROR_INF_OR_NAN];
        yield 'nan' => [NAN, JSON_ERROR_INF_OR_NAN];
        $object = new stdClass();
        $object->self = $object;
        yield 'object cycle' => [$object, JSON_ERROR_RECURSION];
        $array = [];
        $array['self'] = &$array;
        yield 'array cycle' => [$array, JSON_ERROR_RECURSION];
    }

    public function test_that_snapshot_rejects_resources_without_retaining_or_closing_them(): void
    {
        $resource = fopen('php://memory', 'r');
        try {
            JsonObject::fromSnapshot(['resource' => $resource]);
            self::fail('Expected resource rejection');
        } catch (DomainException $exception) {
            self::assertSame(JSON_ERROR_UNSUPPORTED_TYPE, $exception->getPrevious()->getCode());
            self::assertIsResource($resource);
        } finally {
            fclose($resource);
        }
    }

    public function test_that_snapshot_rejects_encoded_properties_the_object_decoder_cannot_reconstruct(): void
    {
        self::assertSame('ok', JsonObject::fromSnapshot(["x\0y" => 'ok'])->toData()->{"x\0y"});
        self::assertSame('empty', JsonObject::fromSnapshot(['' => 'empty'])->toData()->{''});
        try {
            JsonObject::fromSnapshot(["\0bad" => 'secret']);
            self::fail('Expected property rejection');
        } catch (DomainException $exception) {
            self::assertSame('Unable to decode JSON snapshot.', $exception->getMessage());
            self::assertSame(JSON_ERROR_INVALID_PROPERTY_NAME, $exception->getPrevious()->getCode());
        }
    }

    #[DataProvider('snapshotDepths')]
    public function test_that_snapshot_depth_requires_both_encoding_and_reconstruction(string $open, string $close): void
    {
        $text = str_repeat($open, 511).'0'.str_repeat($close, 511);
        $data = json_decode($text, false, 1024, JSON_THROW_ON_ERROR);
        self::assertSame($text, JsonObject::fromSnapshot($data)->toString());
        self::assertSame($text, JsonObject::fromSnapshotString($text)->toString());
        $empty = str_repeat($open, 510).($open === '[' ? '[]' : '{}').str_repeat($close, 510);
        self::assertSame($empty, JsonObject::fromSnapshotString($empty)->toString());
        foreach ([512, 513] as $depth) {
            $invalid = str_repeat($open, $depth).'0'.str_repeat($close, $depth);
            $deepData = json_decode($invalid, false, 1024, JSON_THROW_ON_ERROR);
            foreach ([fn () => JsonObject::fromSnapshot($deepData), fn () => JsonObject::fromSnapshotString($invalid)] as $create) {
                try {
                    $create();
                    self::fail('Expected depth rejection');
                } catch (DomainException $exception) {
                    self::assertSame(JSON_ERROR_DEPTH, $exception->getPrevious()->getCode());
                }
            }
        }
    }

    public static function snapshotDepths(): iterable
    {
        yield 'lists' => ['[', ']'];
        yield 'objects' => ['{"child":', '}'];
    }

    #[DataProvider('serializerFailures')]
    public function test_that_snapshot_propagates_original_serializer_throwables_and_restores_precision(Throwable $failure): void
    {
        $serializer = new class ($failure) implements JsonSerializable {
            public int $calls = 0;

            public function __construct(private Throwable $failure)
            {
            }

            public function jsonSerialize(): mixed
            {
                ++$this->calls;
                throw $this->failure;
            }
        };
        $precision = ini_get('serialize_precision');
        try {
            JsonObject::fromSnapshot(['serializer' => $serializer]);
            self::fail('Expected original throwable');
        } catch (Throwable $caught) {
            self::assertSame($failure, $caught);
            self::assertSame(17, $caught->getCode());
            self::assertSame($failure->getPrevious(), $caught->getPrevious());
            self::assertSame(1, $serializer->calls);
            self::assertSame($precision, ini_get('serialize_precision'));
        }
    }

    public static function serializerFailures(): iterable
    {
        $cause = new RuntimeException('private cause');
        yield 'exception' => [new RuntimeException('private failure', 17, $cause)];
        yield 'json exception' => [new JsonException('private failure', 17, $cause)];
        yield 'error' => [new Error('private failure', 17, $cause)];
    }

    #[DataProvider('snapshotPresentationOptions')]
    public function test_that_snapshot_presentation_options_change_only_spelling(int $options, string $expected): void
    {
        $snapshot = JsonObject::fromSnapshot('<>&\'"/é'."\u{2028}", $options);
        self::assertSame($expected, $snapshot->toString());
        self::assertSame($expected, $snapshot->encode($options));
        self::assertSame($snapshot->toData(), JsonObject::fromSnapshotString($expected, $options)->toData());
    }

    public static function snapshotPresentationOptions(): iterable
    {
        $escaped = '"<>&\'\\"\\/\\u00e9\\u2028"';
        yield 'none' => [0, $escaped];
        yield 'hex tag' => [JSON_HEX_TAG, '"\\u003C\\u003E&\'\\"\\/\\u00e9\\u2028"'];
        yield 'hex amp' => [JSON_HEX_AMP, '"<>\\u0026\'\\"\\/\\u00e9\\u2028"'];
        yield 'hex apostrophe' => [JSON_HEX_APOS, '"<>&\\u0027\\"\\/\\u00e9\\u2028"'];
        yield 'hex quote' => [JSON_HEX_QUOT, '"<>&\'\\u0022\\/\\u00e9\\u2028"'];
        yield 'slashes' => [JSON_UNESCAPED_SLASHES, '"<>&\'\\"/\\u00e9\\u2028"'];
        yield 'unicode' => [JSON_UNESCAPED_UNICODE, '"<>&\'\\"\\/é\\u2028"'];
        yield 'line terminators alone' => [JSON_UNESCAPED_LINE_TERMINATORS, $escaped];
        yield 'unicode line terminators' => [JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS, '"<>&\'\\"\\/é'."\u{2028}".'"'];
        yield 'pretty scalar' => [JSON_PRETTY_PRINT, $escaped];
        yield 'zero fraction option' => [JSON_PRESERVE_ZERO_FRACTION, $escaped];
    }

    #[DataProvider('unsafeSnapshotOptions')]
    public function test_that_snapshot_paths_reject_nonpresentation_options(int $options): void
    {
        $snapshot = JsonObject::fromSnapshot(['n' => '1']);
        foreach ([fn () => JsonObject::fromSnapshot([], $options), fn () => JsonObject::fromSnapshotString('[]', $options), fn () => $snapshot->encode($options)] as $create) {
            try {
                $create();
                self::fail('Expected option rejection');
            } catch (DomainException $exception) {
                self::assertSame('Unsupported JSON snapshot encoding options.', $exception->getMessage());
            }
        }
        self::assertSame('{"n":"1"}', $snapshot->toString());
    }

    public static function unsafeSnapshotOptions(): iterable
    {
        foreach ([JSON_FORCE_OBJECT, JSON_NUMERIC_CHECK, JSON_PARTIAL_OUTPUT_ON_ERROR, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_THROW_ON_ERROR, -1, 1 << 29] as $option) {
            yield (string) $option => [$option];
        }
    }

    public function test_that_snapshot_equality_remains_representation_based_across_legacy_paths(): void
    {
        $snapshot = JsonObject::fromSnapshot(['value' => 'same']);
        $legacy = JsonObject::fromData(['value' => 'same']);
        self::assertTrue($snapshot->equals($legacy));
        self::assertTrue($legacy->equals($snapshot));
        self::assertSame($snapshot->hashValue(), $legacy->hashValue());
        self::assertFalse(JsonObject::fromSnapshot(1)->equals(JsonObject::fromSnapshot(1.0)));
        self::assertFalse(JsonObject::fromSnapshot(['a' => 1, 'b' => 2])->equals(JsonObject::fromSnapshot(['b' => 2, 'a' => 1])));
        $pretty = JsonObject::fromSnapshot(['value' => 'same'], JSON_PRETTY_PRINT);
        self::assertSame("{\n    \"value\": \"same\"\n}", $pretty->toString());
        self::assertSame($pretty->toString(), $snapshot->prettyPrint());
        self::assertFalse($snapshot->equals($pretty));
        self::assertSame($snapshot->toString(), $pretty->encode());
        self::assertSame('{"value":"same"}', $snapshot->toString());
    }

    public function test_that_legacy_aliasing_and_reconstruction_limitations_remain_separate_from_snapshots(): void
    {
        $child = (object) ['value' => 'original'];
        $legacy = JsonObject::fromData(['child' => $child]);
        $child->value = 'changed input';
        self::assertSame('{"child":{"value":"changed input"}}', $legacy->toString());
        $legacy->toData()['child']->value = 'changed output';
        self::assertSame('{"child":{"value":"changed output"}}', $legacy->toString());
        self::assertSame('[]', JsonObject::fromString('{}')->toString());
        self::assertSame('["zero"]', JsonObject::fromString('{"0":"zero"}')->toString());
        self::assertSame('1', JsonObject::fromString('1.0')->toString());
        self::assertSame('{"n":1}', JsonObject::fromData(['n' => '1'], JSON_NUMERIC_CHECK)->toString());
    }

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
        $resource = fopen('php://memory', 'r');
        $this->expectException(DomainException::class);
        JsonObject::fromData($resource);
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
