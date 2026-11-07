<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Task\Service\TaskPayloadPolicy;
use Fight\Common\Application\Mcp\Task\Service\TaskUpdateAdmission;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use InvalidArgumentException;
use Throwable;

/**
 * Class RequestTaskInputHandler
 */
final readonly class RequestTaskInputHandler implements CommandHandler
{
    /**
     * Constructs RequestTaskInputHandler
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private TaskRepository $taskRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private TaskUpdateAdmission $taskUpdateAdmission,
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
        return RequestTaskInput::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RequestTaskInput) {
            throw new InvalidArgumentException('Unexpected Task input command.');
        }

        $changed = $this->transactionalUnitOfWork->commitTransactional(function () use ($command): Task {
            try {
                try {
                    $allowed = $this->taskUpdateAdmission->allowsUpdate(
                        $command->scope,
                        $command->id,
                        $command->producer
                    );
                } catch (Throwable) {
                    throw new TaskRejected();
                }

                if (!$allowed) {
                    throw new TaskRejected();
                }

                $task = $this->taskRepository->getById($command->scope, $command->id);
                if ($task === null || !$task->availableAt(Task::timestamp(($this->clock)()))) {
                    throw new TaskRejected();
                }

                if ($task->definition()->producer() !== $command->producer) {
                    throw new TaskRejected();
                }

                $arguments = $task->definition()->operation()->get('arguments');
                if (
                    $command->batch->data()->get('confirmation')
                    && (!$arguments instanceof StrictJson || !$arguments->isObject())
                ) {
                    throw new TaskRejected();
                }

                try {
                    TaskInputForms::validate($command->batch);
                } catch (Throwable) {
                    throw new TaskRejected();
                }

                $next = $task->requestInput(
                    $command->batch,
                    $command->expectedVersion,
                    Task::timestamp(($this->clock)())
                );
            } catch (DomainException) {
                throw new TaskConflict();
            }

            try {
                $this->taskPayloadPolicy->validate($next->definition(), $next->state());
            } catch (Throwable) {
                throw new TaskRejected();
            }

            $this->taskRepository->save($next);

            return $next;
        });
        $event = TaskChanged::fromTask($changed);
        try {
            $this->eventDispatcher->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($event, $throwable);
        }
    }
}
