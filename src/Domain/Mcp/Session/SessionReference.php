<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Session;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class SessionReference
 */
final readonly class SessionReference extends ValueObject
{
    /**
     * Constructs SessionReference
     */
    private function __construct(
        private string $projectId,
        private string $sessionId,
        private string $consumerReference
    ) {
    }

    /**
     * Creates a validated session reference
     */
    public static function fromStrings(string $projectId, string $sessionId, string $consumerReference): self
    {
        return new self(
            ObservationReference::fromString($projectId)->toString(),
            ObservationReference::fromString($sessionId)->toString(),
            ObservationReference::fromString($consumerReference)->toString()
        );
    }

    /**
     * Creates a session reference from structured data
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['consumer_reference', 'project_id', 'session_id']) {
            throw new DomainException('Invalid session reference structure.');
        }

        return self::fromStrings(
            ObservationReference::fromString($data['project_id'])->toString(),
            ObservationReference::fromString($data['session_id'])->toString(),
            ObservationReference::fromString($data['consumer_reference'])->toString()
        );
    }

    /**
     * Creates a reference from its stable structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid session reference text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable structured reference text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the structured reference rather than double-encoded text
     *
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the project reference
     */
    public function projectId(): string
    {
        return $this->projectId;
    }

    /**
     * Returns the session reference
     */
    public function sessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Returns the consumer execution reference
     */
    public function consumerReference(): string
    {
        return $this->consumerReference;
    }

    /**
     * Returns the structured reference
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'project_id'         => $this->projectId,
            'session_id'         => $this->sessionId,
            'consumer_reference' => $this->consumerReference
        ];
    }
}
