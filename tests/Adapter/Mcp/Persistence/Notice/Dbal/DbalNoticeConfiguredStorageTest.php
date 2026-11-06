<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecords;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRepository;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTransaction;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalNoticeSchema::class)]
#[CoversClass(DbalNoticeTransaction::class)]
#[CoversClass(DbalNoticeRepository::class)]
#[CoversClass(DbalNoticeRecords::class)]
#[CoversClass(DbalNoticeReader::class)]
#[CoversClass(DbalNoticeRecoveryReader::class)]
final class DbalNoticeConfiguredStorageTest extends UnitTestCase
{
    public function test_that_populated_default_and_selected_storage_remain_independent_through_retries_reads_and_removal(): void
    {
        $connection = $this->database();
        (new DbalNoticeSchema())->install($connection);
        $defaults = new NoticeFixture($connection); $defaults->record(['topic' => 'default-only']);
        $before = $this->snapshot($connection, new DbalNoticeTables());
        $tables = new DbalNoticeTables('NoticeState', 'Order', 'NoticeRelationships', 'NoticeComparisons');
        $schema = new DbalNoticeSchema($tables); $schema->install($connection); $schema->install($connection);
        $selected = new NoticeRemovalFixture($connection, $tables);
        self::assertSame($tables, $selected->recording->transactionalUnitOfWork->tables());
        self::assertSame([], $selected->recording->noticeRecoveryReader->readAfter('scope', 0, 100));
        $selected->recording->record(); $selected->recording->record();
        $query = new GetNotice('scope', 'producer', 'notice-1');
        $reader = new DbalNoticeReader($connection, $tables);
        self::assertSame('catalog/tools', $reader->inspect($query, NoticeAccess::create(['scope']))->notice()->toArray()['topic']);
        try { $selected->recording->record(['topic' => 'changed']); self::fail('Selected comparison must conflict.'); } catch (NoticeConflict) { self::assertTrue(true); }
        $selected->remove($selected->selection([NoticeRemovalFixture::target()]));
        self::assertNull($reader->inspect($query, NoticeAccess::create(['scope'])));
        $window = $selected->recording->noticeRecoveryReader->readWindow('scope', 0, 100);
        self::assertSame(1, $window->highWater()); self::assertSame(1, $window->lostThrough()); self::assertSame([], $window->notices());
        self::assertSame($before, $this->snapshot($connection, new DbalNoticeTables()));
        $selected->recording->record();
        self::assertSame(2, $selected->recording->noticeRecoveryReader->readAfter('scope', 0, 100)[0]->position());
        self::assertSame($before, $this->snapshot($connection, new DbalNoticeTables()));
    }

    #[DataProvider('partialSelections')]
    public function test_that_each_partial_selection_installs_only_its_selected_names_and_is_reusable(DbalNoticeTables $tables): void
    {
        $connection = $this->database();
        $schema = new DbalNoticeSchema($tables); $schema->install($connection); $schema->install($connection);
        $expected = array_map(static fn ($table) => $table->name(), $this->selectedTables($tables)); sort($expected);
        $actual = $connection->createSchemaManager()->listTableNames(); sort($actual); self::assertSame($expected, $actual);
        $fixture = new NoticeFixture($connection, $tables); $fixture->record(); $fixture->record();
        self::assertSame(1, $fixture->noticeRecoveryReader->readWindow('scope', 0, 100)->highWater());
    }

    public static function partialSelections(): iterable
    {
        yield [new DbalNoticeTables(stateTable: 'State')];
        yield [new DbalNoticeTables(noticeTable: 'Order')];
        yield [new DbalNoticeTables(relationshipTable: 'Relations')];
        yield [new DbalNoticeTables(comparisonTable: 'Comparisons')];
    }

    public function test_that_configuration_and_runtime_do_not_install_or_migrate_and_changed_selection_rejects_stale_continuity(): void
    {
        $connection = $this->database();
        (new DbalNoticeSchema())->install($connection); $defaults = new NoticeRemovalFixture($connection); $defaults->recording->record();
        $oldSelection = $defaults->selection([NoticeRemovalFixture::target()]);
        $before = $this->snapshot($connection, new DbalNoticeTables());
        $tables = new DbalNoticeTables('NewState', 'NewNotices', 'NewRelations', 'NewComparisons');
        $selected = new NoticeFixture($connection, $tables);
        self::assertCount(4, $connection->createSchemaManager()->listTableNames());
        try { $selected->record(); self::fail('Missing selected storage cannot fall back.'); } catch (NoticeStorageFailed) { self::assertTrue(true); }
        self::assertCount(4, $connection->createSchemaManager()->listTableNames());
        (new DbalNoticeSchema($tables))->install($connection);
        $window = $selected->noticeRecoveryReader->readWindow('scope', 0, 100);
        self::assertSame([], $window->notices()); self::assertSame(0, $window->highWater()); self::assertNotSame($oldSelection->namespace(), $window->namespace());
        self::assertFalse($window->hasUnbrokenHistoryAfter($oldSelection->namespace(), 0));
        self::assertSame($before, $this->snapshot($connection, new DbalNoticeTables()));
        $partial = new DbalNoticeSchema(new DbalNoticeTables(stateTable: 'MissingState'));
        try { $partial->install($connection); self::fail('Existing omitted tables constitute a partial selected schema.'); } catch (RuntimeException $failure) { self::assertSame('Incomplete notice schema.', $failure->getMessage()); }
        self::assertCount(8, $connection->createSchemaManager()->listTableNames());
    }

    #[DataProvider('incompatibleSchemas')]
    public function test_that_selected_schema_validation_rejects_incompatible_indexes_columns_and_parent_links(string $corruption): void
    {
        $connection = $this->database();
        $tables = new DbalNoticeTables('State', 'Order', 'Relations', 'Comparisons');
        $schema = new DbalNoticeSchema($tables); $schema->install($connection);
        if ($corruption === 'comparison-version') {
            $connection->executeStatement('ALTER TABLE "Comparisons" DROP COLUMN version');
        } elseif ($corruption === 'index') {
            $index = 'fight_notice_recovery_'.substr(hash('sha256', 'Order'), 0, 12);
            $connection->executeStatement('DROP INDEX '.$index);
        } else {
            $table = $corruption === 'relationships' ? 'Relations' : 'Comparisons';
            $connection->executeStatement('DROP TABLE "'.$table.'"');
            $connection->executeStatement('CREATE TABLE "WrongParent" (position INTEGER PRIMARY KEY)');
            $connection->executeStatement('CREATE TABLE "'.$table.'" (position INTEGER PRIMARY KEY, payload TEXT NOT NULL, version INTEGER, FOREIGN KEY (position) REFERENCES "WrongParent" (position) ON DELETE CASCADE)');
        }
        $this->expectException(RuntimeException::class); $this->expectExceptionMessage('Unsupported notice schema.');
        $schema->install($connection);
    }

    public static function incompatibleSchemas(): iterable
    {
        foreach (['comparison-version', 'index', 'relationships', 'comparisons'] as $corruption) { yield [$corruption]; }
    }

    private function database(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function selectedTables(DbalNoticeTables $tables): array
    {
        return [$tables->stateTable(), $tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()];
    }

    private function snapshot(Connection $connection, DbalNoticeTables $tables): array
    {
        return array_map(fn ($table) => $connection->fetchAllAssociative('SELECT * FROM '.$table->quoted($connection->getDatabasePlatform())), $this->selectedTables($tables));
    }
}
