<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpFoundation;

use Fight\Common\Adapter\HttpFoundation\JSendResponse;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(JSendResponse::class)]
class JSendResponseTest extends UnitTestCase
{
    public function test_that_success_creates_response_with_success_status(): void
    {
        $response = JSendResponse::success(['id' => 1]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($response->isSuccess());
        self::assertFalse($response->isFail());
        self::assertFalse($response->isError());
    }

    public function test_that_success_encodes_data_in_json_body(): void
    {
        $response = JSendResponse::success(['key' => 'value']);
        $decoded = $response->getData();

        self::assertSame('success', $decoded['status']);
        self::assertSame(['key' => 'value'], $decoded['data']);
    }

    public function test_that_success_accepts_null_data(): void
    {
        $response = JSendResponse::success();
        $decoded = $response->getData();

        self::assertNull($decoded['data']);
    }

    public function test_that_success_accepts_custom_status_code(): void
    {
        $response = JSendResponse::success(null, Response::HTTP_CREATED);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function test_that_success_accepts_custom_encoding_options(): void
    {
        $response = JSendResponse::success(['url' => 'https://example.com/path'], options: JSON_UNESCAPED_SLASHES);
        $content = $response->getContent();

        self::assertStringContainsString('https://example.com/path', $content);
    }

    public function test_that_fail_creates_response_with_fail_status(): void
    {
        $response = JSendResponse::fail(['field' => 'required']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertTrue($response->isFail());
        self::assertFalse($response->isSuccess());
        self::assertFalse($response->isError());
    }

    public function test_that_fail_encodes_data_in_json_body(): void
    {
        $response = JSendResponse::fail(['email' => 'invalid']);
        $decoded = $response->getData();

        self::assertSame('fail', $decoded['status']);
        self::assertSame(['email' => 'invalid'], $decoded['data']);
    }

    public function test_that_fail_accepts_null_data(): void
    {
        $response = JSendResponse::fail();
        $decoded = $response->getData();

        self::assertNull($decoded['data']);
    }

    public function test_that_fail_accepts_custom_status_code(): void
    {
        $response = JSendResponse::fail(null, Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function test_that_error_creates_response_with_error_status(): void
    {
        $response = JSendResponse::error('Something went wrong');

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertTrue($response->isError());
        self::assertFalse($response->isSuccess());
        self::assertFalse($response->isFail());
    }

    public function test_that_error_encodes_message_in_json_body(): void
    {
        $response = JSendResponse::error('Database error');
        $decoded = $response->getData();

        self::assertSame('error', $decoded['status']);
        self::assertSame('Database error', $decoded['message']);
        self::assertArrayNotHasKey('data', $decoded);
        self::assertArrayNotHasKey('code', $decoded);
    }

    public function test_that_error_includes_data_when_provided(): void
    {
        $response = JSendResponse::error('Not found', data: ['id' => 42]);
        $decoded = $response->getData();

        self::assertSame(['id' => 42], $decoded['data']);
    }

    public function test_that_error_includes_code_when_provided(): void
    {
        $response = JSendResponse::error('Auth failed', code: 4001);
        $decoded = $response->getData();

        self::assertSame(4001, $decoded['code']);
    }

    public function test_that_error_accepts_custom_status_code(): void
    {
        $response = JSendResponse::error('Not found', Response::HTTP_NOT_FOUND);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function test_that_get_data_returns_array_by_default(): void
    {
        $response = JSendResponse::success(['x' => 1]);

        self::assertIsArray($response->getData());
    }

    public function test_that_get_data_returns_object_when_array_is_false(): void
    {
        $response = JSendResponse::success(['x' => 1]);
        $decoded = $response->getData(false);

        self::assertIsObject($decoded);
    }
}
