<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\Dbal;

use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use InvalidArgumentException;

/**
 * Class DbalEventStoreTables
 *
 * Explicit shared schema/runtime selection without database effects
 */
final readonly class DbalEventStoreTables
{
    private DbalTableName $eventTable;
    private DbalTableName $globalPositionTable;

    /**
     * Constructs DbalEventStoreTables
     */
    public function __construct(
        string $eventTable = 'event_store_events',
        string $globalPositionTable = 'event_store_global_position'
    ) {
        $this->eventTable = new DbalTableName($eventTable);
        $this->globalPositionTable = new DbalTableName($globalPositionTable);

        // Reject even case-only collisions for portable composition on MySQL.
        if (strtolower($eventTable) === strtolower($globalPositionTable)) {
            throw new InvalidArgumentException('Event and global-position tables must have distinct names.');
        }
    }

    /**
     * Returns the selected event table
     */
    public function eventTable(): DbalTableName
    {
        return $this->eventTable;
    }

    /**
     * Returns the selected global-position table
     */
    public function globalPositionTable(): DbalTableName
    {
        return $this->globalPositionTable;
    }
}
