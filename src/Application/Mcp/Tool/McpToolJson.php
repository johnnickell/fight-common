<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Domain\Exception\DomainException;
use JsonException;
use stdClass;

/**
 * Class McpToolJson
 *
 * @internal
 */
final class McpToolJson
{
    /**
     * Creates an isolated JSON snapshot without executing consumer serializers
     */
    public static function encode(mixed $value): string
    {
        self::validate($value, 0);
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $jsonException) {
            throw new DomainException('Tool data must contain JSON-safe Unicode values.', 0, $jsonException);
        }
    }

    /**
     * Validates bounded plain JSON values before encoding
     */
    private static function validate(mixed $value, int $depth): void
    {
        if ($depth > 64) {
            throw new DomainException('Tool data exceeds the supported JSON nesting depth.');
        }

        if ($value instanceof stdClass && $value::class === stdClass::class) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                self::validate($item, $depth + 1);
            }

            return;
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        if (is_float($value) && is_finite($value)) {
            return;
        }

        throw new DomainException('Tool data must contain only plain JSON values.');
    }
}
