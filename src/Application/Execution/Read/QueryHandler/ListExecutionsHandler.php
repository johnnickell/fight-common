<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Read\QueryHandler;

use Fight\Common\Application\Execution\Read\ExecutionPage;
use Fight\Common\Application\Execution\Read\ExecutionReadAdmission;
use Fight\Common\Application\Execution\Read\ExecutionReader;
use Fight\Common\Application\Execution\Read\Query\ListExecutions;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class ListExecutionsHandler
 */
final readonly class ListExecutionsHandler implements QueryHandler
{
    /**
     * Constructs ListExecutionsHandler
     */
    public function __construct(private ExecutionReadAdmission $admission, private ExecutionReader $reader)
    {
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
        $access = $this->admission->currentAccess($query->selection());

        return $this->reader->list($query, $access);
    }
}
