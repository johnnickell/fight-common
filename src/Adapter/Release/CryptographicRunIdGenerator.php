<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Release;

use Fight\Common\Application\Release\Boundary\RunIdGenerator;

/**
 * Class CryptographicRunIdGenerator
 *
 * Generates release-run identities from operating-system entropy.
 */
final readonly class CryptographicRunIdGenerator implements RunIdGenerator
{
    /**
     * Generates one lowercase SHA-256-width run identity
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
