<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Execution;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Persistence\Observation\Dbal\DbalActivityReader;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\Observation\QueryHandler\ListPhaseActivityHandler;
use Fight\Common\Application\Mcp\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Mcp\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Mcp\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Common\Domain\Mcp\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class ActivityReadTestCase extends ExecutionReadTestCase
{
    #[DataProvider('databases')]
    public function test_that_summary_counts_and_usage_follow_current_activity_visibility(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record(ObservationFixture::session());
        $record(ObservationFixture::phase());
        $visible = ObservationFixture::activity();
        $hidden = ObservationFixture::activity(['observation_id' => '10000000-0000-4000-8000-000000000001']);
        $record($visible);
        $record($hidden);
        [$queryBus, $admission, $executionQueryBus] = $this->activityBus($db);
        $admission->access = ExecutionAccess::create(['project' => ['session']], ['project' => ['phase']], ['project' => [$this->key($visible)]]);
        self::assertSame(1, $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase'))->toArray()['total']);
        $summary = $executionQueryBus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray();
        self::assertSame(1, $summary['activities']);
        self::assertSame('12.5', array_column($summary['usage'], null, 'metric')['provider/output']['measured']);
        self::assertSame($summary, $executionQueryBus->fetch(new GetExecution('project', 'session'))->toArray()['summary']);
        // Paging activity never narrows the full-selection summary, and admission is fresh per call.
        self::assertSame(1, $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['total']);
        self::assertSame($summary, $executionQueryBus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray());
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => []]);
        $empty = $executionQueryBus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray();
        self::assertSame(1, $empty['executions']);
        self::assertSame(0, $empty['activities']);
        self::assertSame(0, $empty['activities_without_usage']);
        self::assertSame([], $empty['usage']);
        self::assertNotNull($executionQueryBus->fetch(new GetExecution('project', 'session'))->toArray()['observation']);
        foreach ([[], ['project' => null]] as $grants) {
            $admission->access = ExecutionAccess::create(['project' => null], activityGrants: $grants);
            $all = $executionQueryBus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray();
            self::assertSame(2, $all['activities']);
            self::assertSame('25', array_column($all['usage'], null, 'metric')['provider/output']['measured']);
        }
    }

    #[DataProvider('databases')]
    public function test_that_hidden_activity_cannot_change_freshness_order_or_models_but_state_and_detail_remain_independent(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record(ObservationFixture::session());
        $record(ObservationFixture::phase());
        $visible = ObservationFixture::activity();
        $record($visible);
        // The same UUID from another producer must not match the visible producer's grant.
        $data = $visible->toArray();
        $data['producer_id'] = 'other-producer';
        $data['activity']['model_id'] = 'hidden:model';
        $hidden = Observation::fromArray($data);
        $record($hidden);
        $db->executeStatement('UPDATE '.$this->table($db, 'observations')." SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        $db->update($this->table($db, 'observations'), ['recorded_at' => '2026-10-23T12:00:00.000001Z'], ['producer_id' => $hidden->producerId()]);
        [$queryBus, $admission, $executionQueryBus] = $this->activityBus($db);
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => [$this->key($visible)]]);
        $before = $this->storedState($db);
        $selection = ExecutionSelection::create(['project']);
        $summary = $executionQueryBus->fetch(new SummarizeExecutions($selection))->toArray();
        self::assertSame(1, $summary['activities']);
        self::assertSame('2026-10-22T12:00:00.000001Z', $summary['latest_recorded_at']);
        self::assertSame($summary['latest_recorded_at'], $executionQueryBus->fetch(new ListExecutions($selection))->toArray()['executions'][0]['latest_recorded_at']);
        $phase = $executionQueryBus->fetch(new ListExecutionPhases('project', 'session'))->toArray()['phases'][0];
        self::assertSame(['model:one'], $phase['models']);
        self::assertSame($summary['latest_recorded_at'], $phase['latest_recorded_at']);
        self::assertNotNull($phase['observation']);
        self::assertSame([], $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase'))->activities()[0]->details());
        self::assertSame($before, $this->storedState($db));
        self::assertFalse($db->isTransactionActive());
        // Hidden corrupt-version evidence must not be decoded to calculate permitted projections.
        $db->update($this->table($db, 'observations'), ['version' => 999], ['producer_id' => $hidden->producerId()]);
        self::assertSame($summary, $executionQueryBus->fetch(new SummarizeExecutions($selection))->toArray());
        self::assertSame(['model:one'], $executionQueryBus->fetch(new ListExecutionPhases('project', 'session'))->toArray()['phases'][0]['models']);
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => []]);
        self::assertSame([], $executionQueryBus->fetch(new ListExecutionPhases('project', 'session'))->toArray()['phases'][0]['models']);
        // Detail evidence has its own retained freshness meaning; reference/body grants stay independent.
        $db->update($this->table($db, 'observations'), ['version' => 1], ['producer_id' => $hidden->producerId()]);
        $detail = $this->detail($hidden, CaptureCompleteness::ABSENT);
        $record($detail, ProtectedContent::create($detail->detail(), null));
        $db->update($this->table($db, 'observations'), ['recorded_at' => '2026-10-24T12:00:00.000001Z'], ['observation_id' => $detail->id()->toString()]);
        self::assertSame('2026-10-24T12:00:00.000001Z', $executionQueryBus->fetch(new SummarizeExecutions($selection))->toArray()['latest_recorded_at']);
        self::assertSame([], $executionQueryBus->fetch(new SummarizeExecutions($selection))->toArray()['usage']);
        $db->update($this->table($db, 'observations'), ['version' => 999], ['observation_id' => $visible->id()->toString(), 'producer_id' => $visible->producerId()]);
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => [$this->key($visible)]]);
        try { $executionQueryBus->fetch(new SummarizeExecutions($selection)); self::fail('Visible corrupt evidence must fail, never return a partial summary.'); }
        catch (ExecutionReadFailed $failure) { self::assertSame('Execution evidence could not be read completely.', $failure->getMessage()); }
    }

    #[DataProvider('databases')]
    public function test_that_activity_query_uses_current_scope_and_explicit_reference_grants_without_bodies_or_mutation(string $backend): void
    {
        $db = $this->database($backend);
        $eventDispatcher = new SimpleEventDispatcher();
        $count = 0;
        $eventDispatcher->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$count): void { ++$count; });
        $record = $this->writer($db, $eventDispatcher);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity();
        $record($activity);
        $detail = $this->detail($activity, CaptureCompleteness::PARTIAL);
        $record($detail, ProtectedContent::create($detail->detail(), 'PROTECTED-SENTINEL tool arguments/results/process output'));
        $hidden = $this->detail($activity, CaptureCompleteness::COMPLETE);
        $record($hidden, ProtectedContent::create($hidden->detail(), 'HIDDEN-BODY'));
        $otherSession = SessionReference::fromStrings('other', 'session', 'other:consumer');
        $record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $otherSession->toArray()]));
        [$queryBus, $admission, $executionQueryBus] = $this->activityBus($db);
        self::assertSame(1, $executionQueryBus->fetch(new ListExecutions(ExecutionSelection::create(['project', 'other'])))->toArray()['total']);
        $query = new ListPhaseActivity('project', 'session', 'phase', 1, 1);
        $envelope = QueryMessage::arrayDeserialize(QueryMessage::create($query)->arraySerialize());
        // Timeline permission alone reveals neither references nor their counts.
        $page = $queryBus->dispatch($envelope);
        self::assertSame([], $page->activities()[0]->details());
        $admission->access = ExecutionAccess::create(['project' => ['session']], ['project' => ['phase']], [], ['project' => [$this->key($detail)]]);
        $before = $this->storedState($db);
        $eventCount = $count;
        $page = $queryBus->dispatch($envelope);
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
        self::assertSame([], $queryBus->dispatch($envelope)->activities()[0]->details());
        $admission->access = ExecutionAccess::create(['project' => null], [], ['project' => []], ['project' => null]);
        self::assertSame(0, $queryBus->dispatch($envelope)->toArray()['total']);
        $admission->access = ExecutionAccess::create(['project' => null], ['project' => []], [], ['project' => null]);
        self::assertNull($queryBus->dispatch($envelope));
        $admission->access = ExecutionAccess::create([]);
        self::assertNull($queryBus->dispatch($envelope));
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
        [$queryBus, $admission, $executionQueryBus] = $this->activityBus($db);
        $admission->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        $query = new ListPhaseActivity('project', 'session', 'phase');
        $first = $queryBus->fetch($query)->toArray();
        $byCapture = array_column($first['activities'][0]['details'], null, 'observation_id');
        foreach ($details as $capture => $detail) {
            self::assertSame(in_array($capture, ['complete', 'partial'], true) ? 'retained' : $capture, $byCapture[$detail->id()->toString()]['availability']);
        }
        $summary = $executionQueryBus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray();
        foreach ($details as $detail) { $db->delete($this->table($db, 'contents'), ['producer_id' => $detail->producerId(), 'observation_id' => $detail->id()->toString()]); }
        $after = $queryBus->fetch($query)->toArray();
        foreach ($after['activities'][0]['details'] as $reference) {
            $capture = $reference['capture']['completeness'];
            self::assertSame(in_array($capture, ['complete', 'partial'], true) ? 'missing' : $capture, $reference['availability']);
        }
        self::assertSame($summary, $executionQueryBus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray());
        self::assertSame(5, (int) $db->fetchOne('SELECT COUNT(*) FROM '.$this->table($db, 'observations').' WHERE activity_id IS NOT NULL'));
        $admission->access = ExecutionAccess::create(['project' => null]);
        self::assertSame([], $queryBus->fetch($query)->activities()[0]->details());
        // Concealed detail metadata is never decoded or counted, even if corrupt.
        $db->executeStatement('UPDATE '.$this->table($db, 'observations').' SET version = 999 WHERE activity_id IS NOT NULL');
        self::assertSame(1, $queryBus->fetch($query)->toArray()['total']);
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
        $db->executeStatement('UPDATE '.$this->table($db, 'observations')." SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        $record($low);
        self::assertSame('2026-10-22T12:00:00.000001Z', $db->fetchOne('SELECT recorded_at FROM '.$this->table($db, 'observations').' WHERE observation_id = ?', [$low->id()->toString()]));
        [$queryBus] = $this->activityBus($db);
        $query = new ListPhaseActivity('project', 'session', 'phase', 1, 1);
        $all = $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase'))->toArray()['activities'];
        self::assertSame(['z-producer', 'instrumentation', 'instrumentation'], array_column($all, 'producer_id'));
        self::assertSame([$high->id()->toString(), $high->id()->toString(), $low->id()->toString()], array_column($all, 'observation_id'));
        $time = Observation::timestamp('2026-10-22T12:00:00.000001Z');
        self::assertGreaterThan(0, $high->recorded($time)->compareRecordingTo($low->recorded($time)));
        self::assertSame($low->id()->toString(), $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 1, 1, orderings: ['recorded_at' => 'asc']))->toArray()['activities'][0]['observation_id']);
        $late = ObservationFixture::activity(['observation_id' => '00000000-0000-4000-8000-000000000001', 'occurred_at' => '2020-01-01T00:00:00Z']);
        $record($late);
        $db->update($this->table($db, 'observations'), ['recorded_at' => '2026-10-22T12:00:00.000002Z'], ['observation_id' => $late->id()->toString()]);
        self::assertSame($late->id()->toString(), $queryBus->fetch($query)->toArray()['activities'][0]['observation_id']);
        self::assertSame($producer->id()->toString(), $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['activities'][0]['observation_id']);
        // Insert after page one: page two can repeat the old first row.
        $new = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()]); $record($new);
        self::assertSame($late->id()->toString(), $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['activities'][0]['observation_id']);
        $db->delete($this->table($db, 'observations'), ['observation_id' => $new->id()->toString()]);
        self::assertSame('z-producer', $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['activities'][0]['producer_id']);
        self::assertSame('2020-01-01T00:00:00Z', $queryBus->fetch($query)->toArray()['activities'][0]['occurred_at']);
    }

    #[DataProvider('databases')]
    public function test_that_selection_filters_batch_boundaries_empty_absence_and_storage_failures_are_safe(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        [$queryBus, $admission] = $this->activityBus($db);
        $query = new ListPhaseActivity('project', 'session', 'phase');
        self::assertSame([], $queryBus->fetch($query)->activities());
        foreach ([['project', 'session', 'missing'], ['other', 'session', 'phase'], ['project', 'missing', 'phase']] as $scope) { self::assertNull($queryBus->fetch(new ListPhaseActivity(...$scope))); }
        for ($i = 0; $i < 101; ++$i) { $record(ObservationFixture::activity(['observation_id' => sprintf('10000000-0000-4000-8000-%012x', $i)])); }
        $page = $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 100));
        self::assertSame(101, $page->toArray()['total']); self::assertCount(1, $page->activities());
        $ascending = $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 100, orderings: ['recorded_at' => 'ASC']));
        self::assertSame(101, $ascending->toArray()['total']); self::assertCount(1, $ascending->activities());
        foreach ([['producer_id' => 'other'], ['kind' => 'consumer/other'], ['model_id' => 'other']] as $filters) { self::assertSame(0, $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', filters: $filters))->toArray()['total']); }
        self::assertSame(101, $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', filters: ['producer_id' => 'instrumentation', 'kind' => 'consumer/model-call', 'model_id' => 'model:one']))->toArray()['total']);
        self::assertSame([], $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 3, 100))->activities());
        $id = '10000000-0000-4000-8000-000000000000';
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => ['instrumentation/'.$id]]);
        $db->executeStatement('UPDATE '.$this->table($db, 'observations').' SET version = 999 WHERE observation_id <> ?', [$id]);
        self::assertSame(1, $queryBus->fetch($query)->toArray()['total']);
        $db->executeStatement('DROP TABLE '.$this->table($db, 'contents'));
        self::assertSame(1, $queryBus->fetch($query)->toArray()['total'], 'No reference grant means no protected storage dependency.');
        $admission->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => ['instrumentation/'.$id]], detailGrants: ['project' => null]);
        try { $queryBus->fetch($query); self::fail('Storage error must not become absent detail.'); }
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
        $db->executeStatement('UPDATE '.$this->table($db, 'observations')." SET recorded_at = '2026-10-22T12:00:00.123456Z'");
        [$queryBus, $admission] = $this->activityBus($db);
        $admission->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        self::assertSame($first->id()->toString(), $queryBus->fetch(new ListPhaseActivity('project', 'session', 'phase', 1, 1))->toArray()['activities'][0]['observation_id']);
        $query = new ListPhaseActivity('project', 'session', 'phase', 2, 1);
        $db->delete($this->table($db, 'contents'), ['observation_id' => $detail->id()->toString()]);
        $after = $queryBus->fetch($query)->toArray();
        self::assertSame(2, $after['total']);
        self::assertSame('complete', $after['activities'][0]['details'][0]['capture']['completeness']);
        self::assertSame('missing', $after['activities'][0]['details'][0]['availability']);
        $admission->access = ExecutionAccess::create(['project' => null]);
        self::assertSame([], $queryBus->fetch($query)->toArray()['activities'][0]['details']);
        $admission->access = ExecutionAccess::create(['project' => []]);
        self::assertNull($queryBus->fetch($query));
    }

    protected function activityBus(Connection $db): array
    {
        [$executionQueryBus, $admission] = $this->bus($db, ['project' => null]);
        $router = new InMemoryQueryRouter();
        $router->registerHandler(ListPhaseActivityHandler::queryRegistration(), new ListPhaseActivityHandler($admission, new DbalActivityReader($db, $this->tables($db))));
        return [new RoutingQueryBus($router), $admission, $executionQueryBus];
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
        foreach (['schema', 'sessions', 'phases', 'observations', 'contents'] as $suffix) { $state[$suffix] = $db->fetchAllAssociative('SELECT * FROM '.$this->table($db, $suffix)); }
        return $state;
    }
}
