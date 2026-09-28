<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Http\Mcp\McpEventStream;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpResponder::class)]
#[CoversClass(McpEventStream::class)]
#[CoversClass(McpRequestHandler::class)]
#[CoversClass(McpResponseFactory::class)]
final class McpEventStreamTest extends UnitTestCase
{
    #[DataProvider('tokens')]
    public function test_that_progress_is_lazy_incremental_and_token_exact(int|string $token): void
    {
        $steps = [];
        $fixture = new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter) use (&$steps): McpToolOutput {
            $steps[] = 'first';
            $reporter->report(0, 2, "public\nstatus");
            $steps[] = 'second';
            $reporter->report(1);
            $steps[] = 'final';
            return McpToolOutput::structured('done');
        }));
        $response = $fixture->handler->handle(ProgressEndpoint::request($token));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
        self::assertSame('no', $response->getHeaderLine('X-Accel-Buffering'));
        self::assertStringContainsString('no-cache', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Content-Length'));
        $stream = $response->getBody();
        self::assertInstanceOf(McpEventStream::class, $stream);
        self::assertSame([], $steps);
        self::assertFalse($stream->eof());
        self::assertNull($stream->getSize());
        self::assertFalse($stream->isSeekable());
        self::assertFalse($stream->isWritable());
        self::assertTrue($stream->isReadable());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('uri'));
        self::assertSame('', $stream->read(0));
        self::assertSame(0, $stream->tell());
        $prefix = $stream->read(6);
        self::assertSame('data: ', $prefix);
        self::assertSame(['first'], $steps);
        $frame = $prefix.$stream->read(8192);
        self::assertSame(['first'], $steps);
        $first = $this->frame($frame);
        self::assertSame($token, $first['params']['progressToken']);
        self::assertSame("public\nstatus", $first['params']['message']);
        self::assertArrayNotHasKey('id', $first);
        self::assertSame(strlen($frame), $stream->tell());
        self::assertSame('notifications/progress', $this->frame($stream->read(8192))['method']);
        self::assertSame(['first', 'second'], $steps);
        self::assertSame('done', $this->frame($stream->read(8192))['result']['structuredContent']);
        self::assertSame(['first', 'second', 'final'], $steps);
        self::assertSame('', $stream->read(8192));
        self::assertTrue($stream->eof());
        self::assertSame('', $stream->read(8192));
        self::assertSame(1, $fixture->tool->calls);
        self::assertSame(1, $fixture->guardCalls);
        self::assertSame(2, $fixture->availabilityCalls);
        self::assertSame([], $fixture->diagnostics);
    }

    public static function tokens(): iterable { yield ['']; yield [0]; yield ['0']; yield [-1]; yield ['token']; }

    public function test_that_direct_json_and_legacy_opt_out_keep_noop_behavior(): void
    {
        $fixture = $this->fixture();
        $response = $fixture->handler->handle(ProgressEndpoint::request(null));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('done', json_decode((string) $response->getBody(), true)['result']['structuredContent']);
        self::assertInstanceOf(NullMcpProgressReporter::class, $fixture->tool->reporter);
        $legacy = new ProgressEndpoint($fixture->tool, false);
        self::assertSame(400, $legacy->handler->handle(ProgressEndpoint::request())->getStatusCode());
        self::assertSame(1, $fixture->tool->calls);
    }

    public function test_that_transport_and_protocol_rejections_never_start_a_producer(): void
    {
        $fixture = $this->fixture();
        foreach ([
            [ProgressEndpoint::request()->withHeader('Origin', 'https://untrusted.test'), 403],
            [ProgressEndpoint::request()->withMethod('GET'), 405],
            [ProgressEndpoint::request()->withoutHeader('Mcp-Name'), 400],
            [ProgressEndpoint::request(true), 400],
            [ProgressEndpoint::request('live', parameters: ['name' => 'progress', 'extra' => 1]), 400],
        ] as [$request, $status]) {
            self::assertSame($status, $fixture->handler->handle($request)->getStatusCode());
        }
        $fixture->allowed = false;
        self::assertSame(429, $fixture->handler->handle(ProgressEndpoint::request())->getStatusCode());
        $fixture->available = false;
        self::assertSame(400, $fixture->handler->handle(ProgressEndpoint::request())->getStatusCode());
        self::assertSame(0, $fixture->tool->calls);
        self::assertSame([], $fixture->diagnostics);
    }

    public function test_that_availability_is_rechecked_at_emission_not_cached_from_mirrors(): void
    {
        $fixture = $this->fixture();
        $stream = $fixture->handler->handle(ProgressEndpoint::request())->getBody();
        $fixture->available = false;
        self::assertSame(-32602, $this->frame($stream->getContents())['error']['code']);
        self::assertSame(0, $fixture->tool->calls);
        self::assertSame([], $fixture->diagnostics);
    }

    public function test_that_other_methods_remain_direct_json(): void
    {
        $fixture = $this->fixture();
        $response = $fixture->handler->handle(ProgressEndpoint::request('live', 'server/discover', []));
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(0, $fixture->tool->calls);
    }

    #[DataProvider('failures')]
    public function test_that_late_failure_is_one_safe_final_after_progress(bool $expected): void
    {
        $fixture = new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter) use ($expected): never {
            $reporter->report(1);
            throw $expected ? new RuntimeException('private') : new LogicException('private');
        }));
        $stream = $fixture->handler->handle(ProgressEndpoint::request())->getBody();
        self::assertSame('notifications/progress', $this->frame($stream->read(8192))['method']);
        $wire = $stream->getContents();
        $final = $this->frame($wire);
        self::assertStringNotContainsString('private', $wire);
        if ($expected) { self::assertTrue($final['result']['isError']); self::assertSame('Unavailable.', $final['result']['content'][0]['text']); }
        else { self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $final['error']); }
        self::assertCount($expected ? 0 : 1, $fixture->diagnostics);
        self::assertTrue($stream->eof());
    }

    public static function failures(): iterable { yield [true]; yield [false]; }

    public function test_that_close_cooperatively_resumes_cancels_and_suppresses_all_later_bytes(): void
    {
        $stopped = false;
        $fixture = new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter) use (&$stopped): McpToolOutput {
            $reporter->report(1);
            $stopped = $reporter->isCancelled();
            $reporter->report(2);
            return McpToolOutput::structured('not sent');
        }));
        $stream = $fixture->handler->handle(ProgressEndpoint::request())->getBody();
        $stream->read(8192);
        $stream->close();
        self::assertTrue($stopped);
        self::assertTrue($stream->eof());
        self::assertFalse($stream->isReadable());
        self::assertSame('', (string) $stream);
        self::assertNull($stream->detach());
        self::assertSame([], $fixture->diagnostics);
        $next = $fixture->handler->handle(ProgressEndpoint::request())->getBody();
        self::assertStringContainsString('not sent', (string) $next);
        self::assertFalse($stopped);
    }

    public function test_that_close_before_first_read_never_invokes_the_tool(): void
    {
        $fixture = $this->fixture();
        $stream = $fixture->handler->handle(ProgressEndpoint::request())->getBody();
        $stream->close();
        self::assertSame(0, $fixture->tool->calls);
        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    #[DataProvider('unsupportedOperations')]
    public function test_that_non_read_operations_reject_without_invocation(string $method, array $arguments): void
    {
        $fixture = $this->fixture();
        $stream = $fixture->handler->handle(ProgressEndpoint::request())->getBody();
        try { $stream->$method(...$arguments); self::fail('Must reject.'); }
        catch (RuntimeException) { self::assertSame(0, $fixture->tool->calls); }
    }

    public static function unsupportedOperations(): iterable
    {
        yield ['seek', [0]]; yield ['rewind', []]; yield ['write', ['bad']]; yield ['read', [-1]];
    }

    private function fixture(): ProgressEndpoint
    {
        return new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter): McpToolOutput {
            $reporter->report(1);
            return McpToolOutput::structured('done');
        }));
    }

    private function frame(string $wire): array
    {
        self::assertStringStartsWith('data: ', $wire);
        self::assertStringEndsWith("\n\n", $wire);
        self::assertSame(2, substr_count($wire, "\n"));
        return json_decode(substr($wire, 6, -2), true, flags: JSON_THROW_ON_ERROR);
    }
}
