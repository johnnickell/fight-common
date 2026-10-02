<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

/**
 * Interface McpMetadataAwareCapability
 *
 * Optional capability contract for validating complete results against the responder's actual central metadata.
 * Metadata is supplied per request, not bound to the capability or used as an authorization context.
 */
interface McpMetadataAwareCapability extends McpCapability
{
    /**
     * Handles one validated request with the responder's current central result metadata
     *
     * Direct handle() calls retain the base capability's behavior without configured server identity.
     * The responder owns the final metadata merge and its reserved server identity key.
     *
     * @phpstan-param array<string, mixed> $metadata
     */
    public function handleWithMetadata(McpRequest $request, array $metadata): McpResult;
}
