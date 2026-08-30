<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\CodeIgniter;

use CodeIgniter\HTTP\ResponseInterface;
use Fight\Common\Adapter\Http\CodeIgniter\JSendResponse;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Test\Common\TestCase\UnitTestCase;
use JsonException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JSendResponse::class)]
final class JSendResponseTest extends UnitTestCase
{
    public function test_that_success_preserves_selected_status_headers_and_exact_json_bytes(): void
    {
        /** @var ResponseInterface&MockInterface $response */
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('setStatusCode')->once()->with(201)->andReturnSelf();
        $response->shouldReceive('setHeader')->once()->with('X-Request-ID', 'request-42')->andReturnSelf();
        $response->shouldReceive('setJSON')->once()->with('{"status":"success","data":{"id":42}}', true)->andReturnSelf();

        self::assertSame(
            $response,
            JSendResponse::success($response, $this->presentation(['id' => 42]), 201, ['X-Request-ID' => 'request-42'])
        );
    }

    public function test_that_fail_and_error_preserve_exact_json_bytes(): void
    {
        /** @var ResponseInterface&MockInterface $fail */
        $fail = $this->mock(ResponseInterface::class);
        $fail->shouldReceive('setStatusCode')->once()->with(422)->andReturnSelf();
        $fail->shouldReceive('setJSON')->once()->with('{"status":"fail","data":{"email":"invalid"}}', true)->andReturnSelf();
        JSendResponse::fail($fail, $this->presentation(['email' => 'invalid']), 422);

        /** @var ResponseInterface&MockInterface $error */
        $error = $this->mock(ResponseInterface::class);
        $error->shouldReceive('setStatusCode')->once()->with(502)->andReturnSelf();
        $error->shouldReceive('setHeader')->once()->with('Retry-After', '30')->andReturnSelf();
        $error->shouldReceive('setJSON')->once()->with(
            '{"status":"error","message":"The bridge is out","data":{"request_id":"request-42"},"code":4102}',
            true
        )->andReturnSelf();
        JSendResponse::error($error, 'The bridge is out', 502, $this->presentation(['request_id' => 'request-42']), 4102, ['Retry-After' => '30']);
    }

    public function test_that_invalid_envelope_encoding_fails_before_mutating_the_native_response(): void
    {
        /** @var ResponseInterface&MockInterface $response */
        $response = $this->mock(ResponseInterface::class);
        $response->shouldNotReceive('setStatusCode');

        $this->expectException(JsonException::class);

        JSendResponse::success($response, $this->presentation(['value' => "\xB1\x31"]));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function presentation(array $data): Arrayable
    {
        return new readonly class ($data) implements Arrayable {
            public function __construct(private array $data)
            {
            }

            public function toArray(): array
            {
                return $this->data;
            }
        };
    }
}
