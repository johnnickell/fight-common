<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Mcp\Http\McpRequestHandler as CanonicalMcpRequestHandler;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpOriginPolicy;
use Fight\Common\Application\Mcp\McpRequestDecoder;

/**
 * Class McpRequestHandler
 *
 * @deprecated Use \Fight\Common\Adapter\Mcp\Http\McpRequestHandler instead; retained throughout 1.x
 */
final readonly class McpRequestHandler extends CanonicalMcpRequestHandler
{
    /**
     * Constructs McpRequestHandler
     */
    public function __construct(
        McpCapabilityRegistry $registry,
        McpOriginPolicy $originPolicy,
        McpInvocationGuard $guard,
        McpResponseFactory $responses,
        McpRequestDecoder $decoder = new McpRequestDecoder(),
        bool $progressive = false
    ) {
        parent::__construct($registry, $originPolicy, $guard, $responses, $decoder, $progressive);
    }
}
