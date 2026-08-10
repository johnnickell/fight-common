<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Iterator;

use Iterator;

/**
 * Class ArrayStackIterator
 *
 * @implements Iterator<int, mixed>
 */
final class ArrayStackIterator implements Iterator
{
    private int $index;
    private readonly int $count;

    /**
     * Constructs ArrayStackIterator
     *
     * @param array<mixed> $items
     */
    public function __construct(private array $items)
    {
        $this->count = count($this->items);
        $this->index = $this->count - 1;
    }

    /**
     * Initializes the iterator at the top item
     */
    public function rewind(): void
    {
        $this->index = $this->count - 1;
    }

    /**
     * Checks if the current index is valid
     */
    public function valid(): bool
    {
        return $this->index >= 0;
    }

    /**
     * Retrieves the current key
     */
    public function key(): ?int
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->index;
    }

    /**
     * Retrieves the current item
     */
    public function current(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->items[$this->index];
    }

    /**
     * Advances the iterator to the next item
     */
    public function next(): void
    {
        $this->index--;
    }
}
