<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Symfony;

use Fight\Common\Adapter\Http\Symfony\McpResponseFactory as LegacyMcpResponseFactory;
use Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use Fight\Test\Common\Fixture\Mcp\SapiDouble;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;

require_once dirname(__DIR__, 3).'/Fixture/Mcp/SapiDouble.php';

#[CoversClass(McpResponseFactory::class)]
#[CoversClass(LegacyMcpResponseFactory::class)]
final class McpResponseFactoryTest extends UnitTestCase
{
    protected function setUp(): void { parent::setUp(); SapiDouble::reset(); }
    protected function tearDown(): void { SapiDouble::reset(); parent::tearDown(); }

    public static function factoryClasses(): iterable
    {
        yield 'canonical' => [McpResponseFactory::class];
        yield 'legacy' => [LegacyMcpResponseFactory::class];
    }

    #[DataProvider('factoryClasses')]
    public function test_that_native_translation_is_lazy_single_use_and_has_no_framework_sentinel(string $factoryClass): void
    {
        $fixture = new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter): McpToolOutput {
            $reporter->report(1);
            return McpToolOutput::structured('done');
        }));
        $response = $fixture->handler->handle(ProgressEndpoint::request());
        $factory = new $factoryClass();
        self::assertSame($factoryClass, $factory::class);
        $native = $factory->fromResponse($response);
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

    #[DataProvider('factoryClasses')]
    public function test_that_native_direct_json_translation_preserves_status_body_and_headers(string $factoryClass): void
    {
        $native = (new $factoryClass())->fromResponse(new Response(400, ['Content-Type' => 'application/json', 'X-Test' => ['a', 'b']], '{"error":"safe"}', '1.0'));
        self::assertNotInstanceOf(StreamedResponse::class, $native);
        self::assertSame(400, $native->getStatusCode());
        self::assertSame('{"error":"safe"}', $native->getContent());
        self::assertSame(['a', 'b'], $native->headers->all('X-Test'));
        self::assertSame('1.0', $native->getProtocolVersion());
    }
}
