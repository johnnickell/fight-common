<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

/**
 * Interface McpRequestMirrors
 */
interface McpRequestMirrors
{
    /**
     * Returns declarations for the available named operation before HTTP argument validation
     *
     * Implementations conceal unavailable operations exactly like absent ones, validate declarations
     * during composition, and never invoke an operation. HTTP adapters own header comparison.
     *
     * @return list<McpMirrorDeclaration>
     */
    public function mirrorsFor(McpRequest $request): array;
}
