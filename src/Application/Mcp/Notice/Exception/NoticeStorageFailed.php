<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Exception;

use RuntimeException;
use Throwable;

/**
 * Class NoticeStorageFailed
 */
final class NoticeStorageFailed extends RuntimeException
{
    /**
     * Constructs NoticeStorageFailed
     */
    public function __construct(private readonly bool $outcomeUncertain, Throwable $previous)
    {
        parent::__construct('Notice storage failed.', 0, $previous);
    }

    /**
     * Returns whether failure cannot establish rollback or absence
     */
    public function outcomeUncertain(): bool
    {
        return $this->outcomeUncertain;
    }
}
