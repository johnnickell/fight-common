<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;
use JsonException;

/**
 * Class McpServerInfo
 */
final readonly class McpServerInfo implements Arrayable
{
    /**
     * Constructs McpServerInfo
     */
    public function __construct(private string $name, private string $version)
    {
        try {
            json_encode($this->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('An MCP server identity must contain JSON Unicode strings.');
        }
    }

    /**
     * Returns the configured server name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the configured server version
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * Returns the MCP implementation representation
     *
     * @return array{name: string, version: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'version' => $this->version];
    }
}
