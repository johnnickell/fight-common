<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use stdClass;

/**
 * Class McpToolSchemaValidator
 *
 * @internal
 */
final class McpToolSchemaValidator
{
    /**
     * Returns whether plain JSON data satisfies an already validated Tool schema
     */
    public static function matches(mixed $value, stdClass|bool $schema): bool
    {
        if (is_bool($schema)) {
            return $schema;
        }

        foreach (get_object_vars($schema) as $keyword => $constraint) {
            $valid = match ($keyword) {
                'type' => array_any((array) $constraint, fn(string $type): bool => self::hasType($value, $type)),
                'const' => self::equal($value, $constraint),
                'enum' => array_any($constraint, fn(mixed $item): bool => self::equal($value, $item)),
                'allOf' => array_all($constraint, fn($item): bool => self::matches($value, $item)),
                'anyOf' => array_any($constraint, fn($item): bool => self::matches($value, $item)),
                'oneOf' => count(array_filter($constraint, fn($item): bool => self::matches($value, $item))) === 1,
                'not' => !self::matches($value, $constraint),
                'properties', 'additionalProperties', 'required', 'minProperties', 'maxProperties' =>
                    !$value instanceof stdClass || self::objectMatches($value, $schema, (string) $keyword, $constraint),
                'items', 'minItems', 'maxItems', 'uniqueItems' =>
                    !is_array($value) || self::arrayMatches($value, (string) $keyword, $constraint),
                'minLength' => !is_string($value) || preg_match_all('/./us', $value) >= $constraint,
                'maxLength' => !is_string($value) || preg_match_all('/./us', $value) <= $constraint,
                'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf' =>
                    (!is_int($value) && !is_float($value))
                    || self::numberMatches($value, (string) $keyword, $constraint),
                default => true,
            };
            if (!$valid) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether a value has the declared JSON type
     */
    private static function hasType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => $value instanceof stdClass,
            'array' => is_array($value),
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value) || (is_float($value) && floor($value) === $value),
            'boolean' => is_bool($value),
            default => $value === null,
        };
    }

    /**
     * Returns whether an object satisfies a property constraint without mutating it
     */
    private static function objectMatches(stdClass $value, stdClass $schema, string $keyword, mixed $constraint): bool
    {
        $properties = get_object_vars($value);
        if ($keyword === 'required') {
            return array_all($constraint, fn(string $key): bool => array_key_exists($key, $properties));
        }

        if ($keyword === 'minProperties' || $keyword === 'maxProperties') {
            return $keyword === 'minProperties' ? count($properties) >= $constraint : count($properties) <= $constraint;
        }

        $declared = get_object_vars($schema->properties ?? new stdClass());
        foreach ($properties as $key => $item) {
            if (
                $keyword === 'properties' && array_key_exists($key, $declared)
                && !self::matches($item, $declared[$key])
            ) {
                return false;
            }

            if ($keyword === 'additionalProperties' && !array_key_exists($key, $declared)) {
                if (!self::matches($item, $constraint)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns whether a list satisfies an item constraint
     *
     * @phpstan-param list<mixed> $value
     */
    private static function arrayMatches(array $value, string $keyword, mixed $constraint): bool
    {
        return match ($keyword) {
            'items' => array_all($value, fn(mixed $item): bool => self::matches($item, $constraint)),
            'minItems' => count($value) >= $constraint,
            'maxItems' => count($value) <= $constraint,
            default => !$constraint || self::unique($value),
        };
    }

    /**
     * Returns whether JSON values are unique under structural numeric-aware equality
     *
     * @phpstan-param list<mixed> $values
     */
    private static function unique(array $values): bool
    {
        foreach ($values as $index => $value) {
            foreach (array_slice($values, 0, $index) as $previous) {
                if (self::equal($value, $previous)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns JSON structural equality without PHP's scalar coercions
     */
    private static function equal(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return self::compare($left, $right) === 0;
        }

        if ($left instanceof stdClass && $right instanceof stdClass) {
            $left = get_object_vars($left);
            $right = get_object_vars($right);
        } elseif (get_debug_type($left) !== get_debug_type($right)) {
            return false;
        }

        if (!is_array($left)) {
            return $left === $right;
        }

        return count($left) === count($right) && array_all(
            $left,
            fn(mixed $value, int|string $key): bool => array_key_exists($key, $right)
                && self::equal($value, $right[$key])
        );
    }

    /**
     * Returns whether a number satisfies a bound or exact decimal multiple
     */
    private static function numberMatches(int|float $value, string $keyword, int|float $constraint): bool
    {
        if ($keyword === 'multipleOf') {
            [$dividend, $divisor] = self::aligned($value, $constraint);
            $remainder = '0';
            foreach (str_split($dividend) as $digit) {
                $remainder = ltrim($remainder.$digit, '0') ?: '0';
                while (self::compareDigits($remainder, $divisor) >= 0) {
                    $remainder = self::subtract($remainder, $divisor);
                }
            }

            return $remainder === '0';
        }

        $comparison = self::compare($value, $constraint);

        return match ($keyword) {
            'minimum' => $comparison >= 0,
            'maximum' => $comparison <= 0,
            'exclusiveMinimum' => $comparison > 0,
            default => $comparison < 0,
        };
    }

    /**
     * Returns exact decimal ordering for the numbers represented by PHP's JSON encoder
     */
    private static function compare(int|float $left, int|float $right): int
    {
        if (($left < 0) !== ($right < 0)) {
            return $left <=> $right;
        }

        [$leftDigits, $rightDigits] = self::aligned($left, $right);
        $comparison = self::compareDigits($leftDigits, $rightDigits);

        return $left < 0 ? -$comparison : $comparison;
    }

    /**
     * Returns unsigned decimal integers on one shared scale without floating-point division
     *
     * @return array{string, string}
     */
    private static function aligned(int|float $left, int|float $right): array
    {
        [$leftDigits, $leftScale] = self::decimal($left);
        [$rightDigits, $rightScale] = self::decimal($right);
        $scale = min($leftScale, $rightScale);

        return [
            ltrim($leftDigits.str_repeat('0', $leftScale - $scale), '0') ?: '0',
            ltrim($rightDigits.str_repeat('0', $rightScale - $scale), '0') ?: '0'
        ];
    }

    /**
     * Returns the unsigned coefficient and power of ten in the canonical JSON number
     *
     * @return array{string, int}
     */
    private static function decimal(int|float $number): array
    {
        $parts = explode('e', ltrim(json_encode($number, JSON_THROW_ON_ERROR), '-'));
        $fraction = explode('.', $parts[0]);
        $scale = (int) ($parts[1] ?? 0) - strlen($fraction[1] ?? '');

        return [implode('', $fraction), $scale];
    }

    /**
     * Returns unsigned integer ordering without converting long strings to floats
     */
    private static function compareDigits(string $left, string $right): int
    {
        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    /**
     * Returns an unsigned decimal difference where left is at least right
     */
    private static function subtract(string $left, string $right): string
    {
        $right = str_pad($right, strlen($left), '0', STR_PAD_LEFT);
        $borrow = 0;
        for ($index = strlen($left) - 1; $index >= 0; --$index) {
            $digit = (int) $left[$index] - (int) $right[$index] - $borrow;
            $borrow = $digit < 0 ? 1 : 0;
            $left[$index] = (string) (($digit + 10) % 10);
        }

        return ltrim($left, '0') ?: '0';
    }
}
