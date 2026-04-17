<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Command\Sync\Routing;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Interface CommandRouter
 */
interface CommandRouter
{
    /**
     * Matches a command to a handler
     *
     * @throws LookupException When the handler is not found
     */
    public function match(Command $command): CommandHandler;
}
