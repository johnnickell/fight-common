<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskLifecycle;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Mcp\Task\Command\RemoveTasks;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class RemoveTasksHandler
 */
final readonly class RemoveTasksHandler implements CommandHandler
{
    /**
     * Constructs RemoveTasksHandler
     */
    public function __construct(private TaskLifecycle $lifecycle)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RemoveTasks::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RemoveTasks) {
            throw new InvalidArgumentException('Unexpected Task command.');
        }

        $this->lifecycle->remove($command);
    }
}
