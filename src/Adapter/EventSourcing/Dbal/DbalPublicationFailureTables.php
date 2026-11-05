<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use InvalidArgumentException;

/**
 * Class DbalPublicationFailureTables
 *
 * Explicit shared schema/runtime selection without database effects
 */
final readonly class DbalPublicationFailureTables
{
    private DbalTableName $failureTable;
    private DbalTableName $handlerFailureTable;

    /**
     * Constructs DbalPublicationFailureTables
     */
    public function __construct(
        string $failureTable = 'publication_failures',
        string $handlerFailureTable = 'publication_handler_failures'
    ) {
        $this->failureTable = new DbalTableName($failureTable);
        $this->handlerFailureTable = new DbalTableName($handlerFailureTable);

        // Reject even case-only collisions for portable composition on MySQL.
        if (strtolower($failureTable) === strtolower($handlerFailureTable)) {
            throw new InvalidArgumentException('Failure and handler-failure tables must have distinct names.');
        }
    }

    /**
     * Returns the selected publication-failure table
     */
    public function failureTable(): DbalTableName
    {
        return $this->failureTable;
    }

    /**
     * Returns the selected handler-failure table
     */
    public function handlerFailureTable(): DbalTableName
    {
        return $this->handlerFailureTable;
    }
}
