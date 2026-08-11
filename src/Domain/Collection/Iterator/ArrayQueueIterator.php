<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Iterator;

use Iterator;

/**
 * Class ArrayQueueIterator
 *
 * @implements Iterator<int, mixed>
 */
final class ArrayQueueIterator implements Iterator
{
    private int $index = 0;
    private readonly int $count;

    /**
     * Constructs ArrayQueueIterator
     *
     * @param array<mixed> $items
     */
    public function __construct(
        private array $items,
        private readonly int $front,
        private readonly int $cap
    ) {
        $this->count = count($this->items);
    }

    /**
     * Initializes the iterator at the first item
     */
    public function rewind(): void
    {
        $this->index = 0;
    }

    /**
     * Checks if the current index is valid
     */
    public function valid(): bool
    {
        return $this->index < $this->count;
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

        $index = $this->index;
        $front = $this->front;
        $cap = $this->cap;
        $offset = ($index + $front) % $cap;

        return $this->items[$offset];
    }

    /**
     * Advances the iterator to the next item
     */
    public function next(): void
    {
        $this->index++;
    }
}
