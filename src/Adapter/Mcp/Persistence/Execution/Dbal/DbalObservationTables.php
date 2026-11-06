<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use InvalidArgumentException;

/**
 * Class DbalObservationTables
 *
 * Explicit shared schema/runtime selection without database effects
 */
final readonly class DbalObservationTables
{
    private DbalTableName $schemaTable;
    private DbalTableName $sessionTable;
    private DbalTableName $phaseTable;
    private DbalTableName $observationTable;
    private DbalTableName $contentTable;

    /**
     * Constructs DbalObservationTables
     */
    public function __construct(
        string $schemaTable = 'fight_execution_schema',
        string $sessionTable = 'fight_execution_sessions',
        string $phaseTable = 'fight_execution_phases',
        string $observationTable = 'fight_execution_observations',
        string $contentTable = 'fight_execution_contents'
    ) {
        $this->schemaTable = new DbalTableName($schemaTable);
        $this->sessionTable = new DbalTableName($sessionTable);
        $this->phaseTable = new DbalTableName($phaseTable);
        $this->observationTable = new DbalTableName($observationTable);
        $this->contentTable = new DbalTableName($contentTable);

        // Reject even case-only collisions for portable composition on MySQL.
        $names = [$schemaTable, $sessionTable, $phaseTable, $observationTable, $contentTable];
        if (count(array_unique(array_map(strtolower(...), $names))) !== count($names)) {
            throw new InvalidArgumentException('Execution tables must have distinct names.');
        }
    }

    /**
     * Returns the selected schema state table
     */
    public function schemaTable(): DbalTableName
    {
        return $this->schemaTable;
    }

    /**
     * Returns the selected session table
     */
    public function sessionTable(): DbalTableName
    {
        return $this->sessionTable;
    }

    /**
     * Returns the selected phase table
     */
    public function phaseTable(): DbalTableName
    {
        return $this->phaseTable;
    }

    /**
     * Returns the selected observation table
     */
    public function observationTable(): DbalTableName
    {
        return $this->observationTable;
    }

    /**
     * Returns the selected protected-content table
     */
    public function contentTable(): DbalTableName
    {
        return $this->contentTable;
    }
}
