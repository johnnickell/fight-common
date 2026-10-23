<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Phase\QueryHandler;

use Fight\Common\Application\Execution\Phase\Data\PhasePage;
use Fight\Common\Application\Execution\Phase\Service\PhaseReader;
use Fight\Common\Application\Execution\Session\Service\ExecutionReadAdmission;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Execution\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class ListExecutionPhasesHandler
 */
final readonly class ListExecutionPhasesHandler implements QueryHandler
{
    /**
     * Constructs ListExecutionPhasesHandler
     */
    public function __construct(private ExecutionReadAdmission $admission, private PhaseReader $reader)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListExecutionPhases::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?PhasePage
    {
        /**
         * @var ListExecutionPhases $query
         */
        $query = $queryMessage->payload();
        $access = $this->admission->currentAccess($query->execution()->selection());

        return $this->reader->list($query, $access);
    }
}
