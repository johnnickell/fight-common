<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Auth;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Operation\AiOperation as McpAiOperation;
use JsonSerializable;

/**
 * Class AiOperation
 *
 * @deprecated since 1.2, removal remains reserved for 2.0
 * Use the equally deprecated Mcp\Operation\AiOperation while migrating legacy AI integrations.
 */
final readonly class AiOperation implements JsonSerializable
{
    /**
     * Constructs AiOperation
     *
     * @param string $action
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private string $action,
        private array $payload
    ) {
    }

    /**
     * Creates an instance from an associative array
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainException When required fields are missing or the action is unknown
     */
    public static function fromArray(array $data): static
    {
        $operation = McpAiOperation::fromArray($data);

        return new static($operation->action(), $operation->payload());
    }

    /**
     * Creates an instance from a JSON string
     *
     * @throws DomainException When the JSON is invalid or required fields are missing
     */
    public static function fromJson(string $json): static
    {
        return static::fromArray(McpAiOperation::fromJson($json)->toArray());
    }

    /**
     * Retrieves the action name
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Retrieves the action payload
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Retrieves an array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action'  => $this->action,
            'payload' => $this->payload
        ];
    }

    /**
     * Returns data for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
