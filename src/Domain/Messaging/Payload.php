<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Interface Payload
 */
interface Payload extends Arrayable
{
    /**
     * Creates instance from array representation
     *
     * @throws DomainException When data is not valid
     */
    public static function fromArray(array $data): static;

    /**
     * Retrieves an array representation
     */
    public function toArray(): array;
}
