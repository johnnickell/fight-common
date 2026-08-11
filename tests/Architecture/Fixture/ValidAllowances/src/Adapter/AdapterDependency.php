<?php

declare(strict_types=1);

namespace Fight\Common\Adapter;

use Fight\Common\Application\ApplicationDependency;
use Fight\Common\Domain\DomainDependency;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final readonly class AdapterDependency
{
    public function __construct(
        private ApplicationDependency $application,
        private DomainDependency $domain,
        private LoggerInterface $logger,
        private Process $process,
    ) {}
}
