<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;
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

                return $this->success($request->id(), $this->discoveryResult());
            }

            $capability = $this->registry->capabilityFor($request->method());
            if ($capability === null) {
                return McpJsonResponse::error($request->id(), McpProtocolError::methodNotFound());
            }

            $capability->validate($request);

            return $this->success($request->id(), $capability->handle($request));
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
            'cacheScope'        => 'private'
        ]);
    }

    /**
     * Creates a successful response with configured server identity metadata
     */
    private function success(int|string $id, McpResult $result): McpJsonResponse
    {
        $data = $result->toArray();
        $metadata = $data['_meta'] ?? [];
        if ($metadata instanceof stdClass) {
            $metadata = get_object_vars($metadata);
        }

        if (!is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            throw new DomainException('An MCP result metadata value must be a JSON object.');
        }

        $data['_meta'] = [
            ...$metadata,
            'io.modelcontextprotocol/serverInfo' => $this->registry->serverInfo()->toArray()
        ];
        unset($data['resultType']);

        return McpJsonResponse::success($id, McpResult::complete($data));
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
