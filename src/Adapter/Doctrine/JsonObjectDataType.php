<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Doctrine;

use Override;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Throwable;

/**
 * Class JsonObjectDataType
 */
final class JsonObjectDataType extends Type
{
    public const string TYPE_NAME = 'common_json';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * Converts a value from its PHP representation to its database representation
     *
     * @throws ConversionException When the conversion fails
     */
    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (!($value instanceof JsonObject)) {
            throw InvalidType::new($value, 'string', [JsonObject::class]);
        }

        return $value->toString();
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?JsonObject
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof JsonObject) {
            return $value;
        }

        try {
            return JsonObject::fromString($value);
        } catch (Throwable $e) {
            throw ValueNotConvertible::new($value, JsonObject::class, $e->getMessage(), $e);
        }
    }

    /**
     * Gets the name of this type
     */
    public function getName(): string
    {
        return static::TYPE_NAME;
    }
}
