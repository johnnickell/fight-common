<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Common\Domain\Value\Basic\StrictJson;

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
    private function __construct(private array $data, private ?int $maxEncodedBytes = null)
    {
        if (
            $maxEncodedBytes !== null
            && ($maxEncodedBytes < 1 || strlen(json_encode($data, JSON_THROW_ON_ERROR)) > $maxEncodedBytes)
        ) {
            throw new DomainException('The MCP result exceeds its encoded result budget.');
        }
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
     * Creates a complete result with a byte budget retained when central metadata is added
     *
     * The positive budget covers the final JSON-encoded result, not the outer JSON-RPC envelope. Existing
     * unbounded complete results remain unchanged. An overflow throws rather than truncating a success.
     * Callers supply owned immutable JSON data, as Resource discovery does; this is an internal result seam.
     *
     * @param array<string, mixed> $data
     *
     * @internal
     */
    public static function boundedComplete(array $data, int $maxEncodedBytes): self
    {
        return new self(self::complete($data)->data, $maxEncodedBytes);
    }

    /**
     * Creates an input-required result from protected interaction mechanics
     *
     * @internal
     */
    public static function inputRequired(StrictJson $requests, string $state): self
    {
        return new self(['resultType' => 'input_required', 'inputRequests' => $requests, 'requestState' => $state]);
    }

    /**
     * Returns the same result kind with centrally validated response metadata
     *
     * @param array<string, mixed> $metadata
     *
     * @internal
     */
    public function withMetadata(array $metadata): self
    {
        return new self([...$this->data, '_meta' => $metadata], $this->maxEncodedBytes);
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
