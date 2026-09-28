<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

/**
 * Interface McpOriginPolicy
 */
interface McpOriginPolicy
{
    /**
     * Returns whether a canonical scheme-host-port origin is permitted
     *
     * The HTTP adapter validates and canonicalizes a supplied Origin before this call.
     * An absent Origin never calls this policy and is valid for native clients.
     */
    public function allows(string $origin): bool;
}
