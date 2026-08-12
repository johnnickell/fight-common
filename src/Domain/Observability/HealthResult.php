<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Observability;

use JsonSerializable;

/**
 * Class HealthResult
 */
final readonly class HealthResult implements JsonSerializable
{
    /**
     * Constructs HealthResult
     *
     * @param string $name
     * @param HealthStatus $status
     * @param string|null $message
     * @param array<string, mixed> $context
     */
    public function __construct(
        private string $name,
        private HealthStatus $status,
        private ?string $message = null,
        private array $context = []
    ) {
    }

    /**
     * Retrieves the check name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the status
     */
    public function status(): HealthStatus
    {
        return $this->status;
    }

    /**
     * Retrieves the human-readable message
     */
    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * Retrieves the additional context
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Retrieves an array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'    => $this->name,
            'status'  => $this->status->toString(),
            'message' => $this->message,
            'context' => $this->context
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
