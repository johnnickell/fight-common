<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Observation\QueryHandler;

use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityPage;
use Fight\Common\Application\Mcp\Execution\Observation\Service\ActivityReader;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionReadAdmission;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class ListPhaseActivityHandler
 */
final readonly class ListPhaseActivityHandler implements QueryHandler
{
    /**
     * Constructs ListPhaseActivityHandler
     */
    public function __construct(private ExecutionReadAdmission $admission, private ActivityReader $reader)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListPhaseActivity::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?ActivityPage
    {
        /**
         * @var ListPhaseActivity $query
         */
        $query = $queryMessage->payload();
        $access = $this->admission->currentAccess($query->execution()->selection());

        return $this->reader->list($query, $access);
    }
}
