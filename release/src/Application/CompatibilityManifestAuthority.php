<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use Fight\Release\Application\Boundary\CompatibilityInputPort;
use Fight\Release\Application\Boundary\GitPort;
use Fight\Release\Application\Boundary\StructuralInventoryPort;

/**
 * Interface CompatibilityManifestAuthority
 */
interface CompatibilityManifestAuthority
{
    /**
     * Validates one committed policy against exact current repository authority
     *
     * @return array<string, mixed>
     */
    public function validate(
        string $manifestPath,
        string $repository,
        CompatibilityInputPort $input,
        StructuralInventoryPort $inventory,
        GitPort $git
    ): array;
}
