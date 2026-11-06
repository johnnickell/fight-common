<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Mcp\Notice\Data\NoticeRecoveryWindow;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;
use RuntimeException;

/**
 * Class DbalNoticeRecords
 *
 * Shares persisted identity and bounded loss-evidence validation between repository and recovery reads
 *
 * @internal
 */
final readonly class DbalNoticeRecords
{
    /**
     * Constructs DbalNoticeRecords
     */
    public function __construct(private Connection $connection, private DbalNoticeTables $tables)
    {
    }

    /**
     * Returns the selected whole-Notice reconstruction join
     */
    public function select(): string
    {
        $platform = $this->connection->getDatabasePlatform();

        return sprintf(
            <<<'SQL'
                SELECT n.*, r.payload AS relationships, c.payload AS comparison, c.version
                FROM %s n LEFT JOIN %s r ON r.position = n.position
                LEFT JOIN %s c ON c.position = n.position
                SQL,
            $this->tables->noticeTable()->quoted($platform),
            $this->tables->relationshipTable()->quoted($platform),
            $this->tables->comparisonTable()->quoted($platform)
        );
    }

    /**
     * Returns validated bounded evidence without inferring completeness from a missing state row
     */
    public function recoveryState(): NoticeRecoveryWindow
    {
        $row = $this->connection->fetchAssociative(
            sprintf(
                'SELECT namespace, last_position, lost_through FROM %s WHERE singleton = 1',
                $this->tables->stateTable()->quoted($this->connection->getDatabasePlatform())
            )
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
     * Creates a typed retained record and rejects inconsistent comparison or identity evidence
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): RecordedNotice
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
}
