<?php

declare(strict_types=1);

namespace Fight\Common\Application;

use Fight\Common\Standards\StandardsDependency;

final readonly class ApplicationDependsOnStandards
{
    public function __construct(private StandardsDependency $dependency) {}
}
