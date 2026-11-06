<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeInspectionFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRecoveryFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversNothing]
final class NoticeTableSelectionJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_populated_defaults_never_supply_selected_identity_comparison_inspection_recovery_or_removal(string $backend): void
    {
        $tables = new DbalNoticeTables('IsolationState', 'Select', 'IsolationRelations', 'IsolationComparisons');
        $connection = $this->database($backend);
        (new DbalNoticeSchema($tables))->install($connection);
        (new DbalNoticeSchema())->install($connection);
        $defaults = new NoticeFixture($connection); $defaults->record(['topic' => 'default-only']);
        $before = $this->snapshot($connection, new DbalNoticeTables());
        $selected = new NoticeRemovalFixture($connection, $tables);
        self::assertSame([], $selected->recording->noticeRecoveryReader->readAfter('scope', 0, 100));
        $selected->recording->record(); $selected->recording->record(['id' => 'survivor']); $selected->recording->record();
        try { $selected->recording->record(['topic' => 'conflict']); self::fail('Selected comparisons must conflict.'); } catch (NoticeConflict) { self::assertTrue(true); }
        $other = $this->connect($connection->getParams());
        $inspection = new NoticeInspectionFixture($other, $tables);
        $recovery = new NoticeRecoveryFixture($other, tables: $tables);
        self::assertSame('catalog/tools', $inspection->queryBus->fetch(new GetNotice('scope', 'producer', 'notice-1'))->notice()->toArray()['topic']);
        $batch = $recovery->queryBus->fetch(new RecoverNotices(NoticeSelection::create(['scope'])));
        self::assertSame(['notice-1', 'survivor'], array_column($batch->toArray()['notices'], 'id'));
        self::assertNotSame($defaults->noticeRecoveryReader->readWindow('scope', 0, 1)->namespace(), $selected->recording->noticeRecoveryReader->readWindow('scope', 0, 1)->namespace());
        $selected->remove($selected->selection([NoticeRemovalFixture::target()]));
        self::assertNull($inspection->queryBus->fetch(new GetNotice('scope', 'producer', 'notice-1')));
        $window = $selected->recording->noticeRecoveryReader->readWindow('scope', 0, 100);
        self::assertSame(1, $window->lostThrough()); self::assertSame(2, $window->highWater());
        foreach (array_slice($this->names($tables), 1) as $table) {
            self::assertSame([2], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table->quoted($connection->getDatabasePlatform()))));
        }
        self::assertSame('incomplete', $recovery->call(['scopes' => ['scope']])['body']['result']['structuredContent']['status']);
        self::assertSame($before, $this->snapshot($connection, new DbalNoticeTables()));
        self::assertSame('default-only', $defaults->noticeRecoveryReader->readAfter('scope', 0, 100)[0]->notice()->toArray()['topic']);
    }

    #[DataProvider('databases')]
    public function test_that_partial_selection_uses_omitted_defaults_but_not_populated_overridden_state_or_comparisons(string $backend): void
    {
        $tables = new DbalNoticeTables(stateTable: 'PartialState', comparisonTable: 'PartialComparisons');
        $connection = $this->database($backend);
        (new DbalNoticeSchema($tables))->install($connection);
        $fixture = new NoticeRemovalFixture($connection, $tables); $fixture->recording->record();
        // Create only the two overridden default counterparts, retaining the intentionally shared parent.
        $emptyConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = $emptyConnection->createSchemaManager()->introspectSchema();
        (new DbalNoticeSchema())->addTo($schema, $connection->getDatabasePlatform());
        foreach (['fight_notice_state', 'fight_notice_comparisons'] as $name) {
            $connection->createSchemaManager()->createTable($schema->getTable($name));
        }
        $emptyConnection->close();
        $connection->insert('fight_notice_state', ['singleton' => 1, 'version' => 1, 'last_position' => 91, 'namespace' => str_repeat('a', 32), 'lost_through' => 80]);
        $connection->insert('fight_notice_comparisons', ['position' => 1, 'version' => 99, 'payload' => 'must-not-supply-retry']);
        $snapshot = static fn () => [
            $connection->fetchAllAssociative('SELECT * FROM fight_notice_state'),
            $connection->fetchAllAssociative('SELECT * FROM fight_notice_comparisons')
        ];
        $before = $snapshot();
        (new DbalNoticeSchema($tables))->install($connection);
        $fixture->recording->record(); $fixture->recording->record(['id' => 'removed']);
        $inspection = new NoticeInspectionFixture($connection, $tables);
        self::assertNotNull($inspection->queryBus->fetch(new GetNotice('scope', 'producer', 'notice-1')));
        $window = $fixture->recording->noticeRecoveryReader->readWindow('scope', 0, 100);
        self::assertSame([1, 2], array_map(static fn ($row) => $row->position(), $window->notices()));
        self::assertSame(2, $window->highWater()); self::assertSame(0, $window->lostThrough());
        $fixture->remove($fixture->selection([NoticeRemovalFixture::target(2, 'removed')]));
        self::assertSame(2, $fixture->recording->noticeRecoveryReader->readWindow('scope', 0, 100)->lostThrough());
        self::assertSame($before, $snapshot());
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_notice_relationships'));
    }

    #[DataProvider('partialSelections')]
    public function test_that_each_single_override_installs_only_selected_tables_and_missing_or_partial_changed_selections_never_fall_back(string $backend, DbalNoticeTables $tables): void
    {
        $connection = $this->database($backend);
        $schema = new DbalNoticeSchema($tables); $schema->install($connection); $schema->install($connection);
        $expected = array_map(static fn ($table) => $table->name(), $this->names($tables)); sort($expected);
        $actual = array_map(static fn ($name) => trim($name, '`"'), $connection->createSchemaManager()->listTableNames()); sort($actual);
        self::assertSame($expected, array_values(array_intersect($actual, array_unique([...$expected, ...DbalNoticeSchema::TABLES]))));
        $fixture = new NoticeFixture($connection, $tables); $fixture->record(); $fixture->record();
        $before = $this->snapshot($connection, $tables);
        $missingTables = new DbalNoticeTables('IsolationState', 'Select', 'IsolationRelations', 'IsolationComparisons');
        $missing = new NoticeFixture($connection, $missingTables);
        try { $missing->record(); self::fail('Runtime must not install or fall back.'); } catch (NoticeStorageFailed) { self::assertTrue(true); }
        try { $missing->noticeRecoveryReader->readWindow('scope', 0, 100); self::fail('Missing evidence must not become complete empty history.'); } catch (NoticeStorageFailed) { self::assertTrue(true); }
        self::assertSame($before, $this->snapshot($connection, $tables));
        $state = $tables->stateTable()->quoted($connection->getDatabasePlatform());
        $connection->update($state, ['version' => 2], ['singleton' => 1]);
        try { $schema->install($connection); self::fail('Selected version is authoritative.'); } catch (RuntimeException $failure) { self::assertSame('Unsupported notice schema.', $failure->getMessage()); }
        $connection->update($state, ['version' => 1], ['singleton' => 1]);
        $connection->executeStatement('DROP TABLE '.$tables->comparisonTable()->quoted($connection->getDatabasePlatform()));
        try { $schema->install($connection); self::fail('Partial selection requires operator recovery.'); } catch (RuntimeException $failure) { self::assertSame('Incomplete notice schema.', $failure->getMessage()); }
        self::assertCount(3, array_intersect($expected, array_map(static fn ($name) => trim($name, '`"'), $connection->createSchemaManager()->listTableNames())));
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    public static function partialSelections(): iterable
    {
        foreach (self::databases() as [$backend]) {
            yield $backend.'-state' => [$backend, new DbalNoticeTables(stateTable: 'PartialState')];
            yield $backend.'-notice' => [$backend, new DbalNoticeTables(noticeTable: 'Order')];
            yield $backend.'-relationships' => [$backend, new DbalNoticeTables(relationshipTable: 'PartialRelations')];
            yield $backend.'-comparisons' => [$backend, new DbalNoticeTables(comparisonTable: 'PartialComparisons')];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-notice-selection-'); }
        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $directory = sys_get_temp_dir().'/fight-notice-selection-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($directory, 0700)); $this->directories[] = $directory;
            $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/notices.sqlite'];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual owned database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params); $platform = $connection->getDatabasePlatform();
        // Retire only this suite's known fixtures, children before all possible parents.
        foreach (['fight_notice_comparisons', 'fight_notice_relationships', 'PartialComparisons', 'PartialRelations', 'IsolationComparisons', 'IsolationRelations', 'NoticeComparisons', 'NoticeRelationships', 'PartialNoticeComparisons', 'fight_notices', 'Order', 'Select', 'fight_notice_state', 'PartialState', 'IsolationState', 'NoticeState', 'PartialNoticeState'] as $name) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$platform->quoteSingleIdentifier($name));
        }
        return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params); $this->connections[] = $connection;
        if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA foreign_keys = ON'); }
        return $connection;
    }

    private function names(DbalNoticeTables $tables): array
    {
        return [$tables->stateTable(), $tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()];
    }

    private function snapshot(Connection $connection, DbalNoticeTables $tables): array
    {
        return array_map(fn ($table) => $connection->fetchAllAssociative('SELECT * FROM '.$table->quoted($connection->getDatabasePlatform())), $this->names($tables));
    }
}
