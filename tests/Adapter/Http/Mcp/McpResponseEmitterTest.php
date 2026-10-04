<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Adapter\Http\Symfony\McpResponseFactory;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use Fight\Test\Common\Fixture\Mcp\SapiDouble;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

require_once dirname(__DIR__, 3).'/Fixture/Mcp/SapiDouble.php';

#[CoversClass(McpResponseEmitter::class)]
#[CoversClass(McpResponseFactory::class)]
final class McpResponseEmitterTest extends UnitTestCase
{
    protected function setUp(): void { parent::setUp(); SapiDouble::reset(); }
    protected function tearDown(): void { SapiDouble::reset(); parent::tearDown(); }

    public function test_that_emitter_flushes_incrementally_and_cancels_before_work_resumes(): void
    {
        $cancelled = false;
        $fixture = new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $progress) use (&$cancelled): McpToolOutput {
            $progress->report(1);
            self::assertSame(1, SapiDouble::$flushes);
            $cancelled = $progress->isCancelled();
            $progress->report(2);
            return McpToolOutput::structured('not delivered');
        }));
        $response = $fixture->handler->handle(ProgressEndpoint::request());
        SapiDouble::$abortAfter = 1;
        ob_start();
        try { new McpResponseEmitter()->emit($response); $wire = ob_get_contents(); }
        finally { ob_end_clean(); }
        self::assertTrue($cancelled);
        self::assertSame(1, substr_count($wire, 'data: '));
        self::assertStringNotContainsString('not delivered', $wire);
        self::assertSame(['HTTP/1.1 200 OK', true], SapiDouble::$headers[0]);
        self::assertContains(['Content-Type: text/event-stream', false], SapiDouble::$headers);
        self::assertFalse(SapiDouble::$ignoreAbort);
        self::assertTrue($response->getBody()->eof());
    }

    public function test_that_preexisting_disconnect_does_not_start_a_tool_and_restores_abort_policy(): void
    {
        $fixture = $this->fixture();
        SapiDouble::$abortAfter = 0;
        SapiDouble::$ignoreAbort = true;
        new McpResponseEmitter()->emit($fixture->handler->handle(ProgressEndpoint::request()));
        self::assertSame(0, $fixture->tool->calls);
        self::assertTrue(SapiDouble::$ignoreAbort);
    }

    public function test_that_direct_response_emits_original_body_and_header_values_without_streaming(): void
    {
        $response = new Response(429, ['Set-Cookie' => ['one', 'two']], 'safe');
        ob_start();
        try { new McpResponseEmitter()->emit($response); self::assertSame('safe', ob_get_contents()); }
        finally { ob_end_clean(); }
        self::assertSame([['HTTP/1.1 429 Too Many Requests', true], ['Set-Cookie: one', false], ['Set-Cookie: two', false]], SapiDouble::$headers);
        self::assertSame(0, SapiDouble::$flushes);
    }

    public function test_that_already_sent_headers_fail_before_tool_work(): void
    {
        SapiDouble::$headersSent = true;
        $this->expectException(RuntimeException::class);
        new McpResponseEmitter()->emit(new Response());
    }

    #[DataProvider('buffering')]
    public function test_that_unsupported_buffering_fails_before_headers_or_tool_invocation(int $buffers, string $compression, ?string $header): void
    {
        SapiDouble::$buffers = $buffers;
        SapiDouble::$compression = $compression;
        $fixture = $this->fixture();
        $response = $fixture->handler->handle(ProgressEndpoint::request());
        if ($header !== null) { $response = $response->withHeader($header, '1'); }
        try { new McpResponseEmitter()->emit($response); self::fail('Unsupported emission must reject.'); }
        catch (RuntimeException) { self::assertSame(0, $fixture->tool->calls); self::assertSame([], SapiDouble::$headers); }
    }

    public static function buffering(): iterable
    {
        yield [1, '0', null]; yield [0, '1', null]; yield [0, 'On', null];
        yield [0, 'off', 'Content-Length']; yield [0, '', 'Content-Encoding'];
    }

    public function test_that_native_translation_is_lazy_single_use_and_has_no_framework_sentinel(): void
    {
        $fixture = $this->fixture();
        $response = $fixture->handler->handle(ProgressEndpoint::request());
        $native = new McpResponseFactory()->fromResponse($response);
        self::assertInstanceOf(StreamedResponse::class, $native);
        self::assertSame(0, $fixture->tool->calls);
        self::assertSame('text/event-stream', $native->headers->get('Content-Type'));
        self::assertSame('no', $native->headers->get('X-Accel-Buffering'));
        ob_start();
        try { $native->sendContent(); $native->sendContent(); $wire = ob_get_contents(); }
        finally { ob_end_clean(); }
        self::assertSame(1, $fixture->tool->calls);
        self::assertSame(2, substr_count($wire, 'data: '));
        self::assertStringNotContainsString('event:', $wire);
        self::assertStringNotContainsString('</stream>', $wire);
        self::assertSame(3, SapiDouble::$flushes);
        self::assertFalse(SapiDouble::$ignoreAbort);
    }

    public function test_that_native_direct_json_translation_preserves_status_body_and_headers(): void
    {
        $native = new McpResponseFactory()->fromResponse(new Response(400, ['Content-Type' => 'application/json', 'X-Test' => ['a', 'b']], '{"error":"safe"}', '1.0'));
        self::assertNotInstanceOf(StreamedResponse::class, $native);
        self::assertSame(400, $native->getStatusCode());
        self::assertSame('{"error":"safe"}', $native->getContent());
        self::assertSame(['a', 'b'], $native->headers->all('X-Test'));
        self::assertSame('1.0', $native->getProtocolVersion());
    }

    private function fixture(): ProgressEndpoint
    {
        return new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter): McpToolOutput {
            $reporter->report(1);
            return McpToolOutput::structured('done');
        }));
    }
}
