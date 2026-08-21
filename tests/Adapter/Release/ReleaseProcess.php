<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release;

use Symfony\Component\Process\Process;

/**
 * Class ReleaseProcess
 *
 * Test-only handoff for journeys already executing in the canonical PHP runtime.
 */
final class ReleaseProcess
{
    /**
     * Creates a release process already executing in the canonical test runtime
     *
     * @param array $command Command arguments.
     *
     * @phpstan-param list<string> $command
     */
    public static function create(array $command): Process
    {
        return new Process($command);
    }
}
