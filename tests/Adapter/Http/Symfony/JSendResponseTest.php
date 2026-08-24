<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Symfony;

use Fight\Common\Adapter\Http\Symfony\JSendResponse;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Test\Common\TestCase\UnitTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(JSendResponse::class)]
class JSendResponseTest extends UnitTestCase
{
    public function test_that_from_envelope_uses_the_already_encoded_body(): void
    {
        $envelope = JSendEnvelope::success(
            $this->presentation(['path' => '/api/access', 'markup' => '<safe>'])
        );

        $response = JSendResponse::fromEnvelope(
            $envelope,
            Response::HTTP_CREATED,
            ['X-Request-ID' => 'request-42', 'Content-Type' => 'text/plain']
        );

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('request-42', $response->headers->get('X-Request-ID'));
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame($envelope->toJson(), $response->getContent());
    }

    public function test_that_success_creates_an_exact_success_response(): void
    {
        $response = JSendResponse::success(
            $this->presentation(['id' => 42]),
            Response::HTTP_ACCEPTED,
            ['X-Result' => 'accepted']
        );

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame('accepted', $response->headers->get('X-Result'));
        self::assertSame('{"status":"success","data":{"id":42}}', $response->getContent());
    }

    public function test_that_success_includes_null_data_by_default(): void
    {
        $response = JSendResponse::success();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"success","data":null}', $response->getContent());
    }

    public function test_that_fail_creates_an_exact_fail_response(): void
    {
        $response = JSendResponse::fail(
            $this->presentation(['email' => 'invalid']),
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('{"status":"fail","data":{"email":"invalid"}}', $response->getContent());
    }

    public function test_that_error_omits_absent_optional_fields(): void
    {
        $response = JSendResponse::error('The bridge is out');

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"The bridge is out"}', $response->getContent());
    }

    public function test_that_error_includes_optional_data_and_code(): void
    {
        $response = JSendResponse::error(
            'The bridge is out',
            Response::HTTP_BAD_GATEWAY,
            $this->presentation(['request_id' => 'request-42']),
            4102,
            ['Retry-After' => '30']
        );

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));
        self::assertSame(
            '{"status":"error","message":"The bridge is out","data":{"request_id":"request-42"},"code":4102}',
            $response->getContent()
        );
    }

    public function test_that_invalid_encoding_fails_before_a_response_is_returned(): void
    {
        $this->expectException(JsonException::class);

        JSendResponse::success($this->presentation(['value' => "\xB1\x31"]));
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
