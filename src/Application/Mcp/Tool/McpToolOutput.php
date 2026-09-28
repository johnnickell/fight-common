<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpToolOutput
 */
final readonly class McpToolOutput
{
    /**
     * Constructs McpToolOutput
     */
    private function __construct(private StrictJson $content)
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
