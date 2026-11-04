<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\QueryHandler;

use Closure;
use Fight\Common\Application\Mcp\Task\Service\CommittedTaskReader;
use Fight\Common\Application\Mcp\Task\Service\TaskCancellationAdmission;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use InvalidArgumentException;
use Throwable;

/**
 * Class GetTaskCancellationHandler
 */
final readonly class GetTaskCancellationHandler implements QueryHandler
{
    /**
     * Constructs GetTaskCancellationHandler
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private CommittedTaskReader $committedTaskReader,
        private TaskCancellationAdmission $taskCancellationAdmission,
        private Closure $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetTaskCancellation::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?StrictJson
    {
        $query = $queryMessage->payload();
        if (!$query instanceof GetTaskCancellation) {
            throw new InvalidArgumentException('Unexpected Task cancellation query.');
        }

        try {
            $allowed = $this->taskCancellationAdmission->allowsCancellationObservation(
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
            'id'           => $query->id,
            'version'      => $task->version(),
            'cancellation' => $task->cancellation()
        ]);
    }
}
