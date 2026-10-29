<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Session\QueryHandler;

use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionSummary;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionReadAdmission;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class SummarizeExecutionsHandler
 */
final readonly class SummarizeExecutionsHandler implements QueryHandler
{
    /**
     * Constructs SummarizeExecutionsHandler
     */
    public function __construct(private ExecutionReadAdmission $admission, private ExecutionReader $reader)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return SummarizeExecutions::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ExecutionSummary
    {
        /**
         * @var SummarizeExecutions $query
         */
        $query = $queryMessage->payload();
        $access = $this->admission->currentAccess($query->selection());

        return $this->reader->summarize($query->selection(), $access);
    }
}
