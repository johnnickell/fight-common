<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Doctrine;

use Override;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Domain\Type\Type as SystemType;
use Throwable;

/**
 * Class TypeDataType
 */
final class TypeDataType extends Type
{
    public const string TYPE_NAME = 'common_type';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
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

        if (!($value instanceof SystemType)) {
            throw InvalidType::new($value, 'string', [SystemType::class]);
        }

        return $value->toString();
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SystemType
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof SystemType) {
            return $value;
        }

        try {
            return SystemType::create($value);
        } catch (Throwable $e) {
            throw ValueNotConvertible::new($value, SystemType::class, $e->getMessage(), $e);
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
