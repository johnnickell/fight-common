<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Exception;

use Fight\Common\Domain\Mcp\Notice\Event\NoticeRecorded;
use RuntimeException;
use Throwable;

/**
 * Class CommittedNoticeDispatchFailed
 */
final class CommittedNoticeDispatchFailed extends RuntimeException
{
    /**
     * Constructs CommittedNoticeDispatchFailed
     */
    public function __construct(private readonly NoticeRecorded $event, Throwable $previous)
    {
        parent::__construct('Notice committed; fact dispatch failed.', 0, $previous);
    }

    /**
     * Returns the safe committed fact without promising publication replay
     */
    public function event(): NoticeRecorded
    {
        return $this->event;
    }
}
