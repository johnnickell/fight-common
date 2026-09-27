<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Application\Mcp\McpOriginPolicy;

/**
 * Class DenyAllMcpOriginPolicy
 */
final class DenyAllMcpOriginPolicy implements McpOriginPolicy
{
    /**
     * @inheritDoc
     */
    public function allows(string $origin): bool
    {
        return false;
    }
}
