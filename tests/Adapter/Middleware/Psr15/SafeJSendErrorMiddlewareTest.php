<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Middleware\Psr15;

use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

#[CoversClass(SafeJSendErrorMiddleware::class)]
class SafeJSendErrorMiddlewareTest extends UnitTestCase
{
    public function test_that_process_conceals_unexpected_diagnostics(): void
    {
        $http = new HttpFactory();
        $handler = $this->mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andThrow(
            new RuntimeException('password=synthetic-secret', 401, new RuntimeException('cause=synthetic-secret'))
        );

        $response = (new SafeJSendErrorMiddleware(
            new JSendResponseFactory($http, $http),
            new SafeHttpErrorPolicy(),
            $this->diagnostics()
        ))->process(
            $http->createServerRequest('GET', '/access?debug=true')->withHeader('X-Debug', '1')->withCookieParams(['debug' => 'true']),
            $handler
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], json_decode((string) $response->getBody(), true));
        self::assertStringNotContainsString('synthetic-secret', (string) $response->getBody());
    }

    public function test_that_process_passes_through_normal_downstream_responses(): void
    {
        $http = new HttpFactory();
        $expected = $http->createResponse(204);
        $handler = $this->mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturn($expected);
        $diagnostics = $this->mock(SafeHttpDiagnostics::class);
        $diagnostics->shouldNotReceive('report');

        $response = $this->middleware($http, $diagnostics)->process($http->createServerRequest('GET', '/'), $handler);
        self::assertSame($expected, $response);
    }

    public function test_that_process_reports_once_and_uses_safe_category_data(): void
    {
        $http = new HttpFactory();
        $failure = new PermissionDenied('password=synthetic-secret');
        $diagnostics = $this->mock(SafeHttpDiagnostics::class);
        $diagnostics->shouldReceive('report')->once()->with($failure);
        $response = $this->middleware($http, $diagnostics)->process($http->createServerRequest('GET', '/'), $this->throwing($failure));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['status' => 'fail', 'data' => ['message' => 'Permission denied.']], json_decode((string) $response->getBody(), true));
        self::assertStringNotContainsString('synthetic-secret', (string) $response->getBody());
    }

    public function test_that_process_rejects_missing_challenge_without_a_protocol_incomplete_401(): void
    {
        $http = new HttpFactory();
        $response = $this->middleware($http, $this->diagnostics())->process(
            $http->createServerRequest('GET', '/'),
            $this->throwing(new AuthenticationRequired('credential=synthetic-secret'))
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($response->hasHeader('WWW-Authenticate'));
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], json_decode((string) $response->getBody(), true));
    }

    public function test_that_process_projects_only_deliberately_public_validation_data_and_challenge(): void
    {
        $http = new HttpFactory();
        $projector = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                if ($status === 401) {
                    return new SafeHttpPresentation(metadata: new SafeHttpMetadata(challenge: 'Basic realm="test"'));
                }
                return new SafeHttpPresentation(data: ['email' => ['Invalid address.']]);
            }
        };
        $validation = $this->middleware($http, $this->diagnostics(), $projector)->process(
            $http->createServerRequest('POST', '/'),
            $this->throwing(new ValidationException(['email' => ['password=synthetic-secret']]))
        );
        self::assertSame(['status' => 'fail', 'data' => ['email' => ['Invalid address.']]], json_decode((string) $validation->getBody(), true));

        $auth = $this->middleware($http, $this->diagnostics(), $projector)->process(
            $http->createServerRequest('POST', '/'),
            $this->throwing(new AuthenticationRequired('password=synthetic-secret'))
        );
        self::assertSame(401, $auth->getStatusCode());
        self::assertSame('Basic realm="test"', $auth->getHeaderLine('WWW-Authenticate'));
    }

    public function test_that_process_fails_closed_when_reporter_projector_or_response_creation_fails(): void
    {
        $http = new HttpFactory();
        $reporter = $this->mock(SafeHttpDiagnostics::class);
        $reporter->shouldReceive('report')->once()->andThrow(new RuntimeException('password=synthetic-secret'));
        $response = $this->middleware($http, $reporter)->process($http->createServerRequest('GET', '/'), $this->throwing(new RuntimeException('original secret')));
        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('secret', (string) $response->getBody());

        $projector = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                throw new RuntimeException('password=synthetic-secret');
            }
        };
        $response = $this->middleware($http, $this->diagnostics(), $projector)->process(
            $http->createServerRequest('GET', '/'), $this->throwing(new PermissionDenied('secret'))
        );
        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('secret', (string) $response->getBody());
    }

    public function test_that_process_falls_back_when_response_factory_fails_once(): void
    {
        $http = new HttpFactory();
        $factory = $this->mock(ResponseFactoryInterface::class);
        $factory->shouldReceive('createResponse')->once()->with(403)->andThrow(new RuntimeException('private factory error'));
        $factory->shouldReceive('createResponse')->once()->with(500)->andReturn($http->createResponse(500));
        $diagnostics = $this->mock(SafeHttpDiagnostics::class);
        $diagnostics->shouldReceive('report')->once();
        $middleware = new SafeJSendErrorMiddleware(new JSendResponseFactory($factory, $http), new SafeHttpErrorPolicy(), $diagnostics);

        $response = $middleware->process($http->createServerRequest('GET', '/'), $this->throwing(new PermissionDenied('private')));
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], json_decode((string) $response->getBody(), true));
    }

    public function test_that_process_throws_only_sanitized_error_if_psr_factory_cannot_render_any_response(): void
    {
        $http = new HttpFactory();
        $factory = $this->mock(ResponseFactoryInterface::class);
        $factory->shouldReceive('createResponse')->twice()->andThrow(new RuntimeException('private factory error'));
        $diagnostics = $this->mock(SafeHttpDiagnostics::class);
        $diagnostics->shouldReceive('report')->once();
        $middleware = new SafeJSendErrorMiddleware(new JSendResponseFactory($factory, $http), new SafeHttpErrorPolicy(), $diagnostics);

        try {
            $middleware->process($http->createServerRequest('GET', '/'), $this->throwing(new PermissionDenied('private')));
            self::fail('Expected sanitized response factory failure.');
        } catch (RuntimeException $failure) {
            self::assertSame('Unable to render safe HTTP error.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    public function test_that_process_rejects_unserializable_projected_data_without_logging_twice(): void
    {
        $http = new HttpFactory();
        $projector = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                return new SafeHttpPresentation(data: ['private' => new RuntimeException('private')]);
            }
        };
        $diagnostics = $this->mock(SafeHttpDiagnostics::class);
        $diagnostics->shouldReceive('report')->once();
        $response = $this->middleware($http, $diagnostics, $projector)->process(
            $http->createServerRequest('GET', '/'), $this->throwing(new PermissionDenied('private'))
        );
        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('private', (string) $response->getBody());
    }

    public function test_that_process_does_not_serialize_mutated_referenced_public_data(): void
    {
        $http = new HttpFactory();
        $serializer = new class implements JsonSerializable {
            public int $calls = 0;

            public function jsonSerialize(): string
            {
                ++$this->calls;
                return 'password=synthetic-secret';
            }
        };
        $projector = new class ($serializer) implements SafeHttpProjector {
            public function __construct(private readonly JsonSerializable $serializer)
            {
            }

            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                $nested = ['field' => 'Safe'];
                $presentation = new SafeHttpPresentation(data: ['nested' => &$nested]);
                $nested['field'] = $this->serializer;
                return $presentation;
            }
        };
        $diagnostics = $this->mock(SafeHttpDiagnostics::class);
        $diagnostics->shouldReceive('report')->once();
        $response = $this->middleware($http, $diagnostics, $projector)->process(
            $http->createServerRequest('GET', '/'), $this->throwing(new PermissionDenied('private'))
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['status' => 'fail', 'data' => ['nested' => ['field' => 'Safe']]], json_decode((string) $response->getBody(), true));
        self::assertStringNotContainsString('synthetic-secret', (string) $response->getBody());
        self::assertSame(0, $serializer->calls);
    }

    private function middleware(HttpFactory $http, SafeHttpDiagnostics $diagnostics, ?SafeHttpProjector $projector = null): SafeJSendErrorMiddleware
    {
        return new SafeJSendErrorMiddleware(new JSendResponseFactory($http, $http), new SafeHttpErrorPolicy(), $diagnostics, $projector);
    }

    private function throwing(Throwable $failure): RequestHandlerInterface
    {
        $handler = $this->mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andThrow($failure);
        return $handler;
    }

    private function diagnostics(): SafeHttpDiagnostics
    {
        return new class implements SafeHttpDiagnostics {
            public function report(Throwable $failure): void
            {
            }
        };
    }
}
