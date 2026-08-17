<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

/**
 * Class EventMapping
 *
 * Maps one local stored name to its current event class and schema
 */
final readonly class EventMapping
{
    /** @var array<Upcaster> */
    private array $upcasters;

    /**
     * Constructs EventMapping
     *
     * @param string   $localName
     * @param string   $eventClass
     * @param integer  $currentSchemaVersion
     * @param iterable $upcasters
     *
     * @phpstan-param class-string $eventClass
     * @phpstan-param iterable<Upcaster> $upcasters
     */
    public function __construct(
        private string $localName,
        private string $eventClass,
        private int $currentSchemaVersion,
        iterable $upcasters = []
    ) {
        $this->upcasters = [...$upcasters];
    }

    /**
     * Returns the locally unique stored name
     */
    public function localName(): string
    {
        return $this->localName;
    }

    /**
     * Returns the current event class
     *
     * @return class-string
     */
    public function eventClass(): string
    {
        return $this->eventClass;
    }

    /**
     * Returns the current payload schema version
     */
    public function currentSchemaVersion(): int
    {
        return $this->currentSchemaVersion;
    }

    /**
     * Returns the declared payload-schema transformations
     *
     * @return array<Upcaster>
     */
    public function upcasters(): array
    {
        return $this->upcasters;
    }
}
