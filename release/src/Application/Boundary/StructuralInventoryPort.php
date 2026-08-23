<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface StructuralInventoryPort
 */
interface StructuralInventoryPort
{
    /**
     * Returns runtime declarations without assigning compatibility policy
     *
     * @return array<string, mixed>
     */
    public function structuralInventory(string $sourceRoot, string $sourceOid): array;
}
