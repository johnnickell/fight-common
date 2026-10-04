<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpSkillLimits
 */
final readonly class McpSkillLimits
{
    /**
     * Constructs McpSkillLimits
     *
     * A static revision stays within the pinned host interoperability envelope of 512 files and 16 MiB.
     * Resource limits independently constrain individual files and their complete encoded read results.
     */
    public function __construct(
        public int $maxFiles = 512,
        public int $maxTotalBytes = 16777216,
        public int $maxFrontmatterBytes = 65536,
        public int $maxDepth = 32,
        public int $maxEntryBytes = 4194304
    ) {
        if (
            $maxFiles < 1 || $maxFiles > 512
            || $maxTotalBytes < 1 || $maxTotalBytes > 16777216
            || $maxFrontmatterBytes < 1 || $maxFrontmatterBytes > 1048576
            || $maxDepth < 1 || $maxDepth > 64
            || $maxEntryBytes < $maxFrontmatterBytes || $maxEntryBytes > 16777216
        ) {
            throw new DomainException('Skill limits must be finite and within the static interoperability envelope.');
        }
    }
}
