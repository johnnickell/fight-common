<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Session\QueryHandler;

use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionInspection;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionReadAdmission;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class GetExecutionHandler
 */
final readonly class GetExecutionHandler implements QueryHandler
{
    /**
     * Constructs GetExecutionHandler
     */
    public function __construct(private ExecutionReadAdmission $admission, private ExecutionReader $reader)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return GetExecution::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?ExecutionInspection
    {
        /**
         * @var GetExecution $query
         */
        $query = $queryMessage->payload();
        $access = $this->admission->currentAccess($query->selection());

        return $this->reader->inspect($query, $access);
    }
}
