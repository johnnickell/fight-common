<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Subscription;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberPage;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberReadScope;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberSummary;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberView;
use Fight\Common\Application\Mcp\Subscription\Exception\SubscriberReadFailed;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberAccess;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReader;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Subscription\SubscriberSelection;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Generator;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Class DbalSubscriberReader
 *
 * Direct bounded observations of the lifecycle owner's existing records, never a second store
 */
final readonly class DbalSubscriberReader implements SubscriberReader
{
    /**
     * Constructs DbalSubscriberReader
     *
     * @phpstan-param (Closure(): int)|null $clock
     */
    public function __construct(
        private Connection $connection,
        private string $storageScope,
        private ?Closure $clock = null
    ) {
        Notice::reference($storageScope);
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Subscriber reads support SQLite, MySQL and PostgreSQL only.');
        }
    }

    /**
     * @inheritDoc
     */
    public function summarize(SubscriberSelection $selection, SubscriberAccess $access): SubscriberSummary
    {
        try {
            $scope = $this->scope();

            return SubscriberSummary::fromSubscribers($this->matching($selection, $access, $scope), $scope);
        } catch (Throwable $throwable) {
            throw new SubscriberReadFailed($throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function list(ListSubscribers $query, SubscriberAccess $access): SubscriberPage
    {
        try {
            $scope = $this->scope();
            $page = $query->pagination();
            $direction = $page->orderings()['opened_at'] ?? 'DESC';
            $total = 0;
            $views = [];
            foreach ($this->matching($query->selection(), $access, $scope, $direction) as $view) {
                if ($total >= $page->offset() && count($views) < $page->limit()) {
                    $views[] = $view;
                }

                ++$total;
            }

            return new SubscriberPage($views, $total, $page->page(), $page->perPage(), $scope);
        } catch (Throwable $throwable) {
            throw new SubscriberReadFailed($throwable);
        }
    }

    /**
     * Returns a protocol map from stored PHP fields, including the empty-array map representation
     */
    private function filterMap(mixed $value): StrictJson
    {
        return $value === [] ? StrictJson::fromObject() : $value;
    }

    /**
     * Returns a single invocation clock without probing concealed global freshness
     */
    private function scope(): SubscriberReadScope
    {
        return new SubscriberReadScope($this->storageScope, $this->clock === null ? time() : ($this->clock)());
    }

    /**
     * Returns complete admitted matches in immutable-opening-time and bytewise identity order
     *
     * Batches retain at most 32 technical payloads; 8192 examined rows bound churn without truncating success
     *
     * @return Generator<int, SubscriberView>
     */
    private function matching(
        SubscriberSelection $selection,
        SubscriberAccess $access,
        SubscriberReadScope $scope,
        string $direction = 'DESC'
    ): Generator {
        if ($access->isDenied() || $selection->scopes() === []) {
            return null;
        }

        if (
            $this->connection->isTransactionActive()
            || $this->connection->getTransactionIsolation() === TransactionIsolationLevel::READ_UNCOMMITTED
            || (int) $this->connection->fetchOne(
                'SELECT version FROM fight_mcp_subscription_state WHERE singleton = 1'
            ) !== 1
        ) {
            throw new LogicException('Subscriber reads require idle committed version-one storage.');
        }

        $afterTime = null;
        $afterId = '';
        $examined = 0;
        $operator = $direction === 'ASC' ? '>' : '<';
        do {
            $sql = <<<'SQL'
                SELECT registration_id, context, opened_at, observed_at, expires_at, closed_at
                FROM fight_mcp_subscriptions
                SQL;
            $parameters = [];
            if ($afterTime !== null) {
                $sql .= ' WHERE (opened_at '.$operator.' ? OR (opened_at = ? AND registration_id '.$operator.' ?))';
                $parameters = [$afterTime, $afterTime, $afterId];
            }

            $sql .= ' ORDER BY opened_at '.$direction.', registration_id '.$direction;
            $rows = $this->connection->fetchAllAssociative(
                $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, 32),
                $parameters
            );
            foreach ($rows as $row) {
                if (++$examined > 8192 || strlen($row['context']) > 65536) {
                    throw new LogicException('Subscriber read scan or context bound exceeded.');
                }

                $afterTime = (int) $row['opened_at'];
                $afterId = $row['registration_id'];
                $context = StrictJson::fromString($row['context']);
                NoticeSelection::structure(
                    $context->properties(),
                    ['subscriber', 'selection', 'requested', 'accepted']
                );
                $noticeSelection = NoticeSelection::fromArray(
                    json_decode($context->get('selection')->toString(), true, flags: JSON_THROW_ON_ERROR)
                );
                if (!$access->allows($afterId, $noticeSelection)) {
                    continue;
                }

                $view = new SubscriberView(
                    $afterId,
                    $context->get('subscriber'),
                    $noticeSelection,
                    $this->filterMap($context->get('requested')),
                    $this->filterMap($context->get('accepted')),
                    $afterTime,
                    (int) $row['observed_at'],
                    (int) $row['expires_at'],
                    (int) $row['closed_at'],
                    $scope->queriedAt()
                );
                if ($view->matches($selection)) {
                    yield $view;
                }
            }
        } while (count($rows) === 32);
    }
}
