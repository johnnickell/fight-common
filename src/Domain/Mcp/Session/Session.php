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
    private SessionId $identity;

    /**
     * Constructs Session
     */
    public function __construct(private SessionReference $reference)
    {
        $this->identity = SessionId::fromStrings($reference->projectId(), $reference->sessionId());
    }

    /**
     * Returns the role-specific project-scoped aggregate identity
     */
    public function identity(): SessionId
    {
        return $this->identity;
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
        return $this->reference->equals($reference);
    }
}
