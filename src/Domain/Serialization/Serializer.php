<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Serialization;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Interface Serializer
 */
interface Serializer
{
    /**
     * Creates instance from a serialized state
     *
     * @throws DomainException When the state is not valid
     */
    public function deserialize(string $state): Serializable;

    /**
     * Retrieves serialized state from an object
     */
    public function serialize(Serializable $object): string;
}
