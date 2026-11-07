<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\QueryHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Service\CommittedTaskReader;
use Fight\Common\Application\Mcp\Task\Service\TaskCancellationAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskInputAdmission;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use InvalidArgumentException;
use Throwable;

/**
 * Class GetTaskInputHandler
 */
final readonly class GetTaskInputHandler implements QueryHandler
{
    /**
     * Constructs GetTaskInputHandler
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private CommittedTaskReader $committedTaskReader,
        private TaskInputAdmission $taskInputAdmission,
        private TaskCancellationAdmission $taskCancellationAdmission,
        private Closure $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetTaskInput::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?StrictJson
    {
        $query = $queryMessage->payload();
        if (!$query instanceof GetTaskInput) {
            throw new InvalidArgumentException('Unexpected Task input query.');
        }

        try {
            $allowed = $this->taskInputAdmission->allowsObservation($query->scope, $query->id, $query->caller)
                && $this->taskCancellationAdmission->allowsCancellationObservation(
                    $query->scope,
                    $query->id,
                    $query->caller
                );
        } catch (Throwable) {
            return null;
        }

        if (!$allowed) {
            return null;
        }

        $task = $this->committedTaskReader->read($query->scope, $query->id);
        if ($task === null || !$task->availableAt(Task::timestamp(($this->clock)()))) {
            return null;
        }

        return StrictJson::fromObject([
            'definition'   => $task->definition()->toArray(),
            'terminal'     => $task->state()->isTerminal(),
            'version'      => $task->version(),
            'cancellation' => $task->cancellation(),
            'groups'       => $task->inputs()->observe(
                $query->caller,
                Task::timestamp(($this->clock)()),
                $task->state()->isTerminal()
            )
        ], 72);
    }
}
