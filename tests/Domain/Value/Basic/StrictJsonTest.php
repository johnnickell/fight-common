<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Basic;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

#[CoversClass(StrictJson::class)]
final class StrictJsonTest extends UnitTestCase
{
    #[DataProvider('values')]
    public function test_that_json_round_trips_without_losing_types(string $text, bool $object): void
    {
        $value = StrictJson::fromString($text);
        self::assertSame($object, $value->isObject());
        self::assertSame($text, $value->toString());
        self::assertSame($text, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        self::assertTrue($value->equals(StrictJson::fromData($value)));
        self::assertTrue($value->equals(StrictJson::fromData($value->toData())));
        self::assertSame($value->hashValue(), StrictJson::fromString($text)->hashValue());
        self::assertSame($text, (string) $value);
        self::assertFalse($value->equals($text));
    }

    public static function values(): iterable
    {
        foreach (['null', 'true', 'false', '0', '-2', '1.0', '1.25', '"hello"', '[]', '[{},[],null,1.0]'] as $text) {
            yield $text => [$text, false];
        }
        foreach (['{}', '{"0":"zero"}', '{"":"empty","a\\u0000b":"NUL"}', '{"nested":{},"list":[]}'] as $text) {
            yield $text => [$text, true];
        }
    }

    public function test_that_objects_lists_null_and_missing_are_distinct(): void
    {
        $value = StrictJson::fromObject(['null' => null, 'object' => StrictJson::fromObject(), 'list' => []]);
        self::assertTrue($value->has('null'));
        self::assertFalse($value->has('missing'));
        self::assertNull($value->get('null'));
        self::assertNull($value->get('missing'));
        self::assertTrue($value->get('object')->isObject());
        self::assertSame([], $value->get('list'));
        self::assertFalse(StrictJson::fromObject()->equals(StrictJson::fromData([])));
        self::assertFalse(StrictJson::fromData(1)->equals(StrictJson::fromData(1.0)));
    }

    public function test_that_sources_properties_and_serialization_cannot_mutate_the_value(): void
    {
        $child = (object) ['name' => 'original'];
        $source = (object) ['child' => $child, 'list' => [$child]];
        $value = StrictJson::fromData($source);
        $child->name = 'changed';
        $source->list = [];
        $properties = $value->properties();
        $properties['child'] = $properties['child']->with('name', 'changed property');
        $serialized = $value->jsonSerialize();
        $serialized->child = 'changed serialization';
        $replacement = $value->with('child', null);
        self::assertSame('original', $value->get('child')->get('name'));
        self::assertSame('original', $value->get('list')[0]->get('name'));
        self::assertNull($replacement->get('child'));
        self::assertSame('{"child":{"name":"original"},"list":[{"name":"original"}]}', $value->toString());
    }

    public function test_that_object_factory_and_replacement_preserve_numeric_property_names(): void
    {
        $value = StrictJson::fromObject([2 => 'two', 0 => 'zero']);
        self::assertSame('{"2":"two","0":"zero"}', $value->toString());
        self::assertSame('{"2":"two","0":"updated","1":null}', $value->with('0', 'updated')->with('1', null)->toString());
        self::assertSame('two', $value->get('2'));
        self::assertTrue($value->has('0'));
        self::assertTrue(StrictJson::fromData(['key' => 'value'])->isObject());
    }

    #[DataProvider('invalidText')]
    public function test_that_invalid_text_is_rejected_without_reflecting_input(string $text): void
    {
        try {
            StrictJson::fromString($text);
            self::fail('Invalid JSON was accepted.');
        } catch (DomainException $exception) {
            self::assertSame('Invalid JSON text.', $exception->getMessage());
        }
    }

    public static function invalidText(): iterable
    {
        yield ['{"secret":'];
        yield ['NaN'];
        yield ["\"\xFF\""];
        yield ['{"\\u0000secret":true}'];
    }

    public function test_that_non_objects_cannot_be_navigated_as_objects(): void
    {
        $this->expectException(DomainException::class);
        StrictJson::fromData([])->get('0');
    }

    public function test_that_unsafe_values_are_rejected_without_running_serializers(): void
    {
        $serializer = new class implements JsonSerializable {
            public bool $called = false;
            public function jsonSerialize(): mixed
            {
                $this->called = true;
                return ['private' => 'secret'];
            }
        };
        $resource = fopen('php://memory', 'r');
        try {
            foreach ([INF, NAN, "\xFF", $resource, $serializer, new class extends stdClass {}, fn() => null, ["\0secret" => 1]] as $data) {
                try {
                    StrictJson::fromData(['nested' => $data]);
                    self::fail('Unsafe input was accepted.');
                } catch (DomainException $exception) {
                    self::assertStringNotContainsString('secret', $exception->getMessage());
                    self::assertFalse($serializer->called);
                }
            }
        } finally {
            fclose($resource);
        }
    }

    public function test_that_depth_is_bounded_even_when_composing_existing_values(): void
    {
        $value = null;
        for ($depth = 0; $depth < 64; ++$depth) {
            $value = [$value];
        }
        $json = StrictJson::fromData($value);
        self::assertSame(str_repeat('[', 64).'null'.str_repeat(']', 64), $json->toString());
        $this->expectException(DomainException::class);
        StrictJson::fromObject(['nested' => $json]);
    }

    public function test_that_codec_and_tool_depth_budgets_can_be_separated(): void
    {
        $value = null;
        for ($depth = 0; $depth < 510; ++$depth) {
            $value = [$value];
        }
        $json = StrictJson::fromObject(['value' => $value], maxDepth: 511);
        self::assertTrue($json->equals(StrictJson::fromString($json->toString(), maxDepth: 511)));
        self::assertSame($json->toString(), $json->with('value', $value, maxDepth: 511)->toString());
        self::assertSame('null', StrictJson::fromData(null, maxDepth: 0)->toString());
        $this->expectException(DomainException::class);
        StrictJson::fromData([null], maxDepth: 0);
    }

    #[DataProvider('codecBoundaryValues')]
    public function test_that_accepted_codec_boundary_values_round_trip_through_factories_and_replacement(
        string $leaf,
        int $limit
    ): void {
        $value = StrictJson::fromString($leaf);
        $depth = $limit === 511 && $leaf !== 'null' ? 510 : $limit;
        for ($level = 1; $level < $depth; ++$level) {
            $value = [$value];
        }
        $list = StrictJson::fromData([$value], maxDepth: $limit);
        $object = StrictJson::fromObject(['value' => $value], maxDepth: $limit);
        $replacement = StrictJson::fromObject()->with('value', $value, maxDepth: $limit);
        foreach ([$list, $object, $replacement] as $json) {
            self::assertSame($json->toString(), StrictJson::fromString($json->toString(), maxDepth: $limit)->toString());
            self::assertSame($json->toString(), StrictJson::fromData($json, maxDepth: $limit)->toString());
        }
        self::assertTrue($object->equals($replacement));
    }

    public static function codecBoundaryValues(): iterable
    {
        foreach ([64, 510, 511] as $limit) {
            foreach (['null', '[]', '{}'] as $leaf) {
                yield $limit.' '.$leaf => [$leaf, $limit];
            }
        }
    }

    #[DataProvider('unsupportedCodecContainers')]
    public function test_that_containers_beyond_the_decoder_budget_reject_at_every_construction_boundary(
        string $leaf,
        string $factory
    ): void {
        $value = StrictJson::fromString($leaf);
        for ($level = 1; $level < 511; ++$level) {
            $value = [$value];
        }
        $original = StrictJson::fromObject();
        try {
            match ($factory) {
                'fromData' => StrictJson::fromData([$value], maxDepth: 511),
                'fromObject' => StrictJson::fromObject(['value' => $value], maxDepth: 511),
                'with' => $original->with('value', $value, maxDepth: 511),
                'fromString' => StrictJson::fromString(str_repeat('[', 511).$leaf.str_repeat(']', 511), maxDepth: 511)
            };
            self::fail('A container beyond the decoder budget was accepted.');
        } catch (DomainException $exception) {
            self::assertSame(
                $factory === 'fromString' ? 'Invalid JSON text.' : 'JSON data exceeds the supported nesting depth.',
                $exception->getMessage()
            );
            self::assertSame('{}', $original->toString());
        }
    }

    public static function unsupportedCodecContainers(): iterable
    {
        foreach (['[]', '{}'] as $leaf) {
            foreach (['fromData', 'fromObject', 'with', 'fromString'] as $factory) {
                yield $factory.' '.$leaf => [$leaf, $factory];
            }
        }
    }

    #[DataProvider('invalidLimits')]
    public function test_that_invalid_nesting_limits_reject(int $limit): void
    {
        $this->expectException(DomainException::class);
        StrictJson::fromData(null, $limit);
    }

    public static function invalidLimits(): iterable
    {
        yield [-1];
        yield [512];
    }

    public function test_that_cycles_reject_without_unbounded_recursion(): void
    {
        $value = new stdClass();
        $value->cycle = $value;
        $this->expectException(DomainException::class);
        StrictJson::fromData($value);
    }
}
