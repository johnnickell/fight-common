<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use InvalidArgumentException;

/**
 * Class DbalNoticeTables
 *
 * Explicit shared schema/runtime selection without database effects
 */
final readonly class DbalNoticeTables
{
    private DbalTableName $stateTable;
    private DbalTableName $noticeTable;
    private DbalTableName $relationshipTable;
    private DbalTableName $comparisonTable;

    /**
     * Constructs DbalNoticeTables
     */
    public function __construct(
        string $stateTable = 'fight_notice_state',
        string $noticeTable = 'fight_notices',
        string $relationshipTable = 'fight_notice_relationships',
        string $comparisonTable = 'fight_notice_comparisons'
    ) {
        $this->stateTable = new DbalTableName($stateTable);
        $this->noticeTable = new DbalTableName($noticeTable);
        $this->relationshipTable = new DbalTableName($relationshipTable);
        $this->comparisonTable = new DbalTableName($comparisonTable);
        $names = array_map(strtolower(...), [$stateTable, $noticeTable, $relationshipTable, $comparisonTable]);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidArgumentException('Notice tables must have distinct names.');
        }
    }

    /**
     * Returns the selected namespace state and serialization table
     */
    public function stateTable(): DbalTableName
    {
        return $this->stateTable;
    }

    /**
     * Returns the selected retained Notice table
     */
    public function noticeTable(): DbalTableName
    {
        return $this->noticeTable;
    }

    /**
     * Returns the selected whole-Notice relationship table
     */
    public function relationshipTable(): DbalTableName
    {
        return $this->relationshipTable;
    }

    /**
     * Returns the selected retained retry comparison table
     */
    public function comparisonTable(): DbalTableName
    {
        return $this->comparisonTable;
    }
}
