<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Http;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use Fight\Test\Common\Fixture\Mcp\SapiDouble;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

require_once dirname(__DIR__, 3).'/Fixture/Mcp/SapiDouble.php';

#[CoversClass(McpResponseEmitter::class)]
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

    public function test_that_callback_body_close_never_writes_a_new_subscription_notification(): void
    {
        $fixture = new SubscriptionFixture(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $response = $fixture->endpoint->handle(SubscriptionFixture::request());
        $fixture->queryPolicy = static function () use ($fixture): void {
            // The first Query establishes the boundary before this independently recorded live change.
            static $reads = 0;
            if (++$reads === 2) { (new NoticeFixture($fixture->db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']); }
        };
        $fixture->policy = static function ($filter, $uri, $notice) use ($response): bool {
            if ($notice !== null) { $response->getBody()->close(); }
            return true;
        };
        ob_start();
        try { new McpResponseEmitter()->emitBody($response); $wire = ob_get_contents(); }
        finally { ob_end_clean(); }
        self::assertSame(1, substr_count($wire, 'data: '));
        self::assertStringContainsString('acknowledged', $wire);
        self::assertStringNotContainsString('list_changed', $wire);
        self::assertStringNotContainsString('resultType', $wire);
        self::assertTrue($response->getBody()->eof());
        self::assertGreaterThan(0, (int) $fixture->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        self::assertSame(1, (int) $fixture->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertFalse(SapiDouble::$ignoreAbort);
        self::assertSame([], $fixture->diagnostics);
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

    private function fixture(): ProgressEndpoint
    {
        return new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter): McpToolOutput {
            $reporter->report(1);
            return McpToolOutput::structured('done');
        }));
    }
}
