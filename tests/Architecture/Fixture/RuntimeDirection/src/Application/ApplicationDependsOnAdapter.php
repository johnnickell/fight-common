<?php

declare(strict_types=1);

namespace Fight\Common\Application\Fixture;

use Fight\Common\Adapter\Fixture\AdapterDependency;

final readonly class ApplicationDependsOnAdapter
{
    public function __construct(private AdapterDependency $dependency)
    {
    }
}
