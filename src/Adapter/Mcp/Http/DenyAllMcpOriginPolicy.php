<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Http;

use Fight\Common\Application\Mcp\Http\McpOriginPolicy;

/**
 * Class DenyAllMcpOriginPolicy
 */
class DenyAllMcpOriginPolicy implements McpOriginPolicy
{
    /**
     * @inheritDoc
     */
    public function allows(string $origin): bool
    {
        return false;
    }
}
