<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Laravel;

use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Class LaravelCommandBus
 *
 * Submits complete Fight command messages to Laravel Queue after commit.
 */
final readonly class LaravelCommandBus implements AsynchronousCommandBus
{
    private Dispatcher $jobDispatcher;

    /**
     * Constructs LaravelCommandBus
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->jobDispatcher = $dispatcher;
    }

    /**
     * @inheritDoc
     */
    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(CommandMessage $commandMessage): void
    {
        $this->jobDispatcher->dispatch(new QueuedCommandMessage($commandMessage));
    }
}
