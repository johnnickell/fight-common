<?php

declare(strict_types=1);

namespace Fight\Common\Application;

use Fight\Common\Shared\UnassignedDependency;

final readonly class ApplicationDependsOnUnassigned
{
    public function __construct(private UnassignedDependency $dependency) {}
}
