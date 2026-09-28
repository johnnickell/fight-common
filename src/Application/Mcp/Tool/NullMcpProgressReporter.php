<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class NullMcpProgressReporter
 */
final class NullMcpProgressReporter implements McpProgressReporter
{
    private float $previous = 0.0;

    /**
     * @inheritDoc
     */
    public function report(float $progress, ?float $total = null, ?string $message = null): void
    {
        if (
            !is_finite($progress) || $progress < $this->previous
            || ($total !== null && (!is_finite($total) || $total < $progress))
        ) {
            throw new DomainException('Tool progress must be finite, non-negative, monotonic and within its total.');
        }

        $this->previous = $progress;
    }

    /**
     * @inheritDoc
     */
    public function isCancelled(): bool
    {
        return false;
    }
}
