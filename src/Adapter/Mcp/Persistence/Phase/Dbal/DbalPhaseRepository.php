<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Phase\Dbal;

use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Domain\Mcp\Phase\Phase;
use Fight\Common\Domain\Mcp\Phase\PhaseId;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Phase\PhaseRepository;

/**
 * Class DbalPhaseRepository
 */
final readonly class DbalPhaseRepository implements PhaseRepository
{
    /**
     * Constructs DbalPhaseRepository
     */
    public function __construct(private DbalRecordingTransaction $recordingTransaction)
    {
    }

    /**
     * @inheritDoc
     */
    public function getById(PhaseId $id): ?Phase
    {
        $sql = 'SELECT session_id, phase_id, parent_phase_id, agent_id, name, attempt FROM fight_execution_phases';
        $sql .= ' WHERE project_id = ? AND phase_id = ?';
        $row = $this->recordingTransaction->connection()->fetchAssociative($sql, [$id->projectId(), $id->phaseId()]);
        if ($row === false) {
            return null;
        }

        $row['attempt'] = (int) $row['attempt'];

        return new Phase($id->projectId(), PhaseReference::fromArray($row));
    }

    /**
     * @inheritDoc
     */
    public function add(Phase $phase): void
    {
        $this->recordingTransaction->requireOwnedTransaction();
        $this->recordingTransaction->connection()->insert(
            'fight_execution_phases',
            ['project_id' => $phase->projectId()] + $phase->reference()->toArray()
        );
    }
}
