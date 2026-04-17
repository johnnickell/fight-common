<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Contract;

use Countable;
use IteratorAggregate;

/**
 * Interface Collection
 */
interface Collection extends Countable, IteratorAggregate
{
    /**
     * Checks if the collection is empty
     */
    public function isEmpty(): bool;
}
