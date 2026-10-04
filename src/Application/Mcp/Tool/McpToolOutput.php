<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpToolOutput
 */
final readonly class McpToolOutput
{
    /**
     * Constructs McpToolOutput
     *
     * @phpstan-param list<McpResourceInfo> $resources
     */
    private function __construct(private StrictJson $content, private array $resources = [])
    {
    }

    /**
     * Creates complete public-safe structured output without constructing a protocol response
     *
     * The consumer explicitly projects safe data. This type validates JSON, not authorization
     * or output-schema conformance. Object keys beginning with U+0000 reject at construction
     * because PHP cannot decode them into the isolated object representation.
     */
    public static function structured(mixed $content): self
    {
        return new self(StrictJson::fromData($content));
    }

    /**
     * Creates complete structured output with validated standard Resource links
     *
     * A link is not a Resource read grant. The consumer must project safe metadata and the
     * Resource capability independently checks current availability on every read.
     */
    public static function structuredWithResourceLinks(mixed $content, McpResourceInfo ...$resources): self
    {
        return new self(StrictJson::fromData($content), $resources);
    }

    /**
     * Returns complete MCP content items, with text before any Resource links
     *
     * @return list<array<string, mixed>>
     */
    public function contentItems(): array
    {
        $items = [['type' => 'text', 'text' => $this->text()]];
        foreach ($this->resources as $resource) {
            $items[] = ['type' => 'resource_link', ...$resource->toArray()];
        }

        return $items;
    }

    /**
     * Returns an isolated JSON value for the complete structured content
     */
    public function structuredContent(): StrictJson
    {
        return $this->content;
    }

    /**
     * Returns compatible JSON text for the same complete structured content
     */
    public function text(): string
    {
        return $this->content->toString();
    }
}
