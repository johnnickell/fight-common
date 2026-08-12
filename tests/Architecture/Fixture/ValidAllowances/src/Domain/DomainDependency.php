<?php

declare(strict_types=1);

namespace Fight\Common\Domain;

use DateTimeImmutable;

final readonly class DomainDependency
{
    public function __construct(private DateTimeImmutable $createdAt) {}
}
