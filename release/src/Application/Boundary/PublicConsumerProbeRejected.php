<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use RuntimeException;

/**
 * Class PublicConsumerProbeRejected
 *
 * Reports that the installed-package Scheduler probe failed to compile or execute.
 */
final class PublicConsumerProbeRejected extends RuntimeException
{
}
