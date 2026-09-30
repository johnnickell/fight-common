<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Resource;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpResourceReadLimits
 */
final readonly class McpResourceReadLimits
{
    /**
     * Constructs McpResourceReadLimits
     *
     * Reserve worst-case JSON text expansion (six bytes per raw byte), which also covers Base64 expansion.
     * Descriptor-specific URI/MIME overhead is checked before listing or opening; central metadata is checked
     * against the final result budget by McpResult. Consumers must leave room for their server identity.
     */
    public function __construct(public int $maxContentBytes = 1048576, public int $maxResultBytes = 8388608)
    {
        if (
            $maxContentBytes < 1 || $maxContentBytes > 8388608
            || $maxResultBytes < 6 * $maxContentBytes + 256 || $maxResultBytes > 67108864
        ) {
            throw new DomainException('Resource read limits must bound raw content and its worst-case encoding.');
        }
    }

    /**
     * Validates that advertised content can fit the configured raw and encoded serving budgets
     */
    public function validate(McpResourceInfo $resource): void
    {
        $metadata = $resource->toArray();
        $contentMetadata = array_intersect_key($metadata, array_flip(['uri', 'mimeType']));
        $encodedBound = 6 * $this->maxContentBytes + strlen(json_encode($contentMetadata, JSON_THROW_ON_ERROR)) + 256;
        if (
            ($metadata['size'] ?? 0) > $this->maxContentBytes
            || $encodedBound > $this->maxResultBytes
        ) {
            throw new DomainException('Resource metadata is inconsistent with its content serving limits.');
        }
    }
}
