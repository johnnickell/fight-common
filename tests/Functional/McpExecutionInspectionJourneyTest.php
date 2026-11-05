<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Application\Mcp\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\DetailCapture;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class McpExecutionInspectionJourneyTest extends TestCase
{
    public static function databases(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
        yield 'postgresql' => ['postgresql'];
    }

    #[DataProvider('databases')]
    public function test_that_guarded_http_matches_shared_queries_across_the_complete_safe_live_journey(string $backend): void
    {
        $fixture = new McpExecutionFixture($backend);
        try {
            $fixture->seed(ObservationFixture::session());
            $fixture->seed(ObservationFixture::phase());
            $first = ObservationFixture::activity(['observation_id' => '10000000-0000-4000-8000-000000000001']);
            $second = ObservationFixture::activity(['observation_id' => '10000000-0000-4000-8000-ffffffffffff']);
            $fixture->seed($first); $fixture->seed($second);
            $detail = ObservationFixture::detail(['detail' => DetailCapture::create($second->id(), DetailKind::MESSAGE, 'text/plain', CaptureCompleteness::PARTIAL)->toArray()]);
            $fixture->seed($detail, ProtectedContent::create($detail->detail(), 'PROTECTED-SENTINEL-body-never-in-safe-output'));
            $other = SessionReference::fromStrings('other', 'session', 'concealed:consumer')->toArray();
            $fixture->seed(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $other]));
            $next = SessionReference::fromStrings('project', 'next', 'next:consumer')->toArray();
            $fixture->seed(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $next, 'outcome' => 'succeeded']));
            $fixture->seed(ObservationFixture::phase(['observation_id' => ObservationId::generate()->toString(), 'phase' => PhaseReference::fromStrings('session', 'empty', null, 'agent', 'empty', 1)->toArray()]));
            // Controlled immutable receipt-time tie; the full UUID, not source time, orders activity.
            $fixture->connection->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
            $before = $fixture->state(); $eventCount = $fixture->eventCount;
            $discovery = $fixture->request('server/discover')['body']['result']['capabilities'];
            self::assertArrayHasKey('tools', $discovery);
            foreach (['prompts', 'subscriptions', 'tasks'] as $capability) { self::assertArrayNotHasKey($capability, $discovery); }
            $names = []; $params = [];
            do {
                $page = $fixture->request('tools/list', $params)['body']['result'];
                self::assertLessThanOrEqual(2, count($page['tools']));
                $names = [...$names, ...array_column($page['tools'], 'name')];
                $params = isset($page['nextCursor']) ? ['cursor' => $page['nextCursor']] : [];
            } while ($params !== []);
            self::assertSame(['execution.activity', 'execution.get', 'execution.list', 'execution.phases', 'execution.summarize'], $names);
            self::assertSame(0, $fixture->admissionCalls, 'Registration and catalog discovery never inspect business records');
            $selection = ExecutionSelection::create(['project', 'other']);
            $summary = $this->equivalent($fixture, 'execution.summarize', ['projects' => ['project', 'other']], new SummarizeExecutions($selection));
            self::assertSame(2, $summary['executions']);
            self::assertSame(['unknown' => 1, 'succeeded' => 1, 'failed' => 0, 'cancelled' => 0], $summary['outcomes']);
            self::assertSame(2, $summary['activities']);
            self::assertSame('2026-10-22T12:00:00.000001Z', $summary['latest_recorded_at']);
            $usage = array_column($summary['usage'], null, 'metric');
            self::assertSame('0', $usage['provider/input']['measured']);
            self::assertSame('25', $usage['provider/output']['measured']);
            self::assertSame(2, $usage['provider/output']['counts']['partial']);
            self::assertNull($usage['provider/cost']['measured']);
            self::assertSame('USD', $usage['provider/cost']['unit']);
            self::assertSame('response', $usage['provider/cost']['provenance']);
            $one = $this->equivalent($fixture, 'execution.list', ['projects' => ['project', 'other'], 'per_page' => 1], new ListExecutions($selection, 1, 1));
            $two = $this->equivalent($fixture, 'execution.list', ['projects' => ['project', 'other'], 'page' => 2, 'per_page' => 1], new ListExecutions($selection, 2, 1));
            self::assertSame(2, $one['total']);
            self::assertNotSame($one['executions'][0]['session']['session_id'], $two['executions'][0]['session']['session_id']);
            $exact = ['project_id' => 'project', 'session_id' => 'session'];
            $inspection = $this->equivalent($fixture, 'execution.get', $exact, new GetExecution('project', 'session'));
            self::assertSame(1, $inspection['summary']['executions']);
            self::assertSame('unknown', $inspection['observation']['outcome']);
            foreach ([1, 2] as $page) {
                $this->equivalent($fixture, 'execution.phases', $exact + ['page' => $page, 'per_page' => 1], new ListExecutionPhases('project', 'session', $page, 1));
            }
            $activityArgs = $exact + ['phase_id' => 'phase'];
            $rows = [];
            foreach ([1, 2] as $page) {
                $data = $this->equivalent($fixture, 'execution.activity', $activityArgs + ['page' => $page, 'per_page' => 1], new ListPhaseActivity('project', 'session', 'phase', $page, 1));
                self::assertSame(2, $data['total']); $rows[] = $data['activities'][0];
            }
            self::assertSame([$second->id()->toString(), $first->id()->toString()], array_column($rows, 'observation_id'));
            self::assertSame('2026-10-20T12:34:56.120Z', $rows[0]['occurred_at']);
            self::assertSame('agent', $rows[0]['agent_id']);
            self::assertSame('model:one', $rows[0]['activity']['model_id']);
            self::assertSame('instrumentation', $rows[0]['producer_id']);
            self::assertSame('partial', $rows[0]['details'][0]['capture']['completeness']);
            self::assertSame('retained', $rows[0]['details'][0]['availability']);
            self::assertSame([], $this->equivalent($fixture, 'execution.activity', $exact + ['phase_id' => 'empty'], new ListPhaseActivity('project', 'session', 'empty'))['activities']);
            self::assertNull($this->equivalent($fixture, 'execution.get', ['project_id' => 'other', 'session_id' => 'session'], new GetExecution('other', 'session')));
            self::assertSame($before, $fixture->state()); self::assertSame($eventCount, $fixture->eventCount);
            self::assertFalse($fixture->connection->isTransactionActive());
            // Removed owned fixture content changes availability, not historical capture completeness.
            $fixture->connection->delete('fight_execution_contents', ['producer_id' => $detail->producerId(), 'observation_id' => $detail->id()->toString()]);
            $after = $this->equivalent($fixture, 'execution.activity', $activityArgs, new ListPhaseActivity('project', 'session', 'phase'));
            self::assertSame('missing', $after['activities'][0]['details'][0]['availability']);
            self::assertSame('partial', $after['activities'][0]['details'][0]['capture']['completeness']);
            $fixture->access = ExecutionAccess::create(['project' => null]);
            self::assertSame([], $fixture->call('execution.activity', $activityArgs)['body']['result']['structuredContent']['activities'][0]['details']);
            // A new write changes the next request; no frozen selection or cached result exists.
            $fixture->seed(ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()]));
            self::assertSame(3, $this->equivalent($fixture, 'execution.activity', $activityArgs, new ListPhaseActivity('project', 'session', 'phase'))['total']);
            $fixture->access = ExecutionAccess::create([]);
            self::assertNull($this->equivalent($fixture, 'execution.activity', $activityArgs, new ListPhaseActivity('project', 'session', 'phase')));
            self::assertSame([], $fixture->diagnostics);
        } finally { $fixture->connection->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_current_availability_guard_input_validation_scope_and_storage_failures_reject_safely(string $backend): void
    {
        $fixture = new McpExecutionFixture($backend);
        try {
            $fixture->seed(ObservationFixture::session()); $fixture->seed(ObservationFixture::phase()); $fixture->seed(ObservationFixture::activity());
            $args = ['project_id' => 'project', 'session_id' => 'session', 'phase_id' => 'phase'];
            $before = $fixture->state(); $eventCount = $fixture->eventCount;
            $fixture->request('tools/list');
            $fixture->available = false;
            $denied = $fixture->call('execution.activity', $args);
            self::assertSame($denied, $fixture->call('unknown', $args));
            self::assertSame(-32602, $denied['body']['error']['code']);
            self::assertSame(0, $fixture->admissionCalls);
            $fixture->available = true; $fixture->guard = false;
            self::assertSame(429, $fixture->call('execution.activity', $args)['status']);
            self::assertSame(0, $fixture->admissionCalls);
            $fixture->guard = true;
            foreach ([['access' => true], ['principal' => 'admin'], ['page' => 0], ['per_page' => 101], ['page' => 1.5], ['filters' => ['body' => 'secret']], ['direction' => 'sideways']] as $bad) {
                $response = $fixture->call('execution.activity', array_replace($args, $bad));
                self::assertTrue($response['body']['result']['isError']);
                self::assertArrayNotHasKey('structuredContent', $response['body']['result']);
            }
            self::assertSame(0, $fixture->admissionCalls);
            // Shared use-case validation still rejects references and unrepresentable application offsets.
            foreach ([['session_id' => '../bad'], ['page' => 1000001, 'per_page' => 100]] as $bad) {
                self::assertSame(-32603, $fixture->call('execution.activity', array_replace($args, $bad))['body']['error']['code']);
            }
            self::assertSame(0, $fixture->admissionCalls);
            self::assertNull($fixture->call('execution.activity', array_replace($args, ['session_id' => 'missing']))['body']['result']['structuredContent']);
            self::assertNull($fixture->call('execution.activity', array_replace($args, ['project_id' => 'other']))['body']['result']['structuredContent']);
            self::assertSame(400, $fixture->request('tools/call', ['name' => 'execution.activity', 'arguments' => (object) $args], ['Mcp-Name' => 'execution.get'])['status']);
            $fixture->access = ExecutionAccess::create([]);
            self::assertNull($fixture->call('execution.activity', $args)['body']['result']['structuredContent']);
            $fixture->access = ExecutionAccess::create(['project' => null]);
            self::assertSame($before, $fixture->state()); self::assertSame($eventCount, $fixture->eventCount);
            $fixture->connection->executeStatement('UPDATE fight_execution_observations SET version = 999');
            $response = $fixture->call('execution.activity', $args);
            self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $response['body']['error']);
            self::assertArrayNotHasKey('result', $response['body']);
            self::assertSame($eventCount, $fixture->eventCount);
            self::assertGreaterThanOrEqual(3, count($fixture->diagnostics));
        } finally { $fixture->connection->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_units_missing_usage_capture_markers_filters_and_tie_breakers_keep_their_query_meaning(string $backend): void
    {
        $fixture = new McpExecutionFixture($backend);
        try {
            $fixture->seed(ObservationFixture::session()); $fixture->seed(ObservationFixture::phase());
            $activity = ObservationFixture::activity(); $fixture->seed($activity);
            $variant = $activity->activity()->toArray();
            $variant['summary'] = 'Safe escape-heavy 雪 " \\ /';
            $variant['model_id'] = null;
            foreach ($variant['usage'] as &$measurement) {
                if ($measurement['metric'] === 'provider/input') { $measurement['unit'] = 'request'; $measurement['value'] = '1'; }
            }
            unset($measurement);
            $fixture->seed(ObservationFixture::activity(['producer_id' => 'z-producer', 'activity' => $variant]));
            $variant['usage'] = [];
            $fixture->seed(ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString(), 'activity' => $variant]));
            $captureById = [];
            foreach (CaptureCompleteness::cases() as $completeness) {
                $detail = ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString(), 'detail' => DetailCapture::create($activity->id(), DetailKind::MESSAGE, 'text/plain', $completeness)->toArray()]);
                $body = in_array($completeness, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true) ? '' : null;
                $fixture->seed($detail, ProtectedContent::create($detail->detail(), $body));
                $captureById[$detail->id()->toString()] = $completeness->value;
            }
            $fixture->connection->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.000001Z'");
            $before = $fixture->state(); $eventCount = $fixture->eventCount;
            $summary = $this->equivalent($fixture, 'execution.summarize', ['projects' => ['project']], new SummarizeExecutions(ExecutionSelection::create(['project'])));
            self::assertSame(3, $summary['activities']); self::assertSame(1, $summary['activities_without_usage']);
            $inputs = array_values(array_filter($summary['usage'], static fn(array $usage): bool => $usage['metric'] === 'provider/input'));
            self::assertCount(2, $inputs);
            self::assertSame(['request', 'token'], array_column($inputs, 'unit'));
            self::assertSame(['1', '0'], array_column($inputs, 'measured'));
            $args = ['project_id' => 'project', 'session_id' => 'session', 'phase_id' => 'phase'];
            $data = $this->equivalent($fixture, 'execution.activity', $args, new ListPhaseActivity('project', 'session', 'phase'));
            $byProducer = array_values(array_filter($data['activities'], static fn(array $row): bool => $row['observation_id'] === $activity->id()->toString()));
            self::assertSame(['z-producer', 'instrumentation'], array_column($byProducer, 'producer_id'));
            foreach ($byProducer[1]['details'] as $reference) {
                $capture = $captureById[$reference['observation_id']];
                self::assertSame($capture, $reference['capture']['completeness']);
                self::assertSame(in_array($capture, ['complete', 'partial'], true) ? 'retained' : $capture, $reference['availability']);
            }
            self::assertCount(5, $byProducer[1]['details']);
            $filtered = $this->equivalent($fixture, 'execution.activity', $args + ['filters' => ['producer_id' => 'z-producer'], 'direction' => 'ASC', 'per_page' => 1], new ListPhaseActivity('project', 'session', 'phase', 1, 1, ['producer_id' => 'z-producer'], ['recorded_at' => 'ASC']));
            self::assertSame(1, $filtered['total']); self::assertNull($filtered['activities'][0]['activity']['model_id']);
            self::assertSame('Safe escape-heavy 雪 " \\ /', $filtered['activities'][0]['activity']['summary']);
            $fixture->access = ExecutionAccess::create(['project' => null], ['project' => ['phase']], ['project' => ['instrumentation/'.$activity->id()->toString()]]);
            $restricted = $this->equivalent($fixture, 'execution.activity', $args, new ListPhaseActivity('project', 'session', 'phase'));
            self::assertSame(1, $restricted['total']); self::assertSame([], $restricted['activities'][0]['details']);
            self::assertSame($before, $fixture->state()); self::assertSame($eventCount, $fixture->eventCount);
        } finally { $fixture->connection->close(); }
    }

    private function equivalent(McpExecutionFixture $fixture, string $name, array $arguments, \Fight\Common\Domain\Messaging\Query\Query $query): mixed
    {
        $expected = $fixture->queryBus->fetch($query)?->toArray();
        $response = $fixture->call($name, $arguments);
        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('result', $response['body'], json_encode($response));
        $result = $response['body']['result'];
        self::assertArrayHasKey('structuredContent', $result, json_encode($response));
        self::assertSame($expected, $result['structuredContent']);
        self::assertSame($expected, json_decode($result['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR));
        foreach (['PROTECTED-SENTINEL', 'concealed:consumer', 'extensions', 'body', 'comparison'] as $secret) { self::assertStringNotContainsString($secret, json_encode($result, JSON_THROW_ON_ERROR)); }
        return $result['structuredContent'];
    }
}
