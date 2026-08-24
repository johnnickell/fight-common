<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Interface PublicConsumerPort
 */
interface PublicConsumerPort
{
    /**
     * Runs one installed-package public API probe
     *
     * @return array<string, mixed>
     *
     * @throws PublicConsumerProbeRejected When the installed Scheduler-specific probe fails.
     */
    public function run(string $repository, string $fixture, string $consumer): array;
}
