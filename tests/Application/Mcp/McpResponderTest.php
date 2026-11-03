<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpMetadataAwareCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\FixtureCapability;
use Fight\Test\Common\Fixture\Mcp\ProtocolMetadataCases;
use Fight\Test\Common\Fixture\Mcp\ProtocolRequest;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(McpResponder::class)]
final class McpResponderTest extends UnitTestCase
{
    public function test_that_discovery_returns_only_registered_capabilities_and_server_identity_metadata(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        $response = $responder->respond(ProtocolRequest::json('server/discover', 7));

        self::assertEquals(
            [
                'jsonrpc' => '2.0',
                'id'      => 7,
                'result'  => [
                    'resultType'      => 'complete',
                    'supportedVersions' => ['2026-07-28'],
                    'capabilities'      => StrictJson::fromObject(['example' => ['enabled' => true]]),
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
            (new McpResponder($this->registry($emptyDefinition)))->respond(ProtocolRequest::json('server/discover', 2))->toJson(),
        );
    }

    public function test_that_valid_method_dispatches_once_after_outer_parameter_validation(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        $response = $responder->respond(ProtocolRequest::json('example/echo', 'request-9', ['message' => 'hello']));

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

    public function test_that_an_optional_capability_receives_request_local_central_metadata_without_changing_legacy_calls(): void
    {
        $capability = new class implements McpMetadataAwareCapability {
            /**
             * @var list<array<string, mixed>>
             */
            public array $seen = [];

            public function methods(): array
            {
                return ['example/echo'];
            }

            public function capabilities(): array
            {
                return ['example' => ['enabled' => true]];
            }

            public function mirrorDeclarations(): array
            {
                return [];
            }

            public function validate(McpRequest $request): void
            {
                if (!is_string($request->parameters()['message'] ?? null)) {
                    throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
                }
            }

            public function handle(McpRequest $request): McpResult
            {
                return McpResult::complete(['message' => $request->parameters()['message']]);
            }

            public function handleWithMetadata(McpRequest $request, array $metadata): McpResult
            {
                $this->seen[] = $metadata;

                return McpResult::complete([
                    'message' => $request->parameters()['message'],
                    '_meta' => ['example/metadata' => 'retained',
                        'io.modelcontextprotocol/serverInfo' => ['name' => 'not authoritative', 'version' => '0']],
                ]);
            }
        };
        $first = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('First', '1'), [$capability]));
        $second = new McpResponder(new McpCapabilityRegistry(new McpServerInfo('Second', '2'), [$capability]));
        $wire = ProtocolRequest::json('example/echo', 19, ['message' => 'hello']);

        self::assertSame('First', $first->respond($wire)->toArray()['result']['_meta']['io.modelcontextprotocol/serverInfo']['name']);
        $result = $second->respond($wire)->toArray()['result'];
        self::assertSame(['name' => 'Second', 'version' => '2'], $result['_meta']['io.modelcontextprotocol/serverInfo']);
        self::assertSame('retained', $result['_meta']['example/metadata']);
        self::assertSame([
            ['io.modelcontextprotocol/serverInfo' => ['name' => 'First', 'version' => '1']],
            ['io.modelcontextprotocol/serverInfo' => ['name' => 'Second', 'version' => '2']],
        ], $capability->seen);
        self::assertSame(
            ['resultType' => 'complete', 'message' => 'hello'],
            $capability->handle((new McpRequestDecoder())->decode($wire))->toArray(),
        );
        self::assertSame(-32602, $first->respond(ProtocolRequest::json('example/echo', 20))->errorCode());
        self::assertCount(2, $capability->seen);
        $legacy = new FixtureCapability();
        self::assertSame('hello', (new McpResponder($this->registry($legacy)))
            ->respond($wire)->toArray()['result']['message']);
        self::assertSame(1, $legacy->handleCalls);
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
            $responder->respond(ProtocolRequest::json('example/echo', 4, [], '2025-11-25'))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 4, 'error' => ['code' => -32601, 'message' => 'Method not found.']],
            $responder->respond(ProtocolRequest::json('resources/read', 4))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 4, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
            $responder->respond(ProtocolRequest::json('example/echo', 4, []))->toArray(),
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
            $responder->respond(ProtocolRequest::json('server/discover', 3, ['unexpected' => true]))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 3, 'error' => ['code' => -32603, 'message' => 'Internal error.']],
            $responder->respond(ProtocolRequest::json('example/echo', 3, ['message' => 'hello']))->toArray(),
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
                $responder->respond(ProtocolRequest::json('example/echo', 3, ['message' => 'hello']))->toArray(),
            );
            self::assertSame(1, $capability->validateCalls);
            self::assertSame(0, $capability->handleCalls);
        }
    }

    public function test_that_decoder_and_responder_accept_once_escaped_base64_data_uri_payloads(): void
    {
        foreach (ProtocolMetadataCases::escapedImageSources() as $iconSource) {
            $metadata = [
                'io.modelcontextprotocol/clientInfo' => (object) [
                    'name'    => 'client',
                    'version' => '1.0',
                    'icons'   => [(object) ['src' => $iconSource]],
                ],
            ];

            $request = (new McpRequestDecoder())->decode(ProtocolRequest::json('example/echo', 1, metadata: $metadata));
            self::assertSame($iconSource, $request->metadata()->clientInfo()?->get('icons')[0]->get('src'));

            $capability = new FixtureCapability();
            self::assertSame(
                [
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'result'  => [
                        'resultType' => 'complete',
                        'message'    => 'hello',
                        '_meta'      => [
                            'io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0'],
                        ],
                    ],
                ],
                (new McpResponder($this->registry($capability)))
                    ->respond(ProtocolRequest::json('example/echo', 1, ['message' => 'hello'], metadata: $metadata))
                    ->toArray(),
            );
            self::assertSame(1, $capability->validateCalls);
            self::assertSame(1, $capability->handleCalls);
        }
    }

    public function test_that_decoder_and_responder_accept_rfc2397_escaped_tspecial_and_quoted_parameters(): void
    {
        foreach (ProtocolMetadataCases::escapedImageParameters() as $iconSource) {
            $metadata = [
                'io.modelcontextprotocol/clientInfo' => (object) [
                    'name'    => 'client',
                    'version' => '1.0',
                    'icons'   => [(object) ['src' => $iconSource]],
                ],
            ];

            $request = (new McpRequestDecoder())->decode(ProtocolRequest::json('example/echo', 1, metadata: $metadata));
            self::assertSame($iconSource, $request->metadata()->clientInfo()?->get('icons')[0]->get('src'));

            $capability = new FixtureCapability();
            self::assertSame(
                [
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'result'  => [
                        'resultType' => 'complete',
                        'message'    => 'hello',
                        '_meta'      => [
                            'io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0'],
                        ],
                    ],
                ],
                (new McpResponder($this->registry($capability)))
                    ->respond(ProtocolRequest::json('example/echo', 1, ['message' => 'hello'], metadata: $metadata))
                    ->toArray(),
            );
            self::assertSame(1, $capability->validateCalls);
            self::assertSame(1, $capability->handleCalls);
        }
    }

    public function test_that_decimal_request_ids_and_progress_tokens_are_rejected_without_dispatch(): void
    {
        $capability = new FixtureCapability();
        $responder = new McpResponder($this->registry($capability));

        self::assertSame(
            ['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid request.']],
            $responder->respond(ProtocolRequest::json('example/echo', 7.5, ['message' => 'hello']))->toArray(),
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 3, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
            $responder->respond(ProtocolRequest::json('example/echo', 3, ['message' => 'hello'], metadata: ['progressToken' => 1.25]))->toArray(),
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
            $responder->respond(ProtocolRequest::json('example/echo', 8, [], ''))->toArray(),
        );
        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 9,
                'error'   => ['code' => -32601, 'message' => 'Method not found.'],
            ],
            $responder->respond(ProtocolRequest::json('', 9))->toArray(),
        );
        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'id'      => 10,
                'error'   => ['code' => -32601, 'message' => 'Method not found.'],
            ],
            $responder->respond(ProtocolRequest::json('   ', 10))->toArray(),
        );
        self::assertSame(0, $capability->validateCalls);
        self::assertSame(0, $capability->handleCalls);
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
            $responder->respond(ProtocolRequest::json(
                'example/echo',
                11,
                ['message' => 'hello'],
                metadata: ['' => 'allowed-by-schema'],
            ))->toArray(),
        );
        self::assertSame(1, $capability->validateCalls);
        self::assertSame(1, $capability->handleCalls);
    }

    public function test_that_invalid_defined_metadata_returns_invalid_params_without_capability_dispatch(): void
    {
        foreach (ProtocolMetadataCases::invalidFields() as $metadata) {
            $capability = new FixtureCapability();
            $responder = new McpResponder($this->registry($capability));

            self::assertSame(
                ['jsonrpc' => '2.0', 'id' => 6, 'error' => ['code' => -32602, 'message' => 'Invalid params.']],
                $responder->respond(ProtocolRequest::json('example/echo', 6, ['message' => 'hello'], metadata: $metadata))->toArray(),
            );
            self::assertSame(0, $capability->validateCalls);
            self::assertSame(0, $capability->handleCalls);
        }
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
            $responder->respond(ProtocolRequest::json('example/echo', 15, ['message' => 'hello']))->toArray(),
        );
    }

    public function test_that_responder_normalizes_object_result_metadata_and_rejects_invalid_metadata(): void
    {
        $objectMetadataCapability = new FixtureCapability(
            result: McpResult::complete([
                'message' => 'hello',
                '_meta'   => StrictJson::fromObject(['example/metadata' => ['retained' => true]]),
            ]),
        );

        self::assertEquals(
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
            json_decode((new McpResponder($this->registry($objectMetadataCapability)))
                ->respond(ProtocolRequest::json('example/echo', 16, ['message' => 'hello']))
                ->toJson(), true, 512, JSON_THROW_ON_ERROR),
        );

        foreach (['not-an-object', ['not-an-object']] as $metadata) {
            $invalidMetadataCapability = new FixtureCapability(
                result: McpResult::complete(['message' => 'hello', '_meta' => $metadata]),
            );

            self::assertSame(
                ['jsonrpc' => '2.0', 'id' => 17, 'error' => ['code' => -32603, 'message' => 'Internal error.']],
                (new McpResponder($this->registry($invalidMetadataCapability)))
                    ->respond(ProtocolRequest::json('example/echo', 17, ['message' => 'hello']))
                    ->toArray(),
            );
        }
    }

    public function test_that_registered_standard_capability_anchors_are_discoverable_and_dispatchable(): void
    {
        $capability = new FixtureCapability(
            methods: ['tools/call', 'tools/list'],
            capabilities: ['tools' => []],
        );
        $responder = new McpResponder($this->registry($capability));

        self::assertEquals(
            StrictJson::fromObject(['tools' => StrictJson::fromObject()]),
            $responder->respond(ProtocolRequest::json('server/discover', 13))->toArray()['result']['capabilities'],
        );
        self::assertSame(
            ['jsonrpc' => '2.0', 'id' => 14, 'result' => [
                'resultType' => 'complete',
                'message' => 'hello',
                '_meta' => ['io.modelcontextprotocol/serverInfo' => ['name' => 'Example', 'version' => '1.0.0']],
            ]],
            $responder->respond(ProtocolRequest::json('tools/list', 14, ['message' => 'hello']))->toArray(),
        );
    }

    private function registry(McpCapability $capability): McpCapabilityRegistry
    {
        return new McpCapabilityRegistry(new McpServerInfo('Example', '1.0.0'), [$capability]);
    }
}
