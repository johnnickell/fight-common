<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Exception;

use RuntimeException;
use Throwable;

/**
 * Class TaskStorageFailed
 */
final class TaskStorageFailed extends RuntimeException
{
    /**
     * Constructs TaskStorageFailed
     */
    public function __construct(private readonly bool $outcomeUncertain, Throwable $cause)
    {
        parent::__construct('Task storage failed.', 0, $cause);
    }

    /**
     * Returns whether durable mutation outcome needs fresh reconciliation
     */
    public function outcomeUncertain(): bool
    {
        return $this->outcomeUncertain;
    }
}
