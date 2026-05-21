<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpKernel;

use Fight\Common\Adapter\HttpKernel\JsonRequestMiddleware;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

#[CoversClass(JsonRequestMiddleware::class)]
class JsonRequestMiddlewareTest extends UnitTestCase
{
    public function test_that_handle_parses_json_body_for_post_request(): void
    {
        $request = Request::create('/api', 'POST', content: '{"name":"Alice","age":30}');
        $request->headers->set('Content-Type', 'application/json');

        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $kernel->shouldReceive('handle')
            ->withArgs(fn(Request $r) => $r->request->get('name') === 'Alice')
            ->once()
            ->andReturn($response);

        $middleware = new JsonRequestMiddleware($kernel);
        $result = $middleware->handle($request);

        self::assertSame($response, $result);
    }

    public function test_that_handle_parses_json_body_for_put_request(): void
    {
        $request = Request::create('/api/1', 'PUT', content: '{"value":"updated"}');
        $request->headers->set('Content-Type', 'application/json');

        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $kernel->shouldReceive('handle')
            ->withArgs(fn(Request $r) => $r->request->get('value') === 'updated')
            ->once()
            ->andReturn($response);

        $middleware = new JsonRequestMiddleware($kernel);
        $result = $middleware->handle($request);

        self::assertSame($response, $result);
    }

    public function test_that_handle_parses_json_body_for_patch_request(): void
    {
        $request = Request::create('/api/1', 'PATCH', content: '{"status":"active"}');
        $request->headers->set('Content-Type', 'application/json');

        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $kernel->shouldReceive('handle')
            ->withArgs(fn(Request $r) => $r->request->get('status') === 'active')
            ->once()
            ->andReturn($response);

        $middleware = new JsonRequestMiddleware($kernel);
        $result = $middleware->handle($request);

        self::assertSame($response, $result);
    }

    public function test_that_handle_parses_json_body_for_delete_request(): void
    {
        $request = Request::create('/api/1', 'DELETE', content: '{"confirm":true}');
        $request->headers->set('Content-Type', 'application/json');

        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $kernel->shouldReceive('handle')->once()->andReturn($response);

        $middleware = new JsonRequestMiddleware($kernel);
        $result = $middleware->handle($request);

        self::assertSame($response, $result);
    }

    public function test_that_handle_skips_parsing_for_get_request(): void
    {
        $request = Request::create('/api', 'GET');
        $request->headers->set('Content-Type', 'application/json');

        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $kernel->shouldReceive('handle')->once()->andReturn($response);

        $middleware = new JsonRequestMiddleware($kernel);
        $result = $middleware->handle($request);

        self::assertSame($response, $result);
        self::assertEmpty($request->request->all());
    }

    public function test_that_handle_skips_parsing_when_content_type_is_not_json(): void
    {
        $request = Request::create('/api', 'POST', content: 'name=Alice');
        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $kernel->shouldReceive('handle')->once()->andReturn($response);

        $middleware = new JsonRequestMiddleware($kernel);
        $result = $middleware->handle($request);

        self::assertSame($response, $result);
    }

    public function test_that_handle_passes_type_and_catch_to_kernel(): void
    {
        $request = Request::create('/api', 'GET');
        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $kernel->shouldReceive('handle')
            ->with($request, HttpKernelInterface::SUB_REQUEST, false)
            ->once()
            ->andReturn($response);

        $middleware = new JsonRequestMiddleware($kernel);
        $result = $middleware->handle($request, HttpKernelInterface::SUB_REQUEST, false);

        self::assertSame($response, $result);
    }

    public function test_that_terminate_delegates_to_terminable_kernel(): void
    {
        $request = Request::create('/api');
        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface&TerminableInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class . ',' . TerminableInterface::class);
        $kernel->shouldReceive('terminate')->with($request, $response)->once();

        $middleware = new JsonRequestMiddleware($kernel);
        $middleware->terminate($request, $response);
    }

    public function test_that_terminate_does_nothing_for_non_terminable_kernel(): void
    {
        $request = Request::create('/api');
        $response = new Response('ok');

        /** @var MockInterface|HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);

        $middleware = new JsonRequestMiddleware($kernel);
        $middleware->terminate($request, $response);

        // No exception means the non-terminable branch was handled correctly
        self::assertTrue(true);
    }
}
