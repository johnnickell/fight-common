<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Doctrine;

use Override;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Domain\Messaging\Meta;
use Throwable;

/**
 * Class MetaDataType
 */
final class MetaDataType extends Type
{
    public const string TYPE_NAME = 'common_meta';

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

        if (!($value instanceof Meta)) {
            throw InvalidType::new($value, 'string', [Meta::class]);
        }

        return $value->toString();
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Meta
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Meta) {
            return $value;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

            return Meta::create($data);
        } catch (Throwable $throwable) {
            throw ValueNotConvertible::new($value, Meta::class, $throwable->getMessage(), $throwable);
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
