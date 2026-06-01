<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Data;

use Fight\Common\Domain\Collection\Contract\Collection;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Common\Domain\Type\Arrayable;
use Traversable;

/**
 * Class ErrorData
 *
 * @implements Collection<string, mixed>
 */
final readonly class ErrorData implements Arrayable, Collection
{
    /** @var HashTable<string, HashSet<string>> */
    private HashTable $data;

    /**
     * Constructs ErrorData
     *
     * @param array<string, string[]> $data An associated array keyed by field name
     *                                       Values must be arrays of error messages
     */
    public function __construct(array $data)
    {
        $this->data = HashTable::of('string', HashSet::class);
        foreach ($data as $name => $messages) {
            $set = HashSet::of('string');
            foreach ($messages as $message) {
                $set->add($message);
            }

            $this->data->set($name, $set);
        }
    }

    /**
     * Retrieves a list of errors by field name
     *
     * @return string[]
     */
    public function get(string $name): array
    {
        $errors = [];

        try {
            /** @var HashSet<string> $set */
            $set = $this->data->get($name);

            foreach ($set as $message) {
                $errors[] = $message;
            }

            return $errors;
        } catch (KeyException) {
            return $errors;
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
     * @return array<string, string[]>
     */
    public function toArray(): array
    {
        $errors = [];

        /** @var string $name @var HashSet $messages */
        foreach ($this->data as $name => $messages) {
            $errors[$name] = [];
            foreach ($messages as $message) {
                $errors[$name][] = $message;
            }
        }

        return $errors;
    }
}
