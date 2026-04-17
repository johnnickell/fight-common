<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Type;

/**
 * Interface Arrayble
 */
interface Arrayble
{
    /**
     * Retrieves an array representation
     */
    public function toArray(): array;
}
