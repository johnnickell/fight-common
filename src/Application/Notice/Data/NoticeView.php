<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Data;

use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;

/**
 * Class NoticeView
 */
final readonly class NoticeView
{
    private string $recordedAt;

    /**
     * Constructs NoticeView
     */
    public function __construct(private Notice $notice, string $recordedAt)
    {
        $this->recordedAt = NoticeSelection::instant($recordedAt);
    }

    /**
     * Returns admitted safe content without storage positions or comparison evidence
     */
    public function notice(): Notice
    {
        return $this->notice;
    }

    /**
     * Returns server recording time independently of occurrence and its supplied precision
     */
    public function recordedAt(): string
    {
        return $this->recordedAt;
    }

    /**
     * Returns only admitted bounded safe content and server recording time
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...$this->notice->toArray(), 'recorded_at' => $this->recordedAt];
    }
}
