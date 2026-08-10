<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Application\EventSourcing\PublicationCursorStore;
use InvalidArgumentException;

/**
 * Class InMemoryPublicationCursorStore
 *
 * In-memory reference adapter for event-publication cursors
 */
final class InMemoryPublicationCursorStore implements PublicationCursorStore
{
    /** @var array<string, int> */
    private array $cursors = [];

    /**
     * Loads a publication cursor, defaulting to the start of history
     */
    public function load(string $publicationName): int
    {
        return $this->cursors[$publicationName] ?? 0;
    }

    /**
     * Advances a completed fan-out position without moving backward
     */
    public function save(string $publicationName, int $globalPosition): void
    {
        $cursor = $this->load($publicationName);

        if ($globalPosition < $cursor) {
            throw new InvalidArgumentException(sprintf(
                'Publication cursor %s cannot move backward from %d to %d.',
                $publicationName,
                $cursor,
                $globalPosition,
            ));
        }

        $this->cursors[$publicationName] = $globalPosition;
    }
}
