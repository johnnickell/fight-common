<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Service\TaskInputAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Task\Service\TaskPayloadPolicy;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;
use Throwable;

/**
 * Class SupplyTaskInputHandler
 */
final readonly class SupplyTaskInputHandler implements CommandHandler
{
    /**
     * Constructs SupplyTaskInputHandler
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private TaskRepository $taskRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork,
        private TaskInputAdmission $taskInputAdmission,
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
        return SupplyTaskInput::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof SupplyTaskInput) {
            throw new InvalidArgumentException('Unexpected Task input command.');
        }

        $changed = $this->transactionalUnitOfWork->commitTransactional(function () use ($command): ?Task {
            try {
                try {
                    $allowed = $this->taskInputAdmission->allowsSubmission(
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
                if ($task === null || !$task->availableAt(Task::timestamp(($this->clock)()))) {
                    throw new TaskRejected();
                }

                // Authorized terminal Tasks acknowledge without validating or applying inactive content.
                if ($task->state()->isTerminal()) {
                    return null;
                }

                try {
                    $answers = TaskInputForms::responses(
                        $command->responses,
                        $task->inputs()->active($command->caller, Task::timestamp(($this->clock)()))
                    );
                } catch (Throwable) {
                    throw new TaskRejected();
                }

                if ($answers->properties() === []) {
                    return null;
                }

                $next = $task->answerInput($answers, $command->caller, Task::timestamp(($this->clock)()));
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
