<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Domain\Exception\DomainException;
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
    private function __construct(
        private StrictJson $content,
        private array $resources = [],
        private ?int $maxEncodedResponseBytes = null
    ) {
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
     * Creates structured output with an opt-in final encoded JSON-RPC response budget
     *
     * The invoker and central response factory retain this bound through text duplication,
     * escaping, request identity and actual server metadata. Overflow fails before disclosure.
     */
    public static function boundedStructured(mixed $content, int $maxEncodedResponseBytes = 1048576): self
    {
        if ($maxEncodedResponseBytes < 1 || $maxEncodedResponseBytes > 67108864) {
            throw new DomainException('Invalid Tool response budget.');
        }

        return new self(StrictJson::fromData($content), [], $maxEncodedResponseBytes);
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
     * Returns the optional complete encoded response budget
     */
    public function maxEncodedResponseBytes(): ?int
    {
        return $this->maxEncodedResponseBytes;
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
