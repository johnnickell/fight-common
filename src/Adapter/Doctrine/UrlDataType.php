<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Doctrine;

use Override;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Domain\Value\Internet\Url;
use Throwable;

/**
 * Class UrlDataType
 */
final class UrlDataType extends Type
{
    public const string TYPE_NAME = 'common_url';

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

        if (!($value instanceof Url)) {
            throw InvalidType::new($value, 'string', [Url::class]);
        }

        return $value->toString();
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Url
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Url) {
            return $value;
        }

        try {
            return Url::fromString($value);
        } catch (Throwable $e) {
            throw ValueNotConvertible::new($value, Url::class, $e->getMessage(), $e);
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
