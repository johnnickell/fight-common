<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Session\QueryHandler;

use Fight\Common\Application\Mcp\Session\Data\ExecutionPage;
use Fight\Common\Application\Mcp\Session\Service\ExecutionReadAdmission;
use Fight\Common\Application\Mcp\Session\Service\ExecutionReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class ListExecutionsHandler
 */
final readonly class ListExecutionsHandler implements QueryHandler
{
    /**
     * Constructs ListExecutionsHandler
     */
    public function __construct(
        private ExecutionReadAdmission $executionReadAdmission,
        private ExecutionReader $executionReader
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListExecutions::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ExecutionPage
    {
        /**
         * @var ListExecutions $query
         */
        $query = $queryMessage->payload();
        $access = $this->executionReadAdmission->currentAccess($query->selection());

        return $this->executionReader->list($query, $access);
    }
}
