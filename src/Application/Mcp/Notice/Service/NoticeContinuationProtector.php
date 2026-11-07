<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Service;

/**
 * Interface NoticeContinuationProtector
 */
interface NoticeContinuationProtector
{
    /**
     * Encrypts and authenticates bounded private state without a server-side acknowledgment record
     */
    public function seal(string $plaintext): string;

    /**
     * Opens authenticated confidential state or fails without public diagnostics
     */
    public function open(string $token): string;
}
