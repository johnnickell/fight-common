<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Application\Mcp\Tool\McpToolSchema;
use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpElicitationSchema
 *
 * @internal
 */
final class McpElicitationSchema
{
    /**
     * Creates a flat form schema using the documented bounded elicitation profile
     *
     * @param array<mixed> $declaration
     */
    public static function create(array $declaration): StrictJson
    {
        $schema = McpToolSchema::create($declaration, true);
        $properties = $schema->get('properties');
        if (
            array_diff(array_keys($schema->properties()), ['$schema', 'type', 'properties', 'required']) !== []
            || !$properties instanceof StrictJson
            || array_diff($schema->get('required') ?? [], array_keys($properties->properties())) !== []
        ) {
            throw new DomainException('An elicitation schema requires a flat object with declared required fields.');
        }

        foreach ($properties->properties() as $name => $field) {
            if (!is_string($name) || !$field instanceof StrictJson || !self::validField($field)) {
                throw new DomainException('Unsupported elicitation field declaration.');
            }
        }

        return $schema;
    }

    /**
     * Returns whether a field belongs to the restricted form vocabulary
     */
    private static function validField(StrictJson $field): bool
    {
        $type = $field->get('type');
        $keywords = match ($type) {
            'string' => ['minLength', 'maxLength', 'enum', 'oneOf'],
            'number', 'integer' => ['minimum', 'maximum'],
            'boolean' => [],
            'array' => ['minItems', 'maxItems', 'items'],
            default => null,
        };
        if (
            $keywords === null
            || array_diff(
                array_keys($field->properties()),
                ['type', 'title', 'description', 'default', ...$keywords]
            ) !== []
            || ($field->has('enum') && !self::stringEnum($field->get('enum')))
            || ($field->has('oneOf') && !self::titledEnum($field->get('oneOf')))
            || ($type === 'array' && !self::multiSelect($field->get('items')))
        ) {
            return false;
        }

        return !$field->has('default') || McpToolSchemaValidator::matches($field->get('default'), $field);
    }

    /**
     * Returns whether a multi-select field contains only string enumeration items
     */
    private static function multiSelect(mixed $items): bool
    {
        if (!$items instanceof StrictJson) {
            return false;
        }

        if ($items->has('anyOf')) {
            return array_keys($items->properties()) === ['anyOf'] && self::titledEnum($items->get('anyOf'));
        }

        return array_diff(array_keys($items->properties()), ['type', 'enum']) === []
            && $items->get('type') === 'string' && self::stringEnum($items->get('enum'));
    }

    /**
     * Returns whether enumeration choices are distinct strings
     */
    private static function stringEnum(mixed $values): bool
    {
        return is_array($values) && $values !== []
            && array_all($values, fn(mixed $value): bool => is_string($value))
            && count(array_unique($values)) === count($values);
    }

    /**
     * Returns whether normalized labeled choices contain only a string constant and title
     *
     * @param non-empty-list<StrictJson|bool> $choices
     */
    private static function titledEnum(array $choices): bool
    {
        $values = [];
        foreach ($choices as $choice) {
            if (
                !$choice instanceof StrictJson
                || array_diff(array_keys($choice->properties()), ['const', 'title']) !== []
                || !is_string($choice->get('const')) || !is_string($choice->get('title'))
            ) {
                return false;
            }

            $values[] = $choice->get('const');
        }

        return self::stringEnum($values);
    }
}
