<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpToolSchema
 *
 * @internal
 */
final class McpToolSchema
{
    /**
     * @var list<string>
     */
    private const array TYPES = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];

    /**
     * Creates a validated declaration for the supported JSON Schema 2020-12 profile
     *
     * @param array<mixed> $schema
     */
    public static function create(array $schema, bool $input): StrictJson
    {
        if ($schema !== [] && array_is_list($schema)) {
            throw new DomainException('A Tool schema must be an object declaration.');
        }

        $value = StrictJson::fromObject($schema);
        if ($input && $value->get('type') !== 'object') {
            throw new DomainException('A Tool input schema must declare type object.');
        }

        return self::validateObject($value);
    }

    /**
     * Validates and normalizes a schema object without mutating consumer data
     */
    private static function validateObject(StrictJson $schema): StrictJson
    {
        $properties = [];
        foreach ($schema->properties() as $keyword => $value) {
            $value = match ($keyword) {
                'properties', '$defs' => self::schemaMap($value),
                'items', 'additionalProperties', 'not' => self::validate($value),
                'allOf', 'anyOf', 'oneOf' => is_array($value) ? array_map(self::validate(...), $value) : $value,
                default => $value,
            };
            if (!self::validKeyword((string) $keyword, $value)) {
                throw new DomainException('A Tool schema contains an unsupported keyword or invalid declaration.');
            }

            $properties[$keyword] = $value;
        }

        return StrictJson::fromObject($properties);
    }

    /**
     * Validates a nested schema node without resolving external references
     */
    private static function validate(mixed $schema): StrictJson|bool
    {
        if (is_bool($schema)) {
            return $schema;
        }

        if (!$schema instanceof StrictJson) {
            throw new DomainException('A nested Tool schema must be a JSON object or Boolean.');
        }

        return self::validateObject($schema);
    }

    /**
     * Creates an object map of validated schemas including the empty PHP-array shorthand
     */
    private static function schemaMap(mixed $value): StrictJson
    {
        if ($value === []) {
            return StrictJson::fromObject();
        }

        if (!$value instanceof StrictJson) {
            throw new DomainException('A Tool schema map must be a JSON object.');
        }

        return StrictJson::fromObject(array_map(self::validate(...), $value->properties()));
    }

    /**
     * Returns whether a supported keyword has a valid declaration
     */
    private static function validKeyword(string $keyword, mixed $value): bool
    {
        return match ($keyword) {
            '$schema' => $value === 'https://json-schema.org/draft/2020-12/schema',
            'type' => self::validTypes($value),
            'properties', '$defs', 'items', 'additionalProperties', 'not' => true,
            'allOf', 'anyOf', 'oneOf' => is_array($value) && $value !== [],
            'required' => self::validStringList($value),
            'enum' => is_array($value) && $value !== [],
            'const', 'default' => true,
            'examples' => is_array($value),
            'title', 'description' => is_string($value),
            'minLength', 'maxLength', 'minItems', 'maxItems', 'minProperties', 'maxProperties' =>
                is_int($value) && $value >= 0,
            'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum' => is_int($value) || is_float($value),
            'multipleOf' => (is_int($value) || is_float($value)) && $value > 0,
            'uniqueItems', 'readOnly', 'writeOnly', 'deprecated' => is_bool($value),
            'x-mcp-header' => is_string($value) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9-]*\z/D', $value) === 1,
            default => false,
        };
    }

    /**
     * Returns whether one type or a non-empty unique type list is supported
     */
    private static function validTypes(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array($value, self::TYPES, true);
        }

        return is_array($value) && $value !== [] && self::validStringList($value)
            && array_all($value, fn(string $type): bool => in_array($type, self::TYPES, true));
    }

    /**
     * Returns whether a value is a unique list of strings
     */
    private static function validStringList(mixed $value): bool
    {
        return is_array($value) && array_all($value, fn(mixed $item): bool => is_string($item))
            && count(array_unique($value)) === count($value);
    }
}
