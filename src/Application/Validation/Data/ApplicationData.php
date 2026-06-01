<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Data;

use Fight\Common\Domain\Collection\Contract\Collection;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Common\Domain\Type\Arrayable;
use Traversable;

/**
 * @implements Collection<string, mixed>
 */
final readonly class ApplicationData implements Arrayable, Collection
{
    /** @var HashTable<string, mixed> */
    private HashTable $data;

    /**
     * Constructs ApplicationData
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = HashTable::of('string');
        foreach ($data as $name => $value) {
            $this->data->set($name, $value);
        }
    }

    /**
     * Retrieves a value by field name
     */
    public function get(string $name, mixed $default = null): mixed
    {
        try {
            return $this->data->get($name);
        } catch (KeyException) {
            return $default;
        }
    }

    /**
     * Checks if a name is defined
     */
    public function has(string $name): bool
    {
        return $this->data->has($name);
    }

    /**
     * Retrieves a list of names
     *
     * @return string[]
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->data->keys() as $name) {
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return $this->data->isEmpty();
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->data->count();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return $this->data->getIterator();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->data as $name => $value) {
            $data[$name] = $value;
        }

        return $data;
    }
}
