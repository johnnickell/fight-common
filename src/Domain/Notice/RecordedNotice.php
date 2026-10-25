<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Notice;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class RecordedNotice
 */
final readonly class RecordedNotice
{
    public const int MAX_POSITION = 2147483647;

    /**
     * Constructs RecordedNotice
     */
    public function __construct(private Notice $notice, private int $position, private string $recordedAt)
    {
        if ($position < 1 || $position > self::MAX_POSITION || strlen($recordedAt) !== 27) {
            throw new DomainException('Invalid recorded notice.');
        }

        NoticeOccurrence::fromString($recordedAt);
    }

    /**
     * Returns the submitted immutable notice
     */
    public function notice(): Notice
    {
        return $this->notice;
    }

    /**
     * Returns the store-local commit-ordered position rather than causality
     */
    public function position(): int
    {
        return $this->position;
    }

    /**
     * Returns immutable server UTC time with microsecond representation
     */
    public function recordedAt(): string
    {
        return $this->recordedAt;
    }
}
