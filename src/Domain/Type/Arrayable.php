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
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
