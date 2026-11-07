<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Mcp\Http\McpOrigin as CanonicalMcpOrigin;

/**
 * Class McpOrigin
 *
 * @deprecated Planned v1.5.0; use \Fight\Common\Adapter\Mcp\Http\McpOrigin instead
 * Retained throughout 1.x without runtime notices
 */
final readonly class McpOrigin
{
    /**
     * Constructs McpOrigin
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Creates an origin while preserving the released concrete factory return and private value
     */
    public static function fromString(string $origin): self
    {
        return new self(CanonicalMcpOrigin::fromString($origin)->toString());
    }

    /**
     * Returns the canonical scheme-host-port origin
     */
    public function toString(): string
    {
        return $this->value;
    }
}
