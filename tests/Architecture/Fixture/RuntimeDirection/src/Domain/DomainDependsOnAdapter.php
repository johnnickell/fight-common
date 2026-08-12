<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Fixture;

use Fight\Common\Adapter\Fixture\AdapterDependency;

final readonly class DomainDependsOnAdapter
{
    public function __construct(private AdapterDependency $dependency)
    {
    }
}
