<?php

declare(strict_types=1);

namespace Fight\Common\Application\Process;

use Fight\Common\Application\Process\Exception\ProcessException;

/**
 * Interface ProcessRunner
 */
interface ProcessRunner
{
    /**
     * Adds a process
     */
    public function attach(Process $process): void;

    /**
     * Clears all attached processes
     */
    public function clear(): void;

    /**
     * Runs all attached processes
     *
     * Defaults to throwing an exception when a child process fails.
     * Pass ProcessErrorBehavior::IGNORE to suppress errors.
     * Pass ProcessErrorBehavior::RETRY to retry failed processes.
     *
     * @throws ProcessException When an error occurs and behavior is EXCEPTION
     */
    public function run(?ProcessErrorBehavior $errorBehavior = null): void;
}
