<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution;

/**
 * Class SessionReference
 */
final readonly class SessionReference
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
            ObservationFields::reference($projectId),
            ObservationFields::reference($sessionId),
            ObservationFields::reference($consumerReference)
        );
    }

    /**
     * Creates a session reference from structured data
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        ObservationFields::structure($data, ['project_id', 'session_id', 'consumer_reference']);

        return self::fromStrings(
            ObservationFields::reference($data['project_id']),
            ObservationFields::reference($data['session_id']),
            ObservationFields::reference($data['consumer_reference'])
        );
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
