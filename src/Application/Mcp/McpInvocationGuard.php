<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

/**
 * Interface McpInvocationGuard
 */
interface McpInvocationGuard
{
    /**
     * Returns whether one invocation is allowed before capability selection
     *
     * The consumer owns caller identity, limits, exemptions and storage. Only the
     * validated method and optional standard operation name cross this boundary.
     */
    public function allows(string $method, ?string $name): bool;
}
