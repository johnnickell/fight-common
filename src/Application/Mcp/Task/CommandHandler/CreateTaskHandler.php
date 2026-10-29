<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskLifecycle;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class CreateTaskHandler
 */
final readonly class CreateTaskHandler implements CommandHandler
{
    /**
     * Constructs CreateTaskHandler
     */
    public function __construct(private TaskLifecycle $lifecycle)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return CreateTask::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof CreateTask) {
            throw new InvalidArgumentException('Unexpected Task command.');
        }

        $this->lifecycle->create($command);
    }
}
