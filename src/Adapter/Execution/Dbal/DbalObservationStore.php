<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Application\Execution\ObservationStorageFailed;
use Fight\Common\Application\Execution\ObservationStore;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Observation;
use Fight\Common\Domain\Execution\ObservationId;
use Fight\Common\Domain\Execution\PhaseReference;
use Fight\Common\Domain\Execution\RecordedObservation;
use Fight\Common\Domain\Execution\SessionReference;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Class DbalObservationStore
 */
final class DbalObservationStore implements ObservationStore
{
    private bool $owned = false;
    private bool $closed = false;

    /**
     * Constructs DbalObservationStore
     */
    public function __construct(private readonly Connection $connection)
    {
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Observation storage supports SQLite, MySQL and PostgreSQL only.');
        }

        if ($platform instanceof SQLitePlatform) {
            if ($connection->isTransactionActive()) {
                throw new LogicException('Observation storage requires an idle connection at construction.');
            }

            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->closed || $this->owned || $this->connection->isTransactionActive()) {
            throw new LogicException('Observation recording requires its own real transaction on an idle store.');
        }

        try {
            $this->connection->beginTransaction();
        } catch (Throwable $throwable) {
            $this->closed = true;
            throw new ObservationStorageFailed(true, $throwable);
        }

        $this->owned = true;
        try {
            try {
                // Taking the same write lock before reads avoids snapshot/unique-key retry races.
                $this->connection->executeStatement(
                    'UPDATE fight_execution_schema SET version = version WHERE singleton = 1'
                );
                if (
                    (int) $this->connection->fetchOne(
                        'SELECT version FROM fight_execution_schema WHERE singleton = 1'
                    ) !== 1
                ) {
                    throw new RuntimeException('Unsupported observation storage version.');
                }

                $result = $operation();
            } catch (Throwable $exception) {
                $this->rollback($exception);
                if ($exception instanceof Exception) {
                    throw new ObservationStorageFailed(false, $exception);
                }

                throw $exception;
            }

            try {
                $this->connection->commit();
            } catch (Throwable $exception) {
                $this->closed = true;
                // A lost commit acknowledgment is not proof of rollback, even if cleanup succeeds.
                if ($this->connection->isTransactionActive()) {
                    $this->rollback($exception);
                }

                throw new ObservationStorageFailed(true, $exception);
            }

            return $result;
        } finally {
            $this->owned = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * @inheritDoc
     */
    public function get(string $producerId, ObservationId $id): ?RecordedObservation
    {
        $sql = 'SELECT version, payload, recorded_at FROM fight_execution_observations';
        $sql .= ' WHERE producer_id = ? AND observation_id = ?';
        $row = $this->connection->fetchAssociative($sql, [$producerId, $id->toString()]);
        if ($row === false) {
            return null;
        }

        if ((int) $row['version'] !== 1) {
            throw new DomainException('Unsupported observation version.');
        }

        $data = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new DomainException('Invalid stored observation structure.');
        }

        return new RecordedObservation(Observation::fromArray($data), Observation::timestamp($row['recorded_at']));
    }

    /**
     * @inheritDoc
     */
    public function session(string $projectId, string $sessionId): ?SessionReference
    {
        $sql = 'SELECT project_id, session_id, consumer_reference FROM fight_execution_sessions';
        $sql .= ' WHERE project_id = ? AND session_id = ?';
        $row = $this->connection->fetchAssociative($sql, [$projectId, $sessionId]);

        return $row === false ? null : SessionReference::fromArray($row);
    }

    /**
     * @inheritDoc
     */
    public function phase(string $projectId, string $phaseId): ?PhaseReference
    {
        $sql = 'SELECT session_id, phase_id, parent_phase_id, agent_id, name, attempt FROM fight_execution_phases';
        $sql .= ' WHERE project_id = ? AND phase_id = ?';
        $row = $this->connection->fetchAssociative($sql, [$projectId, $phaseId]);
        if ($row === false) {
            return null;
        }

        $row['attempt'] = (int) $row['attempt'];

        return PhaseReference::fromArray($row);
    }

    /**
     * @inheritDoc
     */
    public function addSession(SessionReference $session): void
    {
        $this->requireOwnedTransaction();
        $this->connection->insert('fight_execution_sessions', $session->toArray());
    }

    /**
     * @inheritDoc
     */
    public function addPhase(string $projectId, PhaseReference $phase): void
    {
        $this->requireOwnedTransaction();
        $this->connection->insert('fight_execution_phases', ['project_id' => $projectId] + $phase->toArray());
    }

    /**
     * @inheritDoc
     */
    public function append(Observation $observation): RecordedObservation
    {
        $this->requireOwnedTransaction();
        $recordedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->connection->insert('fight_execution_observations', [
            'producer_id'    => $observation->producerId,
            'observation_id' => $observation->id->toString(),
            'project_id'     => $observation->session->projectId,
            'session_id'     => $observation->session->sessionId,
            'phase_id'       => $observation->phase?->phaseId,
            'version'        => 1,
            'payload'        => $observation->comparison(),
            'recorded_at'    => $recordedAt->format('Y-m-d\TH:i:s.u\Z')
        ]);

        return new RecordedObservation($observation, $recordedAt);
    }

    /**
     * Handles rollback of only this store's transaction or reports uncertainty
     */
    private function rollback(Throwable $original): void
    {
        try {
            $this->connection->rollBack();
        } catch (Throwable) {
            $this->closed = true;
            throw new ObservationStorageFailed(true, $original);
        }
    }

    /**
     * Validates write ownership rather than accepting arbitrary caller transactions
     */
    private function requireOwnedTransaction(): void
    {
        if (!$this->owned || !$this->connection->isTransactionActive()) {
            throw new LogicException('Observation writes require the store-owned transaction.');
        }
    }
}
