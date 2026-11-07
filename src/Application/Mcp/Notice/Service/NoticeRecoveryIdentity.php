<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Service;

use Fight\Common\Domain\Mcp\Notice\Notice;

/**
 * Class NoticeRecoveryIdentity
 *
 * Trusted current admission context, never reconstructed from Query or MCP input
 */
final readonly class NoticeRecoveryIdentity
{
    /**
     * Constructs NoticeRecoveryIdentity
     *
     * Revision must change on every intervening access change, including revoke then restore
     * Namespace evidence permission explicitly permits disclosure of global history loss
     */
    public function __construct(
        private string $caller,
        private ?string $revision,
        private bool $namespaceEvidence = false
    ) {
        Notice::reference($caller);
        if ($revision !== null) {
            Notice::reference($revision);
        }
    }

    /**
     * Returns a private binding without exposing caller or policy identifiers
     */
    public function binding(): string
    {
        return hash('sha256', json_encode(
            [$this->caller, $this->revision, $this->namespaceEvidence],
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * Returns whether current context can establish access continuity and disclose namespace evidence
     */
    public function canProveContinuity(): bool
    {
        return $this->revision !== null && $this->namespaceEvidence;
    }
}
