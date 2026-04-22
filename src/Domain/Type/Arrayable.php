<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Type;

/**
 * Interface Arrayable
 */
interface Arrayable
{
    /**
     * Retrieves an array representation
     */
    public function toArray(): array;
}
