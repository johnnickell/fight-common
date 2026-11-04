<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;

/**
 * Class DbalTableName
 *
 * Validated ordinary table identifier shared by DBAL adapters
 */
final readonly class DbalTableName
{
    /**
     * Constructs DbalTableName
     */
    public function __construct(private string $name)
    {
        if (1 !== preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,62}\z/', $name)) {
            throw new InvalidArgumentException(
                'Table names require [A-Za-z_][A-Za-z0-9_]{0,62}; qualification is unsupported.'
            );
        }
    }

    /**
     * Returns the unquoted logical name for schema inspection
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the platform-quoted SQL identifier
     */
    public function quoted(AbstractPlatform $platform): string
    {
        return $platform->quoteSingleIdentifier($this->name);
    }

    /**
     * Returns the explicitly quoted DBAL schema asset name
     *
     * DBAL schema assets interpret backticks as a portable quoting marker,
     * then render the identifier with the target platform's delimiters.
     */
    public function schemaName(): string
    {
        return '`'.$this->name.'`';
    }
}
