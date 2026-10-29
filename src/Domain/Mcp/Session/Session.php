<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Session;

/**
 * Class Session
 *
 * Recorded subject with a stable project-scoped identity and consumer relationship
 */
final readonly class Session
{
    /**
     * Constructs Session
     */
    public function __construct(private SessionReference $reference)
    {
    }

    /**
     * Returns the project identity
     */
    public function projectId(): string
    {
        return $this->reference->projectId();
    }

    /**
     * Returns the session identity within its project
     */
    public function id(): string
    {
        return $this->reference->sessionId();
    }

    /**
     * Returns the stable consumer relationship
     */
    public function consumerReference(): string
    {
        return $this->reference->consumerReference();
    }

    /**
     * Returns the immutable subject reference
     */
    public function reference(): SessionReference
    {
        return $this->reference;
    }

    /**
     * Returns whether submitted evidence preserves the subject relationship
     */
    public function matches(SessionReference $reference): bool
    {
        return $this->reference->toArray() === $reference->toArray();
    }
}
