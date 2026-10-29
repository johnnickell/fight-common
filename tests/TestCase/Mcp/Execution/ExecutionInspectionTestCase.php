<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Execution;

use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Mcp\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Common\Domain\Mcp\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Mcp\Usage\UsageCompleteness;
use Fight\Common\Domain\Mcp\Usage\UsageMeasurement;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class ExecutionInspectionTestCase extends ExecutionReadTestCase
{
    #[DataProvider('databases')]
    public function test_that_selected_journey_returns_full_usage_safe_phases_and_independent_current_permissions(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record(ObservationFixture::session(['outcome' => 'succeeded', 'started_at' => '2026-10-20T12:00:00.000001Z', 'ended_at' => '2026-10-20T12:01:00.000002Z']));
        $parent = $this->phase('parent', null, 'agent:one', 1);
        $child = $this->phase('child', 'parent', 'agent:two', 2);
        $hidden = $this->phase('hidden', null, 'hidden-agent', 3);
        foreach ([$parent, $child, $hidden] as $phase) { $record($phase); }
        foreach ([$parent, $child, $hidden] as $index => $phase) {
            $data = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString(), 'phase' => $phase->phase()->toArray()])->toArray();
            $data['activity']['model_id'] = 'model:'.$index;
            $data['activity']['usage'] = [UsageMeasurement::create('provider/output', 'token', 'response', UsageCompleteness::PARTIAL, (string) ($index + 1))->toArray()];
            $record(Observation::fromArray($data));
            if ($index === 0) {
                $detail = ObservationFixture::detail(['phase' => $phase->phase()->toArray(), 'detail' => array_replace(ObservationFixture::detail()->detail()->toArray(), ['activity_id' => $data['observation_id']])]);
                $record($detail, ProtectedContent::create($detail->detail(), 'PROTECTED-SENTINEL'));
            }
        }
        $otherSession = SessionReference::fromStrings('other', 'session', 'other:consumer');
        $record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $otherSession->toArray()]));
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.123456Z'");
        $db->update('fight_execution_observations', ['recorded_at' => '2026-10-22T13:00:00.000001Z', 'version' => 999], ['phase_id' => 'hidden']);
        [$bus, $admission] = $this->bus($db, ['project' => null]);
        $admission->access = ExecutionAccess::create(['project' => ['session']], ['project' => ['parent', 'child']]);
        $before = $this->inspectionState($db);
        self::assertSame(1, $bus->fetch(new ListExecutions(ExecutionSelection::create(['project', 'other'])))->toArray()['total']);
        $summaryEnvelope = QueryMessage::create(new GetExecution('project', 'session'));
        $phaseEnvelope = QueryMessage::create(new ListExecutionPhases('project', 'session', 1, 1));
        $inspection = $bus->dispatch(QueryMessage::arrayDeserialize($summaryEnvelope->arraySerialize()))->toArray();
        self::assertSame('succeeded', $inspection['execution']['outcome']);
        self::assertSame('2026-10-20T12:00:00.000001Z', $inspection['observation']['started_at']);
        self::assertSame('2026-10-20T12:01:00.000002Z', $inspection['observation']['ended_at']);
        self::assertSame('instrumentation', $inspection['observation']['producer_id']);
        self::assertSame(2, $inspection['summary']['activities']);
        self::assertSame('3', $inspection['summary']['usage'][0]['measured']);
        self::assertSame('2026-10-22T12:00:00.123456Z', $inspection['summary']['latest_recorded_at']);
        self::assertSame($inspection['summary'], $bus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'], ['session_id' => 'session'])))->toArray());
        $page = $bus->dispatch(QueryMessage::arrayDeserialize($phaseEnvelope->arraySerialize()));
        self::assertCount(1, $page->phases());
        self::assertSame(2, $page->toArray()['total']);
        $pages = [$page->toArray(), $bus->fetch(new ListExecutionPhases('project', 'session', 2, 1))->toArray()];
        $pageIds = array_map(static fn ($row) => $row['phase']['phase_id'], array_merge($pages[0]['phases'], $pages[1]['phases']));
        sort($pageIds, SORT_STRING);
        self::assertSame(['child', 'parent'], $pageIds);
        $all = $bus->fetch(new ListExecutionPhases('project', 'session'))->toArray();
        $byId = [];
        foreach ($all['phases'] as $row) { $byId[$row['phase']['phase_id']] = $row; }
        self::assertSame('parent', $byId['child']['phase']['parent_phase_id']);
        self::assertSame(2, $byId['child']['phase']['attempt']);
        self::assertSame('agent:two', $byId['child']['phase']['agent_id']);
        self::assertSame(['model:1'], $byId['child']['models']);
        self::assertSame('instrumentation', $byId['child']['observation']['producer_id']);
        self::assertSame('unknown', $byId['child']['observation']['outcome']);
        self::assertNull($byId['child']['observation']['ended_at']);
        self::assertSame('review', $byId['child']['phase']['name']);
        $safeJson = json_encode([$inspection, $all], JSON_THROW_ON_ERROR);
        foreach (['PROTECTED-SENTINEL', 'hidden-agent', 'model:2', 'Safe summary', 'extensions', 'activity_id'] as $concealed) { self::assertStringNotContainsString($concealed, $safeJson); }
        self::assertSame($before, $this->inspectionState($db));
        self::assertFalse($db->isTransactionActive());
        // Reusing the exact envelope rechecks admission; parent access is not implied by child access.
        $admission->access = ExecutionAccess::create(['project' => ['session']], ['project' => ['child']]);
        $onlyChild = $bus->dispatch($phaseEnvelope)->toArray();
        self::assertSame(1, $onlyChild['total']);
        self::assertNull($onlyChild['phases'][0]['phase']['parent_phase_id']);
        self::assertSame('2', $bus->dispatch($summaryEnvelope)->toArray()['summary']['usage'][0]['measured']);
        $admission->access = ExecutionAccess::create(['project' => ['session']], ['project' => []]);
        self::assertSame(0, $bus->dispatch($phaseEnvelope)->toArray()['total']);
        self::assertSame([], $bus->dispatch($summaryEnvelope)->toArray()['summary']['usage']);
        $admission->access = ExecutionAccess::create([]);
        self::assertNull($bus->dispatch($summaryEnvelope));
        self::assertNull($bus->dispatch($phaseEnvelope));
        self::assertGreaterThan(10, $admission->calls);
        self::assertNull($bus->fetch(new GetExecution('other', 'session')));
    }

    #[DataProvider('databases')]
    public function test_that_phase_filters_empty_absent_mismatched_and_storage_failure_remain_distinct_and_safe(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record(ObservationFixture::session());
        [$bus] = $this->bus($db, ['project' => null]);
        self::assertSame([], $bus->fetch(new ListExecutionPhases('project', 'session'))->phases());
        self::assertNull($bus->fetch(new GetExecution('project', 'missing')));
        self::assertNull($bus->fetch(new ListExecutionPhases('project', 'missing')));
        $record($this->phase('phase', null, 'agent', 2));
        foreach ([['phase_id' => 'wrong'], ['name' => 'work'], ['agent_id' => 'other']] as $filter) {
            self::assertSame(0, $bus->fetch(new ListExecutionPhases('project', 'session', filters: $filter))->toArray()['total']);
        }
        self::assertSame(1, $bus->fetch(new ListExecutionPhases('project', 'session', filters: ['phase_id' => 'phase', 'agent_id' => 'agent', 'name' => 'review']))->toArray()['total']);
        self::assertSame([], $bus->fetch(new ListExecutionPhases('project', 'session', 2, 100))->phases());
        $different = SessionReference::fromStrings('project', 'different', 'consumer:other');
        $record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $different->toArray()]));
        self::assertSame(0, $bus->fetch(new ListExecutionPhases('project', 'different', filters: ['phase_id' => 'phase']))->toArray()['total']);
        $db->delete('fight_execution_observations', ['project_id' => 'project']);
        $inspection = $bus->fetch(new GetExecution('project', 'session'))->toArray();
        self::assertNull($inspection['observation']);
        self::assertSame('unknown', $inspection['execution']['outcome']);
        self::assertNull($inspection['summary']['latest_recorded_at']);
        $row = $bus->fetch(new ListExecutionPhases('project', 'session'))->toArray()['phases'][0];
        self::assertNull($row['observation']);
        self::assertNull($row['latest_recorded_at']);
        self::assertSame([], $row['models']);
        $db->executeStatement('DROP TABLE fight_execution_contents');
        self::assertNotNull($bus->fetch(new GetExecution('project', 'session')));
        self::assertNotNull($bus->fetch(new ListExecutionPhases('project', 'session')));
        $db->executeStatement('DROP TABLE fight_execution_observations');
        foreach ([new GetExecution('project', 'session'), new ListExecutionPhases('project', 'session')] as $query) {
            try { $bus->fetch($query); self::fail('Storage failure must not be absence.'); }
            catch (ExecutionReadFailed $failure) { self::assertSame('Execution evidence could not be read completely.', $failure->getMessage()); }
        }
    }

    #[DataProvider('databases')]
    public function test_that_phase_order_uses_receipt_full_uuid_and_producer_and_live_inserts_can_repeat_rows(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record(ObservationFixture::session());
        $low = $this->phase('z-phase', null, 'agent', 1, '10000000-0000-4000-8000-000000000001');
        $high = $this->phase('a-phase', null, 'agent', 1, '10000000-0000-4000-8000-ffffffffffff');
        $record($low); $record($high);
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        [$bus] = $this->bus($db, ['project' => null]);
        $query = new ListExecutionPhases('project', 'session', 1, 1);
        self::assertSame('a-phase', $bus->fetch($query)->phases()[0]->phase()->phaseId());
        $time = Observation::timestamp('2026-10-22T12:00:00.000001Z');
        self::assertGreaterThan(0, $high->recorded($time)->compareRecordingTo($low->recorded($time)));
        self::assertSame('z-phase', $bus->fetch(new ListExecutionPhases('project', 'session', 1, 1, orderings: ['latest_recorded_at' => 'asc']))->phases()[0]->phase()->phaseId());
        $new = $this->phase('new', null, 'agent', 1);
        $record($new);
        self::assertSame('a-phase', $bus->fetch(new ListExecutionPhases('project', 'session', 2, 1))->phases()[0]->phase()->phaseId());
        $db->delete('fight_execution_observations', ['phase_id' => 'new']);
        $db->delete('fight_execution_phases', ['phase_id' => 'new']);
        self::assertSame('z-phase', $bus->fetch(new ListExecutionPhases('project', 'session', 2, 1))->phases()[0]->phase()->phaseId());
        // Same phase, tied receipt and full UUID across producers: bytewise producer breaks the tie.
        $state = Observation::fromArray(array_replace($high->toArray(), ['producer_id' => 'z-producer', 'outcome' => 'failed']));
        $record($state);
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        $page = $bus->fetch(new ListExecutionPhases('project', 'session', filters: ['phase_id' => 'a-phase']))->toArray();
        self::assertSame('failed', $page['phases'][0]['observation']['outcome']);
        self::assertSame('z-producer', $page['phases'][0]['observation']['producer_id']);
        $producerTie = Observation::fromArray(array_replace($high->toArray(), ['producer_id' => 'zz-producer', 'phase' => $low->phase()->toArray()]));
        $record($producerTie);
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
        self::assertSame('z-phase', $bus->fetch($query)->phases()[0]->phase()->phaseId());
        // Later low UUID wins over earlier high UUID; occurrence does not reorder state.
        $later = Observation::fromArray(array_replace($low->toArray(), ['observation_id' => '00000000-0000-4000-8000-000000000001', 'phase' => $high->phase()->toArray(), 'outcome' => 'cancelled', 'occurred_at' => '2020-01-01T00:00:00.000000Z']));
        $record($later);
        $db->update('fight_execution_observations', ['recorded_at' => '2026-10-22T12:00:00.000002Z'], ['observation_id' => $later->id()->toString()]);
        self::assertSame('cancelled', $bus->fetch(new ListExecutionPhases('project', 'session', filters: ['phase_id' => 'a-phase']))->toArray()['phases'][0]['observation']['outcome']);
        $without = $this->phase('no-evidence', null, 'agent', 1);
        $record($without);
        $db->delete('fight_execution_observations', ['phase_id' => 'no-evidence']);
        self::assertSame('no-evidence', $bus->fetch(new ListExecutionPhases('project', 'session', orderings: ['latest_recorded_at' => 'asc']))->phases()[0]->phase()->phaseId());
    }

    #[DataProvider('databases')]
    public function test_that_selected_summary_scans_beyond_observation_batches_and_phase_pages_with_exact_units(string $backend): void
    {
        $db = $this->database($backend);
        $record = $this->writer($db);
        $record(ObservationFixture::session());
        for ($i = 0; $i < 101; ++$i) { $record($this->phase(sprintf('p%03d', $i), null, 'agent', 1)); }
        for ($i = 0; $i < 101; ++$i) {
            $data = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString(), 'phase' => $this->phase('p100', null, 'agent', 1)->phase()->toArray()])->toArray();
            $data['activity']['usage'] = [UsageMeasurement::create('provider/output', 'token', 'response', UsageCompleteness::PARTIAL, '0.1')->toArray()];
            $record(Observation::fromArray($data));
        }
        foreach ([['USD', 'response'], ['token', 'estimate']] as [$unit, $provenance]) {
            $data['observation_id'] = ObservationId::generate()->toString();
            $data['activity']['usage'] = [UsageMeasurement::create('provider/output', $unit, $provenance, UsageCompleteness::COMPLETE, '0')->toArray()];
            $record(Observation::fromArray($data));
        }
        $data['observation_id'] = ObservationId::generate()->toString();
        $data['activity']['usage'] = [];
        $record(Observation::fromArray($data));
        [$bus] = $this->bus($db, ['project' => null]);
        $summary = $bus->fetch(new GetExecution('project', 'session'))->toArray()['summary'];
        self::assertSame(104, $summary['activities']);
        self::assertSame(1, $summary['activities_without_usage']);
        self::assertCount(3, $summary['usage']);
        $tokens = array_values(array_filter($summary['usage'], static fn ($group) => $group['unit'] === 'token' && $group['provenance'] === 'response'))[0];
        self::assertSame('10.1', $tokens['measured']);
        self::assertSame(101, $tokens['counts']['partial']);
        self::assertSame(101, $bus->fetch(new ListExecutionPhases('project', 'session', 1, 100))->toArray()['total']);
        self::assertCount(1, $bus->fetch(new ListExecutionPhases('project', 'session', 2, 100))->phases());
        self::assertSame($summary, $bus->fetch(new GetExecution('project', 'session'))->toArray()['summary']);
    }

    #[DataProvider('databases')]
    public function test_that_separate_summary_and_phase_calls_see_new_evidence_without_business_writes_or_events(string $backend): void
    {
        $db = $this->database($backend);
        $events = new SimpleEventDispatcher();
        $eventCount = 0;
        $events->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$eventCount): void { ++$eventCount; });
        $record = $this->writer($db, $events);
        $record(ObservationFixture::session());
        [$bus] = $this->bus($db, ['project' => null]);
        $selected = new GetExecution('project', 'session');
        $before = $this->inspectionState($db);
        self::assertSame(0, $bus->fetch($selected)->toArray()['summary']['activities']);
        self::assertSame($before, $this->inspectionState($db));
        self::assertSame(1, $eventCount);
        $record(ObservationFixture::phase());
        $record(ObservationFixture::activity());
        $record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'outcome' => 'succeeded']));
        $before = $this->inspectionState($db);
        $count = $eventCount;
        $phases = $bus->fetch(new ListExecutionPhases('project', 'session'))->toArray();
        self::assertSame(1, $phases['total']);
        self::assertSame('unknown', $phases['phases'][0]['observation']['outcome']);
        self::assertSame(['model:one'], $phases['phases'][0]['models']);
        $after = $bus->fetch($selected)->toArray();
        self::assertSame(1, $after['summary']['activities']);
        self::assertSame('succeeded', $after['observation']['outcome']);
        self::assertSame($before, $this->inspectionState($db));
        self::assertSame($count, $eventCount);
        self::assertFalse($db->isTransactionActive());
    }

    protected function phase(string $id, ?string $parent, string $agent, int $attempt, ?string $observationId = null): Observation
    {
        return ObservationFixture::phase([
            'observation_id' => $observationId ?? ObservationId::generate()->toString(),
            'phase' => PhaseReference::fromStrings('session', $id, $parent, $agent, 'review', $attempt)->toArray()
        ]);
    }

    private function inspectionState($db): array
    {
        $state = [];
        foreach (['sessions', 'phases', 'observations', 'contents', 'schema'] as $suffix) { $state[$suffix] = $db->fetchAllAssociative('SELECT * FROM fight_execution_'.$suffix); }
        return $state;
    }
}
