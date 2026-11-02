<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpRequestMetadata;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Protocol\McpImplementationInfoGrammar;
use Fight\Common\Application\Mcp\Protocol\McpTraceContextGrammar;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpImplementationInfoGrammar::class)]
#[CoversClass(McpRequestMetadata::class)]
#[CoversClass(McpTraceContextGrammar::class)]
final class McpRequestMetadataTest extends UnitTestCase
{
    #[DataProvider('grammarCases')]
    public function test_that_metadata_preserves_trace_and_implementation_grammar_boundaries(array $fields, bool $accepted): void
    {
        $object = StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => McpResponder::PROTOCOL_VERSION,
            McpRequestMetadata::CLIENT_CAPABILITIES_KEY => StrictJson::fromObject(),
            ...$fields,
        ]);

        if (!$accepted) {
            try {
                McpRequestMetadata::fromObject($object);
                self::fail('Malformed metadata was accepted.');
            } catch (McpProtocolException $failure) {
                self::assertNull($failure->requestId());
                self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $failure->protocolError()->toArray());
            }

            return;
        }

        $metadata = McpRequestMetadata::fromObject($object);
        self::assertSame(McpResponder::PROTOCOL_VERSION, $metadata->protocolVersion());
        self::assertSame($object->get(McpRequestMetadata::CLIENT_CAPABILITIES_KEY), $metadata->clientCapabilities());
        self::assertSame($object->get(McpRequestMetadata::CLIENT_INFO_KEY), $metadata->clientInfo());
        self::assertSame($object->get(McpRequestMetadata::PROGRESS_TOKEN_KEY), $metadata->progressToken());
    }

    public static function grammarCases(): iterable
    {
        $parent = '00-0af7651916cd43dd8448eb211c80319c-00f067aa0ba902b7-01';
        yield 'version zero' => [['traceparent' => $parent], true];
        yield 'future version with suffix' => [['traceparent' => '01'.substr($parent, 2).'-future'], true];
        yield 'version ff' => [['traceparent' => 'ff'.substr($parent, 2)], false];
        yield 'zero trace identity' => [['traceparent' => '00-'.str_repeat('0', 32).'-00f067aa0ba902b7-01'], false];
        yield 'zero parent identity' => [['traceparent' => '00-0af7651916cd43dd8448eb211c80319c-'.str_repeat('0', 16).'-01'], false];
        yield 'uppercase trace identity' => [['traceparent' => strtoupper($parent)], false];
        yield 'version zero suffix' => [['traceparent' => $parent.'-future'], false];
        yield 'empty tracestate' => [['tracestate' => ''], true];
        yield 'empty tracestate members' => [['tracestate' => " ,\t,"], true];
        yield 'duplicate tracestate keys' => [['tracestate' => 'vendor=one,vendor=two'], false];
        yield 'tracestate value at limit' => [['tracestate' => 'vendor='.str_repeat('x', 256)], true];
        yield 'tracestate value over limit' => [['tracestate' => 'vendor='.str_repeat('x', 257)], false];
        yield 'tracestate leading and trailing whitespace' => [['tracestate' => " \tvendor= value\t "], true];
        yield 'tracestate empty value' => [['tracestate' => 'vendor='], false];
        yield 'tracestate tenant key' => [['tracestate' => '1tenant@vendor=value'], true];
        yield 'tracestate 32 empty members' => [['tracestate' => str_repeat(',', 31)], true];
        yield 'tracestate 33 empty members' => [['tracestate' => str_repeat(',', 32)], false];
        yield 'empty baggage value' => [['baggage' => 'key='], true];
        yield 'baggage properties' => [['baggage' => " key = value%20 ; flag ; other = %2f "], true];
        yield 'baggage at member limit' => [['baggage' => implode(',', array_fill(0, 180, 'key=value'))], true];
        yield 'baggage over member limit' => [['baggage' => implode(',', array_fill(0, 181, 'key=value'))], false];
        yield 'empty baggage' => [['baggage' => ''], false];
        yield 'empty baggage property' => [['baggage' => 'key=value;'], false];
        yield 'invalid baggage key' => [['baggage' => 'bad key=value'], false];
        yield 'invalid baggage escape' => [['baggage' => 'key=%2'], false];
        yield 'null traceparent' => [['traceparent' => null], false];
        yield 'null tracestate' => [['tracestate' => null], false];
        yield 'null baggage' => [['baggage' => null], false];

        foreach ([
            'open empty identity' => [['name' => '', 'version' => '', 'extra' => StrictJson::fromObject()], true],
            'empty icon collection' => [['icons' => []], true],
            'empty optional icon strings' => [['icons' => [['src' => 'http://example.test/icon', 'mimeType' => '', 'sizes' => ['', 'any'], 'theme' => 'dark', 'extra' => []]]], true],
            'ipv6 icon' => [['icons' => [['src' => 'https://[::1]/icon']]], true],
            'ipvfuture icon' => [['icons' => [['src' => 'https://[vF.example]/icon']]], true],
            'non-http website' => [['websiteUrl' => 'urn:example:client'], true],
            'bracket website path' => [['websiteUrl' => 'https://example.test/path[bad]'], false],
            'non-http icon' => [['icons' => [['src' => 'urn:example:icon']]], false],
            'object icon collection' => [['icons' => StrictJson::fromObject()], false],
            'scalar icon' => [['icons' => [false]], false],
            'list icon' => [['icons' => [[]]], false],
            'missing icon source' => [['icons' => [StrictJson::fromObject()]], false],
            'missing data separator' => [['icons' => [['src' => 'data:image/png;base64']]], false],
            'empty data payload' => [['icons' => [['src' => 'data:image/png;base64,']]], false],
            'missing data encoding' => [['icons' => [['src' => 'data:image/png,aA==']]], false],
            'escaped media tokens and encoding case' => [['icons' => [['src' => 'DATA:%69mage/p%6Eg;BASE64,aA==']]], true],
            'escaped quoted parameter' => [['icons' => [['src' => 'data:image/png;name=%22a%5C%22b%22;base64,aA==']]], true],
            'escaped unquoted tspecial' => [['icons' => [['src' => 'data:image/png;name=a%2Fb;base64,aA==']]], true],
            'double escaped payload' => [['icons' => [['src' => 'data:image/png;base64,aA%253D%253D']]], false],
            'noncanonical payload padding' => [['icons' => [['src' => 'data:image/png;base64,aB==']]], false],
            'raw quoted parameter' => [['icons' => [['src' => 'data:image/png;name="a";base64,aA==']]], false],
            'non-image data' => [['icons' => [['src' => 'data:text/plain;base64,aA==']]], false],
        ] as $name => [$info, $accepted]) {
            yield $name => [[McpRequestMetadata::CLIENT_INFO_KEY => array_replace(['name' => 'client', 'version' => '1'], $info)], $accepted];
        }
    }

    public function test_that_metadata_keeps_an_immutable_implementation_snapshot(): void
    {
        $info = (object) ['name' => 'client', 'version' => '1', 'icons' => [(object) ['src' => 'https://example.test/icon']]];
        $input = StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => '',
            McpRequestMetadata::CLIENT_CAPABILITIES_KEY => StrictJson::fromObject(['roots' => ['listChanged' => 'open']]),
            McpRequestMetadata::CLIENT_INFO_KEY => $info,
            McpRequestMetadata::PROGRESS_TOKEN_KEY => 0,
        ]);
        $metadata = McpRequestMetadata::fromObject($input);
        $info->icons[0]->src = 'javascript:changed';
        $encoded = json_decode($metadata->clientInfo()?->toString(), flags: JSON_THROW_ON_ERROR);
        $encoded->icons[0]->src = 'javascript:changed-again';

        self::assertSame('', $metadata->protocolVersion());
        self::assertSame(0, $metadata->progressToken());
        self::assertSame('https://example.test/icon', $metadata->clientInfo()?->get('icons')[0]->get('src'));
        self::assertSame($input->get(McpRequestMetadata::CLIENT_INFO_KEY), $metadata->clientInfo());
    }

    public function test_that_grammar_rejections_keep_usable_ids_without_dispatch_or_diagnostics(): void
    {
        $capability = $this->mock(McpCapability::class);
        $capability->shouldReceive('methods')->andReturn(['example/echo']);
        $capability->shouldReceive('capabilities')->andReturn(['example' => []]);
        $capability->shouldReceive('mirrorDeclarations')->andReturn([]);
        $capability->shouldNotReceive('validate');
        $capability->shouldNotReceive('handle');
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldNotReceive('record');
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Example', '1'), [$capability]), diagnostics: $diagnostics);

        foreach ([0, '', 'request-é'] as $id) {
            foreach ([['traceparent' => 'invalid'], [McpRequestMetadata::CLIENT_INFO_KEY => ['name' => 'client', 'version' => '1', 'icons' => [['src' => 'javascript:invalid']]]]] as $fields) {
                $json = $this->request($id, $fields);
                try {
                    (new McpRequestDecoder())->decode($json);
                    self::fail('Malformed grammar was decoded.');
                } catch (McpProtocolException $failure) {
                    self::assertSame($id, $failure->requestId());
                    self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $failure->protocolError()->toArray());
                }
                self::assertSame(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32602, 'message' => 'Invalid params.']], $responder->respond($json)->toArray());
            }
        }
    }

    public function test_that_unexpected_failures_after_valid_grammar_remain_centrally_diagnosed_and_sanitized(): void
    {
        $failure = new RuntimeException('Private consumer diagnostic.');
        $capability = $this->mock(McpCapability::class);
        $capability->shouldReceive('methods')->andReturn(['example/echo']);
        $capability->shouldReceive('capabilities')->andReturn(['example' => []]);
        $capability->shouldReceive('mirrorDeclarations')->andReturn([]);
        $capability->shouldReceive('validate')->once();
        $capability->shouldReceive('handle')->once()->andThrow($failure);
        $diagnostics = $this->mock(McpDiagnostics::class);
        $diagnostics->shouldReceive('record')->with($failure)->once()->andThrow(new RuntimeException('Private sink diagnostic.'));
        $responder = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Example', '1'), [$capability]), diagnostics: $diagnostics);

        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 'request-é', 'error' => ['code' => -32603, 'message' => 'Internal error.']],
            $responder->respond($this->request('request-é', ['tracestate' => '', McpRequestMetadata::CLIENT_INFO_KEY => ['name' => '', 'version' => '', 'icons' => []]]))->toArray(),
        );
    }

    private function request(int|string $id, array $fields): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'example/echo',
            'params' => ['_meta' => [
                McpRequestMetadata::PROTOCOL_VERSION_KEY => McpResponder::PROTOCOL_VERSION,
                McpRequestMetadata::CLIENT_CAPABILITIES_KEY => (object) [],
                ...$fields,
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}
