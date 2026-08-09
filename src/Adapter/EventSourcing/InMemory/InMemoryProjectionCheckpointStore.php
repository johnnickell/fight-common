<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Application\EventSourcing\ProjectionCheckpointStore;
use InvalidArgumentException;

/**
 * In-memory reference adapter for projection checkpoints
 */
final class InMemoryProjectionCheckpointStore implements ProjectionCheckpointStore
{
    /** @var array<string, int> */
    private array $checkpoints = [];

    /**
     * Loads a projector checkpoint, defaulting to the start of history
     */
    public function load(string $projectorName): int
    {
        return $this->checkpoints[$projectorName] ?? 0;
    }

    /**
     * Saves a projector checkpoint without moving backward
     */
    public function save(string $projectorName, int $globalPosition): void
    {
        $checkpoint = $this->load($projectorName);

        if ($globalPosition < $checkpoint) {
            throw new InvalidArgumentException(sprintf(
                'Projection checkpoint %s cannot move backward from %d to %d.',
                $projectorName,
                $checkpoint,
                $globalPosition,
            ));
        }

        $this->checkpoints[$projectorName] = $globalPosition;
    }

    /**
     * Resets one projector checkpoint to the start of history
     */
    public function reset(string $projectorName): void
    {
        $this->checkpoints[$projectorName] = 0;
    }
}
