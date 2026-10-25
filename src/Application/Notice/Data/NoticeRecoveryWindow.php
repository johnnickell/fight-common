<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Data;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\RecordedNotice;

/**
 * Class NoticeRecoveryWindow
 *
 * Trusted namespace-wide storage evidence, never a public recovery response or access grant
 */
final readonly class NoticeRecoveryWindow
{
    /**
     * @var list<RecordedNotice>
     */
    private array $notices;

    /**
     * Constructs NoticeRecoveryWindow
     *
     * @phpstan-param array<array-key, RecordedNotice> $notices
     */
    public function __construct(
        private string $namespace,
        private int $highWater,
        private int $lostThrough,
        array $notices
    ) {
        if (
            preg_match('/\A[0-9a-f]{32}\z/D', $namespace) !== 1 || $lostThrough < 0
            || $highWater < $lostThrough || $highWater > RecordedNotice::MAX_POSITION
            || !array_is_list($notices) || count($notices) > 100
        ) {
            throw new DomainException('Invalid notice recovery evidence.');
        }

        $this->notices = array_map(static fn (RecordedNotice $notice): RecordedNotice => $notice, $notices);
    }

    /**
     * Returns the storage incarnation for protected continuation binding
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Returns the internal committed high-water mark rather than a public activity counter
     */
    public function highWater(): int
    {
        return $this->highWater;
    }

    /**
     * Returns the conservative global loss boundary without deleted scopes or identities
     */
    public function lostThrough(): int
    {
        return $this->lostThrough;
    }

    /**
     * Returns coherently retained rows that still require current disclosure authorization
     *
     * @return list<RecordedNotice>
     */
    public function notices(): array
    {
        return $this->notices;
    }

    /**
     * Validates only namespace storage continuity after a previously established examined position
     *
     * False means incomplete or uncertain, not expiry or receipt
     * True alone proves neither access continuity, exhausted selection nor public complete catch-up
     */
    public function hasUnbrokenHistoryAfter(string $namespace, int $position): bool
    {
        return $namespace === $this->namespace && $position >= $this->lostThrough && $position <= $this->highWater;
    }
}
