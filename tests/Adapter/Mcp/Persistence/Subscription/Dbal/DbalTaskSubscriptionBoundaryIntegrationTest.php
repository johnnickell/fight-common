<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Subscription\Dbal;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionStore;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\Mcp\Task\TaskLiveFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class DbalTaskSubscriptionBoundaryIntegrationTest extends UnitTestCase
{
    #[DataProvider('faults')]
    public function test_that_a_live_comparison_cannot_disclose_after_revocation_or_registration_loss(
        string $backend,
        bool $configured,
        string $fault,
        string $phase
    ): void {
        $directory = sys_get_temp_dir().'/fight-task-source-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        if ($backend === 'sqlite') {
            $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/db.sqlite'];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn);
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = DriverManager::getConnection($params);
        $independent = DriverManager::getConnection($params);
        $tables = $configured ? LifecycleTables::configured() : new LifecycleTables();
        $platform = $connection->getDatabasePlatform();
        $owned = [$tables->task->taskTable(), $tables->task->stateTable(), $tables->subscription->subscriptionTable(), $tables->subscription->stateTable(), $tables->notice->comparisonTable(), $tables->notice->relationshipTable(), $tables->notice->noticeTable(), $tables->notice->stateTable()];
        try {
            foreach ($owned as $table) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($platform));
            }
            (new DbalTaskSchema($tables->task))->install($connection);
            (new DbalSubscriptionSchema($tables->subscription))->install($connection);
            // The Task-only execution must not read a Notice window.
            $fixture = new TaskLiveFixture($connection, tables: $tables);
            $writer = new TaskFixture($independent, $tables->task);
            $writer->create();
            $tracking = new DbalSubscriptionStore($independent, tables: $tables->subscription);
            $registrationTable = $tables->subscription->subscriptionTable()->quoted($platform);
            $comparisons = 0;
            $triggered = false;
            $updated = false;
            $fixture->taskFixture->onComparison = static function () use ($fixture, $independent, $tracking, $registrationTable, $fault, $phase, &$comparisons, &$triggered, &$updated): void {
                ++$comparisons;
                if ($triggered || !match ($phase) {
                    'opening' => $comparisons === 3,
                    'baseline' => $comparisons === 4,
                    'update' => $updated
                }) { return; }
                $triggered = true;
                if ($fault === 'signal') {
                    $fixture->allowed = false;
                    return;
                }
                $row = $independent->fetchAssociative("SELECT registration_id, owner FROM $registrationTable WHERE closed_at = 0");
                self::assertIsArray($row);
                $tracking->close($row['registration_id'], $row['owner'], time());
                if ($fault === 'cleanup') {
                    self::assertSame(1, $tracking->cleanup(time(), 16));
                }
            };
            $message = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request()->getBody());
            $frames = [];
            $fixture->endpoint->registry->subscriptions()->execution($message)->run(
                static function (string $frame) use ($writer, $phase, &$updated, &$frames): void {
                    if ($frame === ": keepalive\n\n") {
                        return;
                    }
                    $frames[] = json_decode(trim(substr($frame, strlen('data: '))), true, flags: JSON_THROW_ON_ERROR);
                    if ($phase === 'update' && count($frames) === 2) {
                        $writer->record(TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])));
                        $updated = true;
                    }
                },
                $fixture->endpoint
            );
            self::assertTrue($triggered);
            self::assertCount(match ($phase) { 'opening' => 0, 'baseline' => 1, 'update' => 2 }, $frames);
            if ($frames !== []) {
                self::assertSame('notifications/subscriptions/acknowledged', $frames[0]['method']);
            }
            if ($phase === 'update') {
                self::assertSame('notifications/tasks', $frames[1]['method']);
                self::assertSame('working', $frames[1]['params']['status']);
            }
            self::assertFalse($fixture->guarded);
            self::assertNotEmpty($fixture->endpoint->diagnostics);
            self::assertSame(0, (int) $independent->fetchOne("SELECT COUNT(*) FROM $registrationTable WHERE closed_at = 0"));
            self::assertSame($phase === 'update' ? 'failed' : 'working', $writer->get()->get('state')->get('status'));
            self::assertNull($writer->cancellation()->get('cancellation'));
        } finally {
            try {
                foreach ($owned as $table) {
                    $connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($platform));
                }
            } finally {
                $independent->close();
                $connection->close();
                $this->removeTemporaryDirectory($directory, 'fight-task-source-');
            }
        }
    }

    public static function faults(): iterable
    {
        yield 'sqlite-default-cleanup-update' => ['sqlite', false, 'cleanup', 'update'];
        yield 'sqlite-configured-close-baseline' => ['sqlite', true, 'close', 'baseline'];
        yield 'mysql-default-cleanup-opening' => ['mysql', false, 'cleanup', 'opening'];
        yield 'mysql-configured-signal-baseline' => ['mysql', true, 'signal', 'baseline'];
        yield 'postgresql-default-signal-update' => ['postgresql', false, 'signal', 'update'];
        yield 'postgresql-default-close-opening' => ['postgresql', false, 'close', 'opening'];
    }
}
