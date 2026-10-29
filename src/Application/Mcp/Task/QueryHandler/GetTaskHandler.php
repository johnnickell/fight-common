<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\QueryHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskLifecycle;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;
use InvalidArgumentException;

/**
 * Class GetTaskHandler
 */
final readonly class GetTaskHandler implements QueryHandler
{
    /**
     * Constructs GetTaskHandler
     */
    public function __construct(private TaskLifecycle $lifecycle)
    {
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

        return $this->lifecycle->get($query);
    }
}
