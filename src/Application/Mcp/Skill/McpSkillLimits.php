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
        /**
         * @deprecated Use maxFiles() instead; retained until a future major after at least one released minor
         */
        public int $maxFiles = 512,
        /**
         * @deprecated Use maxTotalBytes() instead; retained until a future major after at least one released minor
         */
        public int $maxTotalBytes = 16777216,
        /**
         * @deprecated Use maxFrontmatterBytes() instead
         * Retained until a future major after at least one released minor
         */
        public int $maxFrontmatterBytes = 65536,
        /**
         * @deprecated Use maxDepth() instead; retained until a future major after at least one released minor
         */
        public int $maxDepth = 32,
        /**
         * @deprecated Use maxEntryBytes() instead; retained until a future major after at least one released minor
         */
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

    /**
     * Returns the maximum revision member count
     */
    public function maxFiles(): int
    {
        return $this->maxFiles;
    }

    /**
     * Returns the total raw revision byte budget
     */
    public function maxTotalBytes(): int
    {
        return $this->maxTotalBytes;
    }

    /**
     * Returns the JSON frontmatter byte budget
     */
    public function maxFrontmatterBytes(): int
    {
        return $this->maxFrontmatterBytes;
    }

    /**
     * Returns the frontmatter parsing depth limit
     */
    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * Returns the encoded complete entry byte budget
     */
    public function maxEntryBytes(): int
    {
        return $this->maxEntryBytes;
    }
}
