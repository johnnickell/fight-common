<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Service\TaskRemovalAdmission;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Mcp\Task\Command\RemoveTasks;
use Fight\Common\Domain\Mcp\Task\Event\TaskDataRemoved;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use Throwable;

/**
 * Class RemoveTasksHandler
 */
final readonly class RemoveTasksHandler implements CommandHandler
{
    /**
     * Constructs RemoveTasksHandler
     */
    public function __construct(
        private TaskRepository $taskRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private TaskRemovalAdmission $taskRemovalAdmission,
        private EventDispatcher $eventDispatcher
    ) {
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

        $removed = $this->transactionalUnitOfWork->commitTransactional(function () use ($command): ?TaskDataRemoved {
            $selection = $command->selection();
            // Check every grant before existence or deletion, including repeats and missing rows.
            foreach ($selection->ids() as $id) {
                try {
                    $allowed = $this->taskRemovalAdmission->allowsRemoval($selection->scope(), $id);
                } catch (Throwable) {
                    throw new TaskRejected();
                }

                if (!$allowed) {
                    throw new TaskRejected();
                }
            }

            $actual = $this->taskRepository->remove($selection);

            return $actual === null ? null : new TaskDataRemoved($actual);
        });
        if ($removed === null) {
            return;
        }

        try {
            $this->eventDispatcher->trigger($removed);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($removed, $throwable);
        }
    }
}
