<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Detail\Dbal\DbalDetailReader;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Mcp\Persistence\Observation\Dbal\DbalActivityReader;
use Fight\Common\Adapter\Mcp\Persistence\Phase\Dbal\DbalPhaseReader;
use Fight\Common\Adapter\Mcp\Persistence\Session\Dbal\DbalExecutionReader;
use Fight\Common\Application\Mcp\Detail\Service\DetailAccess;
use Fight\Common\Application\Mcp\Observation\Exception\ProtectedContentRemoved;
use Fight\Common\Application\Mcp\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Mcp\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Mcp\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\Mcp\Execution\RecordingFixture;
use Fight\Test\Common\TestCase\Mcp\Execution\RemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class DbalConfiguredInspectionIntegrationTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('selections')]
    public function test_that_each_override_reads_and_removes_only_selected_counterparts(string $backend, array $overrides): void
    {
        $connection = $this->database($backend);
        $tables = new DbalObservationTables(...$overrides);
        (new DbalObservationSchema(tables: $tables))->install($connection);
        $sentinels = [];
        foreach ($this->names($tables) as $index => $name) {
            $default = DbalObservationSchema::TABLES[$index];
            if ($name !== $default) {
                $connection->executeStatement('CREATE TABLE '.$default.' (marker INTEGER)');
                $connection->insert($default, ['marker' => 42]);
                $sentinels[] = $default;
            }
        }
        $transaction = new DbalRecordingTransaction(connection: $connection, tables: $tables);
        $recording = $this->seed($transaction, 'selected-private');
        $fresh = $this->connect($connection->getParams());
        $before = $this->inspect($fresh, $tables);
        self::assertSame('selected-private', base64_decode($before['detail']['chunk']['data'], true));
        self::assertSame('retained', $before['activity']['activities'][0]['details'][0]['availability']);
        $safeBefore = $this->snapshot($fresh, $tables, false);
        $removal = new RemovalFixture($transaction);
        $detail = ObservationFixture::detail();
        $removal->commandBus->execute(RemovalFixture::command($detail));
        $after = $this->inspect($fresh, $tables);
        self::assertNull($after['detail']['chunk']);
        self::assertSame('missing', $after['detail']['reference']['availability']);
        self::assertSame('missing', $after['activity']['activities'][0]['details'][0]['availability']);
        self::assertSame($safeBefore, $this->snapshot($fresh, $tables, false));
        self::assertSame($before['summary'], $after['summary']);
        self::assertSame($before['phases'], $after['phases']);
        $removal->commandBus->execute(RemovalFixture::command($detail));
        try {
            $recording->record($detail, ProtectedContent::create($detail->detail(), 'new-body'));
            self::fail('Retained selected identity must prevent restoration.');
        } catch (ProtectedContentRemoved) {
            self::assertSame($after, $this->inspect($fresh, $tables));
        }
        foreach ($sentinels as $default) {
            self::assertSame([['marker' => 42]], $fresh->fetchAllAssociative('SELECT marker FROM '.$default));
        }
        self::assertFalse($connection->isTransactionActive());
        self::assertFalse($fresh->isTransactionActive());
    }

    #[DataProvider('backends')]
    public function test_that_populated_defaults_are_not_fallback_or_migrated_and_selected_removal_leaves_them_intact(string $backend): void
    {
        $connection = $this->database($backend);
        $defaults = new DbalObservationTables();
        (new DbalObservationSchema())->install($connection);
        $this->seed(new DbalRecordingTransaction($connection), 'default-private');
        $before = $this->snapshot($connection, $defaults);
        $namesBefore = $connection->createSchemaManager()->listTableNames();
        $tables = new DbalObservationTables(...self::overrides());
        $reader = new DbalExecutionReader(connection: $connection, tables: $tables);
        $phaseReader = new DbalPhaseReader(connection: $connection, tables: $tables);
        $activityReader = new DbalActivityReader(connection: $connection, tables: $tables);
        $detailReader = new DbalDetailReader(connection: $connection, tables: $tables);
        new DbalObservationSchema(tables: $tables);
        self::assertSame($namesBefore, $connection->createSchemaManager()->listTableNames(), 'Configuration is not installation.');
        $access = $this->access();
        $detailAccess = $this->detailAccess();
        $selection = ExecutionSelection::create(['project']);
        $detailQuery = $this->detailQuery();
        $operations = [
            static fn () => $reader->summarize($selection, $access),
            static fn () => $reader->list(new ListExecutions($selection), $access),
            static fn () => $reader->inspect(new GetExecution('project', 'session'), $access),
            static fn () => $phaseReader->list(new ListExecutionPhases('project', 'session'), $access),
            static fn () => $activityReader->list(new ListPhaseActivity('project', 'session', 'phase'), $access),
            static fn () => $detailReader->get($detailQuery, $detailAccess),
            static fn () => $detailReader->messages(new ListCapturedMessages($detailQuery->selection()), $detailAccess)
        ];
        foreach ($operations as $operation) {
            try { $operation(); self::fail('Missing selected storage must fail explicitly.'); }
            catch (ExecutionReadFailed $failure) { self::assertNotNull($failure->getPrevious()); }
        }
        self::assertSame($namesBefore, $connection->createSchemaManager()->listTableNames());
        self::assertSame($before, $this->snapshot($connection, $defaults));
        (new DbalObservationSchema($tables))->install($connection);
        self::assertSame(0, $reader->summarize($selection, $access)->toArray()['executions']);
        self::assertSame(0, $reader->list(new ListExecutions($selection), $access)->toArray()['total']);
        self::assertNull($reader->inspect(new GetExecution('project', 'session'), $access));
        self::assertNull($phaseReader->list(new ListExecutionPhases('project', 'session'), $access));
        self::assertNull($activityReader->list(new ListPhaseActivity('project', 'session', 'phase'), $access));
        self::assertNull($detailReader->get($detailQuery, $detailAccess));
        self::assertNull($detailReader->messages(new ListCapturedMessages($detailQuery->selection()), $detailAccess));
        $transaction = new DbalRecordingTransaction($connection, $tables);
        $this->seed($transaction, 'selected-private');
        self::assertSame('selected-private', base64_decode($this->inspect($connection, $tables)['detail']['chunk']['data'], true));
        (new RemovalFixture($transaction))->commandBus->execute(RemovalFixture::command(ObservationFixture::detail()));
        self::assertNull($this->inspect($connection, $tables)['detail']['chunk']);
        self::assertSame('default-private', base64_decode($this->inspect($connection, $defaults)['detail']['chunk']['data'], true));
        self::assertSame($before, $this->snapshot($connection, $defaults));
    }

    #[DataProvider('backends')]
    public function test_that_incompatible_selected_content_fails_without_reading_default_bodies_or_repairing_storage(string $backend): void
    {
        $connection = $this->database($backend);
        $tables = new DbalObservationTables(...self::overrides());
        (new DbalObservationSchema($tables))->install($connection);
        $this->seed(new DbalRecordingTransaction($connection, $tables), 'selected-private');
        $contentTable = $tables->contentTable()->quoted($connection->getDatabasePlatform());
        $connection->executeStatement('DROP TABLE '.$contentTable);
        $connection->executeStatement('CREATE TABLE '.$contentTable.' (marker INTEGER)');
        $connection->executeStatement('CREATE TABLE fight_execution_contents (marker INTEGER)');
        $connection->insert('fight_execution_contents', ['marker' => 42]);
        $connection->insert($contentTable, ['marker' => 17]);
        $reader = new DbalExecutionReader($connection, $tables);
        self::assertSame(1, $reader->summarize(ExecutionSelection::create(['project']), $this->access())->toArray()['executions'], 'Safe summaries do not touch protected storage.');
        foreach ([
            fn () => (new DbalActivityReader($connection, $tables))->list(new ListPhaseActivity('project', 'session', 'phase'), $this->access()),
            fn () => (new DbalDetailReader($connection, $tables))->get($this->detailQuery(), $this->detailAccess()),
            fn () => (new DbalDetailReader($connection, $tables))->messages(new ListCapturedMessages($this->detailQuery()->selection()), $this->detailAccess())
        ] as $operation) {
            try { $operation(); self::fail('An incompatible selected table is not an empty result.'); }
            catch (ExecutionReadFailed $failure) {
                self::assertSame('Execution evidence could not be read completely.', $failure->getMessage());
                self::assertNotNull($failure->getPrevious());
            }
        }
        self::assertSame([['marker' => 17]], $connection->fetchAllAssociative('SELECT marker FROM '.$contentTable));
        self::assertSame([['marker' => 42]], $connection->fetchAllAssociative('SELECT marker FROM fight_execution_contents'));
    }

    public static function backends(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    public static function selections(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.'-default' => [$backend, []];
            foreach (self::overrides() as $argument => $name) { yield $backend.'-'.$argument => [$backend, [$argument => $name]]; }
            yield $backend.'-all' => [$backend, self::overrides()];
        }
    }

    private static function overrides(): array
    {
        return ['schemaTable' => 'InspectState', 'sessionTable' => 'InspectSessions', 'phaseTable' => 'InspectPhases', 'observationTable' => 'InspectObservations', 'contentTable' => 'Order'];
    }

    private function inspect(Connection $connection, DbalObservationTables $tables): array
    {
        $reader = new DbalExecutionReader(connection: $connection, tables: $tables);
        $access = $this->access();
        $selection = ExecutionSelection::create(['project']);
        $detailReader = new DbalDetailReader(connection: $connection, tables: $tables);
        $query = $this->detailQuery();
        $summary = $reader->summarize($selection, $access)->toArray();
        self::assertSame(1, $summary['executions']);
        self::assertSame(1, $summary['activities']);
        self::assertSame(1, $reader->list(new ListExecutions($selection), $access)->toArray()['total']);
        self::assertNotNull($reader->inspect(new GetExecution('project', 'session'), $access));
        $phases = (new DbalPhaseReader(connection: $connection, tables: $tables))->list(new ListExecutionPhases('project', 'session'), $access)->toArray();
        self::assertSame(1, $phases['total']);
        $activity = (new DbalActivityReader(connection: $connection, tables: $tables))->list(new ListPhaseActivity('project', 'session', 'phase'), $access)->toArray();
        self::assertSame(1, $activity['total']);
        $detail = $detailReader->get($query, $this->detailAccess())->toArray();
        $messages = $detailReader->messages(new ListCapturedMessages($query->selection()), $this->detailAccess())->toArray();
        self::assertSame(1, $messages['total']);
        self::assertSame($detail, $messages['messages'][0]);
        return ['summary' => $summary, 'phases' => $phases, 'activity' => $activity, 'detail' => $detail];
    }

    private function seed(DbalRecordingTransaction $transaction, string $body): RecordingFixture
    {
        $recording = new RecordingFixture($transaction);
        foreach ([ObservationFixture::session(), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) { $recording->record($observation); }
        $detail = ObservationFixture::detail();
        $recording->record($detail, ProtectedContent::create($detail->detail(), $body));
        return $recording;
    }

    private function access(): ExecutionAccess
    {
        return ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
    }

    private function detailAccess(): DetailAccess
    {
        return DetailAccess::create(['project' => null], metadataGrants: ['project' => null], bodyGrants: ['project' => null]);
    }

    private function detailQuery(): GetCapturedDetail
    {
        $detail = ObservationFixture::detail();
        return new GetCapturedDetail(RemovalFixture::command($detail)->selection(), $detail->id(), $detail->detail()->kind());
    }

    private function names(DbalObservationTables $tables): array
    {
        return array_map(static fn ($table) => $table->name(), [$tables->schemaTable(), $tables->sessionTable(), $tables->phaseTable(), $tables->observationTable(), $tables->contentTable()]);
    }

    private function snapshot(Connection $connection, DbalObservationTables $tables, bool $includeContent = true): array
    {
        $names = $this->names($tables);
        if (!$includeContent) { array_pop($names); }
        return array_map(fn (string $name) => $connection->fetchAllAssociative('SELECT * FROM '.$connection->getDatabasePlatform()->quoteSingleIdentifier($name)), $names);
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $directory = sys_get_temp_dir().'/fight-configured-inspection-'.bin2hex(random_bytes(8));
            mkdir($directory, 0700);
            $this->directories[] = $directory;
            $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/db.sqlite'];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Real disposable database is required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params);
        foreach (array_reverse(array_map(null, DbalObservationSchema::TABLES, array_values(self::overrides()))) as $counterparts) {
            foreach ($counterparts as $table) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$connection->getDatabasePlatform()->quoteSingleIdentifier($table));
            }
        }
        return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        $this->connections[] = $connection;
        if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA foreign_keys = ON'); }
        return $connection;
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { foreach (glob($directory.'/*') as $file) { unlink($file); } rmdir($directory); }
        parent::tearDown();
    }
}
