<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Domain\Value\Basic\JsonObject;

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
     * Returns an isolated JSON value for the complete structured content
     */
    public function structuredContent(): JsonObject
    {
        return JsonObject::fromData(
            json_decode($this->json, false, 512, JSON_THROW_ON_ERROR),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /**
     * Returns compatible JSON text for the same complete structured content
     */
    public function text(): string
    {
        return $this->json;
    }
}
