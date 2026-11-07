<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Service\TaskPayloadPolicy;
use Fight\Common\Application\Mcp\Task\Service\TaskUpdateAdmission;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use Throwable;

/**
 * Class RecordTaskStateHandler
 */
final readonly class RecordTaskStateHandler implements CommandHandler
{
    /**
     * Constructs RecordTaskStateHandler
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
        return RecordTaskState::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RecordTaskState) {
            throw new InvalidArgumentException('Unexpected Task command.');
        }

        $changed = $this->transactionalUnitOfWork->commitTransactional(function () use ($command): ?Task {
            if (!$this->taskUpdateAdmission->allowsUpdate($command->scope(), $command->id(), $command->producer())) {
                throw new TaskRejected();
            }

            $existing = $this->taskRepository->getById($command->scope(), $command->id());
            $now = Task::timestamp(($this->clock)());
            if (
                $existing === null || !$existing->availableAt($now)
                || $existing->definition()->producer() !== $command->producer()
            ) {
                throw new TaskRejected();
            }

            try {
                $this->taskPayloadPolicy->validate($existing->definition(), $command->state());
            } catch (Throwable) {
                throw new TaskRejected();
            }

            try {
                $next = $command->applyTo($existing, $now);
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

        $event = TaskChanged::fromTask($changed);
        try {
            $this->eventDispatcher->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($event, $throwable);
        }
    }
}
