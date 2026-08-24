<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Http\JSend;

use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Application\Http\JSend\JSendStatus;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JSendEnvelope::class)]
#[CoversClass(JSendStatus::class)]
class JSendEnvelopeTest extends UnitTestCase
{
    public function test_that_success_includes_presented_data(): void
    {
        $presentation = new class implements Arrayable {
            /**
             * @return array{id: int, name: string}
             */
            public function toArray(): array
            {
                return ['id' => 42, 'name' => 'Frodo'];
            }
        };

        self::assertSame(
            ['status' => 'success', 'data' => ['id' => 42, 'name' => 'Frodo']],
            JSendEnvelope::success($presentation)->toArray()
        );
    }

    public function test_that_success_includes_null_data_when_no_presentation_is_provided(): void
    {
        self::assertSame(
            ['status' => 'success', 'data' => null],
            JSendEnvelope::success()->toArray()
        );
    }

    public function test_that_success_projects_paginated_arrayable_records(): void
    {
        $records = ArrayList::of(Arrayable::class);
        $records->add(new class implements Arrayable {
            /**
             * @return array{id: int, name: string}
             */
            public function toArray(): array
            {
                return ['id' => 42, 'name' => 'Frodo'];
            }
        });
        $records->add(new class implements Arrayable {
            /**
             * @return array{id: int, name: string}
             */
            public function toArray(): array
            {
                return ['id' => 43, 'name' => 'Samwise'];
            }
        });
        $resultSet = new ResultSet(2, 2, 5, $records);

        $envelope = JSendEnvelope::success($resultSet);

        self::assertSame(
            [
                'status' => 'success',
                'data'   => [
                    'page'          => 2,
                    'per_page'      => 2,
                    'total_pages'   => 3,
                    'total_records' => 5,
                    'records'       => [
                        ['id' => 42, 'name' => 'Frodo'],
                        ['id' => 43, 'name' => 'Samwise']
                    ]
                ]
            ],
            $envelope->toArray()
        );
        self::assertSame(
            '{"status":"success","data":{"page":2,"per_page":2,"total_pages":3,'
            . '"total_records":5,"records":[{"id":42,"name":"Frodo"},'
            . '{"id":43,"name":"Samwise"}]}}',
            $envelope->toJson()
        );
    }

    public function test_that_success_rejects_paginated_non_arrayable_records_at_the_presentation_boundary(): void
    {
        $records = ArrayList::of('int');
        $records->add(42);
        $resultSet = new ResultSet(1, 10, 1, $records);

        self::assertSame(
            [
                'page'          => 1,
                'per_page'      => 10,
                'total_pages'   => 1,
                'total_records' => 1,
                'records'       => [42]
            ],
            $resultSet->toArray()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Every ResultSet record must implement Arrayable.');
        JSendEnvelope::success($resultSet);
    }

    public function test_that_fail_includes_presented_data(): void
    {
        $presentation = new class implements Arrayable {
            /**
             * @return array{email: string}
             */
            public function toArray(): array
            {
                return ['email' => 'invalid'];
            }
        };

        self::assertSame(
            ['status' => 'fail', 'data' => ['email' => 'invalid']],
            JSendEnvelope::fail($presentation)->toArray()
        );
    }

    public function test_that_error_includes_message_and_omits_absent_optional_fields(): void
    {
        self::assertSame(
            ['status' => 'error', 'message' => 'The bridge is out'],
            JSendEnvelope::error('The bridge is out')->toArray()
        );
    }

    public function test_that_error_includes_presented_data_and_integer_code_when_provided(): void
    {
        $presentation = new class implements Arrayable {
            /**
             * @return array{request_id: string}
             */
            public function toArray(): array
            {
                return ['request_id' => 'request-42'];
            }
        };

        self::assertSame(
            [
                'status'  => 'error',
                'message' => 'The bridge is out',
                'data'    => ['request_id' => 'request-42'],
                'code'    => 4102,
            ],
            JSendEnvelope::error('The bridge is out', $presentation, 4102)->toArray()
        );
    }

    public function test_that_to_json_uses_the_canonical_encoding_options(): void
    {
        $presentation = new class implements Arrayable {
            /**
             * @return array{url: string, tag: string, quote: string, apostrophe: string, ampersand: string}
             */
            public function toArray(): array
            {
                return [
                    'url'        => 'https://example.com/path',
                    'tag'        => '<>',
                    'quote'      => '"',
                    'apostrophe' => "'",
                    'ampersand'  => '&',
                ];
            }
        };

        self::assertSame(
            '{"status":"success","data":{"url":"https://example.com/path","tag":"\u003C\u003E","quote":"\u0022","apostrophe":"\u0027","ampersand":"\u0026"}}',
            JSendEnvelope::success($presentation)->toJson()
        );
    }

    public function test_that_to_json_throws_for_invalid_utf8(): void
    {
        $presentation = new class implements Arrayable {
            /**
             * @return array{value: string}
             */
            public function toArray(): array
            {
                return ['value' => "\xB1\x31"];
            }
        };

        $this->expectException(JsonException::class);
        JSendEnvelope::success($presentation)->toJson();
    }

    public function test_that_to_json_throws_for_an_unencodable_value(): void
    {
        $presentation = new class implements Arrayable {
            /**
             * @return array{value: float}
             */
            public function toArray(): array
            {
                return ['value' => NAN];
            }
        };

        $this->expectException(JsonException::class);
        JSendEnvelope::success($presentation)->toJson();
    }
}
