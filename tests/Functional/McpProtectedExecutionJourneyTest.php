<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Application\Execution\Detail\Service\DetailAccess;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Execution\Detail\CaptureCompleteness;
use Fight\Common\Domain\Execution\Detail\DetailCapture;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Detail\OutputSegment;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Execution\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;
use Fight\Common\Domain\Execution\Session\Query\ListExecutions;
use Fight\Common\Domain\Execution\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Execution\Session\SessionReference;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Test\Common\Fixture\Execution\McpExecutionFixture;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class McpProtectedExecutionJourneyTest extends TestCase
{
    public static function databases(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
        yield 'postgresql' => ['postgresql'];
    }

    #[DataProvider('databases')]
    public function test_that_guarded_summary_to_detail_journey_matches_shared_queries_and_reconstructs_all_kinds_without_mutation(string $backend): void
    {
        $f = new McpExecutionFixture($backend, true);
        try {
            $activity = $this->seedActivity($f);
            $bodies = []; $details = [];
            foreach (DetailKind::cases() as $kind) {
                $limit = $kind === DetailKind::PROCESS_OUTPUT ? 16384 : 65536;
                $body = str_pad('PROTECTED-SENTINEL-'.str_repeat("雪🙂\r\n\"\\/",  $kind === DetailKind::PROCESS_OUTPUT ? 1000 : 5000), $limit, 'x');
                $detail = $this->seedDetail($f, $activity, $kind, CaptureCompleteness::PARTIAL, $body);
                $details[$kind->value] = $detail; $bodies[$kind->value] = $body;
            }
            $empty = $this->seedDetail($f, $activity, body: '');
            $f->db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.123456Z'");
            $before = $f->state(); $events = $f->events; $commands = $f->commandCalls;
            $capabilities = $f->request('server/discover')['body']['result']['capabilities'];
            foreach (['prompts', 'subscriptions', 'tasks', 'resources'] as $name) { self::assertArrayNotHasKey($name, $capabilities); }
            $names = []; $params = [];
            do {
                $page = $f->request('tools/list', $params)['body']['result'];
                $names = [...$names, ...array_column($page['tools'], 'name')];
                $params = isset($page['nextCursor']) ? ['cursor' => $page['nextCursor']] : [];
                $this->assertNoBody($page, $bodies);
            } while ($params !== []);
            self::assertSame(['execution.activity', 'execution.detail', 'execution.get', 'execution.list', 'execution.messages', 'execution.phases', 'execution.summarize'], $names);
            self::assertSame(0, $f->detailAdmissionCalls);
            $summary = $this->equivalent($f, 'execution.summarize', ['projects' => ['project']], new SummarizeExecutions(ExecutionSelection::create(['project'])));
            self::assertSame(1, $summary['executions']); $this->assertNoBody($summary, $bodies);
            $executions = $this->equivalent($f, 'execution.list', ['projects' => ['project']], new ListExecutions(ExecutionSelection::create(['project'])));
            $exact = ['project_id' => $executions['executions'][0]['session']['project_id'], 'session_id' => $executions['executions'][0]['session']['session_id']];
            $this->assertNoBody($this->equivalent($f, 'execution.get', $exact, new GetExecution(...array_values($exact))), $bodies);
            $phases = $this->equivalent($f, 'execution.phases', $exact, new ListExecutionPhases(...array_values($exact)));
            $phase = $phases['phases'][0]['phase']['phase_id'];
            $activities = $this->equivalent($f, 'execution.activity', $exact + ['phase_id' => $phase], new ListPhaseActivity('project', 'session', $phase));
            $this->assertNoBody($activities, $bodies);
            $row = $activities['activities'][0];
            $selection = $exact + ['phase_id' => $phase, 'producer_id' => $row['producer_id'], 'activity_id' => $row['observation_id']];
            self::assertCount(5, $row['details']);
            foreach ($row['details'] as $reference) {
                $kind = $reference['capture']['kind']; $id = $reference['observation_id'];
                $args = $selection + ['detail_id' => $id, 'kind' => $kind];
                $offset = 0; $actual = ''; $chunks = 0;
                do {
                    $result = $this->detail($f, $args + ['offset' => $offset, 'length' => 4093]);
                    $chunk = $result['chunk'];
                    self::assertTrue($result['body_permitted']);
                    self::assertSame('base64', $chunk['encoding']);
                    self::assertSame($offset, $chunk['offset']);
                    $bytes = base64_decode($chunk['data'], true);
                    self::assertIsString($bytes); self::assertSame(base64_encode($bytes), $chunk['data']);
                    self::assertSame(strlen($bytes), $chunk['bytes']); self::assertLessThanOrEqual(4093, $chunk['bytes']);
                    self::assertSame('2026-10-20T12:34:56.12Z', $result['reference']['occurred_at']);
                    self::assertSame('2026-10-22T12:00:00.123456Z', $result['reference']['recorded_at']);
                    self::assertSame($reference['capture']['content_type'], $result['reference']['capture']['content_type']);
                    $actual .= $bytes; $offset = $chunk['next_offset']; ++$chunks;
                } while ($offset !== null);
                $expected = $id === $empty->id()->toString() ? '' : $bodies[$kind];
                self::assertSame($expected, $actual); self::assertSame(strlen($expected), $chunk['total_bytes']);
                self::assertSame($expected === '' ? 'complete' : 'partial', $result['reference']['capture']['completeness']);
                if ($expected !== '') { self::assertGreaterThan(1, $chunks); }
                $end = $this->detail($f, $args + ['offset' => strlen($expected), 'length' => 1]);
                self::assertSame('', $end['chunk']['data']); self::assertSame(0, $end['chunk']['bytes']); self::assertNull($end['chunk']['next_offset']);
                if ($kind === 'process_output') { self::assertSame(['stream' => 'stdout', 'index' => 3, 'offset' => 100], $result['reference']['capture']['segment']); }
            }
            $ids = [];
            foreach ([1, 2] as $page) {
                $messages = $this->messages($f, $selection + ['page' => $page, 'per_page' => 1, 'direction' => 'ASC']);
                self::assertSame(2, $messages['total']);
                $ids[] = $messages['messages'][0]['reference']['observation_id'];
                self::assertLessThanOrEqual(1024, $messages['messages'][0]['chunk']['bytes']);
            }
            $sorted = [$empty->id()->toString(), $details['message']->id()->toString()]; sort($sorted, SORT_STRING);
            self::assertSame($sorted, $ids, 'Full UUID breaks recording-time ties');
            self::assertSame([], $this->messages($f, $selection + ['page' => 20])['messages']);
            self::assertSame($before, $f->state()); self::assertSame($events, $f->events); self::assertSame($commands, $f->commandCalls);
            self::assertFalse($f->db->isTransactionActive()); self::assertSame([], $f->diagnostics);
        } finally { $f->db->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_every_page_and_chunk_rechecks_independent_availability_and_detail_permission_and_rejects_forged_relationships(string $backend): void
    {
        $f = new McpExecutionFixture($backend, true);
        try {
            $activity = $this->seedActivity($f); $detail = $this->seedDetail($f, $activity, body: 'PROTECTED-HUMAN-ONLY');
            $selection = $this->selection($activity); $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
            $foreignSession = SessionReference::fromStrings('other', 'session', 'concealed:consumer')->toArray();
            $f->seed(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $foreignSession]));
            $f->seed(ObservationFixture::phase(['observation_id' => ObservationId::generate()->toString(), 'session' => $foreignSession]));
            $foreignActivity = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString(), 'session' => $foreignSession]); $f->seed($foreignActivity);
            $foreign = $this->seedDetail($f, $foreignActivity, body: 'FOREIGN-PROTECTED-SENTINEL');
            self::assertNull($this->detail($f, array_replace($args, ['detail_id' => $foreign->id()->toString()])));
            self::assertNull($this->detail($f, array_replace($args, ['project_id' => 'other', 'activity_id' => $foreignActivity->id()->toString(), 'detail_id' => $foreign->id()->toString()])));
            $before = $f->state(); $events = $f->events; $commands = $f->commandCalls;
            self::assertSame(3, $this->detail($f, $args + ['length' => 3])['chunk']['next_offset']);
            self::assertSame(1, $this->messages($f, $selection + ['per_page' => 1])['total']);
            $f->detailAccess = DetailAccess::create(['project' => null], metadataGrants: ['project' => null]);
            foreach ([$args, $args + ['offset' => 3]] as $request) {
                $agent = $this->detail($f, $request); self::assertFalse($agent['body_permitted']); self::assertNull($agent['chunk']);
                self::assertSame('retained', $agent['reference']['availability']); $this->assertNoBody($agent, ['PROTECTED-HUMAN-ONLY']);
            }
            $f->detailAccess = DetailAccess::create([]);
            self::assertNull($this->messages($f, $selection + ['page' => 2, 'per_page' => 1])); self::assertNull($this->detail($f, $args));
            $f->detailAccess = DetailAccess::create(['project' => null], bodyGrants: ['project' => null]);
            self::assertNull($this->detail($f, $args));
            $f->detailAccess = $this->allAccess(); $f->access = ExecutionAccess::create([]);
            self::assertNotNull($this->detail($f, $args)['chunk'], 'Detail authority is neither inferred from nor blocked by timeline policy');
            foreach ([['project_id' => 'other'], ['session_id' => 'other'], ['phase_id' => 'other'], ['producer_id' => 'other'], ['activity_id' => ObservationId::generate()->toString()], ['detail_id' => ObservationId::generate()->toString()], ['kind' => 'tool_result']] as $wrong) {
                self::assertNull($this->detail($f, array_replace($args, $wrong)));
                if (!isset($wrong['detail_id']) && !isset($wrong['kind'])) { self::assertNull($this->messages($f, array_replace($selection, $wrong))); }
            }
            $other = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()]); $f->seed($other);
            $otherDetail = $this->seedDetail($f, $other, body: 'OTHER-ACTIVITY');
            self::assertNull($this->detail($f, array_replace($args, ['detail_id' => $otherDetail->id()->toString()])));
            $before = $f->state(); $events = $f->events; $commands = $f->commandCalls;
            foreach ([['principal' => 'human'], ['access' => true], ['bodyGrants' => ['project' => null]], ['_meta' => ['admin' => true]], ['length' => 4097], ['offset' => -1], ['offset' => 65537], ['length' => 0], ['length' => 1.5], ['kind' => 'sql']] as $bad) {
                $calls = $f->detailAdmissionCalls; $response = $f->call('execution.detail', array_replace($args, $bad));
                self::assertTrue($response['body']['result']['isError']); self::assertArrayNotHasKey('structuredContent', $response['body']['result']);
                self::assertSame($calls, $f->detailAdmissionCalls); $this->assertNoBody($response, ['PROTECTED-HUMAN-ONLY', 'OTHER-ACTIVITY']);
            }
            foreach ([['per_page' => 33], ['page' => 0], ['direction' => 'sideways']] as $bad) { self::assertTrue($f->call('execution.messages', $selection + $bad)['body']['result']['isError']); }
            $calls = $f->detailAdmissionCalls;
            $f->available = false;
            foreach (['execution.detail' => $args, 'execution.messages' => $selection + ['page' => 2]] as $name => $arguments) {
                self::assertSame($f->call('unknown', $arguments), $f->call($name, $arguments));
            }
            self::assertSame($calls, $f->detailAdmissionCalls);
            $f->available = true; $f->availabilitySequence = [true, false];
            self::assertSame(-32602, $f->call('execution.detail', $args)['body']['error']['code'], 'Invocation rechecks after HTTP mirror availability');
            self::assertSame($calls, $f->detailAdmissionCalls);
            $f->guard = false; self::assertSame(429, $f->call('execution.detail', $args)['status']); self::assertSame($calls, $f->detailAdmissionCalls);
            self::assertSame($before, $f->state()); self::assertSame($events, $f->events); self::assertSame($commands, $f->commandCalls);
        } finally { $f->db->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_capture_markers_empty_bytes_unknown_absence_and_disappearance_remain_truthful_and_live(string $backend): void
    {
        $f = new McpExecutionFixture($backend, true);
        try {
            $activity = $this->seedActivity($f); $selection = $this->selection($activity);
            foreach (CaptureCompleteness::cases() as $capture) {
                $retained = in_array($capture, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true);
                $detail = $this->seedDetail($f, $activity, completeness: $capture, body: $retained ? '' : null);
                $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
                $result = $this->detail($f, $args);
                self::assertSame($capture->value, $result['reference']['capture']['completeness']);
                self::assertSame($retained ? 'retained' : $capture->value, $result['reference']['availability']);
                if ($retained) { self::assertSame(['encoding' => 'base64', 'data' => '', 'offset' => 0, 'bytes' => 0, 'total_bytes' => 0, 'next_offset' => null], $result['chunk']); }
                else { self::assertNull($result['chunk']); }
                $f->db->delete('fight_execution_contents', ['observation_id' => $detail->id()->toString()]);
                $missing = $this->detail($f, $args);
                self::assertSame($retained ? 'missing' : $capture->value, $missing['reference']['availability']); self::assertNull($missing['chunk']);
            }
            $detail = $this->seedDetail($f, $activity, body: 'PROTECTED-disappearing');
            $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
            self::assertSame(3, $this->detail($f, $args + ['length' => 3])['chunk']['next_offset']);
            $first = $this->messages($f, $selection + ['per_page' => 1]);
            $late = $this->seedDetail($f, $activity, body: 'late');
            $second = $this->messages($f, $selection + ['page' => 2, 'per_page' => 1]);
            self::assertSame($first['messages'][0]['reference']['observation_id'], $second['messages'][0]['reference']['observation_id'], 'New receipt repeats a row on a live offset page');
            self::assertSame($first['total'] + 1, $second['total']);
            $f->db->delete('fight_execution_contents', ['observation_id' => $detail->id()->toString()]);
            $after = $this->detail($f, $args + ['offset' => 3]); self::assertNull($after['chunk']); self::assertSame('missing', $after['reference']['availability']);
            $safe = $f->call('execution.activity', ['project_id' => 'project', 'session_id' => 'session', 'phase_id' => 'phase'])['body']['result']['structuredContent'];
            $references = array_column($safe['activities'][0]['details'], null, 'observation_id');
            self::assertSame('missing', $references[$detail->id()->toString()]['availability']); $this->assertNoBody($safe, ['PROTECTED-disappearing']);
            $f->db->delete('fight_execution_observations', ['observation_id' => $detail->id()->toString()]);
            self::assertNull($this->detail($f, $args));
            self::assertSame($first['total'], $this->messages($f, $selection)['total']);
            self::assertSame([], $f->diagnostics);
        } finally { $f->db->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_invalid_ranges_corrupt_storage_and_application_failures_never_become_empty_success_or_public_bodies(string $backend): void
    {
        $f = new McpExecutionFixture($backend, true);
        try {
            $activity = $this->seedActivity($f); $detail = $this->seedDetail($f, $activity, body: 'PROTECTED-FAILURE-SENTINEL');
            $selection = $this->selection($activity); $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
            foreach ([array_replace($args, ['offset' => 100]), array_replace($args, ['detail_id' => str_repeat('x', 36)]), array_replace($args, ['phase_id' => '../bad'])] as $bad) { $this->assertInternalFailure($f, 'execution.detail', $bad); }
            $f->db->executeStatement('UPDATE fight_execution_observations SET version = 999 WHERE observation_id = ?', [$detail->id()->toString()]);
            $this->assertInternalFailure($f, 'execution.detail', $args); $this->assertInternalFailure($f, 'execution.messages', $selection);
            $f->db->executeStatement('UPDATE fight_execution_observations SET version = 1');
            $f->db->executeStatement('DROP TABLE fight_execution_contents');
            $this->assertInternalFailure($f, 'execution.detail', $args); $this->assertInternalFailure($f, 'execution.messages', $selection);
            self::assertSame(array_fill(0, 7, 'sanitized_failure'), $f->diagnostics);
        } finally { $f->db->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_maximum_message_page_and_full_encoded_metadata_boundary_include_text_escaping_and_protocol_overhead(string $backend): void
    {
        $f = new McpExecutionFixture($backend, true, '');
        try {
            $activity = $this->seedActivity($f); $selection = $this->selection($activity);
            for ($i = 0; $i < 32; ++$i) { $this->seedDetail($f, $activity, body: str_repeat("雪\"\\/", 9000)); }
            $page = $this->messages($f, $selection);
            self::assertCount(32, $page['messages']); self::assertSame(32, $page['total']);
            self::assertLessThanOrEqual(262144, strlen(json_encode($page, JSON_THROW_ON_ERROR)));
            self::assertLessThanOrEqual(1048576, strlen($f->lastResponse));
            foreach ($page['messages'] as $message) { self::assertSame(1024, $message['chunk']['bytes']); self::assertSame(1024, $message['chunk']['next_offset']); }
            $baseline = strlen($f->lastResponse);
        } finally { $f->db->close(); }
        // Actual central identity padding reaches the inclusive bound without changing the Tool budget.
        $escaped = str_repeat('雪"\\/', 64);
        $identity = $escaped.str_repeat('x', 1048576 - $baseline - (strlen(json_encode($escaped, JSON_THROW_ON_ERROR)) - 2));
        $f = new McpExecutionFixture($backend, true, $identity);
        try {
            $activity = $this->seedActivity($f); $selection = $this->selection($activity);
            for ($i = 0; $i < 32; ++$i) { $this->seedDetail($f, $activity, body: str_repeat("雪\"\\/", 9000)); }
            $this->messages($f, $selection);
            self::assertSame(1048576, strlen($f->lastResponse), 'Full structured/text/protocol/metadata bound is inclusive');
            $f->requestId .= 'x';
            $this->assertInternalFailure($f, 'execution.messages', $selection);
            self::assertCount(1, $f->diagnostics);
        } finally { $f->db->close(); }
    }

    private function equivalent(McpExecutionFixture $f, string $name, array $arguments, Query $query): mixed
    {
        $expected = $f->queries->fetch($query)?->toArray();
        $response = $f->call($name, $arguments);
        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('result', $response['body'], json_encode($response));
        $result = $response['body']['result'];
        self::assertArrayHasKey('structuredContent', $result);
        self::assertSame($expected, $result['structuredContent']);
        self::assertSame($expected, json_decode($result['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR));
        self::assertLessThanOrEqual(1048576, strlen($f->lastResponse));
        return $expected;
    }

    private function detail(McpExecutionFixture $f, array $args): mixed
    {
        return $this->equivalent($f, 'execution.detail', $args, GetCapturedDetail::fromArray(['selection' => array_intersect_key($args, array_flip(['project_id', 'session_id', 'phase_id', 'producer_id', 'activity_id'])), 'detail_id' => $args['detail_id'], 'kind' => $args['kind'], 'offset' => $args['offset'] ?? 0, 'length' => $args['length'] ?? 4096]));
    }

    private function messages(McpExecutionFixture $f, array $args): mixed
    {
        return $this->equivalent($f, 'execution.messages', $args, new ListCapturedMessages(DetailSelection::fromArray(array_intersect_key($args, array_flip(['project_id', 'session_id', 'phase_id', 'producer_id', 'activity_id']))), $args['page'] ?? null, $args['per_page'] ?? null, ['recorded_at' => $args['direction'] ?? 'DESC']));
    }

    private function seedActivity(McpExecutionFixture $f): Observation
    {
        $f->seed(ObservationFixture::session()); $f->seed(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $f->seed($activity); return $activity;
    }

    private function seedDetail(McpExecutionFixture $f, Observation $activity, DetailKind $kind = DetailKind::MESSAGE, CaptureCompleteness $completeness = CaptureCompleteness::COMPLETE, ?string $body = 'PROTECTED-SENTINEL'): Observation
    {
        $capture = DetailCapture::create($activity->id(), $kind, $kind === DetailKind::TOOL_ARGUMENTS ? 'application/json' : 'text/plain', $completeness, $kind === DetailKind::PROCESS_OUTPUT ? OutputSegment::create('stdout', 3, 100) : null);
        $detail = ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString(), 'session' => $activity->session()->toArray(), 'phase' => $activity->phase()->toArray(), 'producer_id' => $activity->producerId(), 'detail' => $capture->toArray()]);
        $f->seed($detail, ProtectedContent::create($capture, $body)); return $detail;
    }

    private function selection(Observation $activity): array
    {
        return ['project_id' => $activity->session()->projectId(), 'session_id' => $activity->session()->sessionId(), 'phase_id' => $activity->phase()->phaseId(), 'producer_id' => $activity->producerId(), 'activity_id' => $activity->id()->toString()];
    }

    private function allAccess(): DetailAccess
    {
        return DetailAccess::create(['project' => null], metadataGrants: ['project' => null], bodyGrants: ['project' => null]);
    }

    private function assertNoBody(mixed $result, array $bodies): void
    {
        $json = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('PROTECTED-SENTINEL', $json);
        self::assertStringNotContainsString(base64_encode('PROTECTED-SENTINEL'), $json);
        foreach ($bodies as $body) { self::assertStringNotContainsString($body, $json); self::assertStringNotContainsString(base64_encode($body), $json); }
    }

    private function assertInternalFailure(McpExecutionFixture $f, string $name, array $args): void
    {
        $response = $f->call($name, $args);
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $response['body']['error']);
        self::assertArrayNotHasKey('result', $response['body']);
        $this->assertNoBody($response, ['PROTECTED-FAILURE-SENTINEL']);
        foreach (['SQL', 'fight_execution_contents', 'Exception', 'trace'] as $private) { self::assertStringNotContainsString($private, $f->lastResponse); }
    }
}
