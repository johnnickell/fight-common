<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class McpResult
 */
final readonly class McpResult implements Arrayable
{
    /**
     * Constructs McpResult
     *
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * Creates a complete semantic result
     *
     * @param array<string, mixed> $data
     */
    public static function complete(array $data): self
    {
        if (array_key_exists('resultType', $data)) {
            throw new DomainException('An MCP result payload cannot override resultType.');
        }

        return new self(['resultType' => 'complete', ...$data]);
    }

    /**
     * Returns the semantic MCP result
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
