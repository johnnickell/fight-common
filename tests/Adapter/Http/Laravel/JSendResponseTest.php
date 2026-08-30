<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Laravel;

use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Http\JsonResponse;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JSendResponse::class)]
final class JSendResponseTest extends UnitTestCase
{
    public function test_that_from_envelope_returns_an_exact_laravel_json_response(): void
    {
        $response = JSendResponse::fromEnvelope(
            JSendEnvelope::success($this->presentation(['id' => 42])),
            201,
            ['X-Request-ID' => 'request-42']
        );

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('request-42', $response->headers->get('X-Request-ID'));
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"status":"success","data":{"id":42}}', $response->getContent());
    }

    public function test_that_invalid_envelope_encoding_fails_before_a_response_is_returned(): void
    {
        $this->expectException(JsonException::class);

        JSendResponse::success($this->presentation(['value' => "\xB1\x31"]));
    }

    public function test_that_fail_creates_an_exact_fail_response(): void
    {
        $response = JSendResponse::fail($this->presentation(['email' => 'invalid']), 422);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('{"status":"fail","data":{"email":"invalid"}}', $response->getContent());
    }

    public function test_that_error_includes_optional_data_code_and_headers(): void
    {
        $response = JSendResponse::error(
            'The bridge is out',
            502,
            $this->presentation(['request_id' => 'request-42']),
            4102,
            ['Retry-After' => '30']
        );

        self::assertSame(502, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));
        self::assertSame(
            '{"status":"error","message":"The bridge is out","data":{"request_id":"request-42"},"code":4102}',
            $response->getContent()
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function presentation(array $data): Arrayable
    {
        return new readonly class ($data) implements Arrayable {
            /**
             * @param array<array-key, mixed> $data
             */
            public function __construct(private array $data)
            {
            }

            /**
             * @return array<array-key, mixed>
             */
            public function toArray(): array
            {
                return $this->data;
            }
        };
    }
}
