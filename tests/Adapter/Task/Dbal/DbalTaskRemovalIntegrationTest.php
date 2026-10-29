<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Task\Exception\TaskStorageFailed;
use Fight\Common\Domain\Task\Command\RecordTaskState;
use Fight\Common\Domain\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class DbalTaskRemovalIntegrationTest extends UnitTestCase
{
    private ?Connection $connection = null;

    #[DataProvider('databases')]
    public function test_that_maximum_selected_removal_erases_all_owned_material_but_not_other_scopes(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection);
        $ids = array_map(static fn (int $id): string => str_pad(dechex($id), 32, '0', STR_PAD_LEFT), range(1, 100));
        foreach ($ids as $index => $id) {
            $fixture->create(id: $id);
            $state = match ($index % 5) {
                0 => TaskState::create('working', 'Protected progress'),
                1 => TaskFixture::input(),
                2 => TaskFixture::completed(true),
                3 => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])),
                4 => TaskState::create('cancelled')
            };
            if ($state->status() === 'input_required') { $fixture->issue(id: $id); }
            else { $fixture->commands->execute(new RecordTaskState('scope', $id, 'producer', 1, $state)); }
        }
        $protected = $connection->fetchOne('SELECT payload FROM fight_tasks WHERE scope = ? AND task_id = ?', ['scope', $ids[1]]);
        self::assertStringContainsString('creation_digest', $protected);
        self::assertStringContainsString('last_digest', $protected);
        self::assertStringContainsString('input-', $protected);
        $connection->insert('fight_tasks', ['scope' => 'other', 'task_id' => $ids[0], 'payload' => 'unrelated protected data']);
        $fixture->remove($ids);
        foreach ($ids as $id) { self::assertNull($fixture->get($id)); }
        self::assertSame([['scope' => 'other', 'task_id' => $ids[0], 'payload' => 'unrelated protected data']], $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM fight_task_state WHERE singleton = 1'));
        self::assertCount(100, $fixture->removalChecks);
        $fixture->remove($ids); self::assertCount(200, $fixture->removalChecks);
    }

    #[DataProvider('databases')]
    public function test_that_a_real_second_delete_failure_rolls_back_bodies_and_comparison_evidence(string $backend): void
    {
        $connection = $this->database($backend); $fixture = new TaskFixture($connection); $other = str_repeat('a', 32);
        $fixture->create(); $fixture->record(TaskFixture::completed()); $fixture->create(id: $other);
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id');
        if ($backend === 'sqlite') {
            $connection->executeStatement("CREATE TRIGGER fail_removal BEFORE DELETE ON fight_tasks WHEN OLD.task_id = '".$other."' BEGIN SELECT RAISE(ABORT, 'private'); END");
        } elseif ($backend === 'mysql') {
            $connection->executeStatement('CREATE TABLE task_removal_hold (scope VARCHAR(96) NOT NULL, task_id VARCHAR(32) NOT NULL, FOREIGN KEY (scope, task_id) REFERENCES fight_tasks (scope, task_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
            $connection->insert('task_removal_hold', ['scope' => 'scope', 'task_id' => $other]);
        } else {
            $connection->executeStatement("CREATE FUNCTION fail_task_removal() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF OLD.task_id = '".$other."' THEN RAISE EXCEPTION 'private'; END IF; RETURN OLD; END $$");
            $connection->executeStatement('CREATE TRIGGER fail_removal BEFORE DELETE ON fight_tasks FOR EACH ROW EXECUTE FUNCTION fail_task_removal()');
        }
        try {
            try { $fixture->remove([TaskFixture::ID, $other]); self::fail('Real delete failure required.'); }
            catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); self::assertSame('Task storage failed.', $failure->getMessage()); }
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id'));
            self::assertSame('completed', $fixture->get()->get('state')->get('status'));
        } finally {
            $connection->executeStatement(match ($backend) { 'mysql' => 'DROP TABLE task_removal_hold', 'postgresql' => 'DROP TRIGGER fail_removal ON fight_tasks', default => 'DROP TRIGGER fail_removal' });
            if ($backend === 'postgresql') { $connection->executeStatement('DROP FUNCTION fail_task_removal()'); }
        }
        $fixture->remove([TaskFixture::ID, $other]); self::assertNull($fixture->get());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    protected function tearDown(): void
    {
        $this->connection?->close();
        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'memory' => true]; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual disposable server database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = DriverManager::getConnection($params); $this->connection = $connection;
        foreach (array_reverse(DbalTaskSchema::TABLES) as $table) { $connection->executeStatement('DROP TABLE IF EXISTS '.$table); }
        (new DbalTaskSchema())->install($connection);
        return $connection;
    }
}
