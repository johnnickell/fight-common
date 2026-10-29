<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Symfony;

use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory as CanonicalMcpResponseFactory;

/**
 * Class McpResponseFactory
 *
 * @deprecated Use \Fight\Common\Adapter\Mcp\Symfony\McpResponseFactory instead; retained throughout 1.x
 */
final readonly class McpResponseFactory extends CanonicalMcpResponseFactory
{
    /**
     * Constructs McpResponseFactory
     */
    public function __construct(McpResponseEmitter $emitter = new McpResponseEmitter())
    {
        parent::__construct($emitter);
    }
}
