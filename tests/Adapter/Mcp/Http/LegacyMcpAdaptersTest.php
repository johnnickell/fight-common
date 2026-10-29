<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Http;

use Fight\Common\Adapter\Http\Mcp\DenyAllMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpHeaderValidator;
use Fight\Common\Adapter\Http\Mcp\McpOrigin;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Adapter\Mcp\Http\McpOrigin as CanonicalMcpOrigin;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use Fight\Test\Common\Fixture\Mcp\ProgressTool;
use Fight\Test\Common\Fixture\Mcp\SapiDouble;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3).'/Fixture/Mcp/SapiDouble.php';

#[CoversClass(DenyAllMcpOriginPolicy::class)]
#[CoversClass(ExactMcpOriginPolicy::class)]
#[CoversClass(McpHeaderValidator::class)]
#[CoversClass(McpOrigin::class)]
#[CoversClass(McpRequestHandler::class)]
#[CoversClass(McpResponseEmitter::class)]
#[CoversClass(McpResponseFactory::class)]
final class LegacyMcpAdaptersTest extends UnitTestCase
{
    public function test_that_legacy_origin_preserves_its_factory_type_validation_and_released_serialization(): void
    {
        $origin = McpOrigin::fromString('HTTPS://EXAMPLE.COM');
        self::assertSame(McpOrigin::class, $origin::class);
        self::assertSame('https://example.com:443', $origin->toString());
        self::assertSame(CanonicalMcpOrigin::fromString('HTTPS://EXAMPLE.COM')->toString(), $origin->toString());
        // This literal is the released PHP object's private-property representation, not new output as expectation.
        $serialized = 'O:39:"Fight\\Common\\Adapter\\Http\\Mcp\\McpOrigin":1:{s:46:"'."\0".'Fight\\Common\\Adapter\\Http\\Mcp\\McpOrigin'."\0".'value";s:23:"https://example.com:443";}';
        self::assertSame($serialized, serialize($origin));
        self::assertSame('https://example.com:443', unserialize($serialized)->toString());
        self::assertTrue((new ExactMcpOriginPolicy(['HTTPS://EXAMPLE.COM']))->allows($origin->toString()));
        self::assertFalse((new DenyAllMcpOriginPolicy())->allows($origin->toString()));
        $this->expectException(DomainException::class);
        McpOrigin::fromString('https://example.com/path');
    }

    #[DataProvider('responseModes')]
    public function test_that_legacy_named_composition_keeps_guards_safe_json_and_lazy_progress(bool $progressive, mixed $token): void
    {
        SapiDouble::reset();
        try {
            $fixture = new ProgressEndpoint(new ProgressTool(static function (McpProgressReporter $progress): McpToolOutput {
                $progress->report(1);
                return McpToolOutput::structured('done');
            }));
            $tools = new McpToolRegistry([$fixture->tool]);
            $registry = new McpCapabilityRegistry(new McpServerInfo('Legacy', '1'), [
                new McpToolDiscovery($tools, $fixture, str_repeat('fixture-key-', 4)),
                new McpToolInvocation(new McpToolInvoker($tools, $fixture)),
            ]);
            $factory = new HttpFactory();
            $responses = new McpResponseFactory(responses: $factory, streams: $factory, diagnostics: $fixture);
            $handler = new McpRequestHandler(
                registry: $registry,
                originPolicy: new ExactMcpOriginPolicy(['https://client.test']),
                guard: $fixture,
                responses: $responses,
                decoder: new McpRequestDecoder(),
                progressive: $progressive
            );
            self::assertSame(McpRequestHandler::class, $handler::class);
            self::assertSame(McpResponseFactory::class, $responses::class);
            $request = ProgressEndpoint::request(null, 'server/discover', []);
            $decoded = (new McpRequestDecoder())->decode((string) $request->getBody());
            self::assertNull((new McpHeaderValidator($registry))->validate($decoded, $request->getHeaders()));
            self::assertSame(403, $handler->handle($request->withHeader('Origin', 'https://denied.test'))->getStatusCode());
            self::assertSame(0, $fixture->guardCalls);
            $response = $handler->handle($request->withHeader('Origin', 'https://client.test'));
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
            self::assertSame('Legacy', json_decode((string) $response->getBody(), true)['result']['_meta']['io.modelcontextprotocol/serverInfo']['name']);
            $fixture->allowed = false;
            self::assertSame(429, $handler->handle($request)->getStatusCode());

            $fixture->allowed = true;
            $toolResponse = $handler->handle(ProgressEndpoint::request($token));
            self::assertSame($token === null ? 1 : 0, $fixture->tool->calls);
            ob_start();
            try { (new McpResponseEmitter())->emit($toolResponse); $wire = ob_get_contents(); }
            finally { ob_end_clean(); }
            self::assertSame(1, $fixture->tool->calls);
            self::assertStringContainsString('done', $wire);
            self::assertSame($token === null ? 0 : 2, substr_count($wire, 'data: '));
            self::assertSame([], $fixture->diagnostics);
        } finally {
            SapiDouble::reset();
        }
    }

    public static function responseModes(): iterable
    {
        yield 'direct' => [false, null];
        yield 'progressive' => [true, 'live'];
    }
}
