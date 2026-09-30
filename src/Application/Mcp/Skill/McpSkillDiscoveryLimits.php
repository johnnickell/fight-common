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
        public int $pageSize = 100,
        public int $maxEntries = 10000,
        public int $maxEntryBytes = 4194304,
        public int $maxResultBytes = 8388608,
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
}
