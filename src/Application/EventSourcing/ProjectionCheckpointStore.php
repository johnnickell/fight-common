<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

/**
 * Stores independent global projection progress by stable projector name
 */
interface ProjectionCheckpointStore
{
    /**
     * Loads the last successfully projected global position
     */
    public function load(string $projectorName): int;

    /**
     * Saves a successfully projected global position without moving backward
     */
    public function save(string $projectorName, int $globalPosition): void;

    /**
     * Resets one projector to the start of history
     *
     * Consumers stop the projector and clear or recreate its read model before
     * calling this idempotent administrative operation.
     */
    public function reset(string $projectorName): void;
}
