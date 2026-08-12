<?php

declare(strict_types=1);

namespace Fight\Common\Application;

use Symfony\Component\Process\Process;

final readonly class ApplicationDependsOnSymfonyProcess
{
    public function __construct(private Process $process) {}
}
