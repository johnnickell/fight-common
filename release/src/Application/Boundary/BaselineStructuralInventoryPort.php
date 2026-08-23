<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface BaselineStructuralInventoryPort
 */
interface BaselineStructuralInventoryPort
{
    /**
     * Exports and inventories the immutable baseline source
     *
     * @return array<string, mixed>
     */
    public function baselineStructuralInventory(string $commitOid, string $workspace): array;
}
