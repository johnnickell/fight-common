<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Release;

use RuntimeException;
use Throwable;

/**
 * Class ReleaseJsonObjectMemberValidator
 *
 * Rejects ambiguous JSON objects before a decoder can apply last-member-wins semantics.
 */
final class ReleaseJsonObjectMemberValidator
{
    /**
     * Maximum raw fixture bytes parsed at the bootstrap seam
     */
    public const int MAXIMUM_BYTES = 1_048_576;

    private const int MAXIMUM_DEPTH = 64;

    /**
     * Reports whether one bounded JSON document is structurally valid and every object has unique decoded names
     */
    public function isValid(string $json): bool
    {
        if (strlen($json) > self::MAXIMUM_BYTES) {
            return false;
        }

        $position = 0;

        try {
            $this->skipWhitespace($json, $position);
            $this->parseValue($json, $position, 0);
            $this->skipWhitespace($json, $position);

            return $position === strlen($json);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Parses one JSON value
     */
    private function parseValue(string $json, int &$position, int $depth): void
    {
        if ($depth > self::MAXIMUM_DEPTH || !isset($json[$position])) {
            throw new RuntimeException('The JSON structure is invalid.');
        }

        match ($json[$position]) {
            '{' => $this->parseObject($json, $position, $depth),
            '[' => $this->parseArray($json, $position, $depth),
            '"' => $this->parseString($json, $position),
            't' => $this->parseLiteral($json, $position, 'true'),
            'f' => $this->parseLiteral($json, $position, 'false'),
            'n' => $this->parseLiteral($json, $position, 'null'),
            default => $this->parseNumber($json, $position)
        };
    }

    /**
     * Parses one JSON object while retaining decoded names for that object only
     */
    private function parseObject(string $json, int &$position, int $depth): void
    {
        ++$position;
        $this->skipWhitespace($json, $position);

        if (($json[$position] ?? null) === '}') {
            ++$position;

            return;
        }

        $members = [];

        while (true) {
            if (($json[$position] ?? null) !== '"') {
                throw new RuntimeException('The JSON object member is invalid.');
            }

            $member = base64_encode($this->parseString($json, $position));

            if (array_key_exists($member, $members)) {
                throw new RuntimeException('The JSON object member is duplicated.');
            }

            $members[$member] = true;
            $this->skipWhitespace($json, $position);

            if (($json[$position] ?? null) !== ':') {
                throw new RuntimeException('The JSON object member separator is invalid.');
            }

            ++$position;
            $this->skipWhitespace($json, $position);
            $this->parseValue($json, $position, $depth + 1);
            $this->skipWhitespace($json, $position);
            $delimiter = $json[$position] ?? null;

            if ($delimiter === '}') {
                ++$position;

                return;
            }

            if ($delimiter !== ',') {
                throw new RuntimeException('The JSON object delimiter is invalid.');
            }

            ++$position;
            $this->skipWhitespace($json, $position);
        }
    }

    /**
     * Parses one JSON array without treating repeated values as object members
     */
    private function parseArray(string $json, int &$position, int $depth): void
    {
        ++$position;
        $this->skipWhitespace($json, $position);

        if (($json[$position] ?? null) === ']') {
            ++$position;

            return;
        }

        while (true) {
            $this->parseValue($json, $position, $depth + 1);
            $this->skipWhitespace($json, $position);
            $delimiter = $json[$position] ?? null;

            if ($delimiter === ']') {
                ++$position;

                return;
            }

            if ($delimiter !== ',') {
                throw new RuntimeException('The JSON array delimiter is invalid.');
            }

            ++$position;
            $this->skipWhitespace($json, $position);
        }
    }

    /**
     * Parses and decodes one JSON string, including escapes and surrogate pairs
     */
    private function parseString(string $json, int &$position): string
    {
        $start = $position++;
        $length = strlen($json);

        while ($position < $length) {
            $character = $json[$position];

            if ($character === '"') {
                ++$position;
                $decoded = json_decode(
                    substr($json, $start, $position - $start),
                    flags: JSON_THROW_ON_ERROR
                );

                return (string) $decoded;
            }

            if (ord($character) < 0x20) {
                throw new RuntimeException('The JSON string contains a control character.');
            }

            if ($character !== '\\') {
                ++$position;

                continue;
            }

            ++$position;
            $escape = $json[$position] ?? null;

            if ($escape === 'u') {
                $hexadecimal = substr($json, $position + 1, 4);

                if (strlen($hexadecimal) !== 4 || preg_match('/\A[0-9a-fA-F]{4}\z/D', $hexadecimal) !== 1) {
                    throw new RuntimeException('The JSON Unicode escape is invalid.');
                }

                $position += 5;

                continue;
            }

            if (!is_string($escape) || !str_contains('"\\/bfnrt', $escape)) {
                throw new RuntimeException('The JSON escape is invalid.');
            }

            ++$position;
        }

        throw new RuntimeException('The JSON string is unterminated.');
    }

    /**
     * Parses one fixed JSON literal
     */
    private function parseLiteral(string $json, int &$position, string $literal): void
    {
        if (substr($json, $position, strlen($literal)) !== $literal) {
            throw new RuntimeException('The JSON literal is invalid.');
        }

        $position += strlen($literal);
    }

    /**
     * Parses one JSON number without coercing it into a platform integer
     */
    private function parseNumber(string $json, int &$position): void
    {
        if (
            preg_match(
                '/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/D',
                $json,
                $matches,
                offset: $position
            ) !== 1
        ) {
            throw new RuntimeException('The JSON number is invalid.');
        }

        $position += strlen($matches[0]);
    }

    /**
     * Advances over JSON whitespace only
     */
    private function skipWhitespace(string $json, int &$position): void
    {
        $length = strlen($json);

        while ($position < $length && str_contains(" \t\r\n", $json[$position])) {
            ++$position;
        }
    }
}
