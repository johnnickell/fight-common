<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Protocol;

use Fight\Common\Application\Mcp as ReleasedMcp;
use Fight\Common\Application\Mcp\Protocol\Exception\McpProtocolException;
use Fight\Common\Application\Mcp\Protocol\McpRequestDecoder;
use Fight\Common\Application\Mcp\Protocol\McpRequestLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\ProtocolRequest;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

#[CoversClass(ReleasedMcp\McpRequest::class)]
#[CoversClass(ReleasedMcp\McpRequestDecoder::class)]
#[CoversClass(ReleasedMcp\McpRequestLimits::class)]
final class McpRequestDecoderTest extends UnitTestCase
{
    public function test_that_default_and_explicit_limits_preserve_existing_decoder_calls(): void
    {
        $decoder = new McpRequestDecoder();
        self::assertSame(1048576, $decoder->limits()->maxBytes());
        self::assertSame(512, $decoder->limits()->maxDepth());
        self::assertSame('request-é', $decoder->decode($this->json())->id());
        $limits = new McpRequestLimits(maxBytes: 67108864, maxDepth: 1);
        self::assertSame($limits, (new McpRequestDecoder($limits))->limits());
    }

    #[DataProvider('invalidLimits')]
    public function test_that_invalid_limits_fail_at_composition(int $bytes, int $depth): void
    {
        $this->expectException(DomainException::class);
        new McpRequestLimits($bytes, $depth);
    }

    public static function invalidLimits(): iterable
    {
        yield [0, 512];
        yield [-1, 512];
        yield [67108865, 512];
        yield [PHP_INT_MAX, 512];
        yield [1024, 0];
        yield [1024, -1];
        yield [1024, 513];
        yield [1024, PHP_INT_MAX];
    }

    public function test_that_encoded_bytes_include_unicode_and_whitespace_without_recovering_an_oversize_id(): void
    {
        $json = $this->json();
        $decoder = new McpRequestDecoder(new McpRequestLimits(maxBytes: strlen($json)));
        self::assertSame('request-é', $decoder->decode($json)->id());
        self::assertSame('request-é', (new McpRequestDecoder(new McpRequestLimits(maxBytes: strlen($json) + 1)))->decode($json)->id());
        $this->assertRejection($decoder, $json.' ', -32600);
        $this->assertRejection(new McpRequestDecoder(new McpRequestLimits(maxBytes: strlen($json) - 1)), $json, -32600);
        // A malformed oversized input must fail the byte check before parsing as well.
        $this->assertRejection($decoder, str_repeat('{', strlen($json) + 1), -32600);
    }

    #[DataProvider('depthBoundaries')]
    public function test_that_parsing_enforces_the_configured_container_depth(int $depth, int $containers, string $leaf, bool $accepted): void
    {
        // Root and params contribute two containers; metadata's own branch uses four total.
        $json = $this->json(str_repeat('[', $containers).$leaf.str_repeat(']', $containers));
        $decoder = new McpRequestDecoder(new McpRequestLimits(maxDepth: $depth));
        if ($accepted) {
            self::assertSame('request-é', $decoder->decode($json)->id());
        } else {
            $this->assertRejection($decoder, $json, -32700);
        }
    }

    public static function depthBoundaries(): iterable
    {
        yield 'metadata fits exactly' => [5, 0, '0', true];
        yield 'metadata exceeds limit' => [4, 0, '0', false];
        yield 'configured nested scalar fits' => [8, 5, '0', true];
        yield 'configured nested scalar exceeds' => [8, 6, '0', false];
        yield 'empty object at boundary' => [8, 4, '{}', true];
        yield 'empty object exceeds boundary' => [8, 5, '{}', false];
        yield 'default 511 containers' => [512, 509, '0', true];
        yield 'default 512 containers' => [512, 510, '0', false];
    }

    #[DataProvider('malformedJson')]
    public function test_that_malformed_and_unrepresentable_json_keeps_safe_parse_errors(string $json): void
    {
        $this->assertRejection(new McpRequestDecoder(), $json, -32700);
    }

    public static function malformedJson(): iterable
    {
        yield [''];
        yield ['{"id":"secret",'];
        yield ["\xff"];
        yield ['{"\\u0000private":1}'];
        yield ['{"value":1e999}'];
    }

    public function test_that_decoder_retains_its_existing_codec_boundary_for_scalars_and_empty_containers(): void
    {
        $prefix = '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":'
            .'{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
            .'"io.modelcontextprotocol/clientCapabilities":{}},"value":';
        $decoder = new McpRequestDecoder();
        foreach (['null', '[]', '{}'] as $leaf) {
            $levels = $leaf === 'null' ? 509 : 508;
            $value = str_repeat('[', $levels).$leaf.str_repeat(']', $levels);
            $request = $decoder->decode($prefix.$value.'}}');
            self::assertSame(1, $request->id());
            self::assertSame($value, StrictJson::fromData($request->parameters()['value'], maxDepth: 511)->toString());
            try {
                $decoder->decode($prefix.'['.$value.']}}');
                self::fail('The protocol decoder accepted an extra nesting level.');
            } catch (McpProtocolException $exception) {
                self::assertSame(['code' => -32700, 'message' => 'Parse error.'], $exception->protocolError()->toArray());
            }
        }
    }

    public function test_that_decoder_keeps_only_bounded_request_metadata(): void
    {
        $request = (new McpRequestDecoder())->decode(ProtocolRequest::json(
            'example/echo',
            2,
            ['message' => 'hello'],
            metadata: [
                'io.modelcontextprotocol/protocolVersion'    => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object) [
                    'sampling' => new stdClass(),
                    'extensions' => (object) [
                        'com.example/extension' => new stdClass(),
                        'com.example/' => new stdClass(),
                    ],
                ],
                'io.modelcontextprotocol/clientInfo'         => (object) [
                    'name'        => 'client',
                    'version'     => '2.0',
                    'title'       => 'Example Client',
                    'description' => 'Example implementation',
                    'websiteUrl'  => 'https://example.test/client',
                    'icons'       => [(object) [
                        'src'      => 'https://example.test/icon.png',
                        'mimeType' => 'image/png',
                        'sizes'    => ['any'],
                        'theme'    => 'light',
                    ]],
                ],
                'progressToken'                              => 'progress-1',
                'com.example/opaque'                         => ['not' => 'retained'],
            ],
        ));

        self::assertSame(2, $request->id());
        self::assertSame('example/echo', $request->method());
        self::assertSame(['message' => 'hello'], $request->parameters());
        self::assertSame('2026-07-28', $request->metadata()->protocolVersion());
        self::assertEquals(
            StrictJson::fromObject([
                'sampling' => StrictJson::fromObject(),
                'extensions' => [
                    'com.example/extension' => StrictJson::fromObject(),
                    'com.example/' => StrictJson::fromObject(),
                ],
            ]),
            $request->metadata()->clientCapabilities(),
        );
        self::assertEquals(
            StrictJson::fromObject([
                'name'        => 'client',
                'version'     => '2.0',
                'title'       => 'Example Client',
                'description' => 'Example implementation',
                'websiteUrl'  => 'https://example.test/client',
                'icons'       => [[
                    'src'      => 'https://example.test/icon.png',
                    'mimeType' => 'image/png',
                    'sizes'    => ['any'],
                    'theme'    => 'light',
                ]],
            ]),
            $request->metadata()->clientInfo(),
        );
        self::assertSame('progress-1', $request->metadata()->progressToken());
    }

    public function test_that_metadata_rejects_missing_required_values(): void
    {
        $decoder = new McpRequestDecoder();

        $this->expectException(McpProtocolException::class);
        $decoder->decode('{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28"}}}');
    }

    public function test_that_decoder_accepts_ipvfuture_implementation_urls(): void
    {
        foreach (['https://[vF.example]/client', 'https://[VF.example]/client'] as $websiteUrl) {
            $request = (new McpRequestDecoder())->decode(ProtocolRequest::json(
                'example/echo',
                1,
                metadata: [
                    'io.modelcontextprotocol/clientInfo' => (object) [
                        'name'       => 'client',
                        'version'    => '1.0',
                        'websiteUrl' => $websiteUrl,
                        'icons'      => [(object) ['src' => 'data:image/png;base64,iVBORw0KGgo=']],
                    ],
                ],
            ));

            self::assertSame($websiteUrl, $request->metadata()->clientInfo()?->get('websiteUrl'));
        }
    }

    public function test_that_decoder_accepts_schema_permitted_http_icon_urls(): void
    {
        $request = (new McpRequestDecoder())->decode(ProtocolRequest::json(
            'example/echo',
            1,
            metadata: [
                'io.modelcontextprotocol/clientInfo' => (object) [
                    'name'    => 'client',
                    'version' => '1.0',
                    'icons'   => [(object) ['src' => 'http://example.test/icon.png']],
                ],
            ],
        ));

        self::assertSame(
            'http://example.test/icon.png',
            $request->metadata()->clientInfo()?->get('icons')[0]->get('src'),
        );
    }

    public function test_that_decoder_accepts_parameterized_image_data_icons(): void
    {
        $request = (new McpRequestDecoder())->decode(ProtocolRequest::json(
            'example/echo',
            1,
            metadata: [
                'io.modelcontextprotocol/clientInfo' => (object) [
                    'name'    => 'client',
                    'version' => '1.0',
                    'icons'   => [(object) ['src' => 'data:image/svg+xml;charset=utf-8;base64,PHN2Zy8+']],
                ],
            ],
        ));

        self::assertSame(
            'data:image/svg+xml;charset=utf-8;base64,PHN2Zy8+',
            $request->metadata()->clientInfo()?->get('icons')[0]->get('src'),
        );
    }

    public function test_that_decoder_accepts_rfc2397_token_and_percent_encoded_parameters(): void
    {
        foreach ([
            'data:image/svg+xml;profile=foo*bar;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=foo%7Cbar;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=foo%25bar;base64,PHN2Zy8+',
            'data:image/svg+xml;charset=utf%2D8;base64,PHN2Zy8+',
        ] as $iconSource) {
            $request = (new McpRequestDecoder())->decode(ProtocolRequest::json(
                'example/echo',
                1,
                metadata: [
                    'io.modelcontextprotocol/clientInfo' => (object) [
                        'name'    => 'client',
                        'version' => '1.0',
                        'icons'   => [(object) ['src' => $iconSource]],
                    ],
                ],
            ));

            self::assertSame($iconSource, $request->metadata()->clientInfo()?->get('icons')[0]->get('src'));
        }
    }

    public function test_that_decoder_requires_json_objects_and_well_formed_defined_metadata(): void
    {
        $decoder = new McpRequestDecoder();

        $emptyObjects = $decoder->decode(
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
            . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
            . '"io.modelcontextprotocol/clientCapabilities":{}}}}'
        );
        self::assertEquals(StrictJson::fromObject(), $emptyObjects->metadata()->clientCapabilities());

        foreach ([
            '[]',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":[]}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":[]}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":[]}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":[]}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":null}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":{"name":"client"}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":{"name":"client","version":"1.0","title":false}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":{"name":"client","version":"1.0","websiteUrl":"not-a-uri"}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":{"name":"client","version":"1.0","websiteUrl":"https://"}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":{"name":"client","version":"1.0","websiteUrl":"http://["}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":{"name":"client","version":"1.0","icons":{}}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},'
                . '"io.modelcontextprotocol/clientInfo":{"name":"client","version":"1.0","icons":[{"src":"https://"}]}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{"sampling":true}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{"sampling":{"tools":true}}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{"experimental":{"custom":true}}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{"extensions":{"unprefixed":{}}}}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},"progressToken":null}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},"progressToken":1.25}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},"io.modelcontextprotocol/logLevel":true}}}',
            '{"jsonrpc":"2.0","id":1,"method":"example/echo","params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{},"io.modelcontextprotocol/logLevel":"verbose"}}}',
            '{"jsonrpc":"2.0","id":1,"params":{"_meta":'
                . '{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
                . '"io.modelcontextprotocol/clientCapabilities":{}}}}',
            '{"jsonrpc":"2.0","method":"example/echo"}',
        ] as $invalidRequest) {
            try {
                $decoder->decode($invalidRequest);
                self::fail('Expected invalid request metadata to be rejected.');
            } catch (McpProtocolException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_decoder_accepts_schema_permitted_open_metadata_objects(): void
    {
        $request = (new McpRequestDecoder())->decode(ProtocolRequest::json(
            'example/echo',
            11,
            metadata: [
                'io.modelcontextprotocol/clientCapabilities' => (object) [
                    'roots' => (object) ['listChanged' => 'yes'],
                ],
                'io.modelcontextprotocol/clientInfo' => (object) ['name' => '', 'version' => ''],
            ],
        ));

        self::assertSame('', $request->metadata()->clientInfo()?->get('name'));
        self::assertSame('', $request->metadata()->clientInfo()?->get('version'));
        self::assertEquals(StrictJson::fromObject(['listChanged' => 'yes']), $request->metadata()->clientCapabilities()->get('roots'));
    }

    public function test_that_decoder_validates_present_w3c_trace_context_before_dispatch(): void
    {
        $decoder = new McpRequestDecoder();
        $valid = $decoder->decode(ProtocolRequest::json(
            'example/echo',
            12,
            metadata: [
                'traceparent' => '00-0af7651916cd43dd8448eb211c80319c-00f067aa0ba902b7-02',
                'tracestate'  => 'vendor=value, ,tenant@system=second',
                'baggage'     => 'key=value%20with%20space;property;other=%20',
            ],
        ));
        self::assertSame('2026-07-28', $valid->metadata()->protocolVersion());

        foreach ([
            ['traceparent' => '00-0af7651916cd43dd8448eb211c80319c-00f067aa0ba902b7-01-extra'],
            ['tracestate' => '1vendor=value'],
            ['tracestate' => 'tenant@1system=value'],
            ['tracestate' => 'tenant@@system=value'],
            ['tracestate' => 'vendor =value'],
            ['tracestate' => implode(',', array_fill(0, 33, 'vendor=value'))],
            ['baggage' => 'key="quoted"'],
            ['baggage' => 'key'],
            ['baggage' => 'key=%ZZ'],
            ['baggage' => 'key=%'],
            ['baggage' => implode(',', array_fill(0, 181, 'key=value'))],
        ] as $metadata) {
            try {
                (new McpRequestDecoder())->decode(ProtocolRequest::json('example/echo', 12, metadata: $metadata));
                self::fail('Expected malformed W3C trace context to be rejected.');
            } catch (McpProtocolException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_invalid_jsonrpc_versions_keep_a_usable_request_id(): void
    {
        try {
            (new McpRequestDecoder())->decode('{"jsonrpc":"1.0","id":4,"method":"example/echo"}');
            self::fail('An invalid JSON-RPC version was decoded.');
        } catch (McpProtocolException $failure) {
            self::assertSame(4, $failure->requestId());
            self::assertSame(['code' => -32600, 'message' => 'Invalid request.'], $failure->protocolError()->toArray());
        }
    }

    private function assertRejection(McpRequestDecoder $decoder, string $json, int $code): void
    {
        try {
            $decoder->decode($json);
            self::fail('Unsafe input was decoded.');
        } catch (McpProtocolException $failure) {
            self::assertNull($failure->requestId());
            self::assertSame($code, $failure->protocolError()->toArray()['code']);
        }
    }

    private function json(string $nested = '0'): string
    {
        return '{"jsonrpc":"2.0","id":"request-é","method":"example/echo","params":{"nested":'.$nested.',"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';
    }
}
