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
     * Symfony owns scalar decoding and block syntax. Flow collections use an explicit key/value/separator
     * state so a key cannot escape validation through comments or whitespace. Only single-line quoted keys
     * or single-token plain keys with a same-line colon are supported there; Symfony can truncate other
     * spellings. Merge keys and implicit flow-sequence mappings reject before conversion. Source bytes stay
     * unchanged, and scalar values, comments and block scalar bodies are not mistaken for keys.
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
            $collection = array_key_last($collections);
            if ($collection !== null) {
                if (
                    $collections[$collection]['state'] === 'end'
                    && $character !== ',' && $character !== $collections[$collection]['close']
                ) {
                    throw new DomainException('Skill frontmatter requires a flow collection separator.');
                }

                if ($collections[$collection]['state'] === 'key' && $character !== '}') {
                    $key = $this->readFlowKey($source, $offset, $flags, $maxDepth);
                    if ($key === '<<' || isset($collections[$collection]['keys'][$key])) {
                        throw new DomainException('Skill frontmatter contains a merge or duplicate mapping key.');
                    }

                    $collections[$collection]['keys'][$key] = true;
                    $collections[$collection]['state'] = 'value';
                    continue;
                }
            }

            if (
                $collections === []
                && preg_match('/\G(?:---|\.\.\.)(?=\s|$)/', $source, $match, offset: $offset) === 1
            ) {
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

                    if ($collection !== null) {
                        $collections[$collection]['state'] = 'end';
                    }

                    $collections[] = [
                        'close' => $character === '{' ? '}' : ']',
                        'state' => $character === '{' ? 'key' : 'value',
                        'keys'  => []
                    ];
                } elseif (str_contains(']}', $character)) {
                    if ($collection === null || $collections[$collection]['close'] !== $character) {
                        throw new DomainException('Skill frontmatter contains a mismatched flow collection.');
                    }

                    array_pop($collections);
                } elseif ($collection !== null) {
                    if ($character !== ',') {
                        throw new DomainException('Skill frontmatter does not support implicit flow mappings.');
                    }

                    $collections[$collection]['state'] = $collections[$collection]['close'] === '}' ? 'key' : 'value';
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
                    $pattern = '/\G(?:(?![ \t]\#|:[\s,\[\]{}]|:$)[^\n,\[\]{}])++/';
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
            if ($collection !== null) {
                $collections[$collection]['state'] = 'end';
            }

            if (is_string($key) && preg_match('/\G\s*:/', $source, offset: $offset) === 1) {
                if ($key === '<<') {
                    throw new DomainException('Skill frontmatter does not support YAML merge keys.');
                }
            }
        }
    }

    /**
     * Returns a losslessly decoded flow key and consumes its same-line separator
     *
     * The accepted grammar cannot invoke Symfony's forward search for a colon or its plain-key truncation.
     * Tags, anchors, multiline keys and comments before the colon are unsupported, not normalized away.
     *
     * @phpstan-param int-mask-of<Yaml::PARSE_*> $flags
     */
    private function readFlowKey(string $source, int &$offset, int $flags, int $maxDepth): string
    {
        $quoted = '"[^"\\\\\n]*+(?:\\\\[^\n][^"\\\\\n]*+)*+"|\'[^\'\n]*+(?:\'\'[^\'\n]*+)*+\'';
        $pattern = '/\G('.$quoted.'|[^\s:,\[\]{}\#\'"!&*?]++)[ \t]*:/';
        if (preg_match($pattern, $source, $match, offset: $offset) !== 1) {
            throw new DomainException('Skill frontmatter contains an unsupported flow mapping key.');
        }

        $offset += strlen($match[0]);
        $key = $match[1];
        if ($key[0] === '"' || $key[0] === "'") {
            $key = Yaml::parse($key, $flags, $maxDepth, 0);
        }

        return $key;
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
