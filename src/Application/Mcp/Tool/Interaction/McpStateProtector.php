<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

/**
 * Interface McpStateProtector
 */
interface McpStateProtector
{
    public const int MAX_STATE_BYTES = 65536;

    /**
     * Encrypts ordinary interaction state with versioned authenticated confidentiality
     *
     * Fail closed before encryption when the resulting token would exceed MAX_STATE_BYTES.
     * Tokens disclose only protocol and key versions; no server-side interaction record is retained.
     */
    public function seal(string $plaintext): string;

    /**
     * Opens authenticated state without authorizing its use or revealing failures publicly
     *
     * Reject tokens exceeding MAX_STATE_BYTES before parsing or cryptography. Reject malformed,
     * unknown-version, retired-key and unauthenticated tokens with a generic DomainException.
     */
    public function open(string $token): string;
}
