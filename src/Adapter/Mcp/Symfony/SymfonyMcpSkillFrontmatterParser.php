<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Symfony;

use Fight\Common\Application\Mcp\Skill\McpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\Skill\McpSkillLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Class SymfonyMcpSkillFrontmatterParser
 */
final readonly class SymfonyMcpSkillFrontmatterParser implements McpSkillFrontmatterParser
{
    /**
     * @inheritDoc
     */
    public function parse(string $yaml, McpSkillLimits $limits): StrictJson
    {
        if (strlen($yaml) > $limits->maxFrontmatterBytes || !mb_check_encoding($yaml, 'UTF-8')) {
            throw new DomainException('Skill frontmatter exceeds its raw budget or is not UTF-8.');
        }

        try {
            // Dates remain typed unsupported values instead of silently becoming Unix timestamps.
            // No object, constant or custom-tag execution flags are enabled. Aliases fail before expansion.
            $flags = Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE;
            $flags |= Yaml::PARSE_EXCEPTION_ON_ALIAS | Yaml::PARSE_DATETIME;
            $value = Yaml::parse($yaml, $flags, $limits->maxDepth, 0);
        } catch (Throwable $throwable) {
            throw new DomainException('Skill frontmatter requires safe unambiguous YAML.', previous: $throwable);
        }

        $data = StrictJson::fromData($value, $limits->maxDepth);
        if (!$data->isObject() || strlen($data->toString()) > $limits->maxFrontmatterBytes) {
            throw new DomainException('Skill frontmatter must be a bounded JSON object.');
        }

        return $data;
    }
}
