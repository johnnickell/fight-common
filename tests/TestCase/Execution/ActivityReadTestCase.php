<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Execution;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Execution\Dbal\DbalActivityReader;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Execution\Observation\QueryHandler\ListPhaseActivityHandler;
use Fight\Common\Application\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Execution\Detail\CaptureCompleteness;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\ListExecutions;
use Fight\Common\Domain\Execution\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Execution\Session\SessionReference;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class ActivityReadTestCase extends ExecutionReadTestCase
{
    #[DataProvider('databases')]
    public function test_that_activity_query_uses_current_scope_and_explicit_reference_grants_without_bodies_or_mutation(string $backend): void
    {
        $db = $this->database($backend);
        $events = new SimpleEventDispatcher();
        $count = 0;
        $events->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$count): void { ++$count; });
        $record = $this->writer($db, $events);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity();
        $record($activity);
        $detail = $this->detail($activity, CaptureCompleteness::PARTIAL);
        $record($detail, ProtectedContent::create($detail->detail(), 'PROTECTED-SENTINEL tool arguments/results/process output'));
        $hidden = $this->detail($activity, CaptureCompleteness::COMPLETE);
        $record($hidden, ProtectedContent::create($hidden->detail(), 'HIDDEN-BODY'));
        $otherSession = SessionReference::fromStrings('other', 'session', 'other:consumer');
        $record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $otherSession->toArray()]));
        [$bus, $admission, $overview] = $this->activityBus($db);
        self::assertSame(1, $overview->fetch(new ListExecutions(ExecutionSelection::create(['project', 'other'])))->toArray()['total']);
        $query = new ListPhaseActivity('project', 'session', 'phase', 1, 1);
        $envelope = QueryMessage::arrayDeserialize(QueryMessage::create($query)->arraySerialize());
        // Timeline permission alone reveals neither references nor their counts.
        $page = $bus->dispatch($envelope);
        self::assertSame([], $page->activities()[0]->details());
        $admission->access = ExecutionAccess::create(['project' => ['session']], ['project' => ['phase']], [], ['project' => [$this->key($detail)]]);
        $before = $this->storedState($db);
        $eventCount = $count;
        $page = $bus->dispatch($envelope);
        $data = $page->toArray();
        self::assertCount(1, $page->activities());
        self::assertCount(1, $page->activities()[0]->details());
        self::assertSame(1, $data['total']);
        $row = $data['activities'][0];
        self::assertSame('agent', $row['agent_id']);
        self::assertSame('instrumentation', $row['producer_id']);
        self::assertSame('model:one', $row['activity']['model_id']);
        self::assertSame('Safe summary', $row['activity']['summary']);
        self::assertSame('2026-10-20T12:34:56.120Z', $row['occurred_at']);
        self::assertSame('0', array_column($row['activity']['usage'], null, 'metric')['provider/input']['value']);
        $reference = $row['details'][0];
        self::assertSame('partial', $reference['capture']['completeness']);
        self::assertSame('retained', $reference['availability']);
        self::assertSame('2026-10-20T12:34:56.12Z', $reference['occurred_at']);
        self::assertSame($activity->id()->toString(), $reference['capture']['activity_id']);
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        foreach (['PROTECTED-SENTINEL', 'HIDDEN-BODY', $hidden->id()->toString(), 'extensions', 'consumer_reference', 'parent_phase_id'] as $secret) { self::assertStringNotContainsString($secret, $json); }
        self::assertSame($before, $this->storedState($db));
        self::assertSame($eventCount, $count);
        self::assertFalse($db->isTransactionActive());
        // New Agent invocation has activity permission, but no detail-reference disclosure grant.
        $admission->access = ExecutionAccess::create(['project' => ['session']], [], ['project' => [$this->key($activity)]]);
        self::assertSame([], $bus->dispatch($envelope)->activities()[0]->details());
        $admission->access = ExecutionAccess::create(['project' => null], [], ['project' => []], ['project' => null]);
        self::assertSame(0, $bus->dispatch($envelope)->toArray()['total']);
        $admission->access = ExecutionAccess::create(['project' => null], ['project' => []], [], ['project' => null]);
        self::assertNull($bus->dispatch($envelope));
        $admission->access = ExecutionAccess::create([]);
        self::assertNull($bus->dispatch($envelope));
        self::assertGreaterThan(6, $admission->calls);
    }

    #[DataProvider('databases')]
    public function test_that_capture_markers_and_removed_content_remain_truthful_on_subsequent_queries(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        $details = [];
        foreach (CaptureCompleteness::cases() as $completeness) {
            $detail = $this->detail($activity, $completeness);
            $body = in_array($completeness, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true) ? '' : null;
            $record($detail, ProtectedContent::create($detail->detail(), $body));
            $details[$completeness->value] = $detail;
        }
        [$bus, $admission, $overview] = $this->activityBus($db);
        $admission->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        $query = new ListPhaseActivity('project', 'session', 'phase');
        $first = $bus->fetch($query)->toArray();
        $byCapture = array_column($first['activities'][0]['details'], null, 'observation_id');
        foreach ($details as $capture => $detail) {
            self::assertSame(in_array($capture, ['complete', 'partial'], true) ? 'retained' : $capture, $byCapture[$detail->id()->toString()]['availability']);
        }
        $summary = $overview->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray();
        foreach ($details as $detail) { $db->delete('fight_execution_contents', ['producer_id' => $detail->producerId(), 'observation_id' => $detail->id()->toString()]); }
        $after = $bus->fetch($query)->toArray();
        foreach ($after['activities'][0]['details'] as $reference) {
            $capture = $reference['capture']['completeness'];
            self::assertSame(in_array($capture, ['complete', 'partial'], true) ? 'missing' : $capture, $reference['availability']);
        }
        self::assertSame($summary, $overview->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray());
        self::assertSame(5, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_observations WHERE activity_id IS NOT NULL'));
        $admission->access = ExecutionAccess::create(['project' => null]);
        self::assertSame([], $bus->fetch($query)->activities()[0]->details());
        // Concealed detail metadata is never decoded or counted, even if corrupt.
        $db->executeStatement('UPDATE fight_execution_observations SET version = 999 WHERE activity_id IS NOT NULL');
        self::assertSame(1, $bus->fetch($query)->toArray()['total']);
    }

    #[DataProvider('databases')]
    public function test_that_recording_order_uses_microseconds_full_uuid_and_producer_with_live_pages_and_retry_stability(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $low = ObservationFixture::activity(['observation_id' => '10000000-0000-4000-8000-000000000001']);
        $high = ObservationFixture::activity(['observation_id' => '10000000-0000-4000-8000-ffffffffffff']);
        $producer = ObservationFixture::activity(['observation_id' => $high->id()->toString(), 'producer_id' => 'z-producer']);
        foreach ([$low, $high, $producer] as $activity) { $record($activity); }
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        $record($low);
        self::assertSame('2026-10-22T12:00:00.000001Z', $db->fetchOne('SELECT recorded_at FROM fight_execution_observations WHERE observation_id = ?', [$low->id()->toString()]));
        [$bus] = $this->activityBus($db);
        $query = new ListPhaseActivity('project', 'session', 'phase', 1, 1);
        $all = $bus->fetch(new ListPhaseActivity('project', 'session', 'phase'))->toArray()['activities'];
        self::assertSame(['z-producer', 'instrumentation', 'instrumentation'], array_column($all, 'producer_id'));
        self::assertSame([$high->id()->toString(), $high->id()->toString(), $low->id()->toString()], array_column($all, 'observation_id'));
        $time = Observation::timestamp('2026-10-22T12:00:00.000001Z');
        self::assertGreaterThan(0, $high->recorded($time)->compareRecordingTo($low->recorded($time)));
        self::assertSame($low->id()->toString(), $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 1, 1, orderings: ['recorded_at' => 'asc']))->toArray()['activities'][0]['observation_id']);
        $late = ObservationFixture::activity(['observation_id' => '00000000-0000-4000-8000-000000000001', 'occurred_at' => '2020-01-01T00:00:00Z']);
        $record($late);
        $db->update('fight_execution_observations', ['recorded_at' => '2026-10-22T12:00:00.000002Z'], ['observation_id' => $late->id()->toString()]);
        self::assertSame($late->id()->toString(), $bus->fetch($query)->toArray()['activities'][0]['observation_id']);
        self::assertSame($producer->id()->toString(), $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['activities'][0]['observation_id']);
        // Insert after page one: page two can repeat the old first row.
        $new = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()]); $record($new);
        self::assertSame($late->id()->toString(), $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['activities'][0]['observation_id']);
        $db->delete('fight_execution_observations', ['observation_id' => $new->id()->toString()]);
        self::assertSame('z-producer', $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['activities'][0]['producer_id']);
        self::assertSame('2020-01-01T00:00:00Z', $bus->fetch($query)->toArray()['activities'][0]['occurred_at']);
    }

    #[DataProvider('databases')]
    public function test_that_selection_filters_batch_boundaries_empty_absence_and_storage_failures_are_safe(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        [$bus, $admission] = $this->activityBus($db);
        $query = new ListPhaseActivity('project', 'session', 'phase');
        self::assertSame([], $bus->fetch($query)->activities());
        foreach ([['project', 'session', 'missing'], ['other', 'session', 'phase'], ['project', 'missing', 'phase']] as $scope) { self::assertNull($bus->fetch(new ListPhaseActivity(...$scope))); }
        for ($i = 0; $i < 101; ++$i) { $record(ObservationFixture::activity(['observation_id' => sprintf('10000000-0000-4000-8000-%012x', $i)])); }
        $page = $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 100));
        self::assertSame(101, $page->toArray()['total']); self::assertCount(1, $page->activities());
        $ascending = $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 100, orderings: ['recorded_at' => 'ASC']));
        self::assertSame(101, $ascending->toArray()['total']); self::assertCount(1, $ascending->activities());
        foreach ([['producer_id' => 'other'], ['kind' => 'consumer/other'], ['model_id' => 'other']] as $filters) { self::assertSame(0, $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', filters: $filters))->toArray()['total']); }
        self::assertSame(101, $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', filters: ['producer_id' => 'instrumentation', 'kind' => 'consumer/model-call', 'model_id' => 'model:one']))->toArray()['total']);
        self::assertSame([], $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 3, 100))->activities());
        $id = '10000000-0000-4000-8000-000000000000';
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => ['instrumentation/'.$id]]);
        $db->executeStatement('UPDATE fight_execution_observations SET version = 999 WHERE observation_id <> ?', [$id]);
        self::assertSame(1, $bus->fetch($query)->toArray()['total']);
        $db->executeStatement('DROP TABLE fight_execution_contents');
        self::assertSame(1, $bus->fetch($query)->toArray()['total'], 'No reference grant means no protected storage dependency.');
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => ['instrumentation/'.$id]], detailGrants: ['project' => null]);
        try { $bus->fetch($query); self::fail('Storage error must not become absent detail.'); }
        catch (ExecutionReadFailed $failure) { self::assertSame('Execution evidence could not be read completely.', $failure->getMessage()); }
    }

    #[DataProvider('databases')]
    public function test_that_removal_and_reference_revocation_between_pages_recheck_current_evidence_without_a_lease(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $first = ObservationFixture::activity(['observation_id' => '10000000-0000-4000-8000-ffffffffffff']);
        $second = ObservationFixture::activity(['observation_id' => '10000000-0000-4000-8000-000000000001']);
        $record($first); $record($second);
        $detail = $this->detail($second, CaptureCompleteness::COMPLETE);
        $record($detail, ProtectedContent::create($detail->detail(), 'PROTECTED-SENTINEL'));
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.123456Z'");
        [$bus, $admission] = $this->activityBus($db);
        $admission->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        self::assertSame($first->id()->toString(), $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 1, 1))->toArray()['activities'][0]['observation_id']);
        $query = new ListPhaseActivity('project', 'session', 'phase', 2, 1);
        $db->delete('fight_execution_contents', ['observation_id' => $detail->id()->toString()]);
        $after = $bus->fetch($query)->toArray();
        self::assertSame(2, $after['total']);
        self::assertSame('complete', $after['activities'][0]['details'][0]['capture']['completeness']);
        self::assertSame('missing', $after['activities'][0]['details'][0]['availability']);
        $admission->access = ExecutionAccess::create(['project' => null]);
        self::assertSame([], $bus->fetch($query)->toArray()['activities'][0]['details']);
        $admission->access = ExecutionAccess::create(['project' => []]);
        self::assertNull($bus->fetch($query));
    }

    protected function activityBus(Connection $db): array
    {
        [$overview, $admission] = $this->bus($db, ['project' => null]);
        $router = new InMemoryQueryRouter();
        $router->registerHandler(ListPhaseActivityHandler::queryRegistration(), new ListPhaseActivityHandler($admission, new DbalActivityReader($db)));
        return [new RoutingQueryBus($router), $admission, $overview];
    }

    protected function detail(Observation $activity, CaptureCompleteness $completeness): Observation
    {
        $capture = ObservationFixture::detail()->detail()->toArray();
        $capture['activity_id'] = $activity->id()->toString();
        $capture['completeness'] = $completeness->value;
        return ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString(), 'producer_id' => $activity->producerId(), 'session' => $activity->session()->toArray(), 'phase' => $activity->phase()->toArray(), 'detail' => $capture]);
    }

    protected function key(Observation $observation): string
    {
        return $observation->producerId().'/'.$observation->id()->toString();
    }

    private function storedState(Connection $db): array
    {
        $state = [];
        foreach (['schema', 'sessions', 'phases', 'observations', 'contents'] as $suffix) { $state[$suffix] = $db->fetchAllAssociative('SELECT * FROM fight_execution_'.$suffix); }
        return $state;
    }
}
