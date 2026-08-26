<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Throwable;

/**
 * Class ValueObjectDataType
 *
 * @internal
 */
abstract class ValueObjectDataType extends Type
{
    public const string TYPE_NAME = '';

    protected const string VALUE_CLASS = '';
    protected const string VALUE_FACTORY = 'fromString';
    protected const bool USES_GUID_DECLARATION = false;
    protected const bool USES_CLOB_DECLARATION = false;
    protected const bool EMPTY_PHP_VALUE_IS_NULL = true;

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (static::USES_GUID_DECLARATION) {
            return $platform->getGuidTypeDeclarationSQL($column);
        }

        if (static::USES_CLOB_DECLARATION) {
            return $platform->getClobTypeDeclarationSQL($column);
        }

        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * Converts a value from its PHP representation to its database representation
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (!($value instanceof (static::VALUE_CLASS))) {
            throw InvalidType::new($value, 'string', [static::VALUE_CLASS]);
        }

        return $value->toString();
    }

    /**
     * Converts a value from its database representation to its PHP representation
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?object
    {
        if (static::EMPTY_PHP_VALUE_IS_NULL ? empty($value) : $value === null) {
            return null;
        }

        if ($value instanceof (static::VALUE_CLASS)) {
            return $value;
        }

        try {
            $class = static::VALUE_CLASS;
            $factory = static::VALUE_FACTORY;

            return $class::$factory($value);
        } catch (Throwable $throwable) {
            throw ValueNotConvertible::new($value, static::VALUE_CLASS, $throwable->getMessage(), $throwable);
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
