<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Session\Dbal;

use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Domain\Mcp\Session\Session;
use Fight\Common\Domain\Mcp\Session\SessionId;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Mcp\Session\SessionRepository;

/**
 * Class DbalSessionRepository
 */
final readonly class DbalSessionRepository implements SessionRepository
{
    /**
     * Constructs DbalSessionRepository
     */
    public function __construct(private DbalRecordingTransaction $transaction)
    {
    }

    /**
     * @inheritDoc
     */
    public function getById(SessionId $id): ?Session
    {
        $sql = 'SELECT project_id, session_id, consumer_reference FROM fight_execution_sessions';
        $sql .= ' WHERE project_id = ? AND session_id = ?';
        $row = $this->transaction->connection()->fetchAssociative($sql, [$id->projectId(), $id->sessionId()]);

        return $row === false ? null : new Session(SessionReference::fromArray($row));
    }

    /**
     * @inheritDoc
     */
    public function add(Session $session): void
    {
        $this->transaction->requireOwnedTransaction();
        $this->transaction->connection()->insert('fight_execution_sessions', $session->reference()->toArray());
    }
}
