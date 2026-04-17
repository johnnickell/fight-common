<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Tree;

/**
 * Class RedBlackNode
 */
final class RedBlackNode
{
    public const bool RED = true;
    public const bool BLACK = false;

    private ?RedBlackNode $left = null;
    private ?RedBlackNode $right = null;

    /**
     * Constructs RedBlackNode
     */
    public function __construct(
        private mixed $key,
        private mixed $value,
        private int $size,
        private bool $color
    ) {
    }

    /**
     * Sets the left node
     */
    public function setLeft(?RedBlackNode $left): void
    {
        $this->left = $left;
    }

    /**
     * Retrieves the left node
     */
    public function left(): ?RedBlackNode
    {
        return $this->left;
    }

    /**
     * Sets the right node
     */
    public function setRight(?RedBlackNode $right): void
    {
        $this->right = $right;
    }

    /**
     * Retrieves the right node
     */
    public function right(): ?RedBlackNode
    {
        return $this->right;
    }

    /**
     * Sets the key
     */
    public function setKey(mixed $key): void
    {
        $this->key = $key;
    }

    /**
     * Retrieves the key
     */
    public function key(): mixed
    {
        return $this->key;
    }

    /**
     * Sets the value
     */
    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    /**
     * Retrieves the value
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Sets the size
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * Retrieves the size
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Sets the color flag
     */
    public function setColor(bool $color): void
    {
        $this->color = $color;
    }

    /**
     * Retrieves the color flag
     */
    public function color(): bool
    {
        return $this->color;
    }

    /**
     * Handles deep cloning
     */
    public function __clone(): void
    {
        if ($this->left !== null) {
            $left = clone $this->left;
            $this->left = $left;
        }

        if ($this->right !== null) {
            $right = clone $this->right;
            $this->right = $right;
        }
    }
}
