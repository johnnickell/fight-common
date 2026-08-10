<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Domain\EventSourcing\Exception\EventMappingException;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;

/**
 * Class EventMapper
 *
 * Maps current event messages to and from stable storage identity
 */
final class EventMapper
{
    /** @var array<string, array{EventMapping, class-string<Event>}> */
    private array $mappingsByName = [];
    /** @var array<class-string<Event>, array{string, EventMapping}> */
    private array $mappingsByClass = [];

    /**
     * Constructs EventMapper
     *
     * @param iterable $providers
     *
     * @phpstan-param iterable<EventMappingProvider> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    /**
     * Registers all mappings declared by one bounded context
     */
    public function registerProvider(EventMappingProvider $provider): void
    {
        $namespace = $provider->namespace();
        $this->guardValidName($namespace);

        foreach ($provider->mappings() as $mapping) {
            $this->register($namespace, $mapping);
        }
    }

    /**
     * Registers one typed event mapping under its durable namespace
     */
    public function register(string $namespace, EventMapping $mapping): void
    {
        $this->guardValidName($namespace);

        $eventClass = $this->guardValidMapping($mapping);
        $eventName = sprintf('%s.%s', $namespace, $mapping->localName());

        if (isset($this->mappingsByName[$eventName])) {
            throw new EventMappingException(sprintf('Duplicate event alias: %s.', $eventName));
        }

        if (isset($this->mappingsByClass[$eventClass])) {
            throw new EventMappingException(sprintf('Duplicate event class: %s.', $eventClass));
        }

        $this->mappingsByName[$eventName] = [$mapping, $eventClass];
        $this->mappingsByClass[$eventClass] = [$eventName, $mapping];
    }

    /**
     * Maps a current event message to storage-facing data
     */
    public function map(EventMessage $message): MappedEvent
    {
        /** @var Event $event */
        $event = $message->payload();
        $eventClass = $event::class;

        if (!isset($this->mappingsByClass[$eventClass])) {
            throw new EventMappingException(sprintf('Unknown event class: %s.', $eventClass));
        }

        [$eventName, $mapping] = $this->mappingsByClass[$eventClass];

        return new MappedEvent(
            $eventName,
            $mapping->currentSchemaVersion(),
            $event->toArray(),
        );
    }

    /**
     * Reconstitutes a current event message from stored identity and data
     *
     * @param string               $eventName
     * @param integer              $schemaVersion
     * @param array<string, mixed> $data
     * @param MessageId            $id
     * @param DateTimeImmutable    $timestamp
     * @param Meta                 $meta
     */
    public function hydrate(
        string $eventName,
        int $schemaVersion,
        array $data,
        MessageId $id,
        DateTimeImmutable $timestamp,
        Meta $meta,
    ): EventMessage {
        if (!isset($this->mappingsByName[$eventName])) {
            throw new EventMappingException(sprintf('Unknown event alias: %s.', $eventName));
        }

        [$mapping, $eventClass] = $this->mappingsByName[$eventName];

        if ($schemaVersion < 1 || $schemaVersion > $mapping->currentSchemaVersion()) {
            throw new EventMappingException(sprintf(
                'Unsupported schema version %d for event %s.',
                $schemaVersion,
                $eventName,
            ));
        }

        $upcastersBySource = [];

        foreach ($mapping->upcasters() as $upcaster) {
            $upcastersBySource[$upcaster->sourceSchemaVersion()] = $upcaster;
        }

        while ($schemaVersion < $mapping->currentSchemaVersion()) {
            $data = $upcastersBySource[$schemaVersion]->upcast($data);
            ++$schemaVersion;
        }

        $event = $eventClass::fromArray($data);

        return new EventMessage($id, $timestamp, $event, $meta);
    }

    /**
     * Validates one durable event-name segment
     */
    private function guardValidName(string $name): void
    {
        if ($name === '') {
            throw new EventMappingException('Event namespace and local name must be non-empty.');
        }
    }

    /**
     * Validates one typed event mapping and its schema evolution chain
     *
     * @return class-string<Event>
     */
    private function guardValidMapping(EventMapping $mapping): string
    {
        $this->guardValidName($mapping->localName());

        if ($mapping->currentSchemaVersion() < 1) {
            throw new EventMappingException('Event schema version must begin at one.');
        }

        $eventClass = $mapping->eventClass();

        if (!is_a($eventClass, Event::class, true)) {
            throw new EventMappingException(sprintf('Mapped class must implement Event: %s.', $eventClass));
        }

        $upcasters = $mapping->upcasters();

        if ($mapping->currentSchemaVersion() === 1) {
            if ($upcasters !== []) {
                throw new EventMappingException('Schema version one mappings cannot declare upcasters.');
            }

            return $eventClass;
        }

        $stepsBySource = [];

        foreach ($upcasters as $upcaster) {
            $source = $upcaster->sourceSchemaVersion();

            if (
                $source < 1
                || $upcaster->targetSchemaVersion() !== $source + 1
                || $upcaster->targetSchemaVersion() > $mapping->currentSchemaVersion()
                || isset($stepsBySource[$source])
            ) {
                throw new EventMappingException(
                    'Event mapping requires one sequential upcaster step per schema version.',
                );
            }

            $stepsBySource[$source] = $upcaster;
        }

        for ($source = 1; $source < $mapping->currentSchemaVersion(); ++$source) {
            if (!isset($stepsBySource[$source])) {
                throw new EventMappingException(
                    'Event mapping requires one sequential upcaster step per schema version.',
                );
            }
        }

        return $eventClass;
    }
}
