<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Skill;

use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Interface McpSkillFrontmatterParser
 */
interface McpSkillFrontmatterParser
{
    /**
     * Parses bounded YAML into a complete immutable JSON object without executing tags or resolving includes
     *
     * Preserve every authored JSON-representable field. Reject duplicate keys, unsupported values, aliases
     * and excess raw/encoded bytes or nesting before expansion. Never normalize the original SKILL.md bytes.
     */
    public function parse(string $yaml, McpSkillLimits $limits): StrictJson;
}
