<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Data;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class NoticeRecoveryBatch
 */
final readonly class NoticeRecoveryBatch
{
    /**
     * @var list<NoticeView>
     */
    private array $notices;

    /**
     * Constructs NoticeRecoveryBatch
     *
     * Continuation identifies the examined boundary confidentially, even after qualified completion
     *
     * @phpstan-param array<array-key, NoticeView> $notices
     */
    public function __construct(array $notices, private string $status, private string $continuation)
    {
        if (
            !array_is_list($notices) || count($notices) > 32
            || !in_array($status, ['complete', 'incomplete', 'uncertain'], true)
            || $continuation === '' || strlen($continuation) > 2048
        ) {
            throw new DomainException('Invalid notice recovery result.');
        }

        $this->notices = array_map(static fn (NoticeView $notice): NoticeView => $notice, $notices);
    }

    /**
     * Returns admitted safe notices without positions or hidden history details
     *
     * @return list<NoticeView>
     */
    public function notices(): array
    {
        return $this->notices;
    }

    /**
     * Returns qualified interval completion, never receipt or future completeness
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Returns protected examined-boundary state for another batch or subsequent poll
     */
    public function continuation(): string
    {
        return $this->continuation;
    }

    /**
     * Returns the bounded public projection
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'notices'      => array_map(static fn (NoticeView $notice): array => $notice->toArray(), $this->notices),
            'status'       => $this->status,
            'continuation' => $this->continuation
        ];
    }
}
