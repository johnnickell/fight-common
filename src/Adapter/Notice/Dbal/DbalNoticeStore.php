<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Application\Notice\Data\NoticeRecoveryWindow;
use Fight\Common\Application\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Notice\Service\NoticeStore;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\RecordedNotice;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Class DbalNoticeStore
 */
final class DbalNoticeStore implements NoticeStore
{
    private const string SELECT = <<<'SQL'
        SELECT n.*, r.payload AS relationships, c.payload AS comparison, c.version
        FROM fight_notices n LEFT JOIN fight_notice_relationships r ON r.position = n.position
        LEFT JOIN fight_notice_comparisons c ON c.position = n.position
        SQL;

    private bool $owned = false;
    private bool $closed = false;

    /**
     * Constructs DbalNoticeStore
     */
    public function __construct(private readonly Connection $connection)
    {
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Notice storage supports SQLite, MySQL and PostgreSQL only.');
        }

        if ($connection->isTransactionActive()) {
            throw new LogicException('Notice storage requires an idle connection at construction.');
        }

        if ($platform instanceof SQLitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }

        $this->requireCommittedIsolation();
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->closed || $this->owned || $this->connection->isTransactionActive()) {
            throw new LogicException('Notice recording requires its own actual transaction on an idle store.');
        }

        $this->requireCommittedIsolation();

        try {
            $this->connection->beginTransaction();
        } catch (Throwable $throwable) {
            $this->closed = true;
            throw new NoticeStorageFailed(true, $throwable);
        }

        $this->owned = true;
        try {
            try {
                // Acquire the namespace write lock before any identity read and hold through actual commit.
                $this->connection->executeStatement(
                    'UPDATE fight_notice_state SET last_position = last_position WHERE singleton = 1'
                );
                if (
                    (int) $this->connection->fetchOne(
                        'SELECT version FROM fight_notice_state WHERE singleton = 1'
                    ) !== 1
                ) {
                    throw new RuntimeException('Unsupported notice schema.');
                }

                $result = $operation();
                $this->requireOwnedTransaction();
            } catch (Throwable $exception) {
                $this->rollback($exception);
                if ($exception instanceof Exception) {
                    throw new NoticeStorageFailed(false, $exception);
                }

                throw $exception;
            }

            try {
                $this->connection->commit();
            } catch (Throwable $exception) {
                $this->closed = true;
                if ($this->connection->isTransactionActive()) {
                    $this->rollback($exception);
                }

                throw new NoticeStorageFailed(true, $exception);
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
     * Returns the shared connection for trusted storage composition
     *
     * Direct writers must preserve the serialization, ordering and history-loss contract
     * This seam is not a public removal operation or an authorization grant
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Validates ownership rather than accepting a caller transaction or a savepoint
     */
    public function requireOwnedTransaction(): void
    {
        if (!$this->owned || $this->connection->getTransactionNestingLevel() !== 1) {
            throw new LogicException('Notice writes require the store-owned transaction.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getByIdentity(string $scope, string $producer, string $id): ?RecordedNotice
    {
        $this->requireOwnedTransaction();
        $row = $this->connection->fetchAssociative(
            self::SELECT.' WHERE n.scope = ? AND n.producer = ? AND n.notice_id = ?',
            [Notice::reference($scope), Notice::reference($producer), Notice::reference($id)]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function append(Notice $notice, string $recordedAt): RecordedNotice
    {
        $this->requireOwnedTransaction();
        $last = (int) $this->connection->fetchOne('SELECT last_position FROM fight_notice_state WHERE singleton = 1');
        if ($last < 0 || $last >= RecordedNotice::MAX_POSITION) {
            throw new DomainException('Notice position capacity exhausted.');
        }

        $recorded = new RecordedNotice($notice, $last + 1, $recordedAt);
        $data = $notice->toArray();
        unset($data['relationships']);
        $this->connection->update('fight_notice_state', ['last_position' => $recorded->position()], ['singleton' => 1]);
        $this->connection->insert('fight_notices', [
            'position'    => $recorded->position(),
            'scope'       => $notice->scope(),
            'producer'    => $notice->producer(),
            'notice_id'   => $notice->id(),
            'recorded_at' => $recordedAt,
            'payload'     => json_encode($data, JSON_THROW_ON_ERROR)
        ]);
        $this->connection->insert('fight_notice_relationships', [
            'position' => $recorded->position(),
            'payload'  => json_encode($notice->relationships()->toArray(), JSON_THROW_ON_ERROR)
        ]);
        $this->connection->insert('fight_notice_comparisons', [
            'position' => $recorded->position(),
            'version'  => 1,
            'payload'  => $notice->comparison()
        ]);

        return $recorded;
    }

    /**
     * @inheritDoc
     */
    public function remove(NoticeRemovalSelection $selection): void
    {
        $this->requireOwnedTransaction();
        $state = $this->recoveryState();
        if ($selection->namespace() !== $state->namespace()) {
            throw new NoticeRemovalRejected();
        }

        $lost = $state->lostThrough();
        foreach ($selection->targets() as $target) {
            $removed = $this->connection->delete('fight_notices', [
                'scope'     => $target['scope'],
                'producer'  => $target['producer'],
                'notice_id' => $target['id'],
                'position'  => $target['position']
            ]);
            if ((int) $removed !== 1) {
                throw new NoticeRemovalRejected();
            }

            $lost = max($lost, $target['position']);
        }

        if ($lost !== $state->lostThrough()) {
            $this->connection->update('fight_notice_state', ['lost_through' => $lost], ['singleton' => 1]);
        }
    }

    /**
     * @inheritDoc
     */
    public function readWindow(string|array $scope, int $position, int $limit): NoticeRecoveryWindow
    {
        $scopes = NoticeSelection::create(is_string($scope) ? [$scope] : $scope)->scopes();
        $this->validateRead('recovery', $position, $limit);

        return $this->commitTransactional(function () use ($scopes, $position, $limit): NoticeRecoveryWindow {
            try {
                $state = $this->recoveryState();
                $where = '1 = 0';
                if ($scopes !== []) {
                    $where = 'n.scope IN ('.implode(', ', array_fill(0, count($scopes), '?')).')';
                }

                $sql = self::SELECT.' WHERE '.$where.' AND n.position > ? ORDER BY n.position ASC';
                $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit);
                $rows = $this->connection->fetchAllAssociative($sql, [...$scopes, $position]);

                return new NoticeRecoveryWindow(
                    $state->namespace(),
                    $state->highWater(),
                    $state->lostThrough(),
                    array_map($this->hydrate(...), $rows)
                );
            } catch (Throwable $throwable) {
                throw new NoticeStorageFailed(false, $throwable);
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function readAfter(string $scope, int $position, int $limit): array
    {
        if ($this->closed || $this->connection->isTransactionActive()) {
            throw new LogicException('Notice reconstruction requires an idle, open store.');
        }

        $this->validateRead($scope, $position, $limit);
        $this->requireCommittedIsolation();

        try {
            $sql = self::SELECT.' WHERE n.scope = ? AND n.position > ? ORDER BY n.position ASC';
            $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit);
            $rows = $this->connection->fetchAllAssociative($sql, [$scope, $position]);

            return array_map($this->hydrate(...), $rows);
        } catch (Throwable $throwable) {
            throw new NoticeStorageFailed(false, $throwable);
        }
    }

    /**
     * Returns validated bounded evidence without inferring completeness from a missing state row
     */
    private function recoveryState(): NoticeRecoveryWindow
    {
        $row = $this->connection->fetchAssociative(
            'SELECT namespace, last_position, lost_through FROM fight_notice_state WHERE singleton = 1'
        );
        if ($row === false) {
            throw new RuntimeException('Missing notice recovery evidence.');
        }

        return new NoticeRecoveryWindow(
            $row['namespace'],
            $this->storedPosition($row['last_position']),
            $this->storedPosition($row['lost_through']),
            []
        );
    }

    /**
     * Validates persisted integers before casting so unavailable evidence cannot become zero
     */
    private function storedPosition(mixed $value): int
    {
        if (
            (!is_int($value) && !is_string($value))
            || preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', (string) $value) !== 1
            || (int) $value > RecordedNotice::MAX_POSITION
        ) {
            throw new RuntimeException('Invalid notice recovery position.');
        }

        return (int) $value;
    }

    /**
     * Validates finite reconstruction bounds before any database work
     */
    private function validateRead(string $scope, int $position, int $limit): void
    {
        Notice::reference($scope);
        if ($position < 0 || $position > RecordedNotice::MAX_POSITION || $limit < 1 || $limit > 100) {
            throw new DomainException('Invalid notice reconstruction bounds.');
        }
    }

    /**
     * Validates isolation sufficient to exclude uncommitted recovery positions
     */
    private function requireCommittedIsolation(): void
    {
        if ($this->connection->getTransactionIsolation() === TransactionIsolationLevel::READ_UNCOMMITTED) {
            throw new LogicException('Notice storage does not support dirty reads.');
        }
    }

    /**
     * Creates a typed retained record and rejects inconsistent comparison or identity evidence
     *
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): RecordedNotice
    {
        $data = json_decode($row['payload'], true, 16, JSON_THROW_ON_ERROR);
        $relationships = json_decode($row['relationships'], true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($relationships) || (int) $row['version'] !== 1) {
            throw new RuntimeException('Invalid stored notice.');
        }

        $data['relationships'] = $relationships;
        $notice = Notice::fromArray($data);
        if (
            $notice->comparison() !== $row['comparison'] || $notice->id() !== $row['notice_id']
            || $notice->scope() !== $row['scope'] || $notice->producer() !== $row['producer']
        ) {
            throw new RuntimeException('Inconsistent stored notice.');
        }

        return new RecordedNotice($notice, (int) $row['position'], $row['recorded_at']);
    }

    /**
     * Handles rollback of owned work without claiming certainty after cleanup failure
     */
    private function rollback(Throwable $original): void
    {
        try {
            $this->connection->rollBack();
        } catch (Throwable) {
            $this->closed = true;
            throw new NoticeStorageFailed(true, $original);
        }
    }
}
