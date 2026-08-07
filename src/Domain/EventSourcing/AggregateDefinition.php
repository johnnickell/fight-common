<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\Exception\DomainException;
use ReflectionClass;

/**
 * Stable repository configuration for one aggregate type
 */
final readonly class AggregateDefinition
{
    /**
     * Constructs AggregateDefinition
     *
     * @param string                               $name
     * @param class-string<EventSourcedAggregate> $aggregateClass
     */
    public function __construct(
        private string $name,
        private string $aggregateClass,
    ) {
        if ('' === $name) {
            throw new DomainException('Aggregate name cannot be empty.');
        }

        if (
            !class_exists($aggregateClass)
            || !is_subclass_of($aggregateClass, EventSourcedAggregate::class)
            || new ReflectionClass($aggregateClass)->isAbstract()
        ) {
            throw new DomainException('Aggregate class must be a concrete EventSourcedAggregate.');
        }
    }

    /**
     * Returns the stable aggregate name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the current aggregate class
     *
     * @return class-string<EventSourcedAggregate>
     */
    public function aggregateClass(): string
    {
        return $this->aggregateClass;
    }
}
