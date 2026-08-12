<?php

declare(strict_types=1);

namespace Fight\Common\Standards;

use Fight\Common\Domain\DomainDependsOnPsr;

final readonly class StandardsDependsOnDomain
{
    public function __construct(private DomainDependsOnPsr $dependency) {}
}
