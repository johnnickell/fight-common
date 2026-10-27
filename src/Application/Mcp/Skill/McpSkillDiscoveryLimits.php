<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpSkillDiscoveryLimits
 */
final readonly class McpSkillDiscoveryLimits
{
    /**
     * Constructs McpSkillDiscoveryLimits
     *
     * Pages stop at either the entry count or encoded-result budget without splitting entries.
     * Actual entries must fit both list and get wrappers, including the responder's central metadata.
     */
    public function __construct(
        /**
         * @deprecated Use pageSize() instead; retained until a future major after at least one released minor
         */
        public int $pageSize = 100,
        /**
         * @deprecated Use maxEntries() instead; retained until a future major after at least one released minor
         */
        public int $maxEntries = 10000,
        /**
         * @deprecated Use maxEntryBytes() instead; retained until a future major after at least one released minor
         */
        public int $maxEntryBytes = 4194304,
        /**
         * @deprecated Use maxResultBytes() instead; retained until a future major after at least one released minor
         */
        public int $maxResultBytes = 8388608,
        /**
         * @deprecated Use maxUriBytes() instead; retained until a future major after at least one released minor
         */
        public int $maxUriBytes = 8192
    ) {
        if (
            $pageSize < 1 || $pageSize > 1000
            || $maxEntries < 1 || $maxEntries > 1000000
            || $maxEntryBytes < 1 || $maxEntryBytes > 16777216
            || $maxResultBytes < $maxEntryBytes + 256 || $maxResultBytes > 67108864
            || $maxUriBytes < 1 || $maxUriBytes > 65536
        ) {
            throw new DomainException('Skill discovery requires finite consistent entry, page and result budgets.');
        }
    }

    /**
     * Returns the maximum complete entries per page
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Returns the maximum catalog entry count
     */
    public function maxEntries(): int
    {
        return $this->maxEntries;
    }

    /**
     * Returns the encoded complete entry byte budget
     */
    public function maxEntryBytes(): int
    {
        return $this->maxEntryBytes;
    }

    /**
     * Returns the complete encoded result byte budget
     */
    public function maxResultBytes(): int
    {
        return $this->maxResultBytes;
    }

    /**
     * Returns the exact URI byte budget
     */
    public function maxUriBytes(): int
    {
        return $this->maxUriBytes;
    }
}
