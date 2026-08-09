<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Auth;

use Fight\Common\Domain\Exception\DomainException;
use JsonSerializable;

/**
 * Class AiOperation
 */
final readonly class AiOperation implements JsonSerializable
{
    private const array KNOWN_ACTIONS = [
        'health_check',
        'clear_cache',
        'run_migration',
        'deploy'
    ];

    /**
     * Constructs AiOperation
     *
     * @param string $action
     * @param array<string, mixed> $payload
     *
     * @throws DomainException When the action is unknown
     */
    private function __construct(
        private string $action,
        private array $payload
    ) {
        if (!in_array($this->action, self::KNOWN_ACTIONS, true)) {
            throw new DomainException(sprintf('Unknown AI operation action: %s', $this->action));
        }
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
        if (!isset($data['action'])) {
            throw new DomainException('Missing required field: action');
        }

        return new static($data['action'], $data['payload'] ?? []);
    }

    /**
     * Creates an instance from a JSON string
     *
     * @throws DomainException When the JSON is invalid or required fields are missing
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new DomainException('Invalid JSON payload for AI operation');
        }

        return static::fromArray($data);
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
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
