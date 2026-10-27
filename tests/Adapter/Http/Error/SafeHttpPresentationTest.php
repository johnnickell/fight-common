<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Error;

use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SafeHttpPresentation::class)]
class SafeHttpPresentationTest extends UnitTestCase
{
    public function test_that_constructor_accepts_public_plain_json(): void
    {
        $presentation = new SafeHttpPresentation('Public message', ['text' => 'safe', 'ok' => true, 'empty' => null, 'count' => 0]);
        self::assertSame('Public message', $presentation->message());
        self::assertSame(['text' => 'safe', 'ok' => true, 'empty' => null, 'count' => 0], $presentation->data());
        self::assertNull((new SafeHttpPresentation())->data());
    }

    public function test_that_constructor_detaches_nested_references_without_changing_public_shape(): void
    {
        $field = 'Safe';
        $nested = ['field' => &$field];
        $presentation = new SafeHttpPresentation(data: ['nested' => &$nested, 'list' => [0, 1]]);

        $field = 'private';
        $nested['added'] = 'private';
        self::assertSame(['nested' => ['field' => 'Safe'], 'list' => [0, 1]], $presentation->data());
        self::assertSame('{"nested":{"field":"Safe"},"list":[0,1]}', json_encode($presentation->data()));
    }

    public function test_that_constructor_rejects_unsafe_or_unencodable_data_before_serialization(): void
    {
        $object = new class implements JsonSerializable {
            public int $calls = 0;

            public function jsonSerialize(): mixed
            {
                ++$this->calls;
                return 'private';
            }
        };
        $recursive = [];
        $recursive['loop'] = &$recursive;
        $file = fopen('php://memory', 'r');
        foreach ([['object' => $object], ['file' => $file], ['float' => INF], ['text' => "\xff"], $recursive] as $data) {
            try {
                new SafeHttpPresentation(data: $data);
                self::fail('Expected unsafe public data to be rejected.');
            } catch (InvalidArgumentException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
        fclose($file);
        self::assertSame(0, $object->calls);
        try {
            new SafeHttpPresentation("\xff");
            self::fail('Expected invalid UTF-8 public message to be rejected.');
        } catch (InvalidArgumentException $expected) {
            self::assertNotSame('', $expected->getMessage());
        }
    }
}
