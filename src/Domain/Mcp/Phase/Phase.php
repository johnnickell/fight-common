<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Phase;

use Fight\Common\Domain\Mcp\Session\Session;
use Fight\Common\Domain\Mcp\Session\SessionId;

/**
 * Class Phase
 *
 * Recorded subject, not an authoritative Workflow transition
 */
final readonly class Phase
{
    private PhaseId $identity;

    /**
     * Constructs Phase
     */
    public function __construct(string $projectId, private PhaseReference $reference)
    {
        $this->identity = PhaseId::fromStrings($projectId, $reference->phaseId());
    }

    /**
     * Returns the role-specific project-scoped aggregate identity
     */
    public function identity(): PhaseId
    {
        return $this->identity;
    }

    /**
     * Returns the project identity
     */
    public function projectId(): string
    {
        return $this->identity->projectId();
    }

    /**
     * Returns the phase identity within its project
     */
    public function id(): string
    {
        return $this->reference->phaseId();
    }

    /**
     * Returns the session identity
     */
    public function sessionId(): string
    {
        return $this->reference->sessionId();
    }

    /**
     * Returns the optional parent phase identity
     */
    public function parentPhaseId(): ?string
    {
        return $this->reference->parentPhaseId();
    }

    /**
     * Returns the performing Agent identity
     */
    public function agentId(): string
    {
        return $this->reference->agentId();
    }

    /**
     * Returns the observed phase name
     */
    public function name(): string
    {
        return $this->reference->name();
    }

    /**
     * Returns the observed attempt
     */
    public function attempt(): int
    {
        return $this->reference->attempt();
    }

    /**
     * Returns the immutable subject relationship
     */
    public function reference(): PhaseReference
    {
        return $this->reference;
    }

    /**
     * Returns whether evidence preserves this project-scoped subject relationship
     */
    public function matches(string $projectId, PhaseReference $reference): bool
    {
        return $this->identity->equals(PhaseId::fromStrings($projectId, $reference->phaseId()))
            && $this->reference->equals($reference);
    }

    /**
     * Returns whether this phase belongs to the supplied session
     */
    public function belongsTo(Session $session): bool
    {
        return SessionId::fromStrings($this->projectId(), $this->sessionId())->equals($session->identity());
    }

    /**
     * Returns whether the supplied phase is its consistent parent
     */
    public function acceptsParent(Phase $parent): bool
    {
        return $this->projectId() === $parent->projectId() && $this->sessionId() === $parent->sessionId()
            && $this->parentPhaseId() === $parent->id();
    }
}
