<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeId;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Mcp\Notice\NoticeRepository;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;

/**
 * Class DbalNoticeRepository
 */
final readonly class DbalNoticeRepository implements NoticeRepository
{
    private Connection $connection;
    private DbalNoticeTables $tables;
    private DbalNoticeRecords $noticeRecordSupport;

    /**
     * Constructs DbalNoticeRepository
     */
    public function __construct(private DbalNoticeTransaction $noticeTransaction)
    {
        $this->connection = $noticeTransaction->connection();
        $this->tables = $noticeTransaction->tables();
        $this->noticeRecordSupport = new DbalNoticeRecords($this->connection, $this->tables);
    }

    /**
     * @inheritDoc
     */
    public function getByIdentity(NoticeId $identity): ?RecordedNotice
    {
        $this->noticeTransaction->requireOwnedTransaction();
        $row = $this->connection->fetchAssociative(
            $this->noticeRecordSupport->select().' WHERE n.scope = ? AND n.producer = ? AND n.notice_id = ?',
            [$identity->scope(), $identity->producer(), $identity->id()]
        );

        return $row === false ? null : $this->noticeRecordSupport->hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function append(Notice $notice, string $recordedAt): RecordedNotice
    {
        $this->noticeTransaction->requireOwnedTransaction();
        $platform = $this->connection->getDatabasePlatform();
        $lastPosition = (int) $this->connection->fetchOne(
            'SELECT last_position FROM '.$this->tables->stateTable()->quoted($platform).' WHERE singleton = 1'
        );
        if ($lastPosition < 0 || $lastPosition >= RecordedNotice::MAX_POSITION) {
            throw new DomainException('Notice position capacity exhausted.');
        }

        $recordedNotice = new RecordedNotice($notice, $lastPosition + 1, $recordedAt);
        $data = $notice->toArray();
        unset($data['relationships']);
        $this->connection->update(
            $this->tables->stateTable()->quoted($platform),
            ['last_position' => $recordedNotice->position()],
            ['singleton' => 1]
        );
        $this->connection->insert($this->tables->noticeTable()->quoted($platform), [
            'position'    => $recordedNotice->position(),
            'scope'       => $notice->scope(),
            'producer'    => $notice->producer(),
            'notice_id'   => $notice->id(),
            'recorded_at' => $recordedAt,
            'payload'     => json_encode($data, JSON_THROW_ON_ERROR)
        ]);
        $this->connection->insert($this->tables->relationshipTable()->quoted($platform), [
            'position' => $recordedNotice->position(),
            'payload'  => json_encode($notice->relationships()->toArray(), JSON_THROW_ON_ERROR)
        ]);
        $this->connection->insert($this->tables->comparisonTable()->quoted($platform), [
            'position' => $recordedNotice->position(),
            'version'  => 1,
            'payload'  => $notice->comparison()
        ]);

        return $recordedNotice;
    }

    /**
     * @inheritDoc
     */
    public function remove(NoticeRemovalSelection $selection): void
    {
        $this->noticeTransaction->requireOwnedTransaction();
        $state = $this->noticeRecordSupport->recoveryState();
        if ($selection->namespace() !== $state->namespace()) {
            throw new NoticeRemovalRejected();
        }

        $platform = $this->connection->getDatabasePlatform();
        $lostThrough = $state->lostThrough();
        foreach ($selection->targets() as $target) {
            $removed = $this->connection->delete($this->tables->noticeTable()->quoted($platform), [
                'scope'     => $target['scope'],
                'producer'  => $target['producer'],
                'notice_id' => $target['id'],
                'position'  => $target['position']
            ]);
            if ((int) $removed !== 1) {
                throw new NoticeRemovalRejected();
            }

            $lostThrough = max($lostThrough, $target['position']);
        }

        if ($lostThrough !== $state->lostThrough()) {
            $this->connection->update(
                $this->tables->stateTable()->quoted($platform),
                ['lost_through' => $lostThrough],
                ['singleton' => 1]
            );
        }
    }
}
