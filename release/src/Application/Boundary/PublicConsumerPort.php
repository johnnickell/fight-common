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
     */
    public function run(string $repository, string $fixture, string $consumer): array;
}
