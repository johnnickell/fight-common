<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Identity;

/**
 * Interface IdentifierFactory
 */
interface IdentifierFactory
{
    /**
     * Generates a unique identifier
     */
    public static function generate(): Identifier;
}
