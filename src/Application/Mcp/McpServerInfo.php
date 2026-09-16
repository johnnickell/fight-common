<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;

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
        if (trim($this->name) === '' || trim($this->version) === '') {
            throw new DomainException('An MCP server identity requires a name and version.');
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
