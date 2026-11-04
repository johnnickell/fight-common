<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\QueryHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Projection\TaskProjection;
use Fight\Common\Application\Mcp\Task\Service\CommittedTaskReader;
use Fight\Common\Application\Mcp\Task\Service\TaskPayloadPolicy;
use Fight\Common\Application\Mcp\Task\Service\TaskReadAdmission;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use InvalidArgumentException;
use Throwable;

/**
 * Class GetTaskHandler
 */
final readonly class GetTaskHandler implements QueryHandler
{
    /**
     * Constructs GetTaskHandler
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private CommittedTaskReader $committedTaskReader,
        private TaskReadAdmission $taskReadAdmission,
        private TaskPayloadPolicy $taskPayloadPolicy,
        private Closure $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetTask::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?StrictJson
    {
        $query = $queryMessage->payload();
        if (!$query instanceof GetTask) {
            throw new InvalidArgumentException('Unexpected Task query.');
        }

        if (!$this->taskReadAdmission->allowsRead($query->scope(), $query->id())) {
            return null;
        }

        $task = $this->committedTaskReader->read($query->scope(), $query->id());
        if ($task === null || !$task->availableAt(Task::timestamp(($this->clock)()))) {
            return null;
        }

        try {
            $this->taskPayloadPolicy->validate($task->definition(), $task->state());
        } catch (Throwable) {
            throw new TaskRejected();
        }

        $context = $this->taskReadAdmission->comparisonScope($query->scope(), $query->id());
        $now = Task::timestamp(($this->clock)());
        $view = TaskProjection::fromTask($task, $context, $now);
        // All source/projection/context callbacks precede the final independent whole-state disclosure checks.
        if (
            !$task->availableAt($now) || !$this->taskReadAdmission->allowsRead($query->scope(), $query->id())
            || !$this->taskPayloadPolicy->mayDisclose($task)
        ) {
            return null;
        }

        return $view;
    }
}
