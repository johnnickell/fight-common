<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

/**
 * Interface Upcaster
 *
 * Transforms one stored event payload schema into the next schema
 */
interface Upcaster
{
    /**
     * Returns the schema version accepted by this step
     */
    public function sourceSchemaVersion(): int;

    /**
     * Returns the schema version produced by this step
     */
    public function targetSchemaVersion(): int;

    /**
     * Converts stored payload data in memory
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function upcast(array $data): array;
}
