<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Domain\Exception\DomainException;
use stdClass;

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
     * Creates a validated declaration snapshot for the supported JSON Schema 2020-12 profile
     *
     * @param array<mixed> $schema
     */
    public static function encode(array $schema, bool $input): string
    {
        if ($schema !== [] && array_is_list($schema)) {
            throw new DomainException('A Tool schema must be an object declaration.');
        }

        // Snapshot first: validation must neither retain consumer objects nor execute their serializers.
        $value = json_decode(McpToolJson::encode((object) $schema), false, 512, JSON_THROW_ON_ERROR);
        if ($input && ($value->type ?? null) !== 'object') {
            throw new DomainException('A Tool input schema must declare type object.');
        }

        self::validate($value);

        return McpToolJson::encode($value);
    }

    /**
     * Validates a schema node without evaluating data or resolving external references
     */
    private static function validate(mixed $schema): void
    {
        if (is_bool($schema)) {
            return;
        }

        if (!$schema instanceof stdClass) {
            throw new DomainException('A nested Tool schema must be a JSON object or Boolean.');
        }

        foreach (get_object_vars($schema) as $keyword => $value) {
            if (!self::validKeyword((string) $keyword, $value)) {
                throw new DomainException('A Tool schema contains an unsupported keyword or invalid declaration.');
            }

            // Empty PHP arrays at object-schema positions are the convenient spelling of an empty JSON object.
            if (in_array($keyword, ['properties', '$defs'], true) && $value === []) {
                $schema->{$keyword} = new stdClass();
            }
        }
    }

    /**
     * Returns whether a supported keyword has a valid declaration
     */
    private static function validKeyword(string $keyword, mixed $value): bool
    {
        return match ($keyword) {
            '$schema' => $value === 'https://json-schema.org/draft/2020-12/schema',
            'type' => self::validTypes($value),
            'properties', '$defs' => self::validSchemaMap($value),
            'items', 'additionalProperties', 'not' => self::validSchema($value),
            'allOf', 'anyOf', 'oneOf' => is_array($value) && $value !== []
                && array_all($value, self::validSchema(...)),
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
     * Returns whether a schema node is valid
     */
    private static function validSchema(mixed $value): bool
    {
        self::validate($value);

        return true;
    }

    /**
     * Returns whether a map contains only valid schema nodes
     */
    private static function validSchemaMap(mixed $value): bool
    {
        if ($value === []) {
            return true;
        }

        if (!$value instanceof stdClass) {
            return false;
        }

        return array_all(get_object_vars($value), self::validSchema(...));
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
