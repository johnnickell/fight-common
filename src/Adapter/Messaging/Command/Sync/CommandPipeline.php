<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Command\Sync;

use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Collection\LinkedStack;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Class CommandPipeline
 */
final class CommandPipeline implements SynchronousCommandBus, CommandFilter
{
    private readonly LinkedStack $filters;
    private ?LinkedStack $executionStack = null;

    /**
     * Constructs CommandPipeline
     */
    public function __construct(private readonly SynchronousCommandBus $commandBus)
    {
        $this->filters = LinkedStack::of(CommandFilter::class);
        $this->filters->push($this);
    }

    /**
     * Adds a command filter to the pipeline
     */
    public function addFilter(CommandFilter $filter): void
    {
        $this->filters->push($filter);
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
        $this->executionStack = clone $this->filters;
        $this->pipe($commandMessage);
    }

    /**
     * @inheritDoc
     */
    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $this->commandBus->dispatch($commandMessage);
    }

    /**
     * Pipes command message to the next filter
     *
     * @throws Throwable
     */
    public function pipe(CommandMessage $commandMessage): void
    {
        /** @var CommandFilter $filter */
        $filter = $this->executionStack->pop();
        $filter->process($commandMessage, $this->pipe(...));
    }
}
