<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

/**
 * Interface McpCapability
 */
interface McpCapability
{
    /**
     * Returns the MCP methods owned by this capability
     *
     * @return list<string>
     */
    public function methods(): array;

    /**
     * Returns the advertised capability definitions owned by this capability
     *
     * @return array<string, array<string, mixed>>
     */
    public function capabilities(): array;

    /**
     * Returns the custom parameter mirror declarations owned by this capability
     *
     * @return list<McpMirrorDeclaration>
     */
    public function mirrorDeclarations(): array;

    /**
     * Validates the outer parameters for a selected method
     *
     * Throw McpProtocolException with McpProtocolError::invalidParams() when the request's outer parameters are
     * invalid. The responder maps every other Throwable from a capability to the generic internal protocol error.
     */
    public function validate(McpRequest $request): void;

    /**
     * Handles one validated MCP request
     */
    public function handle(McpRequest $request): McpResult;
}
