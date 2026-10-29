<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\CodeIgniter;

use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse as CanonicalMcpResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Class McpResponse
 *
 * @deprecated Use \Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse instead; retained throughout 1.x
 */
final class McpResponse extends CanonicalMcpResponse
{
    /**
     * Constructs McpResponse
     */
    public function __construct(
        ResponseInterface $response,
        McpResponseEmitter $emitter = new McpResponseEmitter()
    ) {
        parent::__construct($response, $emitter);
    }
}
