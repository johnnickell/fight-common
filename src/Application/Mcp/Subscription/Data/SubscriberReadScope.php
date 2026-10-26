<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Data;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Notice;

/**
 * Class SubscriberReadScope
 */
final readonly class SubscriberReadScope
{
    /**
     * Constructs SubscriberReadScope
     */
    public function __construct(private string $storageScope, private int $queriedAt)
    {
        Notice::reference($storageScope);
        if ($queriedAt < 1 || $queriedAt > 2147483647) {
            throw new DomainException('Invalid subscriber inspection clock.');
        }
    }

    /**
     * Returns the current observation clock, never a global checkpoint
     */
    public function queriedAt(): int
    {
        return $this->queriedAt;
    }

    /**
     * Returns configured store visibility, not an installation-wide presence claim
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'storage_scope' => $this->storageScope,
            'queried_at'    => $this->queriedAt,
            'coverage'      => 'authorized_retained_registrations',
            'consistency'   => 'live'
        ];
    }
}
