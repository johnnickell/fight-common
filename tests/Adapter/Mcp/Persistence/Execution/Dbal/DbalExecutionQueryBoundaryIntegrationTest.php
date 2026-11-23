<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Common\Application\Mcp\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\DetailCapture;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Fight\Common\Domain\Mcp\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Common\Domain\Mcp\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Test\Common\Fixture\Mcp\Execution\McpExecutionFixture;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DbalExecutionQueryBoundaryIntegrationTest extends TestCase
{
    public function test_that_current_activity_visibility_controls_statistics_and_derived_ordering(): void
    {
        $fixture = new McpExecutionFixture('sqlite');
        $queries = $fixture->queryBus;
        try {
            $fixture->seed(ObservationFixture::session());
            $fixture->seed(ObservationFixture::phase());
            $visible = ObservationFixture::activity();
            $fixture->seed($visible);
            $hidden = $visible->toArray();
            $hidden['producer_id'] = 'hidden-producer';
            $hidden['activity']['model_id'] = 'hidden:model';
            $fixture->seed(ObservationFixture::activity($hidden));
            $fixture->seed(ObservationFixture::phase(['observation_id' => ObservationId::generate()->toString(), 'phase' => PhaseReference::fromStrings('session', 'other-phase', null, 'agent', 'other', 1)->toArray()]));
            $fixture->seed(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => SessionReference::fromStrings('project', 'other-session', 'other:consumer')->toArray()]));
            $table = $fixture->tables->observationTable()->quoted($fixture->connection->getDatabasePlatform());
            $fixture->connection->executeStatement("UPDATE $table SET recorded_at = '2026-10-22T12:00:00.000001Z'");
            $fixture->connection->executeStatement("UPDATE $table SET recorded_at = '2026-10-23T12:00:00.000001Z' WHERE phase_id = 'other-phase' OR session_id = 'other-session'");
            $fixture->connection->executeStatement("UPDATE $table SET recorded_at = '2026-10-24T12:00:00.000001Z' WHERE producer_id = 'hidden-producer'");
            $fixture->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => [$visible->producerId().'/'.$visible->id()->toString()]]);
            $before = $fixture->state();
            $events = $fixture->eventCount;
            $selection = ExecutionSelection::create(['project']);
            $summary = new SummarizeExecutions($selection);
            $get = new GetExecution('project', 'session');
            $visibleSummary = $queries->fetch($summary)->toArray();
            self::assertSame(1, $visibleSummary['activities']);
            self::assertSame('12.5', array_column($visibleSummary['usage'], null, 'metric')['provider/output']['measured']);
            self::assertSame(1, $queries->fetch($get)->toArray()['summary']['activities']);
            self::assertSame('other-session', $queries->fetch(new ListExecutions($selection, 1, 1))->toArray()['executions'][0]['session']['session_id']);
            $phases = $queries->fetch(new ListExecutionPhases('project', 'session'))->toArray()['phases'];
            self::assertSame(['other-phase', 'phase'], array_column(array_column($phases, 'phase'), 'phase_id'));
            self::assertSame(['model:one'], $phases[1]['models']);
            self::assertSame('2026-10-22T12:00:00.000001Z', $phases[1]['latest_recorded_at']);
            self::assertSame(1, $queries->fetch(new ListPhaseActivity('project', 'session', 'phase', 2, 1))->toArray()['total']);
            $fixture->access = ExecutionAccess::create(['project' => null], activityGrants: ['project' => []]);
            $empty = $queries->fetch($get)->toArray();
            self::assertSame(0, $empty['summary']['activities']);
            self::assertSame([], $empty['summary']['usage']);
            self::assertNotNull($empty['observation']);
            $fixture->access = ExecutionAccess::create(['project' => null]);
            self::assertSame(2, $queries->fetch($summary)->toArray()['activities']);
            self::assertSame($before, $fixture->state());
            self::assertSame($events, $fixture->eventCount);
        } finally {
            $fixture->connection->close();
        }
    }

    public function test_that_empty_bytes_live_pages_and_missing_detail_storage_remain_distinguishable(): void
    {
        $fixture = new McpExecutionFixture('sqlite');
        $queries = $fixture->queryBus;
        try {
            $fixture->seed(ObservationFixture::session());
            $fixture->seed(ObservationFixture::phase());
            $activity = ObservationFixture::activity();
            $fixture->seed($activity);
            $selection = DetailSelection::fromArray(['project_id' => 'project', 'session_id' => 'session', 'phase_id' => 'phase', 'producer_id' => $activity->producerId(), 'activity_id' => $activity->id()->toString()]);
            $platform = $fixture->connection->getDatabasePlatform();
            $content = $fixture->tables->contentTable()->quoted($platform);
            $observations = $fixture->tables->observationTable()->quoted($platform);
            $seed = static function (CaptureCompleteness $capture, ?string $body) use ($fixture, $activity): ObservationId {
                $definition = DetailCapture::create($activity->id(), DetailKind::MESSAGE, 'text/plain', $capture);
                $detail = ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString(), 'detail' => $definition->toArray()]);
                $fixture->seed($detail, ProtectedContent::create($definition, $body));
                return $detail->id();
            };
            foreach (CaptureCompleteness::cases() as $capture) {
                $retained = in_array($capture, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true);
                $id = $seed($capture, $retained ? '' : null);
                $get = new GetCapturedDetail($selection, $id, DetailKind::MESSAGE);
                $detail = $queries->fetch($get)->toArray();
                self::assertSame($capture->value, $detail['reference']['capture']['completeness']);
                self::assertSame($retained ? 'retained' : $capture->value, $detail['reference']['availability']);
                self::assertSame($retained ? ['encoding' => 'base64', 'data' => '', 'offset' => 0, 'bytes' => 0, 'total_bytes' => 0, 'next_offset' => null] : null, $detail['chunk']);
                $fixture->connection->delete($content, ['observation_id' => $id->toString()]);
                $missing = $queries->fetch($get)->toArray();
                self::assertSame($retained ? 'missing' : $capture->value, $missing['reference']['availability']);
                self::assertNull($missing['chunk']);
            }
            $id = $seed(CaptureCompleteness::COMPLETE, 'PROTECTED-disappearing');
            self::assertSame(3, $queries->fetch(new GetCapturedDetail($selection, $id, DetailKind::MESSAGE, 0, 3))->toArray()['chunk']['next_offset']);
            $first = $queries->fetch(new ListCapturedMessages($selection, 1, 1))->toArray();
            $seed(CaptureCompleteness::COMPLETE, 'late');
            $second = $queries->fetch(new ListCapturedMessages($selection, 2, 1))->toArray();
            self::assertSame($first['messages'][0]['reference']['observation_id'], $second['messages'][0]['reference']['observation_id']);
            self::assertSame($first['total'] + 1, $second['total']);
            $fixture->connection->delete($content, ['observation_id' => $id->toString()]);
            $continued = new GetCapturedDetail($selection, $id, DetailKind::MESSAGE, 3);
            $after = $queries->fetch($continued)->toArray();
            self::assertNull($after['chunk']);
            self::assertSame('missing', $after['reference']['availability']);
            $safe = $queries->fetch(new ListPhaseActivity('project', 'session', 'phase'))->toArray();
            $references = array_column($safe['activities'][0]['details'], null, 'observation_id');
            self::assertSame('missing', $references[$id->toString()]['availability']);
            self::assertStringNotContainsString('PROTECTED-disappearing', json_encode($safe, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString(base64_encode('PROTECTED-disappearing'), json_encode($safe, JSON_THROW_ON_ERROR));
            $fixture->connection->delete($observations, ['observation_id' => $id->toString()]);
            self::assertNull($queries->fetch($continued));
            self::assertSame($first['total'], $queries->fetch(new ListCapturedMessages($selection))->toArray()['total']);
        } finally {
            $fixture->connection->close();
        }
    }

    public function test_that_exact_units_capture_markers_and_tie_breakers_survive_committed_reads(): void
    {
        $tables = new DbalObservationTables();
        $fixture = new McpExecutionFixture('postgresql', tables: $tables);
        $queries = $fixture->queryBus;
        try {
            $fixture->seed(ObservationFixture::session());
            $fixture->seed(ObservationFixture::phase());
            $activity = ObservationFixture::activity();
            $fixture->seed($activity);
            $variant = $activity->activity()->toArray();
            $variant['summary'] = 'Safe escape-heavy 雪 " \\ /';
            $variant['model_id'] = null;
            foreach ($variant['usage'] as &$measurement) {
                if ($measurement['metric'] === 'provider/input') {
                    $measurement['unit'] = 'request';
                    $measurement['value'] = '1';
                }
            }
            unset($measurement);
            $fixture->seed(ObservationFixture::activity(['producer_id' => 'z-producer', 'activity' => $variant]));
            $variant['usage'] = [];
            $fixture->seed(ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString(), 'activity' => $variant]));
            $captureById = [];
            foreach (CaptureCompleteness::cases() as $completeness) {
                $capture = DetailCapture::create($activity->id(), DetailKind::MESSAGE, 'text/plain', $completeness);
                $detail = ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString(), 'detail' => $capture->toArray()]);
                $body = in_array($completeness, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true) ? '' : null;
                $fixture->seed($detail, ProtectedContent::create($capture, $body));
                $captureById[$detail->id()->toString()] = $completeness->value;
            }
            $fixture->connection->executeStatement('UPDATE '.$tables->observationTable()->quoted($fixture->connection->getDatabasePlatform())." SET recorded_at = '2026-10-22T12:00:00.000001Z'");
            $before = $fixture->state();
            $events = $fixture->eventCount;
            $summary = $queries->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray();
            self::assertSame(3, $summary['activities']);
            self::assertSame(1, $summary['activities_without_usage']);
            $inputs = array_values(array_filter($summary['usage'], static fn (array $usage): bool => $usage['metric'] === 'provider/input'));
            self::assertCount(2, $inputs);
            self::assertSame(['request', 'token'], array_column($inputs, 'unit'));
            self::assertSame(['1', '0'], array_column($inputs, 'measured'));
            $activityQuery = new ListPhaseActivity('project', 'session', 'phase');
            $data = $queries->fetch($activityQuery)->toArray();
            $byProducer = array_values(array_filter($data['activities'], static fn (array $row): bool => $row['observation_id'] === $activity->id()->toString()));
            self::assertSame(['z-producer', 'instrumentation'], array_column($byProducer, 'producer_id'));
            self::assertCount(5, $byProducer[1]['details']);
            foreach ($byProducer[1]['details'] as $reference) {
                $capture = $captureById[$reference['observation_id']];
                self::assertSame($capture, $reference['capture']['completeness']);
                self::assertSame(in_array($capture, ['complete', 'partial'], true) ? 'retained' : $capture, $reference['availability']);
            }
            $filtered = $queries->fetch(new ListPhaseActivity('project', 'session', 'phase', 1, 1, ['producer_id' => 'z-producer'], ['recorded_at' => 'ASC']))->toArray();
            self::assertSame(1, $filtered['total']);
            self::assertNull($filtered['activities'][0]['activity']['model_id']);
            self::assertSame('Safe escape-heavy 雪 " \\ /', $filtered['activities'][0]['activity']['summary']);
            $fixture->access = ExecutionAccess::create(['project' => null], ['project' => ['phase']], ['project' => ['instrumentation/'.$activity->id()->toString()]]);
            $restricted = $queries->fetch($activityQuery)->toArray();
            self::assertSame(1, $restricted['total']);
            self::assertSame([], $restricted['activities'][0]['details']);
            self::assertSame($before, $fixture->state());
            self::assertSame($events, $fixture->eventCount);
        } finally {
            $fixture->connection->close();
        }
    }
}
