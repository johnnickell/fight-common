<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionTables;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;

/**
 * Class LifecycleTables
 *
 * Explicit eight-table composition transported to independent journey processes
 */
final readonly class LifecycleTables
{
    public DbalTaskTables $task;
    public DbalNoticeTables $notice;
    public DbalSubscriptionTables $subscription;

    public function __construct(public array $configuration = [])
    {
        $this->task = new DbalTaskTables(...($configuration['task'] ?? []));
        $this->notice = new DbalNoticeTables(...($configuration['notice'] ?? []));
        $this->subscription = new DbalSubscriptionTables(...($configuration['subscription'] ?? []));
    }

    public static function configured(): self
    {
        return new self([
            'task' => ['stateTable' => 'LiveTaskState', 'taskTable' => 'LiveTasks'],
            'notice' => ['stateTable' => 'LiveNoticeState', 'noticeTable' => 'LiveNotices', 'relationshipTable' => 'LiveRelationships', 'comparisonTable' => 'LiveComparisons'],
            'subscription' => ['stateTable' => 'LiveSubscriptionState', 'subscriptionTable' => 'Listen']
        ]);
    }

    public function names(): array
    {
        return [
            'fight_task_state' => $this->task->stateTable(), 'fight_tasks' => $this->task->taskTable(),
            'fight_notice_state' => $this->notice->stateTable(), 'fight_notices' => $this->notice->noticeTable(),
            'fight_notice_relationships' => $this->notice->relationshipTable(), 'fight_notice_comparisons' => $this->notice->comparisonTable(),
            'fight_mcp_subscription_state' => $this->subscription->stateTable(), 'fight_mcp_subscriptions' => $this->subscription->subscriptionTable()
        ];
    }

    public function sql(Connection $connection, string $sql): string
    {
        return strtr($sql, array_map(static fn ($table) => $table->quoted($connection->getDatabasePlatform()), $this->names()));
    }
}
