<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Psr17;

use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(JSendResponseFactory::class)]
class JSendResponseFactoryTest extends UnitTestCase
{
    public function test_that_from_envelope_preserves_selected_status_headers_and_pre_encoded_json(): void
    {
        $envelope = JSendEnvelope::success($this->presentation(['path' => '/api/access', 'markup' => '<safe>']));
        $stream = $this->mock(StreamInterface::class);
        $response = $this->mock(ResponseInterface::class);
        $responseFactory = $this->mock(ResponseFactoryInterface::class);
        $streamFactory = $this->mock(StreamFactoryInterface::class);

        $responseFactory->shouldReceive('createResponse')
            ->once()
            ->with(201)
            ->andReturn($response);
        $streamFactory->shouldReceive('createStream')
            ->once()
            ->with($envelope->toJson())
            ->andReturn($stream);
        $response->shouldReceive('withHeader')
            ->once()
            ->with('X-Request-ID', 'request-42')
            ->andReturn($response);
        $response->shouldReceive('withHeader')
            ->once()
            ->with('Content-Type', 'application/json')
            ->andReturn($response);
        $response->shouldReceive('withBody')
            ->once()
            ->with($stream)
            ->andReturn($response);

        $factory = new JSendResponseFactory($responseFactory, $streamFactory);

        self::assertSame(
            $response,
            $factory->fromEnvelope($envelope, 201, ['X-Request-ID' => 'request-42'])
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
