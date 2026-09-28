<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Application\Mcp\Tool\McpToolExecution;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
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
        private McpRequestDecoder $decoder = new McpRequestDecoder(),
        private ?McpDiagnostics $diagnostics = null
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

            return $this->dispatch($request);
        } catch (McpProtocolException $exception) {
            return McpJsonResponse::error($request?->id() ?? $exception->requestId(), $exception->protocolError());
        } catch (Throwable $failure) {
            try {
                $this->diagnostics?->record($failure);
            } catch (Throwable) {
                // A broken diagnostic sink must not expose its own failure to the client.
            }

            return McpJsonResponse::error($request?->id(), McpProtocolError::internalError());
        }
    }

    /**
     * Dispatches an already-decoded request after caller-owned safeguards
     *
     * Propagates capability failures to the transport's diagnostic and error boundary.
     * The optional execution is an internal request binding, not a consumer authorization or transport API.
     */
    public function dispatch(McpRequest $request, ?McpToolExecution $execution = null): McpJsonResponse
    {
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

        if ($execution !== null && $capability instanceof McpToolInvocation) {
            $capability = $capability->withExecution($execution);
        }

        $capability->validate($request);

        return $this->success($request->id(), $capability->handle($request));
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
        if ($metadata instanceof StrictJson && $metadata->isObject()) {
            $metadata = $metadata->properties();
        }

        if (!is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            throw new DomainException('An MCP result metadata value must be a JSON object.');
        }

        $data['_meta'] = [
            ...$metadata,
            'io.modelcontextprotocol/serverInfo' => $this->registry->serverInfo()->toArray()
        ];

        return McpJsonResponse::success($id, $result->withMetadata($data['_meta']));
    }

    /**
     * Creates JSON-object capability values for the discovery wire representation
     */
    private function discoveryCapabilities(): StrictJson
    {
        $capabilities = [];
        foreach ($this->registry->advertisedCapabilities() as $name => $definition) {
            $capabilities[$name] = StrictJson::fromObject($definition, maxDepth: 511);
        }

        return StrictJson::fromObject($capabilities, maxDepth: 511);
    }
}
