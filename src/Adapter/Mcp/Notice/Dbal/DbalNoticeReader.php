<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Application\Mcp\Notice\Data\NoticePage;
use Fight\Common\Application\Mcp\Notice\Data\NoticeSummary;
use Fight\Common\Application\Mcp\Notice\Data\NoticeView;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReader;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Common\Domain\Mcp\Notice\Query\ListNotices;
use Generator;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class DbalNoticeReader
 *
 * Direct bounded live scans, never public storage positions or a recovery checkpoint
 */
final readonly class DbalNoticeReader implements NoticeReader
{
    private const int BATCH_SIZE = 100;
    private const string SELECT = <<<'SQL'
        SELECT n.position, n.scope, n.producer, n.notice_id, n.recorded_at, n.payload, r.payload AS relationships
        FROM fight_notices n LEFT JOIN fight_notice_relationships r ON r.position = n.position
        SQL;

    /**
     * Constructs DbalNoticeReader
     */
    public function __construct(private Connection $connection)
    {
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Notice reads support SQLite, MySQL and PostgreSQL only.');
        }
    }

    /**
     * @inheritDoc
     */
    public function summarize(NoticeSelection $selection, NoticeAccess $access): NoticeSummary
    {
        try {
            return NoticeSummary::fromNotices($this->matching($selection, $access));
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function list(ListNotices $query, NoticeAccess $access): NoticePage
    {
        try {
            $pagination = $query->pagination();
            $direction = $pagination->orderings()['recorded_at'] ?? 'DESC';
            $total = 0;
            $notices = [];
            foreach ($this->matching($query->selection(), $access, $direction) as $view) {
                if ($total >= $pagination->offset() && count($notices) < $pagination->limit()) {
                    $notices[] = $view;
                }

                ++$total;
            }

            return new NoticePage($notices, $total, $pagination->page(), $pagination->perPage());
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function inspect(GetNotice $query, NoticeAccess $access): ?NoticeView
    {
        try {
            foreach ($this->matching($query->selection(), $access) as $view) {
                return $view;
            }

            return null;
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }

    /**
     * Returns admitted matches in bounded keyset batches without retaining the corpus
     *
     * Scope constraints precede decoding; both trusted and caller predicates precede
     * all counts and returned metadata, with all-or-nothing notice disclosure
     *
     * @return Generator<int, NoticeView>
     */
    private function matching(NoticeSelection $selection, NoticeAccess $access, string $direction = 'DESC'): Generator
    {
        if (
            $this->connection->isTransactionActive()
            || $this->connection->getTransactionIsolation() === TransactionIsolationLevel::READ_UNCOMMITTED
        ) {
            throw new LogicException('Notice reads require an idle connection with committed isolation.');
        }

        $grant = $access->selection();
        $scopes = array_values(array_intersect($selection->scopes(), $grant->scopes()));
        if ($scopes === []) {
            return null;
        }

        $where = 'n.scope IN ('.implode(', ', array_fill(0, count($scopes), '?')).')';
        $parameters = $scopes;
        foreach ([$selection, $grant] as $constraint) {
            foreach (['id' => 'notice_id', 'producer' => 'producer'] as $filter => $column) {
                if (isset($constraint->filters()[$filter])) {
                    $where .= ' AND n.'.$column.' = ?';
                    $parameters[] = $constraint->filters()[$filter];
                }
            }
        }

        $afterTime = null;
        $afterPosition = 0;
        $operator = $direction === 'ASC' ? '>' : '<';
        do {
            $sql = self::SELECT.' WHERE '.$where;
            $batchParameters = $parameters;
            if ($afterTime !== null) {
                $sql .= ' AND (n.recorded_at '.$operator.' ? OR (n.recorded_at = ? AND n.position '.$operator.' ?))';
                array_push($batchParameters, $afterTime, $afterTime, $afterPosition);
            }

            $sql .= ' ORDER BY n.recorded_at '.$direction.', n.position '.$direction;
            $rows = $this->connection->fetchAllAssociative(
                $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, self::BATCH_SIZE),
                $batchParameters
            );
            foreach ($rows as $row) {
                $view = $this->view($row);
                $afterTime = $view->recordedAt();
                $afterPosition = (int) $row['position'];
                if (
                    $grant->matches($view->notice(), $view->recordedAt())
                    && $selection->matches($view->notice(), $view->recordedAt())
                ) {
                    yield $view;
                }
            }
        } while (count($rows) === self::BATCH_SIZE);
    }

    /**
     * Returns validated safe content without reading comparison or namespace state
     *
     * @param array<string, mixed> $row
     */
    private function view(array $row): NoticeView
    {
        $data = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
        $data['relationships'] = json_decode($row['relationships'], true, 32, JSON_THROW_ON_ERROR);
        $notice = Notice::fromArray($data);
        if (
            $notice->scope() !== $row['scope'] || $notice->producer() !== $row['producer']
            || $notice->id() !== $row['notice_id']
            || NoticeSelection::instant($row['recorded_at']) !== $row['recorded_at']
        ) {
            throw new DomainException('Inconsistent retained notice identity.');
        }

        return new NoticeView($notice, $row['recorded_at']);
    }
}
