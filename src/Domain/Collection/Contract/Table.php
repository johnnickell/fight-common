<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Contract;

use ArrayAccess;
use Fight\Common\Domain\Exception\KeyException;

/**
 * Interface Table
 */
interface Table extends ArrayAccess, KeyValueCollection
{
    /**
     * Sets a key-value pair
     */
    public function set(mixed $key, mixed $value): void;

    /**
     * Retrieves a value by key
     *
     * @throws KeyException When the key is not defined
     */
    public function get(mixed $key): mixed;

    /**
     * Checks if a key is defined
     */
    public function has(mixed $key): bool;

    /**
     * Removes a value by key
     */
    public function remove(mixed $key): void;

    /**
     * Retrieves an iterator for keys
     */
    public function keys(): iterable;
}
