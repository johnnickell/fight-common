<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use stdClass;
use Throwable;

/**
 * Class McpResponder
 */
final readonly class McpResponder
{
    public const string PROTOCOL_VERSION = '2026-07-28';
    public const string DISCOVER_METHOD = 'server/discover';

    /**
     * Constructs McpResponder
     */
    public function __construct(
        private McpCapabilityRegistry $registry,
        private McpRequestDecoder $decoder = new McpRequestDecoder()
    ) {
    }

    /**
     * Creates a response for one encoded MCP request
     */
    public function respond(string $json): McpJsonResponse
    {
        $request = null;

        try {
            $request = $this->decoder->decode($json);
            if ($request->metadata()->protocolVersion() !== self::PROTOCOL_VERSION) {
                return McpJsonResponse::error(
                    $request->id(),
                    McpProtocolError::unsupportedProtocolVersion(
                        [self::PROTOCOL_VERSION],
                        $request->metadata()->protocolVersion()
                    )
                );
            }

            if ($request->method() === self::DISCOVER_METHOD) {
                if ($request->parameters() !== []) {
                    return McpJsonResponse::error($request->id(), McpProtocolError::invalidParams());
                }

                return McpJsonResponse::success($request->id(), $this->discoveryResult());
            }

            $capability = $this->registry->capabilityFor($request->method());
            if ($capability === null) {
                return McpJsonResponse::error($request->id(), McpProtocolError::methodNotFound());
            }

            $capability->validate($request);

            return McpJsonResponse::success($request->id(), $capability->handle($request));
        } catch (McpProtocolException $exception) {
            return McpJsonResponse::error($request?->id() ?? $exception->requestId(), $exception->protocolError());
        } catch (Throwable) {
            return McpJsonResponse::error($request?->id(), McpProtocolError::internalError());
        }
    }

    /**
     * Creates the built-in server discovery result
     */
    private function discoveryResult(): McpResult
    {
        return McpResult::complete([
            'supportedVersions' => [self::PROTOCOL_VERSION],
            'capabilities'      => $this->discoveryCapabilities(),
            'ttlMs'             => 0,
            'cacheScope'        => 'private',
            '_meta'             => [
                'io.modelcontextprotocol/serverInfo' => $this->registry->serverInfo()->toArray()
            ]
        ]);
    }

    /**
     * Creates JSON-object capability values for the discovery wire representation
     */
    private function discoveryCapabilities(): stdClass
    {
        $capabilities = [];
        foreach ($this->registry->advertisedCapabilities() as $name => $definition) {
            $capabilities[$name] = (object) $definition;
        }

        return (object) $capabilities;
    }
}
