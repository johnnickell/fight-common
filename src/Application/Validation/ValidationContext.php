<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\KeyException;

/**
 * Class ValidationContext
 */
final readonly class ValidationContext
{
    private HashTable $errors;

    /**
     * Constructs ValidationContext
     */
    public function __construct(private InputData $input)
    {
        $this->errors = HashTable::of('string', HashSet::class);
    }

    /**
     * Retrieves a value by field name
     *
     * @throws KeyException
     */
    public function get(string $name): mixed
    {
        return $this->input->get($name);
    }

    /**
     * Checks if there are errors
     */
    public function hasErrors(): bool
    {
        return !$this->errors->isEmpty();
    }

    /**
     * Adds an error
     */
    public function addError(string $name, string $message): void
    {
        if (!$this->errors->has($name)) {
            $this->errors->set($name, HashSet::of('string'));
        }

        /** @var HashSet $messages */
        $messages = $this->errors->get($name);
        $messages->add($message);
    }

    /**
     * Retrieves the collection of errors
     */
    public function getErrors(): array
    {
        $errors = [];

        /** @var string $name @var HashSet $messages */
        foreach ($this->errors as $name => $messages) {
            $errors[$name] = [];
            foreach ($messages as $message) {
                $errors[$name][] = $message;
            }
        }

        return $errors;
    }
}
