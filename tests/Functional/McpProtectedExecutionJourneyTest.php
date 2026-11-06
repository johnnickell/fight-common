<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Common\Application\Mcp\Detail\Exception\DetailRemovalRejected;
use Fight\Common\Application\Mcp\Detail\Service\DetailAccess;
use Fight\Common\Application\Mcp\Observation\Exception\ProtectedContentRemoved;
use Fight\Common\Application\Mcp\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Activity\ActivityExecution;
use Fight\Common\Domain\Mcp\Activity\ActivityResult;
use Fight\Common\Domain\Mcp\Activity\ActivityTarget;
use Fight\Common\Domain\Mcp\Activity\ActivityTiming;
use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\DetailCapture;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\OutputSegment;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\ObservationOccurrence;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Mcp\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Mcp\Session\Query\ListExecutions;
use Fight\Common\Domain\Mcp\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Value\DateTime\Duration;
use Fight\Test\Common\Fixture\Mcp\Execution\McpExecutionFixture;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class McpProtectedExecutionJourneyTest extends TestCase
{
    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.' default' => [$backend, new DbalObservationTables()];
            yield $backend.' configured' => [$backend, new DbalObservationTables('McpSchema', 'McpSessions', 'McpPhases', 'McpObservations', 'McpContents')];
        }
    }

    #[DataProvider('databases')]
    public function test_that_guarded_summary_to_detail_journey_matches_shared_queries_and_reconstructs_all_kinds_without_mutation(string $backend, DbalObservationTables $tables): void
    {
        $fixture = new McpExecutionFixture($backend, true, tables: $tables);
        try {
            $activity = $this->seedActivity($fixture);
            $bodies = []; $details = [];
            foreach (DetailKind::cases() as $kind) {
                $limit = $kind === DetailKind::PROCESS_OUTPUT ? 16384 : 65536;
                $body = str_pad('PROTECTED-SENTINEL-'.str_repeat("雪🙂\r\n\"\\/",  $kind === DetailKind::PROCESS_OUTPUT ? 1000 : 5000), $limit, 'x');
                $detail = $this->seedDetail($fixture, $activity, $kind, CaptureCompleteness::PARTIAL, $body);
                $details[$kind->value] = $detail; $bodies[$kind->value] = $body;
            }
            $empty = $this->seedDetail($fixture, $activity, body: '');
            $fixture->connection->executeStatement('UPDATE '.$tables->observationTable()->quoted($fixture->connection->getDatabasePlatform())." SET recorded_at = '2026-10-22T12:00:00.123456Z'");
            $before = $fixture->state(); $eventCount = $fixture->eventCount; $commandCount = $fixture->commandCalls;
            $capabilities = $fixture->request('server/discover')['body']['result']['capabilities'];
            foreach (['prompts', 'subscriptions', 'tasks', 'resources'] as $name) { self::assertArrayNotHasKey($name, $capabilities); }
            $names = []; $params = [];
            do {
                $page = $fixture->request('tools/list', $params)['body']['result'];
                $names = [...$names, ...array_column($page['tools'], 'name')];
                $params = isset($page['nextCursor']) ? ['cursor' => $page['nextCursor']] : [];
                $this->assertNoBody($page, $bodies);
            } while ($params !== []);
            self::assertSame(['execution.activity', 'execution.detail', 'execution.get', 'execution.list', 'execution.messages', 'execution.phases', 'execution.summarize'], $names);
            self::assertSame(0, $fixture->detailAdmissionCalls);
            $summary = $this->equivalent($fixture, 'execution.summarize', ['projects' => ['project']], new SummarizeExecutions(ExecutionSelection::create(['project'])));
            self::assertSame(1, $summary['executions']); $this->assertNoBody($summary, $bodies);
            $executions = $this->equivalent($fixture, 'execution.list', ['projects' => ['project']], new ListExecutions(ExecutionSelection::create(['project'])));
            $exact = ['project_id' => $executions['executions'][0]['session']['project_id'], 'session_id' => $executions['executions'][0]['session']['session_id']];
            $this->assertNoBody($this->equivalent($fixture, 'execution.get', $exact, new GetExecution(...array_values($exact))), $bodies);
            $phases = $this->equivalent($fixture, 'execution.phases', $exact, new ListExecutionPhases(...array_values($exact)));
            $phase = $phases['phases'][0]['phase']['phase_id'];
            $activities = $this->equivalent($fixture, 'execution.activity', $exact + ['phase_id' => $phase], new ListPhaseActivity('project', 'session', $phase));
            $this->assertNoBody($activities, $bodies);
            $row = $activities['activities'][0];
            self::assertSame($this->executionFacts(), $row['activity']['execution']);
            $selection = $exact + ['phase_id' => $phase, 'producer_id' => $row['producer_id'], 'activity_id' => $row['observation_id']];
            self::assertCount(5, $row['details']);
            foreach ($row['details'] as $reference) {
                $kind = $reference['capture']['kind']; $id = $reference['observation_id'];
                $args = $selection + ['detail_id' => $id, 'kind' => $kind];
                $offset = 0; $actual = ''; $chunks = 0;
                do {
                    $result = $this->detail($fixture, $args + ['offset' => $offset, 'length' => 4093]);
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
                $end = $this->detail($fixture, $args + ['offset' => strlen($expected), 'length' => 1]);
                self::assertSame('', $end['chunk']['data']); self::assertSame(0, $end['chunk']['bytes']); self::assertNull($end['chunk']['next_offset']);
                if ($kind === 'process_output') { self::assertSame(['stream' => 'stdout', 'index' => 3, 'offset' => 100], $result['reference']['capture']['segment']); }
            }
            $ids = [];
            foreach ([1, 2] as $page) {
                $messages = $this->messages($fixture, $selection + ['page' => $page, 'per_page' => 1, 'direction' => 'ASC']);
                self::assertSame(2, $messages['total']);
                $ids[] = $messages['messages'][0]['reference']['observation_id'];
                self::assertLessThanOrEqual(1024, $messages['messages'][0]['chunk']['bytes']);
            }
            $sorted = [$empty->id()->toString(), $details['message']->id()->toString()]; sort($sorted, SORT_STRING);
            self::assertSame($sorted, $ids, 'Full UUID breaks recording-time ties');
            self::assertSame([], $this->messages($fixture, $selection + ['page' => 20])['messages']);
            self::assertSame($before, $fixture->state()); self::assertSame($eventCount, $fixture->eventCount); self::assertSame($commandCount, $fixture->commandCalls);
            self::assertFalse($fixture->connection->isTransactionActive()); self::assertSame([], $fixture->diagnostics);
        } finally { $fixture->connection->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_every_page_and_chunk_rechecks_independent_availability_and_detail_permission_and_rejects_forged_relationships(string $backend, DbalObservationTables $tables): void
    {
        $fixture = new McpExecutionFixture($backend, true, tables: $tables);
        try {
            $activity = $this->seedActivity($fixture); $detail = $this->seedDetail($fixture, $activity, body: 'PROTECTED-HUMAN-ONLY');
            $selection = $this->selection($activity); $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
            $foreignSession = SessionReference::fromStrings('other', 'session', 'concealed:consumer')->toArray();
            $fixture->seed(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $foreignSession]));
            $fixture->seed(ObservationFixture::phase(['observation_id' => ObservationId::generate()->toString(), 'session' => $foreignSession]));
            $foreignActivity = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString(), 'session' => $foreignSession]); $fixture->seed($foreignActivity);
            $foreign = $this->seedDetail($fixture, $foreignActivity, body: 'FOREIGN-PROTECTED-SENTINEL');
            self::assertNull($this->detail($fixture, array_replace($args, ['detail_id' => $foreign->id()->toString()])));
            self::assertNull($this->detail($fixture, array_replace($args, ['project_id' => 'other', 'activity_id' => $foreignActivity->id()->toString(), 'detail_id' => $foreign->id()->toString()])));
            $before = $fixture->state(); $eventCount = $fixture->eventCount; $commandCount = $fixture->commandCalls;
            self::assertSame(3, $this->detail($fixture, $args + ['length' => 3])['chunk']['next_offset']);
            self::assertSame(1, $this->messages($fixture, $selection + ['per_page' => 1])['total']);
            $fixture->detailAccess = DetailAccess::create(['project' => null], metadataGrants: ['project' => null]);
            foreach ([$args, $args + ['offset' => 3]] as $request) {
                $agent = $this->detail($fixture, $request); self::assertFalse($agent['body_permitted']); self::assertNull($agent['chunk']);
                self::assertSame('retained', $agent['reference']['availability']); $this->assertNoBody($agent, ['PROTECTED-HUMAN-ONLY']);
            }
            $fixture->detailAccess = DetailAccess::create([]);
            self::assertNull($this->messages($fixture, $selection + ['page' => 2, 'per_page' => 1])); self::assertNull($this->detail($fixture, $args));
            $fixture->detailAccess = DetailAccess::create(['project' => null], bodyGrants: ['project' => null]);
            self::assertNull($this->detail($fixture, $args));
            $fixture->detailAccess = $this->allAccess(); $fixture->access = ExecutionAccess::create([]);
            self::assertNotNull($this->detail($fixture, $args)['chunk'], 'Detail authority is neither inferred from nor blocked by timeline policy');
            foreach ([['project_id' => 'other'], ['session_id' => 'other'], ['phase_id' => 'other'], ['producer_id' => 'other'], ['activity_id' => ObservationId::generate()->toString()], ['detail_id' => ObservationId::generate()->toString()], ['kind' => 'tool_result']] as $wrong) {
                self::assertNull($this->detail($fixture, array_replace($args, $wrong)));
                if (!isset($wrong['detail_id']) && !isset($wrong['kind'])) { self::assertNull($this->messages($fixture, array_replace($selection, $wrong))); }
            }
            $other = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()]); $fixture->seed($other);
            $otherDetail = $this->seedDetail($fixture, $other, body: 'OTHER-ACTIVITY');
            self::assertNull($this->detail($fixture, array_replace($args, ['detail_id' => $otherDetail->id()->toString()])));
            $before = $fixture->state(); $eventCount = $fixture->eventCount; $commandCount = $fixture->commandCalls;
            foreach ([['principal' => 'human'], ['access' => true], ['bodyGrants' => ['project' => null]], ['_meta' => ['admin' => true]], ['length' => 4097], ['offset' => -1], ['offset' => 65537], ['length' => 0], ['length' => 1.5], ['kind' => 'sql']] as $bad) {
                $calls = $fixture->detailAdmissionCalls; $response = $fixture->call('execution.detail', array_replace($args, $bad));
                self::assertTrue($response['body']['result']['isError']); self::assertArrayNotHasKey('structuredContent', $response['body']['result']);
                self::assertSame($calls, $fixture->detailAdmissionCalls); $this->assertNoBody($response, ['PROTECTED-HUMAN-ONLY', 'OTHER-ACTIVITY']);
            }
            foreach ([['per_page' => 33], ['page' => 0], ['direction' => 'sideways']] as $bad) { self::assertTrue($fixture->call('execution.messages', $selection + $bad)['body']['result']['isError']); }
            $calls = $fixture->detailAdmissionCalls;
            $fixture->available = false;
            foreach (['execution.detail' => $args, 'execution.messages' => $selection + ['page' => 2]] as $name => $arguments) {
                self::assertSame($fixture->call('unknown', $arguments), $fixture->call($name, $arguments));
            }
            self::assertSame($calls, $fixture->detailAdmissionCalls);
            $fixture->available = true; $fixture->availabilitySequence = [true, false];
            self::assertSame(-32602, $fixture->call('execution.detail', $args)['body']['error']['code'], 'Invocation rechecks after HTTP mirror availability');
            self::assertSame($calls, $fixture->detailAdmissionCalls);
            $fixture->guard = false; self::assertSame(429, $fixture->call('execution.detail', $args)['status']); self::assertSame($calls, $fixture->detailAdmissionCalls);
            self::assertSame($before, $fixture->state()); self::assertSame($eventCount, $fixture->eventCount); self::assertSame($commandCount, $fixture->commandCalls);
        } finally { $fixture->connection->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_capture_markers_empty_bytes_unknown_absence_and_disappearance_remain_truthful_and_live(string $backend, DbalObservationTables $tables): void
    {
        $fixture = new McpExecutionFixture($backend, true, tables: $tables);
        try {
            $activity = $this->seedActivity($fixture); $selection = $this->selection($activity);
            foreach (CaptureCompleteness::cases() as $capture) {
                $retained = in_array($capture, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true);
                $detail = $this->seedDetail($fixture, $activity, completeness: $capture, body: $retained ? '' : null);
                $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
                $result = $this->detail($fixture, $args);
                self::assertSame($capture->value, $result['reference']['capture']['completeness']);
                self::assertSame($retained ? 'retained' : $capture->value, $result['reference']['availability']);
                if ($retained) { self::assertSame(['encoding' => 'base64', 'data' => '', 'offset' => 0, 'bytes' => 0, 'total_bytes' => 0, 'next_offset' => null], $result['chunk']); }
                else { self::assertNull($result['chunk']); }
                $fixture->connection->delete($tables->contentTable()->quoted($fixture->connection->getDatabasePlatform()), ['observation_id' => $detail->id()->toString()]);
                $missing = $this->detail($fixture, $args);
                self::assertSame($retained ? 'missing' : $capture->value, $missing['reference']['availability']); self::assertNull($missing['chunk']);
            }
            $detail = $this->seedDetail($fixture, $activity, body: 'PROTECTED-disappearing');
            $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
            self::assertSame(3, $this->detail($fixture, $args + ['length' => 3])['chunk']['next_offset']);
            $first = $this->messages($fixture, $selection + ['per_page' => 1]);
            $late = $this->seedDetail($fixture, $activity, body: 'late');
            $second = $this->messages($fixture, $selection + ['page' => 2, 'per_page' => 1]);
            self::assertSame($first['messages'][0]['reference']['observation_id'], $second['messages'][0]['reference']['observation_id'], 'New receipt repeats a row on a live offset page');
            self::assertSame($first['total'] + 1, $second['total']);
            $fixture->connection->delete($tables->contentTable()->quoted($fixture->connection->getDatabasePlatform()), ['observation_id' => $detail->id()->toString()]);
            $after = $this->detail($fixture, $args + ['offset' => 3]); self::assertNull($after['chunk']); self::assertSame('missing', $after['reference']['availability']);
            $safe = $fixture->call('execution.activity', ['project_id' => 'project', 'session_id' => 'session', 'phase_id' => 'phase'])['body']['result']['structuredContent'];
            $references = array_column($safe['activities'][0]['details'], null, 'observation_id');
            self::assertSame('missing', $references[$detail->id()->toString()]['availability']); $this->assertNoBody($safe, ['PROTECTED-disappearing']);
            $fixture->connection->delete($tables->observationTable()->quoted($fixture->connection->getDatabasePlatform()), ['observation_id' => $detail->id()->toString()]);
            self::assertNull($this->detail($fixture, $args));
            self::assertSame($first['total'], $this->messages($fixture, $selection)['total']);
            self::assertSame([], $fixture->diagnostics);
        } finally { $fixture->connection->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_invalid_ranges_corrupt_storage_and_application_failures_never_become_empty_success_or_public_bodies(string $backend, DbalObservationTables $tables): void
    {
        $fixture = new McpExecutionFixture($backend, true, tables: $tables);
        try {
            $activity = $this->seedActivity($fixture); $detail = $this->seedDetail($fixture, $activity, body: 'PROTECTED-FAILURE-SENTINEL');
            $selection = $this->selection($activity); $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
            foreach ([array_replace($args, ['offset' => 100]), array_replace($args, ['detail_id' => str_repeat('x', 36)]), array_replace($args, ['phase_id' => '../bad'])] as $bad) { $this->assertInternalFailure($fixture, 'execution.detail', $bad); }
            $fixture->connection->executeStatement('UPDATE '.$tables->observationTable()->quoted($fixture->connection->getDatabasePlatform()).' SET version = 999 WHERE observation_id = ?', [$detail->id()->toString()]);
            $this->assertInternalFailure($fixture, 'execution.detail', $args); $this->assertInternalFailure($fixture, 'execution.messages', $selection);
            $fixture->connection->executeStatement('UPDATE '.$tables->observationTable()->quoted($fixture->connection->getDatabasePlatform()).' SET version = 1');
            $fixture->connection->executeStatement('DROP TABLE '.$tables->contentTable()->quoted($fixture->connection->getDatabasePlatform()));
            $this->assertInternalFailure($fixture, 'execution.detail', $args); $this->assertInternalFailure($fixture, 'execution.messages', $selection);
            self::assertSame(array_fill(0, 7, 'sanitized_failure'), $fixture->diagnostics);
        } finally { $fixture->connection->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_maximum_message_page_and_full_encoded_metadata_boundary_include_text_escaping_and_protocol_overhead(string $backend, DbalObservationTables $tables): void
    {
        $fixture = new McpExecutionFixture($backend, true, '', $tables);
        try {
            $activity = $this->seedActivity($fixture); $selection = $this->selection($activity);
            for ($i = 0; $i < 32; ++$i) { $this->seedDetail($fixture, $activity, body: str_repeat("雪\"\\/", 9000)); }
            $page = $this->messages($fixture, $selection);
            self::assertCount(32, $page['messages']); self::assertSame(32, $page['total']);
            self::assertLessThanOrEqual(262144, strlen(json_encode($page, JSON_THROW_ON_ERROR)));
            self::assertLessThanOrEqual(1048576, strlen($fixture->lastResponse));
            foreach ($page['messages'] as $message) { self::assertSame(1024, $message['chunk']['bytes']); self::assertSame(1024, $message['chunk']['next_offset']); }
            $baseline = strlen($fixture->lastResponse);
        } finally { $fixture->connection->close(); }
        // Actual central identity padding reaches the inclusive bound without changing the Tool budget.
        $escaped = str_repeat('雪"\\/', 64);
        $identity = $escaped.str_repeat('x', 1048576 - $baseline - (strlen(json_encode($escaped, JSON_THROW_ON_ERROR)) - 2));
        $fixture = new McpExecutionFixture($backend, true, $identity, $tables);
        try {
            $activity = $this->seedActivity($fixture); $selection = $this->selection($activity);
            for ($i = 0; $i < 32; ++$i) { $this->seedDetail($fixture, $activity, body: str_repeat("雪\"\\/", 9000)); }
            $this->messages($fixture, $selection);
            self::assertSame(1048576, strlen($fixture->lastResponse), 'Full structured/text/protocol/metadata bound is inclusive');
            $fixture->requestId .= 'x';
            $this->assertInternalFailure($fixture, 'execution.messages', $selection);
            self::assertCount(1, $fixture->diagnostics);
        } finally { $fixture->connection->close(); }
    }

    #[DataProvider('databases')]
    public function test_that_authorized_command_removal_preserves_identity_and_truthful_mcp_reads_without_resurrection(string $backend, DbalObservationTables $tables): void
    {
        $fixture = new McpExecutionFixture($backend, true, tables: $tables);
        try {
            $activity = $this->seedActivity($fixture);
            $detail = $this->seedDetail($fixture, $activity, body: 'PROTECTED-REMOVAL-SENTINEL');
            $retained = $this->seedDetail($fixture, $activity, body: 'PROTECTED-STILL-RETAINED');
            $selection = $this->selection($activity);
            $args = $selection + ['detail_id' => $detail->id()->toString(), 'kind' => 'message'];
            $command = new RemoveCapturedDetail(DetailSelection::fromArray($selection), $detail->id(), DetailKind::MESSAGE);
            $before = $fixture->state(); $eventCount = $fixture->eventCount;
            $summary = $this->equivalent($fixture, 'execution.summarize', ['projects' => ['project']], new SummarizeExecutions(ExecutionSelection::create(['project'])));
            self::assertNotNull($this->detail($fixture, $args)['chunk']);
            try {
                $fixture->remove($command);
                self::fail('Read grants must not authorize removal');
            } catch (DetailRemovalRejected) {}
            self::assertSame(1, $fixture->removalAdmissionCalls);
            self::assertSame($before, $fixture->state());
            self::assertFalse($fixture->connection->isTransactionActive());

            $fixture->removalAllowed = true;
            $forged = array_replace($selection, ['project_id' => 'other']);
            try {
                $fixture->remove(new RemoveCapturedDetail(DetailSelection::fromArray($forged), $detail->id(), DetailKind::MESSAGE));
                self::fail('Removal must reject a forged relationship');
            } catch (DetailRemovalRejected) {}
            self::assertSame($before, $fixture->state());
            $fixture->detailAccess = DetailAccess::create([]);
            $fixture->access = ExecutionAccess::create([]);
            $fixture->remove($command);
            self::assertFalse($fixture->connection->isTransactionActive(), 'Acknowledgment follows the real transaction commit');
            $after = $fixture->state();
            self::assertSame(array_slice($before, 0, 4), array_slice($after, 0, 4), 'Safe identity and relationships survive removal');
            self::assertCount(count($before[4]) - 1, $after[4]);
            self::assertSame($eventCount, $fixture->eventCount);
            self::assertNull($this->detail($fixture, $args), 'Removal permission grants no read permission');

            $fixture->detailAccess = $this->allAccess();
            $fixture->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
            $missing = $this->detail($fixture, $args + ['offset' => 3]);
            self::assertNull($missing['chunk']);
            self::assertSame('missing', $missing['reference']['availability']);
            self::assertSame('complete', $missing['reference']['capture']['completeness']);
            $messages = $this->messages($fixture, $selection);
            self::assertSame(2, $messages['total']);
            foreach ($messages['messages'] as $message) {
                if ($message['reference']['observation_id'] === $detail->id()->toString()) {
                    self::assertSame('missing', $message['reference']['availability']);
                    self::assertNull($message['chunk']);
                }
            }
            self::assertNotNull($this->detail($fixture, array_replace($args, ['detail_id' => $retained->id()->toString()]))['chunk']);
            $safe = $this->equivalent($fixture, 'execution.activity', ['project_id' => 'project', 'session_id' => 'session', 'phase_id' => 'phase'], new ListPhaseActivity('project', 'session', 'phase'));
            $references = array_column($safe['activities'][0]['details'], null, 'observation_id');
            self::assertSame('missing', $references[$detail->id()->toString()]['availability']);
            self::assertSame($this->executionFacts(), $safe['activities'][0]['activity']['execution']);
            self::assertSame($summary, $this->equivalent($fixture, 'execution.summarize', ['projects' => ['project']], new SummarizeExecutions(ExecutionSelection::create(['project']))));
            $this->assertNoBody($safe, ['PROTECTED-REMOVAL-SENTINEL', 'PROTECTED-STILL-RETAINED']);
            $fixture->remove($command);
            self::assertSame($after, $fixture->state(), 'Admitted repeated removal is idempotent');
            $fixture->removalAllowed = false;
            try {
                $fixture->remove($command);
                self::fail('Repeated removal must recheck current permission');
            } catch (DetailRemovalRejected) {}
            foreach (['PROTECTED-REMOVAL-SENTINEL', 'changed retry'] as $body) {
                try {
                    $fixture->seed($detail, ProtectedContent::create($detail->detail(), $body));
                    self::fail('Retained identity must reject resurrection');
                } catch (ProtectedContentRemoved) {}
            }
            self::assertSame($after, $fixture->state());
            self::assertSame($eventCount, $fixture->eventCount);
            self::assertSame('missing', $this->detail($fixture, $args)['reference']['availability']);
            self::assertFalse($fixture->connection->isTransactionActive());
            self::assertSame([], $fixture->diagnostics);
        } finally { $fixture->connection->close(); }
    }

    private function equivalent(McpExecutionFixture $fixture, string $name, array $arguments, Query $query): mixed
    {
        $expected = $fixture->queryBus->fetch($query)?->toArray();
        $response = $fixture->call($name, $arguments);
        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('result', $response['body'], json_encode($response));
        $result = $response['body']['result'];
        self::assertArrayHasKey('structuredContent', $result);
        self::assertSame($expected, $result['structuredContent']);
        self::assertSame($expected, json_decode($result['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR));
        self::assertLessThanOrEqual(1048576, strlen($fixture->lastResponse));
        return $expected;
    }

    private function detail(McpExecutionFixture $fixture, array $args): mixed
    {
        return $this->equivalent($fixture, 'execution.detail', $args, GetCapturedDetail::fromArray(['selection' => array_intersect_key($args, array_flip(['project_id', 'session_id', 'phase_id', 'producer_id', 'activity_id'])), 'detail_id' => $args['detail_id'], 'kind' => $args['kind'], 'offset' => $args['offset'] ?? 0, 'length' => $args['length'] ?? 4096]));
    }

    private function messages(McpExecutionFixture $fixture, array $args): mixed
    {
        return $this->equivalent($fixture, 'execution.messages', $args, new ListCapturedMessages(DetailSelection::fromArray(array_intersect_key($args, array_flip(['project_id', 'session_id', 'phase_id', 'producer_id', 'activity_id']))), $args['page'] ?? null, $args['per_page'] ?? null, ['recorded_at' => $args['direction'] ?? 'DESC']));
    }

    private function seedActivity(McpExecutionFixture $fixture): Observation
    {
        $fixture->seed(ObservationFixture::session()); $fixture->seed(ObservationFixture::phase());
        $execution = ActivityExecution::fromParts(
            ActivityTarget::fromStrings('process', 'inspect'),
            ActivityTiming::fromParts(null, ObservationOccurrence::fromString('2026-10-20T12:00:00.1200Z'), Duration::fromMicroseconds(0)),
            ActivityResult::threw('process:interrupted')
        );
        $activity = ObservationFixture::activity(['activity' => array_replace(ObservationFixture::activity()->activity()->toArray(), ['execution' => $execution->toArray()])]);
        $fixture->seed($activity); return $activity;
    }

    private function executionFacts(): array
    {
        return [
            'target' => ['capability' => 'process', 'name' => 'inspect'],
            'timing' => ['started_at' => null, 'ended_at' => '2026-10-20T12:00:00.1200Z', 'elapsed' => '0us'],
            'result' => ['kind' => 'threw', 'failure_kind' => 'process:interrupted', 'exit_code' => null],
        ];
    }

    private function seedDetail(McpExecutionFixture $fixture, Observation $activity, DetailKind $kind = DetailKind::MESSAGE, CaptureCompleteness $completeness = CaptureCompleteness::COMPLETE, ?string $body = 'PROTECTED-SENTINEL'): Observation
    {
        $capture = DetailCapture::create($activity->id(), $kind, $kind === DetailKind::TOOL_ARGUMENTS ? 'application/json' : 'text/plain', $completeness, $kind === DetailKind::PROCESS_OUTPUT ? OutputSegment::create('stdout', 3, 100) : null);
        $detail = ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString(), 'session' => $activity->session()->toArray(), 'phase' => $activity->phase()->toArray(), 'producer_id' => $activity->producerId(), 'detail' => $capture->toArray()]);
        $fixture->seed($detail, ProtectedContent::create($capture, $body)); return $detail;
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

    private function assertInternalFailure(McpExecutionFixture $fixture, string $name, array $args): void
    {
        $response = $fixture->call($name, $args);
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $response['body']['error']);
        self::assertArrayNotHasKey('result', $response['body']);
        $this->assertNoBody($response, ['PROTECTED-FAILURE-SENTINEL']);
        foreach (['SQL', $fixture->tables->contentTable()->name(), 'Exception', 'trace'] as $private) { self::assertStringNotContainsString($private, $fixture->lastResponse); }
    }
}
