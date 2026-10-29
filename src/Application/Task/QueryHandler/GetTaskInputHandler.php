<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\QueryHandler;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Application\Task\Service\TaskInputLifecycle;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Task\Query\GetTaskInput;
use Fight\Common\Domain\Value\Basic\StrictJson;
use InvalidArgumentException;

/**
 * Class GetTaskInputHandler
 */
final readonly class GetTaskInputHandler implements QueryHandler
{
    /**
     * Constructs GetTaskInputHandler
     */
    public function __construct(private TaskInputLifecycle $lifecycle)
    {
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

        return $this->lifecycle->get($query);
    }
}
