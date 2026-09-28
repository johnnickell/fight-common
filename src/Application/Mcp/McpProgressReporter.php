<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

/**
 * Interface McpProgressReporter
 */
interface McpProgressReporter
{
    /**
     * Reports monotonic status without partial or final Tool result content
     *
     * Implementations validate finite non-negative progress and optional total, with total at least progress.
     * Messages are public-safe status text, never credentials or result data.
     * Reporting after cancellation emits nothing.
     */
    public function report(float $progress, ?float $total = null, ?string $message = null): void;

    /**
     * Returns whether the Tool should stop cooperatively at its next safe checkpoint
     *
     * Cancellation does not imply rollback of work already dispatched.
     */
    public function isCancelled(): bool;
}
