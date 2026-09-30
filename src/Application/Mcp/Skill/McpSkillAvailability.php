<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Interface McpSkillAvailability
 */
interface McpSkillAvailability
{
    /**
     * Returns the current consumer decision for the entire revision using only neutral immutable metadata
     *
     * The entry contains uri, complete frontmatter and complete resources manifest, never file bytes, a
     * principal or credentials. Reevaluate on every operation; hashes and prior reads convey no authority.
     */
    public function isAvailable(StrictJson $entry): bool;
}
