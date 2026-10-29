<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Observation;

/**
 * Interface ObservationRepository
 *
 * Retained evidence, not an authorized public inspection Query
 */
interface ObservationRepository
{
    /**
     * Returns recorded evidence by its producer-scoped identity
     */
    public function getById(string $producerId, ObservationId $id): ?Observation;

    /**
     * Adds immutable recorded evidence inside the recording transaction
     */
    public function add(Observation $observation): void;
}
