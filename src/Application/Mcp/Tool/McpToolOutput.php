<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

/**
 * Class McpToolOutput
 */
final readonly class McpToolOutput
{
    /**
     * Constructs McpToolOutput
     */
    private function __construct(private string $json)
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
        return new self(McpToolJson::encode($content));
    }

    /**
     * Returns an isolated structured-content value
     */
    public function structuredContent(): mixed
    {
        return json_decode($this->json, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns compatible JSON text for the same complete structured content
     */
    public function text(): string
    {
        return $this->json;
    }
}
