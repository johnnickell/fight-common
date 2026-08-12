<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Fixture;

use Fight\Common\Application\Fixture\ApplicationDependency;

final readonly class DomainDependsOnApplication
{
    public function __construct(private ApplicationDependency $dependency)
    {
    }
}
