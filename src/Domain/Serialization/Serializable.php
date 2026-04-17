<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Serialization;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Interface Serializable
 */
interface Serializable
{
    /**
     * Creates instance from a serialized representation
     *
     * @throws DomainException When the data is not valid
     */
    public static function arrayDeserialize(array $data): static;

    /**
     * Retrieves a serialized representation
     */
    public function arraySerialize(): array;
}
