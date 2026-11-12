<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Domain\Mcp\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Common\Domain\Mcp\Notice\Query\ListNotices;
use Fight\Common\Domain\Mcp\Notice\Query\SummarizeNotices;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeInspectionFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class DbalNoticeInspectionIntegrationTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_query_bus_summary_history_and_independent_detail_respect_current_caller_and_full_selection(string $backend, DbalNoticeTables $tables): void
    {
        $connection = $this->database($backend, $tables); $writer = new NoticeFixture($connection, $tables); $events = 0;
        $writer->eventDispatcher->addHandler(ClassName::underscore(NoticeRecorded::class), static function () use (&$events): void { ++$events; });
        for ($i = 0; $i < 105; ++$i) {
            $changes = ['id' => 'notice-'.$i, 'producer' => $i % 2 === 0 ? 'producer' : 'producer-2', 'topic' => $i % 2 === 0 ? 'catalog/tools' : 'catalog/resources'];
            if ($i === 103 || $i === 104) {
                $changes['relationships'] = ['subject' => null, 'recipients' => $i === 103 ? [] : null, 'author' => null, 'command' => null, 'event' => null, 'action' => null];
                $changes['occurred_at'] = null;
            }
            $writer->record($changes); $writer->record($changes);
        }
        $writer->record(['scope' => 'scope-2', 'metadata' => ['app/label' => 'CONCEALED-SENTINEL'], 'topic' => 'private-topic']);
        self::assertSame(106, $events);
        $connection->executeStatement('UPDATE '.$tables->noticeTable()->quoted($connection->getDatabasePlatform())." SET recorded_at = '2026-10-25T10:00:00.123456Z'");
        $state = $this->state($connection, $tables); $caller = new NoticeInspectionFixture($connection, $tables); $selection = NoticeSelection::create(['scope', 'scope-2']);
        $detail = $caller->queryBus->fetch(new GetNotice('scope', 'producer-2', 'notice-1'));
        self::assertSame('author-agent', $detail->notice()->relationships()->toArray()['author']);
        self::assertSame(['command' => 'command-id', 'event' => 'event-id', 'action' => 'action-id'], array_intersect_key($detail->notice()->relationships()->toArray(), array_flip(['command', 'event', 'action'])));
        self::assertSame('2026-10-25T01:02:03.12Z', $detail->notice()->occurrence()->toString());
        $summary = $caller->queryBus->fetch(new SummarizeNotices($selection))->toArray();
        self::assertSame(105, $summary['total']); self::assertSame(['named' => 103, 'broadcast' => 1, 'unknown' => 1], $summary['audience']);
        self::assertSame(['subject' => 2, 'author' => 2, 'causation' => 2, 'occurrence' => 2], $summary['unknown']);
        self::assertSame([['value' => 'catalog/resources', 'count' => 52], ['value' => 'catalog/tools', 'count' => 53]], $summary['topics']);
        $ids = [];
        for ($page = 1; $page <= 4; ++$page) {
            $result = $caller->queryBus->fetch(new ListNotices($selection, $page, 32, ['recorded_at' => 'ASC']));
            self::assertSame(105, $result->toArray()['total']);
            foreach ($result->notices() as $view) { $ids[] = $view->notice()->id(); self::assertArrayNotHasKey('position', $view->toArray()); }
            self::assertStringNotContainsString('CONCEALED-SENTINEL', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
        }
        self::assertSame(array_map(static fn ($i) => 'notice-'.$i, range(0, 104)), $ids);
        foreach (['topic' => 'catalog/tools', 'subject' => 'subject-agent', 'recipient' => 'recipient-agent', 'type' => 'catalog-changed', 'producer' => 'producer', 'author' => 'author-agent', 'command' => 'command-id', 'event' => 'event-id', 'action' => 'action-id', 'occurred_from' => '2026-10-25T01:02:03.120Z', 'recorded_from' => '2026-10-25T10:00:00.123456Z'] as $field => $value) {
            $filtered = NoticeSelection::create(['scope'], [$field => $value]);
            $expected = match ($field) { 'topic', 'producer' => 53, 'type', 'recorded_from' => 105, default => 103 };
            self::assertSame($expected, $caller->queryBus->fetch(new SummarizeNotices($filtered))->toArray()['total'], $field);
            self::assertSame($expected, $caller->queryBus->fetch(new ListNotices($filtered, 2, 2))->toArray()['total'], $field);
        }
        foreach (['recipient' => 'author-agent', 'subject' => 'recipient-agent', 'author' => 'subject-agent', 'occurred_until' => '2026-10-25T01:02:03.120000Z', 'recorded_until' => '2026-10-25T10:00:00.123456Z'] as $field => $value) {
            self::assertSame(0, $caller->queryBus->fetch(new SummarizeNotices(NoticeSelection::create(['scope'], [$field => $value])))->toArray()['total']);
        }
        foreach ([new GetNotice('scope-2', 'producer', 'notice-1'), new GetNotice('scope', 'producer', 'guessed')] as $query) { self::assertNull($caller->queryBus->fetch($query)); }
        $caller->access = NoticeAccess::create(['scope-2']);
        self::assertSame(1, $caller->queryBus->fetch(new SummarizeNotices($selection))->toArray()['total']);
        self::assertSame('CONCEALED-SENTINEL', $caller->queryBus->fetch(new GetNotice('scope-2', 'producer', 'notice-1'))->notice()->metadata()->toArray()['app/label']);
        $caller->access = NoticeAccess::create(['scope'], ['producer' => 'producer-2', 'recipient' => 'recipient-agent']);
        self::assertSame(51, $caller->queryBus->fetch(new SummarizeNotices($selection))->toArray()['total']);
        self::assertNull($caller->queryBus->fetch(new GetNotice('scope', 'producer', 'notice-0')));
        $caller->access = NoticeAccess::create([]);
        self::assertNull($caller->queryBus->fetch(new GetNotice('scope', 'producer-2', 'notice-1')));
        self::assertSame(0, $caller->queryBus->fetch(new SummarizeNotices($selection))->toArray()['total']);
        self::assertSame([], $caller->queryBus->fetch(new ListNotices($selection))->notices());
        self::assertSame(0, $caller->queryBus->fetch(new SummarizeNotices(NoticeSelection::create(['SUPER_ADMIN'])))->toArray()['total']);
        self::assertGreaterThan(30, $caller->admissions);
        self::assertSame($state, $this->state($connection, $tables)); self::assertSame(106, $events);
    }

    #[DataProvider('databases')]
    public function test_that_new_commits_and_fixture_disappearance_change_live_reads_without_expiry_or_access_lease(string $backend, DbalNoticeTables $tables): void
    {
        $connection = $this->database($backend, $tables); $writer = new NoticeFixture($connection, $tables); $reader = new NoticeInspectionFixture($connection, $tables);
        $noticeTable = $tables->noticeTable()->quoted($connection->getDatabasePlatform());
        $selection = NoticeSelection::create(['scope']); $writer->record(['id' => 'first']); $writer->record(['id' => 'second']);
        $before = $reader->queryBus->fetch(new ListNotices($selection, 1, 1)); self::assertSame(2, $before->toArray()['total']);
        $other = DriverManager::getConnection($connection->getParams()); $this->connections[] = $other;
        // Owned storage fixture only: this proves disappearance, not the removal/recovery use case.
        $other->executeStatement('DELETE FROM '.$noticeTable.' WHERE notice_id = ?', ['second']);
        self::assertNull($reader->queryBus->fetch(new GetNotice('scope', 'producer', 'second')));
        self::assertSame(1, $reader->queryBus->fetch(new SummarizeNotices($selection))->toArray()['total']);
        (new NoticeFixture($other, $tables))->record(['id' => 'third']);
        self::assertSame('third', $reader->queryBus->fetch(new ListNotices($selection, 1, 1))->notices()[0]->notice()->id());
        $reader->access = NoticeAccess::create([]); self::assertNull($reader->queryBus->fetch(new GetNotice('scope', 'producer', 'first')));
        $reader->access = NoticeAccess::create(['scope']); self::assertNotNull($reader->queryBus->fetch(new GetNotice('scope', 'producer', 'first')));
        self::assertSame(2, $reader->queryBus->fetch(new ListNotices($selection, 2, 1))->toArray()['total']);
        $other->executeStatement('DELETE FROM '.$noticeTable);
        self::assertSame(0, $reader->queryBus->fetch(new SummarizeNotices($selection))->toArray()['total']);
        self::assertNull($reader->queryBus->fetch(new GetNotice('scope', 'producer', 'first')));
        $other->executeStatement('DROP TABLE '.$tables->comparisonTable()->quoted($other->getDatabasePlatform())); $other->executeStatement('DROP TABLE '.$tables->relationshipTable()->quoted($other->getDatabasePlatform()));
        foreach ([new SummarizeNotices($selection), new ListNotices($selection), new GetNotice('scope', 'producer', 'first')] as $query) {
            try { $reader->queryBus->fetch($query); self::fail('Database failure must not become empty.'); }
            catch (NoticeReadFailed $error) { self::assertSame('Retained notices could not be read completely.', $error->getMessage()); }
        }
    }

    #[DataProvider('databases')]
    public function test_that_summary_dimension_overflow_is_explicit_on_the_actual_routed_storage_path(string $backend, DbalNoticeTables $tables): void
    {
        $connection = $this->database($backend, $tables); $writer = new NoticeFixture($connection, $tables); $reader = new NoticeInspectionFixture($connection, $tables);
        for ($i = 0; $i < 128; ++$i) { $writer->record(['id' => 'notice-'.$i, 'topic' => 'topic-'.$i]); }
        $query = new SummarizeNotices(NoticeSelection::create(['scope']));
        self::assertCount(128, $reader->queryBus->fetch($query)->toArray()['topics']);
        $writer->record(['id' => 'overflow', 'topic' => 'topic-overflow']);
        try { $reader->queryBus->fetch($query); self::fail('Full summary must not truncate.'); } catch (NoticeReadFailed $error) { self::assertSame('Retained notices could not be read completely.', $error->getMessage()); }
        self::assertSame(129, $reader->queryBus->fetch(new ListNotices(NoticeSelection::create(['scope'])))->toArray()['total']);
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.'-configured' => [$backend, new DbalNoticeTables('NoticeState', 'Order', 'NoticeRelationships', 'NoticeComparisons')];
        }
    }

    protected function tearDown(): void { foreach ($this->connections as $connection) { $connection->close(); } foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-notice-inspection-'); } parent::tearDown(); }

    private function database(string $backend, DbalNoticeTables $tables): Connection
    {
        if ($backend === 'sqlite') { $directory = sys_get_temp_dir().'/fight-notice-inspection-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($directory, 0700)); $this->directories[] = $directory; $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/notices.sqlite']; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual owned database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = DriverManager::getConnection($params); $this->connections[] = $connection;
        if ($backend === 'sqlite') { $connection->executeStatement('PRAGMA foreign_keys = ON'); }
        // Drop children from every journey selection before shared default parents.
        $names = ['IsolationComparisons', 'IsolationRelations', 'PartialComparisons', 'PartialRelations'];
        foreach (self::databases() as [, $selection]) {
            foreach ([$selection->comparisonTable(), $selection->relationshipTable()] as $table) { $names[] = $table->name(); }
        }
        $names = [...$names, 'Select', 'IsolationState', 'PartialState'];
        foreach (self::databases() as [, $selection]) {
            foreach ([$selection->noticeTable(), $selection->stateTable()] as $table) { $names[] = $table->name(); }
        }
        foreach (array_unique($names) as $name) { $connection->executeStatement('DROP TABLE IF EXISTS '.$connection->getDatabasePlatform()->quoteSingleIdentifier($name)); }
        (new DbalNoticeSchema($tables))->install($connection); return $connection;
    }

    private function state(Connection $connection, DbalNoticeTables $tables): array
    {
        $state = [];
        foreach ([$tables->stateTable(), $tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()] as $table) {
            $state[$table->name()] = $connection->fetchAllAssociative('SELECT * FROM '.$table->quoted($connection->getDatabasePlatform()));
        }
        return $state;
    }
}
