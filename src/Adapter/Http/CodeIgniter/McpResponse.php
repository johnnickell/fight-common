<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\CodeIgniter;

use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse as CanonicalMcpResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Class McpResponse
 *
 * @deprecated Planned v1.5.0; use \Fight\Common\Adapter\Mcp\CodeIgniter\McpResponse instead
 * Retained throughout 1.x without runtime notices
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
