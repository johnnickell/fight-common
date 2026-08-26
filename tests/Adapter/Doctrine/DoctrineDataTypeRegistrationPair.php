<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use InvalidArgumentException;

/**
 * Registers legacy and canonical DBAL type identities in isolated registries.
 */
final readonly class DoctrineDataTypeRegistrationPair
{
    public function __construct(
        private string $typeName,
        private Type $legacyType,
        private Type $canonicalType,
    ) {
        if ($legacyType === $canonicalType) {
            throw new InvalidArgumentException('Legacy and canonical types must use separate DBAL type instances.');
        }
    }

    public function legacyType(): Type
    {
        return $this->legacyType;
    }

    public function canonicalType(): Type
    {
        return $this->canonicalType;
    }

    public function registerLegacy(TypeRegistry $registry): void
    {
        $registry->register($this->typeName, $this->legacyType);
    }

    public function registerCanonical(TypeRegistry $registry): void
    {
        $registry->register($this->typeName, $this->canonicalType);
    }
}
