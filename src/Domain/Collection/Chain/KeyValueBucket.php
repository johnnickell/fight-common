<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Chain;

/**
 * Class KeyValueBucket
 */
final class KeyValueBucket implements Bucket
{
    private ?Bucket $next = null;
    private ?Bucket $prev = null;

    /**
     * Constructs KeyValueBucket
     */
    public function __construct(private readonly mixed $key, private readonly mixed $value)
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
     * Retrieves the key
     */
    public function key(): mixed
    {
        return $this->key;
    }

    /**
     * Retrieves the value
     */
    public function value(): mixed
    {
        return $this->value;
    }
}
