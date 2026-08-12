<?php

declare(strict_types=1);

namespace Fight\Common\Domain;

use Psr\Log\LoggerInterface;

final readonly class DomainDependsOnPsr
{
    public function __construct(private LoggerInterface $logger) {}
}
