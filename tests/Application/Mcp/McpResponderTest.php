<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpRequestMetadata;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(McpCapabilityRegistry::class)]
#[CoversClass(McpJsonResponse::class)]
#[CoversClass(McpMirrorDeclaration::class)]
#[CoversClass(McpProtocolError::class)]
#[CoversClass(McpProtocolException::class)]
#[CoversClass(McpRequest::class)]
#[CoversClass(McpRequestDecoder::class)]
#[CoversClass(McpRequestMetadata::class)]
#[CoversClass(McpResponder::class)]
#[CoversClass(McpResult::class)]
#[CoversClass(McpServerInfo::class)]
final class McpResponderTest extends UnitTestCase
{
    public function test_that_discovery_returns_only_registered_capabilities_and_server_identity_metadata(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        $response = $responder->respond($this->request('server/discover', 7));

        self::assertEquals(
            [
                'jsonrpc' => '2.0',
                'id'      => 7,
                'result'  => [
                    'resultType'      => 'complete',
                    'supportedVersions' => ['2026-07-28'],
                    'capabilities'      => (object) ['example' => (object) ['enabled' => true]],
                    'ttlMs'             => 0,
                    'cacheScope'        => 'private',
                    '_meta'             => [
                        'io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0'],
                    ],
                ],
            ],
            $response->toArray(),
        );
        self::assertSame(0, $capability->handleCalls);
        self::assertSame(0, $capability->validateCalls);
    }

    public function test_that_discovery_serializes_empty_capability_maps_and_definitions_as_json_objects(): void
    {
        $emptyDefinition = new FixtureCapability(definition: []);

        self::assertStringContainsString(
            '"capabilities":{"example":{}}',
            (new McpResponder($this->registry($emptyDefinition)))->respond($this->request('server/discover', 2))->toJson(),
        );
    }

    public function test_that_valid_method_dispatches_once_after_outer_parameter_validation(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        $response = $responder->respond($this->request('example/echo', 'request-9', ['message' => 'hello']));

        self::assertEquals(
            [
                'jsonrpc' => '2.0',
                'id'      => 'request-9',
                'result'  => [
                    'resultType' => 'complete',
                    'message'    => 'hello',
                    '_meta'      => [
                        'io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0'],
                    ],
                ],
            ],
            $response->toArray(),
        );
        self::assertSame(1, $capability->validateCalls);
        self::assertSame(1, $capability->handleCalls);
    }

    public function test_that_protocol_failures_are_centralized_without_capability_dispatch(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        self::assertSame(
            ['jsonrpc' => '2.0', 'error' => ['code' => -32700, 'message' => 'Parse error.']],
            $responder->respond('{')->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 4, 'error' => ['code' => -32600, 'message' => 'Invalid request.']],
            $responder->respond('{"jsonrpc":"1.0","id":4,"method":"example/echo"}')->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 4, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
            $responder->respond('{"jsonrpc":"2.0","id":4,"method":"example/echo","params":[]}')->toArray(),
        );
        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 4,
                'error'   => [
                    'code'    => -32022,
                    'message' => 'Unsupported protocol version.',
                    'data'    => ['supported' => ['2026-07-28'], 'requested' => '2025-11-25'],
                ],
            ],
            $responder->respond($this->request('example/echo', 4, [], '2025-11-25'))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 4, 'error' => ['code' => -32601, 'message' => 'Method not found.']],
            $responder->respond($this->request('resources/read', 4))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 4, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
            $responder->respond($this->request('example/echo', 4, []))->toArray(),
        );
        self::assertSame(0, $capability->handleCalls);
        self::assertSame(1, $capability->validateCalls);
    }

    public function test_that_invalid_discovery_parameters_and_capability_failures_preserve_request_identity(): void
    {
        $failing = new FixtureCapability(throwOnHandle: true);
        $responder = new McpResponder($this->registry($failing));

        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 3, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
            $responder->respond($this->request('server/discover', 3, ['unexpected' => true]))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 3, 'error' => ['code' => -32603, 'message' => 'Internal error.']],
            $responder->respond($this->request('example/echo', 3, ['message' => 'hello']))->toArray(),
        );
        self::assertSame(1, $failing->validateCalls);
        self::assertSame(1, $failing->handleCalls);
    }

    public function test_that_capability_protocol_exceptions_cannot_replace_the_decoded_request_identity(): void
    {
        foreach ([999, null] as $exceptionRequestId) {
            $capability = new FixtureCapability(
                throwProtocolException: true,
                exceptionRequestId: $exceptionRequestId,
            );
            $responder = new McpResponder($this->registry($capability));

            self::assertSame(
                ['jsonrpc' => '2.0', 'id' => 3, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
                $responder->respond($this->request('example/echo', 3, ['message' => 'hello']))->toArray(),
            );
            self::assertSame(1, $capability->validateCalls);
            self::assertSame(0, $capability->handleCalls);
        }
    }

    public function test_that_decoder_keeps_only_bounded_request_metadata(): void
    {
        $request = (new McpRequestDecoder())->decode($this->request(
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
            (object) [
                'sampling' => new stdClass(),
                'extensions' => (object) [
                    'com.example/extension' => new stdClass(),
                    'com.example/' => new stdClass(),
                ],
            ],
            $request->metadata()->clientCapabilities(),
        );
        self::assertEquals(
            (object) [
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
            $request = (new McpRequestDecoder())->decode($this->request(
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

            self::assertSame($websiteUrl, $request->metadata()->clientInfo()?->websiteUrl);
        }
    }

    public function test_that_decoder_accepts_schema_permitted_http_icon_urls(): void
    {
        $request = (new McpRequestDecoder())->decode($this->request(
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
            $request->metadata()->clientInfo()?->icons[0]->src,
        );
    }

    public function test_that_decoder_accepts_parameterized_image_data_icons(): void
    {
        $request = (new McpRequestDecoder())->decode($this->request(
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
            $request->metadata()->clientInfo()?->icons[0]->src,
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
            $request = (new McpRequestDecoder())->decode($this->request(
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

            self::assertSame($iconSource, $request->metadata()->clientInfo()?->icons[0]->src);
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
        self::assertEquals(new stdClass(), $emptyObjects->metadata()->clientCapabilities());

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

    public function test_that_decimal_request_ids_and_progress_tokens_are_rejected_without_dispatch(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        self::assertSame(
            ['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid request.']],
            $responder->respond($this->request('example/echo', 7.5, ['message' => 'hello']))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 3, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
            $responder->respond($this->request('example/echo', 3, ['message' => 'hello'], metadata: ['progressToken' => 1.25]))->toArray(),
        );
        self::assertSame(0, $capability->validateCalls);
        self::assertSame(0, $capability->handleCalls);
    }

    public function test_that_schema_permitted_metadata_boundaries_reach_the_protocol_layer(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 8,
                'error'   => [
                    'code'    => -32022,
                    'message' => 'Unsupported protocol version.',
                    'data'    => ['supported' => ['2026-07-28'], 'requested' => ''],
                ],
            ],
            $responder->respond($this->request('example/echo', 8, [], ''))->toArray(),
        );
        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 9,
                'error'   => ['code' => -32601, 'message' => 'Method not found.'],
            ],
            $responder->respond($this->request('', 9))->toArray(),
        );
        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 10,
                'error'   => ['code' => -32601, 'message' => 'Method not found.'],
            ],
            $responder->respond($this->request('   ', 10))->toArray(),
        );
        self::assertSame(0, $capability->validateCalls);
        self::assertSame(0, $capability->handleCalls);
    }

    public function test_that_decoder_accepts_schema_permitted_open_metadata_objects(): void
    {
        $request = (new McpRequestDecoder())->decode($this->request(
            'example/echo',
            11,
            metadata: [
                'io.modelcontextprotocol/clientCapabilities' => (object) [
                    'roots' => (object) ['listChanged' => 'yes'],
                ],
                'io.modelcontextprotocol/clientInfo' => (object) ['name' => '', 'version' => ''],
            ],
        ));

        self::assertSame('', $request->metadata()->clientInfo()?->name);
        self::assertSame('', $request->metadata()->clientInfo()?->version);
        self::assertEquals((object) ['listChanged' => 'yes'], $request->metadata()->clientCapabilities()->roots);
    }

    public function test_that_decoder_accepts_an_empty_opaque_metadata_key_without_skipping_dispatch(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 11,
                'result'  => [
                    'resultType' => 'complete',
                    'message'    => 'hello',
                    '_meta'      => ['io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0']],
                ],
            ],
            $responder->respond($this->request(
                'example/echo',
                11,
                ['message' => 'hello'],
                metadata: ['' => 'allowed-by-schema'],
            ))->toArray(),
        );
        self::assertSame(1, $capability->validateCalls);
        self::assertSame(1, $capability->handleCalls);
    }

    public function test_that_decoder_validates_present_w3c_trace_context_before_dispatch(): void
    {
        $decoder = new McpRequestDecoder();
        $valid = $decoder->decode($this->request(
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
                (new McpRequestDecoder())->decode($this->request('example/echo', 12, metadata: $metadata));
                self::fail('Expected malformed W3C trace context to be rejected.');
            } catch (McpProtocolException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_invalid_defined_metadata_returns_invalid_params_without_capability_dispatch(): void
    {
        foreach ([
            ['io.modelcontextprotocol/clientCapabilities' => ['sampling' => true]],
            ['io.modelcontextprotocol/clientCapabilities' => ['roots' => []]],
            ['io.modelcontextprotocol/clientInfo' => null],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'title' => false]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'https://']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'http:icon']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'https:icon']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'http:/icon']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'https://example.test/%ZZ']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn:exa|mple']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn://example.test/path[']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn://[::::]/path']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'https://[vF.]/path']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn:example#one#two']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn:example[path]']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'urn:icon', 'mimeType' => false]]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'urn:icon', 'theme' => 'blue']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/png;base64,iVBORw0KGgo=', 'mimeType' => false]]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/png;base64,iVBORw0KGgo=', 'theme' => 'blue']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=utf:8;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo|bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo^bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo#bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo`bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/p%2Fng;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;char%3Dset=utf-8;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=%ZZ;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=utf-8;base64,PHN2Zy8@']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'urn:icon', 'sizes' => ['any', false]]]]],
            ['io.modelcontextprotocol/clientCapabilities' => ['extensions' => ['unprefixed' => new stdClass()]]],
            ['progressToken' => null],
            ['io.modelcontextprotocol/logLevel' => true],
            ['invalid/key/' => 'value'],
            ['traceparent' => true],
            ['tracestate' => true],
            ['baggage' => true],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'javascript:alert(1)']]]],
        ] as $metadata) {
            $capability = new FixtureCapability();
            $responder = new McpResponder($this->registry($capability));

            self::assertSame(
                ['jsonrpc' => '2.0', 'id' => 6, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
                $responder->respond($this->request('example/echo', 6, ['message' => 'hello'], metadata: $metadata))->toArray(),
            );
            self::assertSame(0, $capability->validateCalls);
            self::assertSame(0, $capability->handleCalls);
        }
    }

    public function test_that_server_info_accepts_schema_permitted_empty_identity_strings(): void
    {
        $serverInfo = new McpServerInfo('', '');

        self::assertSame('', $serverInfo->name());
        self::assertSame('', $serverInfo->version());
    }

    public function test_that_registry_rejects_missing_identity_duplicate_ownership_and_contradictory_metadata(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(null, []);
    }

    public function test_that_registry_rejects_duplicate_method_ownership(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [new FixtureCapability(), new FixtureCapability()]);
    }

    public function test_that_registry_rejects_contradictory_capability_metadata(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(), new FixtureCapability(method: 'example/second', definition: ['enabled' => false])],
        );
    }

    public function test_that_registry_rejects_capabilities_without_a_handler_method(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(methods: [])],
        );
    }

    public function test_that_registry_rejects_dispatchable_capabilities_without_advertisement(): void
    {
        $this->expectException(DomainException::class);

        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(capabilities: [])],
        );
    }

    public function test_that_registry_rejects_nonempty_list_capability_definitions(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(definition: ['not', 'an', 'object'])],
        );
    }

    public function test_that_registry_requires_truthful_standard_capability_declarations(): void
    {
        $registry = new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [new FixtureCapability(
            methods: ['tools/list'],
            capabilities: ['tools' => []],
        )]);
        self::assertInstanceOf(McpCapability::class, $registry->capabilityFor('tools/list'));

        foreach ([
            new FixtureCapability(methods: ['tools/list'], capabilities: ['prompts' => []]),
            new FixtureCapability(methods: ['tools/list'], capabilities: ['tools' => ['listChanged' => 'yes']]),
            new FixtureCapability(methods: ['example/echo'], capabilities: ['extensions' => ['unprefixed' => []]]),
        ] as $capability) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
                self::fail('Expected invalid MCP capability composition to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

        $extensionRegistry = new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(
                methods: ['rpc.example'],
                capabilities: ['extensions' => ['com.example/feature' => new stdClass()]],
            )],
        );
        self::assertInstanceOf(McpCapability::class, $extensionRegistry->capabilityFor('rpc.example'));

        foreach ([
            new FixtureCapability(methods: ['example/echo'], capabilities: ['tools' => []]),
            new FixtureCapability(methods: ['tools/list'], capabilities: ['tools' => [], 'prompts' => []]),
        ] as $capability) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
                self::fail('Expected unimplemented standard discovery capability to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

    }

    public function test_that_registry_requires_standard_capability_mandatory_anchor_methods(): void
    {
        $capability = new FixtureCapability(
            methods: ['tools/call', 'tools/list'],
            capabilities: ['tools' => []],
        );
        $responder = new McpResponder($this->registry($capability));

        self::assertEquals(
            (object) ['tools' => new stdClass()],
            $responder->respond($this->request('server/discover', 13))->toArray()['result']['capabilities'],
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 14, 'result' => [
                'resultType' => 'complete',
                'message' => 'hello',
                '_meta' => ['io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0']],
            ]],
            $responder->respond($this->request('tools/list', 14, ['message' => 'hello']))->toArray(),
        );

        foreach ([
            ['prompts/get', 'prompts'],
            ['resources/read', 'resources'],
            ['resources/templates/list', 'resources'],
            ['tools/call', 'tools'],
        ] as [$method, $capabilityName]) {
            try {
                new McpCapabilityRegistry(
                    new McpServerInfo('Example', '1.0.0'),
                    [new FixtureCapability(methods: [$method], capabilities: [$capabilityName => []])],
                );
                self::fail('Expected a standard capability without its mandatory anchor to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

    }

    public function test_that_registry_rejects_subscription_capability_flags_without_a_subscription_contract(): void
    {
        foreach ([
            ['prompts/list', 'prompts', ['listChanged' => true]],
            ['resources/list', 'resources', ['subscribe' => true]],
            ['resources/list', 'resources', ['listChanged' => true]],
            ['tools/list', 'tools', ['listChanged' => true]],
        ] as [$method, $capabilityName, $definition]) {
            try {
                new McpCapabilityRegistry(
                    new McpServerInfo('Example', '1.0.0'),
                    [new FixtureCapability(methods: [$method], capabilities: [$capabilityName => $definition])],
                );
                self::fail('Expected an unsupported subscription capability to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

        try {
            new McpCapabilityRegistry(
                new McpServerInfo('Example', '1.0.0'),
                [new FixtureCapability(
                    methods: ['tools/list', 'subscriptions/listen'],
                    capabilities: ['tools' => ['listChanged' => true]],
                )],
            );
            self::fail('Expected a generic listener to be rejected without a subscription contract.');
        } catch (DomainException) {
            self::addToAssertionCount(1);
        }
    }

    public function test_that_registry_validates_all_defined_standard_capability_shapes(): void
    {
        $registry = new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(
                methods: ['completion/complete', 'resources/list', 'rpc.example', 'subscriptions/listen'],
                capabilities: [
                    'completions' => ['values' => [null, true, 1, 'one', 1.0, (object) ['nested' => []]]],
                    'logging' => [],
                    'resources' => ['subscribe' => false, 'listChanged' => false],
                    'experimental' => ['feature' => (object) ['nested' => []]],
                    'extensions' => ['com.example/feature' => ['nested' => []]],
                ],
            )],
        );

        self::assertInstanceOf(McpCapability::class, $registry->capabilityFor('completion/complete'));
        self::assertInstanceOf(McpCapability::class, $registry->capabilityFor('resources/list'));

        foreach ([
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['completions' => ['list']]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['experimental' => ['feature' => []]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['logging' => ['value' => NAN]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['logging' => ['nested' => [NAN]]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ['logging' => ['nested' => (object) ['value' => NAN]]]),
            new FixtureCapability(methods: ['rpc.example'], capabilities: ["\xB1" => []]),
        ] as $capability) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
                self::fail('Expected invalid standard capability body to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_registry_rejects_non_json_custom_capability_definitions(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            foreach ([
                ['nested' => ['number' => NAN]],
                ['nested' => ['resource' => $resource]],
                ['nested' => ['object' => new \DateTimeImmutable()]],
                ["\xB1" => 'invalid UTF-8 key'],
                ['value' => "\xB1"],
            ] as $definition) {
                try {
                    new McpCapabilityRegistry(
                        new McpServerInfo('Example', '1.0.0'),
                        [new FixtureCapability(definition: $definition)],
                    );
                    self::fail('Expected a custom capability with a non-JSON value to be rejected.');
                } catch (DomainException) {
                    self::addToAssertionCount(1);
                }
            }
        } finally {
            fclose($resource);
        }

        $registry = new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(definition: [
                'nested' => [
                    'null'   => null,
                    'list'   => [true, 1, 'value', 1.5],
                    'object' => (object) ['value' => false],
                ],
            ])],
        );

        self::assertEquals(
            [
                'example' => [
                    'nested' => [
                        'null'   => null,
                        'list'   => [true, 1, 'value', 1.5],
                        'object' => (object) ['value' => false],
                    ],
                ],
            ],
            $registry->advertisedCapabilities(),
        );
    }

    public function test_that_configured_server_info_overrides_only_its_result_metadata_key(): void
    {
        $capability = new FixtureCapability(
            result: McpResult::complete([
                'message' => 'hello',
                '_meta' => [
                    'example/metadata' => ['retained' => true],
                    'io.modelcontextprotocol/serverInfo' => ['name' => 'Incorrect', 'version' => '0.0.0'],
                ],
            ]),
        );
        $responder = new McpResponder($this->registry($capability));

        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 15,
                'result'  => [
                    'resultType' => 'complete',
                    'message' => 'hello',
                    '_meta' => [
                        'example/metadata' => ['retained' => true],
                        'io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0'],
                    ],
                ],
            ],
            $responder->respond($this->request('example/echo', 15, ['message' => 'hello']))->toArray(),
        );
    }

    public function test_that_server_info_rejects_non_json_unicode_strings(): void
    {
        foreach (["\xB1", "\xB1\x31"] as $value) {
            try {
                new McpServerInfo($value, '1.0.0');
                self::fail('Expected a malformed UTF-8 server identity to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

        $serverInfo = new McpServerInfo('Exämple', '1.0.0');
        self::assertSame('Exämple', $serverInfo->name());
    }

    public function test_that_responder_normalizes_object_result_metadata_and_rejects_invalid_metadata(): void
    {
        $objectMetadataCapability = new FixtureCapability(
            result: McpResult::complete([
                'message' => 'hello',
                '_meta'   => (object) ['example/metadata' => ['retained' => true]],
            ]),
        );

        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 16,
                'result'  => [
                    'resultType' => 'complete',
                    'message'    => 'hello',
                    '_meta'      => [
                        'example/metadata' => ['retained' => true],
                        'io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0'],
                    ],
                ],
            ],
            (new McpResponder($this->registry($objectMetadataCapability)))
                ->respond($this->request('example/echo', 16, ['message' => 'hello']))
                ->toArray(),
        );

        foreach (['not-an-object', ['not-an-object']] as $metadata) {
            $invalidMetadataCapability = new FixtureCapability(
                result: McpResult::complete(['message' => 'hello', '_meta' => $metadata]),
            );

            self::assertSame(
                ['jsonrpc' => '2.0', 'id' => 17, 'error' => ['code' => -32603, 'message' => 'Internal error.']],
                (new McpResponder($this->registry($invalidMetadataCapability)))
                    ->respond($this->request('example/echo', 17, ['message' => 'hello']))
                    ->toArray(),
            );
        }
    }

    public function test_that_registry_rejects_invalid_capability_method_and_mirror_values(): void
    {
        foreach ([
            [new stdClass()],
            [new FixtureCapability(methods: [''])],
            [new FixtureCapability(methods: ['server/discover'])],
            [new FixtureCapability(mirrors: [new stdClass()])],
        ] as $invalidCapabilities) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), $invalidCapabilities);
                self::fail('Expected invalid capability registration to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_registry_and_declaration_accessors_expose_registered_values(): void
    {
        $serverInfo = new McpServerInfo('Example', '1.0.0');
        $declaration = new McpMirrorDeclaration('example/echo', ['region'], 'Region');
        $registry = new McpCapabilityRegistry(
            $serverInfo,
            [new FixtureCapability(mirrors: [$declaration])],
        );

        self::assertSame('Example', $serverInfo->name());
        self::assertSame('1.0.0', $serverInfo->version());
        self::assertSame(['region'], $declaration->parameterPath());
        self::assertSame([$declaration], $registry->mirrorDeclarationsFor('example/echo'));
        self::assertSame([], $registry->mirrorDeclarationsFor('unknown/method'));
    }

    public function test_that_mirror_declaration_rejects_empty_methods_and_invalid_headers(): void
    {
        foreach ([
            ['', ['region'], 'Region'],
            ['example/echo', ['named' => 'region'], 'Region'],
            ['example/echo', ['region'], 'invalid header'],
        ] as [$method, $path, $header]) {
            try {
                new McpMirrorDeclaration($method, $path, $header);
                self::fail('Expected invalid mirror declaration to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_registry_rejects_invalid_and_duplicate_mirror_declarations(): void
    {
        $this->expectException(DomainException::class);
        new McpMirrorDeclaration('example/echo', [], 'Region');
    }

    public function test_that_registry_rejects_mirrors_for_unowned_methods_and_duplicate_headers(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(mirrors: [new McpMirrorDeclaration('other/method', ['region'], 'Region')])],
        );
    }

    public function test_that_registry_rejects_case_insensitive_duplicate_mirror_headers(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(
            new McpServerInfo('Example', '1.0.0'),
            [new FixtureCapability(mirrors: [
                new McpMirrorDeclaration('example/echo', ['region'], 'Region'),
                new McpMirrorDeclaration('example/echo', ['zone'], 'region'),
            ])],
        );
    }

    public function test_that_response_and_result_validate_and_encode_semantic_data(): void
    {
        self::assertSame(
            '{"jsonrpc":"2.0","id":"id-1","result":{"resultType":"complete","message":"hello"}}',
            McpJsonResponse::success('id-1', McpResult::complete(['message' => 'hello']))->toJson(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid request.']],
            McpJsonResponse::error(null, McpProtocolError::invalidRequest())->toArray(),
        );

        $this->expectException(DomainException::class);
        McpResult::complete(['resultType' => 'input_required']);
    }

    public function test_that_response_encoding_surfaces_json_errors(): void
    {
        $this->expectException(JsonException::class);
        McpJsonResponse::success(1, McpResult::complete(['value' => NAN]))->toJson();
    }

    private function registry(McpCapability $capability): McpCapabilityRegistry
    {
        return new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
    }

    /**
     * @param integer|float|string             $id
     * @param array<string, mixed>             $parameters
     * @param array<string, mixed>|null        $metadata
     */
    private function request(
        string $method,
        int|float|string $id,
        array $parameters = [],
        string $protocolVersion = '2026-07-28',
        ?array $metadata = null,
    ): string {
        $metadata = array_replace([
            'io.modelcontextprotocol/protocolVersion'    => $protocolVersion,
            'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
        ], $metadata ?? []);

        return json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'method'  => $method,
            'params'  => [...$parameters, '_meta' => $metadata],
        ], JSON_THROW_ON_ERROR);
    }
}

final class FixtureCapability implements McpCapability
{
    public int $validateCalls = 0;
    public int $handleCalls = 0;

    /**
     * @param array<string, mixed>           $definition
     * @param list<McpMirrorDeclaration>     $mirrors
     */
    public function __construct(
        private readonly string $method = 'example/echo',
        private readonly array $definition = ['enabled' => true],
        private readonly array $mirrors = [],
        private readonly bool $throwOnHandle = false,
        private readonly ?array $methods = null,
        private readonly ?array $capabilities = null,
        private readonly bool $throwProtocolException = false,
        private readonly int|string|null $exceptionRequestId = null,
        private readonly ?McpResult $result = null,
    ) {
    }

    public function methods(): array
    {
        return $this->methods ?? [$this->method];
    }

    public function capabilities(): array
    {
        return $this->capabilities ?? ['example' => $this->definition];
    }

    public function mirrorDeclarations(): array
    {
        return $this->mirrors;
    }

    public function validate(McpRequest $request): void
    {
        ++$this->validateCalls;
        if ($this->throwProtocolException) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $this->exceptionRequestId);
        }

        if (!isset($request->parameters()['message']) || !is_string($request->parameters()['message'])) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }
    }

    public function handle(McpRequest $request): McpResult
    {
        ++$this->handleCalls;
        if ($this->throwOnHandle) {
            throw new \RuntimeException('Unexpected fixture failure.');
        }

        return $this->result ?? McpResult::complete(['message' => $request->parameters()['message']]);
    }
}
