<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Mcp\Task\Service\CommittedTaskReader;
use Fight\Common\Application\Mcp\Task\Service\TaskCancellationAdmission;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Class RequestTaskCancellationHandler
 */
final readonly class RequestTaskCancellationHandler implements CommandHandler
{
    /**
     * Constructs RequestTaskCancellationHandler
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private TaskRepository $taskRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private CommittedTaskReader $committedTaskReader,
        private TaskCancellationAdmission $taskCancellationAdmission,
        private EventDispatcher $eventDispatcher,
        private Closure $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RequestTaskCancellation::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RequestTaskCancellation) {
            throw new InvalidArgumentException('Unexpected Task cancellation command.');
        }

        $changed = $this->transactionalUnitOfWork->commitTransactional(function () use ($command): ?Task {
            try {
                $allowed = $this->taskCancellationAdmission->allowsCancellation(
                    $command->scope,
                    $command->id,
                    $command->caller
                );
            } catch (Throwable) {
                throw new TaskRejected();
            }

            if (!$allowed) {
                throw new TaskRejected();
            }

            $task = $this->taskRepository->getById($command->scope, $command->id);
            $now = Task::timestamp(($this->clock)());
            if ($task === null || !$task->availableAt($now)) {
                throw new TaskRejected();
            }

            try {
                $next = $task->requestCancellation($now);
            } catch (DomainException) {
                throw new TaskConflict();
            }

            if ($next !== null) {
                $this->taskRepository->save($next);
            }

            return $next;
        });
        if ($changed === null) {
            return;
        }

        // Reconstruct through the shared committed read contract, not an in-memory write receipt.
        // Removal/expiry racing this check withholds acknowledgment, never claims a rolled-back intent.
        try {
            $retained = $this->committedTaskReader->read($command->scope, $command->id);
        } catch (Throwable $throwable) {
            throw new TaskStorageFailed(true, $throwable);
        }

        if (
            $retained === null || !$retained->availableAt(Task::timestamp(($this->clock)()))
            || $retained->cancellation()?->toString() !== $changed->cancellation()?->toString()
        ) {
            throw new TaskStorageFailed(true, new RuntimeException('Committed Task intent is unavailable.'));
        }

        $event = TaskChanged::fromTask($changed);
        try {
            $this->eventDispatcher->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($event, $throwable);
        }
    }
}
