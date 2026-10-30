<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\QueryHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskCancellationLifecycle;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use InvalidArgumentException;

/**
 * Class GetTaskCancellationHandler
 */
final readonly class GetTaskCancellationHandler implements QueryHandler
{
    /**
     * Constructs GetTaskCancellationHandler
     */
    public function __construct(private TaskCancellationLifecycle $lifecycle)
    {
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

        return $this->lifecycle->get($query);
    }
}
