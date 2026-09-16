<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Type\Arrayable;
use JsonException;

/**
 * Class McpJsonResponse
 */
final readonly class McpJsonResponse implements Arrayable
{
    /**
     * Constructs McpJsonResponse
     *
     * @param integer|string|null       $id
     * @param array<string, mixed>|null $result
     */
    private function __construct(
        private int|string|null $id,
        private ?array $result,
        private ?McpProtocolError $error
    ) {
    }

    /**
     * Creates a successful JSON-RPC response
     */
    public static function success(int|string $id, McpResult $result): self
    {
        return new self($id, $result->toArray(), null);
    }

    /**
     * Creates a JSON-RPC protocol error response
     */
    public static function error(int|string|null $id, McpProtocolError $error): self
    {
        return new self($id, null, $error);
    }

    /**
     * Returns the semantic JSON-RPC response representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = ['jsonrpc' => '2.0'];
        if ($this->id !== null) {
            $response['id'] = $this->id;
        }

        if ($this->result !== null) {
            $response['result'] = $this->result;

            return $response;
        }

        $response['error'] = $this->error->toArray();

        return $response;
    }

    /**
     * Encodes the semantic JSON-RPC response
     *
     * @throws JsonException When the response cannot be encoded
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
