<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use Fight\Common\Domain\Notice\NoticeSelection;

/**
 * Class NoticeAccess
 *
 * Trusted invocation-local grants for complete safe notice metadata and relationships
 * A consumer must exclude notices whose entire metadata it cannot disclose
 */
final readonly class NoticeAccess
{
    /**
     * Constructs NoticeAccess
     */
    private function __construct(private NoticeSelection $selection, private ?NoticeRecoveryIdentity $recoveryIdentity)
    {
    }

    /**
     * Creates finite scope grants with optional intersecting trusted restrictions
     *
     * Empty scopes deny everything; caller Query fields must never supply these grants
     *
     * @param array<mixed> $scopes
     * @param array<array-key, mixed> $filters
     */
    public static function create(
        array $scopes,
        array $filters = [],
        ?NoticeRecoveryIdentity $recoveryIdentity = null
    ): self {
        return new self(NoticeSelection::create($scopes, $filters), $recoveryIdentity);
    }

    /**
     * Returns trusted recovery context from the same current admission snapshot as these grants
     */
    public function recoveryIdentity(): ?NoticeRecoveryIdentity
    {
        return $this->recoveryIdentity;
    }

    /**
     * Returns trusted constraints independently of the caller selection
     */
    public function selection(): NoticeSelection
    {
        return $this->selection;
    }
}
