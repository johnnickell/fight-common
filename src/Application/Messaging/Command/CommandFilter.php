<?php

declare(strict_types=1);

namespace Fight\Common\Application\Messaging\Command;

use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Interface CommandFilter
 */
interface CommandFilter
{
    /**
     * Processes a command message and calls the next filter
     *
     * Signature of $next:
     *
     * <code>
     * function (CommandMessage $commandMessage): void {}
     * </code>
     *
     * @throws Throwable When an error occurs
     */
    public function process(CommandMessage $commandMessage, callable $next): void;
}
