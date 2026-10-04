<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

/**
 * Interface McpConfirmationStore
 */
interface McpConfirmationStore
{
    /**
     * Creates one unconsumed confirmation without overwriting any existing identity
     *
     * The ID is 64 lowercase hexadecimal characters from dedicated random bytes, not a credential or nonce.
     * Binding is the SHA-256 digest of the complete canonical protected state; expires is exclusive Unix time.
     * Persist durably before returning. Reject collisions and failures by throwing; never replace a consumed entry.
     * Consumers own storage, access controls and retention. No raw caller, arguments or forms are supplied.
     */
    public function issue(string $id, string $binding, int $expires): void;

    /**
     * Acquires and permanently consumes exactly one matching unexpired confirmation atomically
     *
     * Compare ID, binding and expiry, check current time, and transition unused to consumed in one atomic operation.
     * Exactly one contender may return CONSUMED, only after the transition is durable. Never read then delete.
     * Preserve consumed identity until expiry; expired or absent records can never be acquired. Retrying after
     * an uncertain write must not restore state. Throw on infrastructure failure; Common fails closed without retry.
     * Distinguish rejection reasons internally; Common exposes none of them. CONTENDED may reject a busy acquisition
     * rather than wait. No release, rollback, lease renewal or automatic retry is part of this contract.
     */
    public function consume(string $id, string $binding, int $expires): McpConfirmationOutcome;
}
