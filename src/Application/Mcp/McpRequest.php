<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

/**
 * Class McpRequest
 */
final readonly class McpRequest
{
    /**
     * Constructs McpRequest
     *
     * @param integer|string       $id
     * @param string               $method
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private int|string $id,
        private string $method,
        private array $parameters,
        private McpRequestMetadata $metadata
    ) {
    }

    /**
     * Returns the JSON-RPC request identifier
     */
    public function id(): int|string
    {
        return $this->id;
    }

    /**
     * Returns the selected MCP method
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns outer parameters without MCP metadata
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Returns request-scoped MCP metadata
     */
    public function metadata(): McpRequestMetadata
    {
        return $this->metadata;
    }
}
