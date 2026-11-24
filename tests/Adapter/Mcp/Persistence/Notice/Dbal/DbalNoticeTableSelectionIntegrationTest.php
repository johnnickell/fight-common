<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRepository;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTransaction;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Common\Domain\Mcp\Notice\Query\ListNotices;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversNothing]
final class DbalNoticeTableSelectionIntegrationTest extends UnitTestCase
{
    private const array DEFAULT_NOTICE_TABLE_NAMES = [
        'fight_notice_state',
        'fight_notices',
        'fight_notice_relationships',
        'fight_notice_comparisons'
    ];

    private const RECORDED_AT = '2026-10-25T01:02:04.000000Z';

    #[DataProvider('databases')]
    public function test_that_partial_selection_uses_shared_parents_but_ignores_overridden_default_state_and_comparisons(string $backend): void
    {
        $tables = new DbalNoticeTables(stateTable: 'SelectionState', comparisonTable: 'SelectionComparisons');
        $connection = self::connect($backend);
        try {
            $schema = new DbalNoticeSchema($tables);
            $schema->install($connection);
            $transaction = new DbalNoticeTransaction($connection, $tables);
            $repository = new DbalNoticeRepository($transaction);
            $notice = Notice::fromArray(NoticeFixture::data());
            $transaction->commitTransactional(static fn () => $repository->append($notice, self::RECORDED_AT));

            $defaults = new Schema();
            (new DbalNoticeSchema())->addTo($defaults, $connection->getDatabasePlatform());
            foreach (['fight_notice_state', 'fight_notice_comparisons'] as $name) {
                $connection->createSchemaManager()->createTable($defaults->getTable($name));
            }
            $connection->insert('fight_notice_state', ['singleton' => 1, 'version' => 1, 'last_position' => 91, 'namespace' => str_repeat('a', 32), 'lost_through' => 80]);
            $connection->insert('fight_notice_comparisons', ['position' => 1, 'version' => 99, 'payload' => 'must-not-supply-retry']);
            $snapshotDefaults = static fn () => [
                $connection->fetchAllAssociative('SELECT * FROM fight_notice_state'),
                $connection->fetchAllAssociative('SELECT * FROM fight_notice_comparisons')
            ];
            $before = $snapshotDefaults();

            $schema->install($connection);
            $recorded = $transaction->commitTransactional(static fn () => $repository->getByIdentity($notice->identity()));
            self::assertNotNull($recorded);
            self::assertSame($notice->comparison(), $recorded->notice()->comparison());
            $removed = Notice::fromArray(NoticeFixture::data(['id' => 'removed']));
            $transaction->commitTransactional(static fn () => $repository->append($removed, self::RECORDED_AT));
            $inspection = new DbalNoticeReader($connection, $tables);
            self::assertNotNull($inspection->inspect(new GetNotice('scope', 'producer', 'notice-1'), NoticeAccess::create(['scope'])));
            $reader = new DbalNoticeRecoveryReader($connection, $tables);
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame([1, 2], array_map(static fn ($row) => $row->position(), $window->notices()));
            self::assertSame(2, $window->highWater());
            self::assertSame(0, $window->lostThrough());

            $selection = NoticeRemovalSelection::fromArray([
                'version'   => 1,
                'namespace' => $window->namespace(),
                'targets'   => [['scope' => 'scope', 'producer' => 'producer', 'id' => 'removed', 'position' => 2]]
            ]);
            $transaction->commitTransactional(static fn () => $repository->remove($selection));
            self::assertSame(2, $reader->readWindow('scope', 0, 100)->lostThrough());
            self::assertSame($before, $snapshotDefaults());
            foreach (['fight_notices', 'fight_notice_relationships'] as $name) {
                self::assertSame([1], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$name)));
            }
        } finally {
            self::close($connection, $tables);
        }
    }

    #[DataProvider('databases')]
    public function test_that_a_fully_selected_layout_never_reads_or_changes_populated_default_tables(string $backend): void
    {
        $tables = new DbalNoticeTables('IsolatedNoticeState', 'Select', 'IsolatedNoticeRelations', 'IsolatedNoticeComparisons');
        $defaults = new DbalNoticeTables();
        $path = $backend === 'sqlite' ? tempnam(sys_get_temp_dir(), 'notice-selection-') : null;
        $connection = $path === null ? self::connect($backend) : DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $observer = DriverManager::getConnection($connection->getParams());
        if ($path !== null) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
            $observer->executeStatement('PRAGMA foreign_keys = ON');
        }
        try {
            (new DbalNoticeSchema($defaults))->install($connection);
            (new DbalNoticeSchema($tables))->install($connection);
            $defaultFixture = new NoticeFixture($connection, $defaults);
            $selectedFixture = new NoticeFixture($connection, $tables);
            $record = static fn (array $fields = []) => CommandMessage::create(RecordNotice::fromArray(NoticeFixture::data($fields)));
            $defaultFixture->recordNoticeHandler->handle($record(['topic' => 'default-only']));
            $before = self::snapshot($observer, self::names($defaults));
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $inspection = new DbalNoticeReader($observer, $tables);
            $query = new GetNotice('scope', 'producer', 'notice-1');
            $access = NoticeAccess::create(['scope']);
            self::assertSame([], $reader->readAfter('scope', 0, 100));
            self::assertNull($inspection->inspect($query, $access));
            foreach ([[], ['id' => 'survivor'], []] as $fields) {
                $selectedFixture->recordNoticeHandler->handle($record($fields));
            }
            try {
                $selectedFixture->recordNoticeHandler->handle($record(['topic' => 'conflict']));
                self::fail('Selected comparison must reject a conflicting retry.');
            } catch (NoticeConflict) {
                self::assertSame('catalog/tools', $inspection->inspect($query, $access)->notice()->toArray()['topic']);
            }
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame(['notice-1', 'survivor'], array_map(static fn ($row) => $row->notice()->id(), $window->notices()));
            self::assertNotSame($defaultFixture->noticeRecoveryReader->readWindow('scope', 0, 1)->namespace(), $window->namespace());
            $transaction = new DbalNoticeTransaction($connection, $tables);
            $repository = new DbalNoticeRepository($transaction);
            $selection = NoticeRemovalSelection::fromArray([
                'version' => 1, 'namespace' => $window->namespace(),
                'targets' => [['scope' => 'scope', 'producer' => 'producer', 'id' => 'notice-1', 'position' => 1]]
            ]);
            $transaction->commitTransactional(static fn () => $repository->remove($selection));
            self::assertNull($inspection->inspect($query, $access));
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame(1, $window->lostThrough());
            self::assertSame(2, $window->highWater());
            foreach ([$tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()] as $table) {
                self::assertSame([2], array_map('intval', $connection->fetchFirstColumn('SELECT position FROM '.$table->quoted($connection->getDatabasePlatform()))));
            }
            self::assertSame($before, self::snapshot($observer, self::names($defaults)));
            self::assertSame('default-only', (new DbalNoticeRecoveryReader($observer, $defaults))->readAfter('scope', 0, 1)[0]->notice()->toArray()['topic']);
        } finally {
            $observer->close();
            try {
                self::close($connection, $tables);
            } finally {
                if ($path !== null) {
                    unlink($path);
                }
            }
        }
    }

    #[DataProvider('singleOverrides')]
    public function test_that_single_overrides_install_only_selected_tables_and_reject_missing_or_incompatible_storage(string $backend, DbalNoticeTables $tables): void
    {
        $connection = self::connect($backend);
        try {
            $schema = new DbalNoticeSchema($tables);
            $schema->install($connection);
            $schema->install($connection);
            $expected = self::names($tables);
            sort($expected);
            self::assertSame($expected, self::selectedNames($connection, $expected));
            $transaction = new DbalNoticeTransaction($connection, $tables);
            $repository = new DbalNoticeRepository($transaction);
            $notice = Notice::fromArray(NoticeFixture::data());
            $transaction->commitTransactional(static fn () => $repository->append($notice, self::RECORDED_AT));
            $before = self::snapshot($connection, $expected);

            $missing = new DbalNoticeTables('AbsentSelectionState', 'AbsentSelectionRows', 'AbsentSelectionRelations', 'AbsentSelectionComparisons');
            $missingTransaction = new DbalNoticeTransaction($connection, $missing);
            $missingRepository = new DbalNoticeRepository($missingTransaction);
            $missingReader = new DbalNoticeRecoveryReader($connection, $missing);
            self::assertStorageFailure(static fn () => $missingTransaction->commitTransactional(static fn () => $missingRepository->append($notice, self::RECORDED_AT)));
            self::assertStorageFailure(static fn () => $missingReader->readWindow('scope', 0, 100));
            self::assertSame($before, self::snapshot($connection, $expected));
            self::assertSame($expected, self::selectedNames($connection, [...$expected, ...self::names($missing)]));

            $platform = $connection->getDatabasePlatform();
            $state = $tables->stateTable()->quoted($platform);
            $connection->update($state, ['version' => 2], ['singleton' => 1]);
            self::assertInstallFailure($schema, $connection, 'Unsupported notice schema.');
            $connection->update($state, ['version' => 1], ['singleton' => 1]);
            $connection->executeStatement('DROP TABLE '.$tables->comparisonTable()->quoted($platform));
            self::assertInstallFailure($schema, $connection, 'Incomplete notice schema.');
            self::assertCount(3, self::selectedNames($connection, $expected));
        } finally {
            self::close($connection, $tables);
        }
    }

    public function test_that_summary_overflow_rejects_without_truncating_the_actual_sqlite_history(): void
    {
        $tables = new DbalNoticeTables();
        $connection = self::connect('sqlite');
        try {
            (new DbalNoticeSchema($tables))->install($connection);
            $reader = new DbalNoticeReader($connection, $tables);
            $transaction = new DbalNoticeTransaction($connection, $tables);
            $repository = new DbalNoticeRepository($transaction);
            $append = static fn (int $index) => $repository->append(
                Notice::fromArray(NoticeFixture::data(['id' => 'notice-'.$index, 'topic' => 'topic-'.$index])),
                self::RECORDED_AT
            );
            foreach (range(0, 127) as $index) {
                $transaction->commitTransactional(static fn () => $append($index));
            }
            $selection = NoticeSelection::create(['scope']);
            $access = NoticeAccess::create(['scope']);
            self::assertCount(128, $reader->summarize($selection, $access)->toArray()['topics']);
            $transaction->commitTransactional(static fn () => $append(128));
            try {
                $reader->summarize($selection, $access);
                self::fail('An oversized full summary must reject, not truncate.');
            } catch (NoticeReadFailed $failure) {
                self::assertSame('Retained notices could not be read completely.', $failure->getMessage());
            }
            self::assertSame(129, $reader->list(new ListNotices($selection), $access)->toArray()['total']);
        } finally {
            self::close($connection, $tables);
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend];
        }
    }

    public static function singleOverrides(): iterable
    {
        foreach (self::databases() as [$backend]) {
            yield $backend.'-state' => [$backend, new DbalNoticeTables(stateTable: 'SelectionState')];
            yield $backend.'-notice' => [$backend, new DbalNoticeTables(noticeTable: 'Order')];
            yield $backend.'-relationships' => [$backend, new DbalNoticeTables(relationshipTable: 'SelectionRelations')];
            yield $backend.'-comparisons' => [$backend, new DbalNoticeTables(comparisonTable: 'SelectionComparisons')];
        }
    }

    private static function connect(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
            $connection->executeStatement('PRAGMA foreign_keys = ON');

            return $connection;
        }

        $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn, 'Actual disposable server database required.');
        $parser = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']);

        return DriverManager::getConnection($parser->parse($dsn));
    }

    private static function close(Connection $connection, DbalNoticeTables $tables): void
    {
        $platform = $connection->getDatabasePlatform();
        // Defaults are deliberately shared with some selections. Drop children before either possible parent.
        $defaults = new DbalNoticeTables();
        $names = [
            $tables->comparisonTable(), $tables->relationshipTable(),
            $defaults->comparisonTable(), $defaults->relationshipTable(),
            $tables->noticeTable(), $tables->stateTable(), $defaults->noticeTable(), $defaults->stateTable()
        ];
        foreach (array_unique(array_map(static fn ($table) => $table->name(), $names)) as $name) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$platform->quoteSingleIdentifier($name));
        }
        $connection->close();
    }

    private static function names(DbalNoticeTables $tables): array
    {
        return [$tables->comparisonTable()->name(), $tables->relationshipTable()->name(), $tables->noticeTable()->name(), $tables->stateTable()->name()];
    }

    private static function selectedNames(Connection $connection, array $expected): array
    {
        $actual = array_map(static fn ($name) => trim($name, '`"'), $connection->createSchemaManager()->listTableNames());
        $selected = array_values(array_intersect($actual, [...$expected, ...self::DEFAULT_NOTICE_TABLE_NAMES]));
        sort($selected);

        return $selected;
    }

    private static function snapshot(Connection $connection, array $tables): array
    {
        $platform = $connection->getDatabasePlatform();

        return array_map(static fn ($name) => $connection->fetchAllAssociative('SELECT * FROM '.$platform->quoteSingleIdentifier($name)), $tables);
    }

    private static function assertStorageFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Uninstalled storage must not fall back or install itself.');
        } catch (NoticeStorageFailed $failure) {
            self::assertFalse($failure->outcomeUncertain());
        }
    }

    private static function assertInstallFailure(DbalNoticeSchema $schema, Connection $connection, string $message): void
    {
        try {
            $schema->install($connection);
            self::fail('Installation must not overwrite incompatible storage.');
        } catch (RuntimeException $failure) {
            self::assertSame($message, $failure->getMessage());
        }
    }
}
