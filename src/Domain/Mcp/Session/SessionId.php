<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Session;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class SessionId
 *
 * Exact project/session tuple, independent of the consumer relationship
 */
final readonly class SessionId extends ValueObject
{
    /**
     * Constructs SessionId
     */
    private function __construct(private ObservationReference $project, private ObservationReference $session)
    {
    }

    /**
     * Creates an exact project-scoped session identity
     */
    public static function fromStrings(string $projectId, string $sessionId): self
    {
        return new self(ObservationReference::fromString($projectId), ObservationReference::fromString($sessionId));
    }

    /**
     * Creates an identity from stable tuple text without delimiter ambiguity
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data) || !array_is_list($data) || count($data) !== 2) {
            throw new DomainException('Invalid session identity.');
        }

        return new self(ObservationReference::fromString($data[0]), ObservationReference::fromString($data[1]));
    }

    /**
     * Returns the exact project reference
     */
    public function projectId(): string
    {
        return $this->project->toString();
    }

    /**
     * Returns the exact local session reference
     */
    public function sessionId(): string
    {
        return $this->session->toString();
    }

    /**
     * Returns stable tuple text without normalizing component bytes
     */
    public function toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the exact identity tuple, not the complete subject relationship
     *
     * @return array{string, string}
     */
    public function jsonSerialize(): array
    {
        return [$this->projectId(), $this->sessionId()];
    }
}
