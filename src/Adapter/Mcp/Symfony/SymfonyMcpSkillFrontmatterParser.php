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
            $this->validateMappingKeys($yaml, $flags, $limits->maxDepth);
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

    /**
     * Validates mapping keys before Symfony can discard them or enable duplicate overwrites
     *
     * This bounded lexical pass does not interpret mappings or rewrite source bytes. Symfony still owns
     * syntax and scalar decoding. Quoted keys resolving to << are also unsupported because Symfony treats
     * them as merges. Flow maps require an additional duplicate check because Symfony uses isset and misses
     * prior null values. Scalar values, comments and block scalar bodies must not be mistaken for keys.
     *
     * @phpstan-param int-mask-of<Yaml::PARSE_*> $flags
     */
    private function validateMappingKeys(string $yaml, int $flags, int $maxDepth): void
    {
        $source = str_replace(["\r\n", "\r"], "\n", $yaml);
        $length = strlen($source);
        $offset = 0;
        $collections = [];
        $tag = '';
        while ($offset < $length) {
            if (preg_match('/\G(?:\s+|\#[^\n]*)/', $source, $match, offset: $offset) === 1) {
                $offset += strlen($match[0]);
                continue;
            }

            $character = $source[$offset];
            if (preg_match('/\G(?:---|\.\.\.)(?=\s|$)/', $source, $match, offset: $offset) === 1) {
                $offset += strlen($match[0]);
                continue;
            }

            if (
                str_contains('[]{},:', $character)
                || preg_match('/\G[-?](?=\s|$)/', $source, offset: $offset) === 1
            ) {
                if (str_contains('[{', $character)) {
                    if (count($collections) >= $maxDepth) {
                        throw new DomainException('Skill frontmatter exceeds its nesting budget.');
                    }

                    $collections[] = $character === '{' ? [] : null;
                } elseif (str_contains(']}', $character)) {
                    array_pop($collections);
                }

                $tag = '';
                ++$offset;
                continue;
            }

            // Tags and anchors precede a node; they do not hide the key following them.
            if (preg_match('/\G[!&][^\s,\[\]{}]+/', $source, $match, offset: $offset) === 1) {
                if ($character === '!') {
                    $tag = $match[0].' ';
                }

                $offset += strlen($match[0]);
                continue;
            }

            $blockHeader = '/\G[|>][0-9+-]*(?:[ \t]+\#[^\n]*)?[ \t]*(?:\n|$)/';
            if ($collections === [] && preg_match($blockHeader, $source, $match, offset: $offset) === 1) {
                $offset = $this->skipBlockScalar($source, $offset, $match[0]);
                $tag = '';
                continue;
            }

            if ($character === '"' || $character === "'") {
                $pattern = '/\G(?:"[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+"|\'[^\']*+(?:\'\'[^\']*+)*+\')/s';
                if (preg_match($pattern, $source, $match, offset: $offset) !== 1) {
                    throw new DomainException('Skill frontmatter contains an unterminated quoted scalar.');
                }

                $key = Yaml::parse($tag.$match[0], $flags, $maxDepth, 0);
            } else {
                // Plain scalars retain embedded quotes and flow-looking punctuation outside flow collections.
                $pattern = '/\G(?:(?!:[\s]|:$)[^\n])++/';
                if ($collections !== []) {
                    $pattern = '/\G(?:(?!:[\s,\[\]{}]|:$)[^\n,\[\]{}])++/';
                }

                if (preg_match($pattern, $source, $match, offset: $offset) !== 1) {
                    throw new DomainException('Skill frontmatter contains an unsupported scalar.');
                }

                $key = trim($match[0]);
                if ($tag !== '') {
                    $key = Yaml::parse($tag.$key, $flags, $maxDepth, 0);
                }
            }

            $tag = '';
            $offset += strlen($match[0]);
            if (is_string($key) && preg_match('/\G\s*:/', $source, offset: $offset) === 1) {
                if ($key === '<<') {
                    throw new DomainException('Skill frontmatter does not support YAML merge keys.');
                }

                $collection = array_key_last($collections);
                if ($collection !== null && $collections[$collection] !== null) {
                    if (isset($collections[$collection][$key])) {
                        throw new DomainException('Skill frontmatter contains a duplicate mapping key.');
                    }

                    $collections[$collection][$key] = true;
                }
            }
        }
    }

    /**
     * Returns the first token offset after a literal or folded block scalar
     */
    private function skipBlockScalar(string $source, int $offset, string $header): int
    {
        $lineStart = strrpos(substr($source, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        $prefix = substr($source, $lineStart, $offset - $lineStart);
        preg_match('/\A *(?:-[ \t]+)*/', $prefix, $match);
        $indent = strlen($match[0]);
        // A scalar directly inside a sequence is relative to its dash, even with intervening node properties.
        $properties = substr($prefix, $indent);
        if (str_contains($match[0], '-') && preg_match('/\A(?:[!&][^\s]+\s+)*\z/', $properties) === 1) {
            $indent = strrpos($match[0], '-');
        }

        $contentIndent = null;
        if (preg_match('/\A[|>][+-]?([1-9])/', $header, $match) === 1) {
            $contentIndent = $indent + (int) $match[1];
        }

        $offset += strlen($header);
        $length = strlen($source);
        while ($offset < $length) {
            preg_match('/\G( *)([^\n]*)(?:\n|$)/', $source, $match, offset: $offset);
            $spaces = strlen($match[1]);
            if (trim($match[2]) !== '') {
                $contentIndent ??= max($indent + 1, $spaces);
                if ($spaces < $contentIndent) {
                    break;
                }
            }

            $offset += strlen($match[0]);
        }

        return $offset;
    }
}
