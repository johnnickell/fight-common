<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Notice\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Notice\NoticeSelection;

/**
 * Class RecoverNotices
 */
final readonly class RecoverNotices implements Query
{
    /**
     * Constructs RecoverNotices
     */
    public function __construct(
        private NoticeSelection $selection,
        private ?string $continuation = null,
        private int $batchSize = 32,
        private int $scanLimit = 100,
        private bool $startAtCurrent = false
    ) {
        if (
            $batchSize < 1 || $batchSize > 32 || $scanLimit < 1 || $scanLimit > 100
            || ($continuation !== null && (strlen($continuation) > 2048 || $continuation === '' || $startAtCurrent))
        ) {
            throw new DomainException('Invalid notice recovery bounds.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        NoticeSelection::structure($data, [
            'selection', 'continuation', 'batch_size', 'scan_limit', 'start_at_current'
        ]);
        if (
            !is_array($data['selection']) || (!is_string($data['continuation']) && $data['continuation'] !== null)
            || !is_int($data['batch_size']) || !is_int($data['scan_limit']) || !is_bool($data['start_at_current'])
        ) {
            throw new DomainException('Invalid notice recovery query.');
        }

        return new self(
            NoticeSelection::fromArray($data['selection']),
            $data['continuation'],
            $data['batch_size'],
            $data['scan_limit'],
            $data['start_at_current']
        );
    }

    /**
     * Returns the current selection without authority
     */
    public function selection(): NoticeSelection
    {
        return $this->selection;
    }

    /**
     * Returns protected examined-boundary state rather than a processing acknowledgment
     */
    public function continuation(): ?string
    {
        return $this->continuation;
    }

    /**
     * Returns the maximum returned notice count
     */
    public function batchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Returns the maximum coherently examined retained rows
     */
    public function scanLimit(): int
    {
        return $this->scanLimit;
    }

    /**
     * Returns whether to establish only the current committed boundary without historical replay
     */
    public function startAtCurrent(): bool
    {
        return $this->startAtCurrent;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'selection'        => $this->selection->toArray(),
            'continuation'     => $this->continuation,
            'batch_size'       => $this->batchSize,
            'scan_limit'       => $this->scanLimit,
            'start_at_current' => $this->startAtCurrent
        ];
    }
}
