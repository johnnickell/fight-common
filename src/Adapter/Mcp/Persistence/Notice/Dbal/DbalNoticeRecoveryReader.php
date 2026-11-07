<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Application\Mcp\Notice\Data\NoticeRecoveryWindow;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeRecoveryReader;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;
use LogicException;
use Throwable;

/**
 * Class DbalNoticeRecoveryReader
 *
 * Owns coherent recovery observations separately from aggregate writes and authorized live browsing
 */
final readonly class DbalNoticeRecoveryReader implements NoticeRecoveryReader
{
    private DbalNoticeTransaction $noticeTransaction;
    private DbalNoticeRecords $noticeRecordSupport;

    /**
     * Constructs DbalNoticeRecoveryReader
     */
    public function __construct(private Connection $connection, DbalNoticeTables $tables = new DbalNoticeTables())
    {
        $this->noticeTransaction = new DbalNoticeTransaction($connection, $tables);
        $this->noticeRecordSupport = new DbalNoticeRecords($connection, $tables);
    }

    /**
     * @inheritDoc
     */
    public function readWindow(string|array $scope, int $position, int $limit): NoticeRecoveryWindow
    {
        $scopes = NoticeSelection::create(is_string($scope) ? [$scope] : $scope)->scopes();
        $this->validateRead('recovery', $position, $limit);

        return $this->noticeTransaction->commitTransactional(function () use (
            $scopes,
            $position,
            $limit
        ): NoticeRecoveryWindow {
            try {
                $state = $this->noticeRecordSupport->recoveryState();
                $where = '1 = 0';
                if ($scopes !== []) {
                    $where = 'n.scope IN ('.implode(', ', array_fill(0, count($scopes), '?')).')';
                }

                $sql = sprintf(
                    '%s WHERE %s AND n.position > ? ORDER BY n.position ASC',
                    $this->noticeRecordSupport->select(),
                    $where
                );
                $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit);
                $rows = $this->connection->fetchAllAssociative($sql, [...$scopes, $position]);

                return new NoticeRecoveryWindow(
                    $state->namespace(),
                    $state->highWater(),
                    $state->lostThrough(),
                    array_map($this->noticeRecordSupport->hydrate(...), $rows)
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
        if ($this->noticeTransaction->isClosed() || $this->connection->isTransactionActive()) {
            throw new LogicException('Notice reconstruction requires an idle, open reader.');
        }

        $this->validateRead($scope, $position, $limit);
        if ($this->connection->getTransactionIsolation() === TransactionIsolationLevel::READ_UNCOMMITTED) {
            throw new LogicException('Notice storage does not support dirty reads.');
        }

        try {
            $sql = $this->noticeRecordSupport->select().' WHERE n.scope = ? AND n.position > ? ORDER BY n.position ASC';
            $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit);
            $rows = $this->connection->fetchAllAssociative($sql, [$scope, $position]);

            return array_map($this->noticeRecordSupport->hydrate(...), $rows);
        } catch (Throwable $throwable) {
            throw new NoticeStorageFailed(false, $throwable);
        }
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
}
