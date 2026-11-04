<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Service\TaskCreationAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskPayloadPolicy;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use Throwable;

/**
 * Class CreateTaskHandler
 */
final readonly class CreateTaskHandler implements CommandHandler
{
    /**
     * Constructs CreateTaskHandler
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private TaskRepository $taskRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private TaskCreationAdmission $taskCreationAdmission,
        private TaskPayloadPolicy $taskPayloadPolicy,
        private EventDispatcher $eventDispatcher,
        private Closure $clock
    ) {
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

        $changed = $this->transactionalUnitOfWork->commitTransactional(function () use ($command): ?Task {
            if (!$this->taskCreationAdmission->allowsCreation($command->definition())) {
                throw new TaskRejected();
            }

            try {
                $this->taskPayloadPolicy->validate($command->definition(), $command->state());
            } catch (Throwable) {
                throw new TaskRejected();
            }

            $now = Task::timestamp(($this->clock)());
            // Compare original content without recalculating a retained Task's expiry at retry time.
            $comparison = $command->at(0);
            $existing = $this->taskRepository->getById($command->definition()->scope(), $command->definition()->id());
            if ($existing !== null) {
                if (!$existing->availableAt($now)) {
                    throw new TaskRejected();
                }

                if (!$existing->repeatsCreation($comparison)) {
                    throw new TaskConflict();
                }

                return null;
            }

            $candidate = $command->at($now);
            $this->taskRepository->save($candidate);

            return $candidate;
        });
        if ($changed === null) {
            return;
        }

        $event = TaskChanged::fromTask($changed);
        try {
            $this->eventDispatcher->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($event, $throwable);
        }
    }
}
