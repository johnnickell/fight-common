<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Doctrine;

use Override;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Domain\Value\Basic\StringObject;
use Throwable;

/**
 * Class StringTextDataType
 */
final class StringTextDataType extends Type
{
    public const string TYPE_NAME = 'common_string_text';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
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

        if (!($value instanceof StringObject)) {
            throw InvalidType::new($value, 'string', [StringObject::class]);
        }

        return $value->toString();
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?StringObject
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StringObject) {
            return $value;
        }

        try {
            return StringObject::fromString($value);
        } catch (Throwable $e) {
            throw ValueNotConvertible::new($value, StringObject::class, $e->getMessage(), $e);
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
