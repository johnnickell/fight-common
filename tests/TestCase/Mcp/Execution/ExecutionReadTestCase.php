<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Execution;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalPhaseReader;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalSessionRepository;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\Execution\Observation\CommandHandler\RecordPhaseObservationHandler;
use Fight\Common\Application\Mcp\Execution\Observation\CommandHandler\RecordProtectedDetailHandler;
use Fight\Common\Application\Mcp\Execution\Observation\CommandHandler\RecordSessionObservationHandler;
use Fight\Common\Application\Mcp\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Application\Mcp\Execution\ObservationAdmission;
use Fight\Common\Application\Mcp\Execution\Phase\QueryHandler\ListExecutionPhasesHandler;
use Fight\Common\Application\Mcp\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Mcp\Execution\Session\QueryHandler\GetExecutionHandler;
use Fight\Common\Application\Mcp\Execution\Session\QueryHandler\ListExecutionsHandler;
use Fight\Common\Application\Mcp\Execution\Session\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionReadAdmission;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Mcp\Observation\Command\RecordProtectedDetail;
use Fight\Common\Domain\Mcp\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Common\Domain\Mcp\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Mcp\Usage\UsageCompleteness;
use Fight\Common\Domain\Mcp\Usage\UsageMeasurement;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class ExecutionReadTestCase extends UnitTestCase
{
    private array $connections = [];

    public static function databases(): iterable
    {
        yield 'sqlite' => ['sqlite'];
    }

    #[DataProvider('databases')]
    public function test_that_session_query_envelopes_reconstruct_and_route_without_carrying_read_authority(string $backend): void
    {
        $db = $this->database($backend);
        $this->writer($db)($this->session('project', 'visible'));
        $this->writer($db)($this->session('project', 'hidden'));
        [$bus, $admission] = $this->bus($db, ['project' => ['visible']]);
        $selection = ExecutionSelection::create(['project']);
        $summary = QueryMessage::create(new SummarizeExecutions($selection));
        $page = QueryMessage::create(new ListExecutions($selection, 1, 1));
        $summary = QueryMessage::arrayDeserialize($summary->arraySerialize());
        $page = QueryMessage::arrayDeserialize($page->arraySerialize());
        self::assertInstanceOf(SummarizeExecutions::class, $summary->payload());
        self::assertInstanceOf(ListExecutions::class, $page->payload());
        self::assertSame(1, $bus->dispatch($summary)->toArray()['executions']);
        $result = $bus->dispatch($page)->toArray();
        self::assertSame(1, $result['total']);
        self::assertSame('visible', $result['executions'][0]['session']['session_id']);
        $admission->access = ExecutionAccess::create([]);
        self::assertSame(0, $bus->dispatch($summary)->toArray()['executions']);
        self::assertSame([], $bus->dispatch($page)->executions());
    }

    #[DataProvider('databases')]
    public function test_that_bus_reads_full_authorized_selection_without_body_or_retry_multiplication(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $a = $this->session('project', 'a');
        $record($a);
        $complete = ObservationFixture::session(['session' => $a->session()->toArray(), 'observation_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff', 'outcome' => 'succeeded']);
        $record($complete);
        $record($complete);
        $phase = ObservationFixture::phase(['session' => $a->session()->toArray(), 'phase' => PhaseReference::fromStrings('a', 'phase', null, 'performer', 'work', 1)->toArray()]);
        $record($phase);
        $activity = ObservationFixture::activity(['session' => $a->session()->toArray(), 'phase' => $phase->phase()->toArray()]);
        $record($activity);
        $record($activity);
        $empty = $activity->toArray();
        $empty['observation_id'] = ObservationId::generate()->toString();
        $empty['activity']['usage'] = [];
        $record(Observation::fromArray($empty));
        $detail = ObservationFixture::detail(['session' => $a->session()->toArray(), 'phase' => $phase->phase()->toArray()]);
        $record($detail, ProtectedContent::create($detail->detail(), 'PROTECTED-SENTINEL'));
        $record($this->session('project', 'b', 'failed'));
        $record($this->session('other', 'c', 'cancelled'));
        $record($this->session('other', 'hidden', 'succeeded'));
        // Deliberately tie receipt time: full UUID determines the latest session outcome.
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-20T12:00:00.123456Z'");
        [$bus, $admission] = $this->bus($db, ['project' => null, 'other' => ['c']]);
        $selection = ExecutionSelection::create(['project', 'other']);
        $before = $this->state($db);
        $summary = $bus->fetch(new SummarizeExecutions($selection))->toArray();
        self::assertSame(3, $summary['executions']);
        self::assertSame(['unknown' => 0, 'succeeded' => 1, 'failed' => 1, 'cancelled' => 1], $summary['outcomes']);
        self::assertSame(2, $summary['activities']);
        self::assertSame(1, $summary['activities_without_usage']);
        self::assertSame('unknown', $summary['capture_completeness']);
        self::assertSame('2026-10-20T12:00:00.123456Z', $summary['latest_recorded_at']);
        $usage = array_column($summary['usage'], null, 'metric');
        self::assertSame('0', $usage['provider/input']['measured']);
        self::assertSame('12.5', $usage['provider/output']['measured']);
        self::assertSame(['complete' => 0, 'partial' => 1, 'unknown' => 0, 'unavailable' => 0], $usage['provider/output']['counts']);
        self::assertNull($usage['provider/cost']['measured']);
        self::assertSame(1, $usage['provider/capacity']['counts']['unknown']);
        self::assertSame('response', $usage['provider/output']['provenance']);
        self::assertSame('token', $usage['provider/output']['unit']);
        $page = $bus->fetch(new ListExecutions($selection, 1, 2));
        self::assertCount(2, $page->executions());
        self::assertSame(['c', 'a'], array_map(static fn ($view) => $view->session()->sessionId(), $page->executions()));
        self::assertSame(3, $page->toArray()['total']);
        self::assertSame('b', $bus->fetch(new ListExecutions($selection, 2, 2))->executions()[0]->session()->sessionId());
        self::assertStringNotContainsString('PROTECTED-SENTINEL', json_encode([$summary, $page->toArray()], JSON_THROW_ON_ERROR));
        self::assertSame($before, $this->state($db), 'Queries must not write subjects, evidence or content.');
        self::assertSame(3, $admission->calls);
        // Agent policy grants only a, independently of human permissions.
        $admission->access = ExecutionAccess::create(['project' => ['a']]);
        self::assertSame(1, $bus->fetch(new SummarizeExecutions($selection))->toArray()['executions']);
        self::assertSame(1, $bus->fetch(new ListExecutions($selection))->toArray()['total']);
        $admission->access = ExecutionAccess::create([]);
        self::assertSame(0, $bus->fetch(new SummarizeExecutions($selection))->toArray()['executions']);
        self::assertSame([], $bus->fetch(new ListExecutions($selection))->executions());
        self::assertSame(0, $bus->fetch(new ListExecutions(ExecutionSelection::create(['other'], ['session_id' => 'hidden'])))->toArray()['total']);
    }

    #[DataProvider('databases')]
    public function test_that_live_pages_filters_empty_and_missing_outcomes_are_honest(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record($this->session('project', 'a'));
        $record($this->session('project', 'b'));
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-20T12:00:00.000001Z'");
        [$bus] = $this->bus($db, ['project' => null, 'omitted' => null]);
        $selection = ExecutionSelection::create(['project']);
        self::assertSame('a', $bus->fetch(new ListExecutions($selection, 1, 1))->executions()[0]->session()->sessionId());
        $record($this->session('project', 'new'));
        self::assertSame('a', $bus->fetch(new ListExecutions($selection, 2, 1))->executions()[0]->session()->sessionId(), 'Live inserts may repeat a row across pages.');
        $db->delete('fight_execution_observations', ['project_id' => 'project', 'session_id' => 'new']);
        $db->delete('fight_execution_sessions', ['project_id' => 'project', 'session_id' => 'new']);
        self::assertSame('b', $bus->fetch(new ListExecutions($selection, 2, 1))->executions()[0]->session()->sessionId());
        $db->delete('fight_execution_observations', ['project_id' => 'project', 'session_id' => 'a']);
        $summary = $bus->fetch(new SummarizeExecutions($selection))->toArray();
        self::assertSame(2, $summary['outcomes']['unknown']);
        self::assertSame([], $summary['usage']);
        $ascending = $bus->fetch(new ListExecutions($selection, orderings: ['latest_recorded_at' => 'asc']));
        self::assertNull($ascending->executions()[0]->latestRecordedAt());
        self::assertSame('a', $ascending->executions()[0]->session()->sessionId());
        self::assertSame(1, $bus->fetch(new ListExecutions(ExecutionSelection::create(['project'], ['session_id' => 'a', 'consumer_reference' => 'consumer:a'])))->toArray()['total']);
        self::assertSame(0, $bus->fetch(new ListExecutions(ExecutionSelection::create(['project'], ['consumer_reference' => 'unknown'])))->toArray()['total']);
        self::assertSame([], $bus->fetch(new ListExecutions($selection, 100, 100))->executions());
        $empty = $bus->fetch(new SummarizeExecutions(ExecutionSelection::create([])))->toArray();
        self::assertSame(0, $empty['executions']);
        self::assertNull($empty['latest_recorded_at']);
        self::assertSame([], $empty['usage']);
        $db->executeStatement('DROP TABLE fight_execution_contents');
        self::assertSame(2, $bus->fetch(new SummarizeExecutions($selection))->toArray()['executions'], 'Safe reads do not need protected storage.');
        $db->executeStatement('DROP TABLE fight_execution_observations');
        foreach ([new SummarizeExecutions($selection), new ListExecutions($selection)] as $query) {
            try { $bus->fetch($query); self::fail('A failed database must not become an empty selection.'); }
            catch (ExecutionReadFailed $failure) {
                self::assertSame('Execution evidence could not be read completely.', $failure->getMessage());
                self::assertNotNull($failure->getPrevious());
            }
        }
    }

    #[DataProvider('databases')]
    public function test_that_batch_boundaries_preserve_full_totals_units_and_provenance(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        for ($i = 0; $i < 101; ++$i) { $record($this->session('project', sprintf('s%03d', $i))); }
        $session = SessionReference::fromStrings('project', 's000', 'consumer:s000');
        $phase = ObservationFixture::phase(['session' => $session->toArray(), 'phase' => PhaseReference::fromStrings('s000', 'phase', null, 'agent', 'work', 1)->toArray()]);
        $record($phase);
        for ($i = 0; $i < 101; ++$i) {
            $activity = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString(), 'session' => $session->toArray(), 'phase' => $phase->phase()->toArray()])->toArray();
            $activity['activity']['usage'] = [
                UsageMeasurement::create('provider/output', 'token', 'response', UsageCompleteness::PARTIAL, '0.1')->toArray(),
                UsageMeasurement::create('provider/output-usd', 'USD', 'response', UsageCompleteness::COMPLETE, '0')->toArray(),
                UsageMeasurement::create('provider/output-estimate', 'token', 'estimate', UsageCompleteness::COMPLETE, '2')->toArray(),
            ];
            $record(Observation::fromArray($activity));
        }
        // Same metric with different units/provenance across distinct activities.
        foreach ([['USD', 'response'], ['token', 'estimate']] as [$unit, $provenance]) {
            $activity['observation_id'] = ObservationId::generate()->toString();
            $activity['activity']['usage'] = [UsageMeasurement::create('provider/output', $unit, $provenance, UsageCompleteness::COMPLETE, '7')->toArray()];
            $record(Observation::fromArray($activity));
        }
        [$bus] = $this->bus($db, ['project' => null]);
        $summary = $bus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray();
        self::assertSame(101, $summary['executions']);
        self::assertSame(101, $summary['outcomes']['unknown']);
        self::assertSame(103, $summary['activities']);
        self::assertCount(5, $summary['usage']);
        $token = array_values(array_filter($summary['usage'], static fn ($total) => $total['metric'] === 'provider/output' && $total['unit'] === 'token' && $total['provenance'] === 'response'))[0];
        self::assertSame('10.1', $token['measured']);
        self::assertSame(101, $token['counts']['partial']);
        self::assertSame(101, $bus->fetch(new ListExecutions(ExecutionSelection::create(['project']), 2, 100))->toArray()['total']);
    }

    #[DataProvider('databases')]
    public function test_that_latest_session_outcome_uses_receipt_then_full_uuid_then_producer_not_occurrence(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $high = ObservationFixture::session(['observation_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff', 'outcome' => 'failed', 'occurred_at' => '2026-10-21T12:00:00.000000Z']);
        $low = ObservationFixture::session(['observation_id' => '00000000-0000-4000-8000-000000000001', 'outcome' => 'succeeded', 'occurred_at' => '2026-10-20T12:00:00.000000Z']);
        $record($high);
        $record($low);
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        $db->update('fight_execution_observations', ['recorded_at' => '2026-10-22T12:00:00.000002Z'], ['observation_id' => $low->id()->toString()]);
        [$bus] = $this->bus($db, ['project' => null]);
        $query = new ListExecutions(ExecutionSelection::create(['project']));
        self::assertSame('succeeded', $bus->fetch($query)->executions()[0]->outcome()->value);
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        self::assertSame('failed', $bus->fetch($query)->executions()[0]->outcome()->value);
        $time = Observation::timestamp('2026-10-22T12:00:00.000001Z');
        self::assertSame(1, $high->recorded($time)->compareRecordingTo($low->recorded($time)));
        $sameId = Observation::fromArray(array_replace($high->toArray(), ['producer_id' => 'z-producer', 'outcome' => 'cancelled']));
        $record($sameId);
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        self::assertSame('cancelled', $bus->fetch($query)->executions()[0]->outcome()->value);
    }

    private function session(string $project, string $session, string $outcome = 'unknown'): Observation
    {
        return ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => SessionReference::fromStrings($project, $session, 'consumer:'.$session)->toArray(), 'outcome' => $outcome]);
    }

    protected function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'memory' => true]; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Real disposable '.$backend.' is required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $db = DriverManager::getConnection($params);
        $this->connections[] = $db;
        if ($backend === 'sqlite') { $db->executeStatement('PRAGMA foreign_keys = ON'); }
        foreach (array_reverse(DbalObservationSchema::TABLES) as $table) { $db->executeStatement('DROP TABLE IF EXISTS '.$table); }
        (new DbalObservationSchema())->install($db);
        return $db;
    }

    protected function writer(Connection $db, ?SimpleEventDispatcher $events = null): callable
    {
        $transaction = new DbalRecordingTransaction($db);
        $admission = new class implements ObservationAdmission { public function admit(Observation $observation): void {} };
        $recorder = new ObservationRecorder($transaction, new DbalSessionRepository($transaction), new DbalPhaseRepository($transaction), new DbalObservationRepository($transaction), $admission, $events ?? new SimpleEventDispatcher(), new DbalProtectedContentRepository($transaction));
        $router = new InMemoryCommandRouter();
        $router->registerHandler(RecordSessionObservation::class, new RecordSessionObservationHandler($recorder));
        $router->registerHandler(RecordPhaseObservation::class, new RecordPhaseObservationHandler($recorder));
        $router->registerHandler(RecordProtectedDetail::class, new RecordProtectedDetailHandler($recorder));
        $bus = new RoutingCommandBus($router);
        return static function (Observation $observation, ?ProtectedContent $content = null) use ($bus): void {
            if ($content !== null) { $command = new RecordProtectedDetail($observation, $content); }
            elseif ($observation->phase() === null) { $command = new RecordSessionObservation($observation); }
            else { $command = new RecordPhaseObservation($observation); }
            $bus->execute($command);
        };
    }

    protected function bus(Connection $db, array $grants): array
    {
        $admission = new class(ExecutionAccess::create($grants)) implements ExecutionReadAdmission {
            public int $calls = 0;
            public function __construct(public ExecutionAccess $access) {}
            public function currentAccess(ExecutionSelection $selection): ExecutionAccess { ++$this->calls; return $this->access; }
        };
        $reader = new DbalExecutionReader($db);
        $router = new InMemoryQueryRouter();
        $router->registerHandler(SummarizeExecutionsHandler::queryRegistration(), new SummarizeExecutionsHandler($admission, $reader));
        $router->registerHandler(ListExecutionsHandler::queryRegistration(), new ListExecutionsHandler($admission, $reader));
        $router->registerHandler(GetExecutionHandler::queryRegistration(), new GetExecutionHandler($admission, $reader));
        $router->registerHandler(ListExecutionPhasesHandler::queryRegistration(), new ListExecutionPhasesHandler($admission, new DbalPhaseReader($db)));
        return [new RoutingQueryBus($router), $admission];
    }

    private function state(Connection $db): array
    {
        return array_map(static fn ($table) => $db->fetchAllAssociative('SELECT * FROM '.$table), DbalObservationSchema::TABLES);
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        parent::tearDown();
    }
}
