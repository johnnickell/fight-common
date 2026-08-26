<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Fight\Common\Domain\Observability\AuditEntryId;

/**
 * Class AuditEntryIdDataType
 *
 * @deprecated Use \Fight\Common\Adapter\Persistence\Doctrine\Type\AuditEntryIdDataType instead.
 */
final class AuditEntryIdDataType extends \Fight\Common\Adapter\Persistence\Doctrine\Type\AuditEntryIdDataType
{
    public const string TYPE_NAME = 'audit_entry_id';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return parent::getSQLDeclaration($column, $platform);
    }

    /**
     * Converts a value from its PHP representation to its database representation
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return parent::convertToDatabaseValue($value, $platform);
    }

    /**
     * Converts a value from its database representation to its PHP representation
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AuditEntryId
    {
        /** @var ?AuditEntryId $converted */
        $converted = parent::convertToPHPValue($value, $platform);

        return $converted;
    }

    /**
     * Gets the name of this type
     */
    public function getName(): string
    {
        return parent::getName();
    }
}
