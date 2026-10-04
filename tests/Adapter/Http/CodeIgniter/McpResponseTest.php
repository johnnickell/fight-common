<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\CodeIgniter;

use Fight\Common\Adapter\Http\CodeIgniter\McpResponse;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use Fight\Test\Common\Fixture\Mcp\SapiDouble;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

#[CoversClass(McpResponse::class)]
final class McpResponseTest extends UnitTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_native_response_defers_guarded_output_until_send_and_does_not_append_framework_content(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Mcp/SapiDouble.php';
        require dirname(__DIR__, 3).'/Fixture/Mcp/codeigniter.php';
        SapiDouble::reset();
        foreach ([null, 'live'] as $token) {
            $fixture = new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $reporter): McpToolOutput {
                $reporter->report(1);
                return McpToolOutput::structured('done');
            }));
            $response = $fixture->handler->handle(ProgressEndpoint::request($token));
            $native = new McpResponse($response);
            self::assertSame($token === null ? 1 : 0, $fixture->tool->calls);
            self::assertSame(200, $native->getStatusCode());
            self::assertSame($response->getHeaderLine('Content-Type'), $native->getHeaderLine('Content-Type'));
            $native->setBody('framework toolbar must not appear');
            ob_start();
            try { $native->send(); $native->sendBody(); $wire = ob_get_contents(); }
            finally { ob_end_clean(); }
            self::assertSame(1, $fixture->tool->calls);
            self::assertStringContainsString('done', $wire);
            self::assertStringNotContainsString('toolbar', $wire);
            self::assertSame($token === null ? 0 : 2, substr_count($wire, 'data: '));
        }
    }
}
