<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Chain;

/**
 * Class ItemBucket
 */
final class ItemBucket implements Bucket
{
    private ?Bucket $next = null;
    private ?Bucket $prev = null;

    /**
     * Constructs ItemBucket
     */
    public function __construct(private readonly mixed $item)
    {
    }

    /**
     * @inheritDoc
     */
    public function setNext(?Bucket $next): void
    {
        $this->next = $next;
    }

    /**
     * @inheritDoc
     */
    public function next(): ?Bucket
    {
        return $this->next;
    }

    /**
     * @inheritDoc
     */
    public function setPrev(?Bucket $prev): void
    {
        $this->prev = $prev;
    }

    /**
     * @inheritDoc
     */
    public function prev(): ?Bucket
    {
        return $this->prev;
    }

    /**
     * Retrieves the item
     */
    public function item(): mixed
    {
        return $this->item;
    }
}
