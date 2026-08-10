<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

/**
 * Interface ProjectionCheckpointStore
 *
 * Stores independent global projection progress by stable projector name
 */
interface ProjectionCheckpointStore
{
    /**
     * Loads the last successfully projected global position
     */
    public function load(string $projectorName): int;

    /**
     * Advances a successfully projected global position without moving backward
     */
    public function save(string $projectorName, int $globalPosition): void;

    /**
     * Clears one projector checkpoint to the start of history
     *
     * Consumers stop the projector and clear or recreate its read model before
     * calling this idempotent administrative operation.
     */
    public function reset(string $projectorName): void;
}
