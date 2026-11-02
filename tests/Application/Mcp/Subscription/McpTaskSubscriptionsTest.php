<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionFilter;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\Mcp\Task\TaskLiveFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

#[CoversClass(McpSubscriptions::class)]
#[CoversClass(McpSubscriptionFilter::class)]
#[CoversClass(McpSubscriptionExecution::class)]
#[CoversClass(McpCapabilityRegistry::class)]
#[CoversClass(McpTasks::class)]
final class McpTaskSubscriptionsTest extends UnitTestCase
{
    public function test_that_task_only_negotiation_subset_and_exact_typed_correlation_precede_full_baseline(): void
    {
        foreach ([7, '7', '雪"\\'] as $id) {
            $f = $this->fixture(); $f->task->create();
            // A Task-only request must not query retained-notice continuity or require its global grant.
            $f->notices->queryPolicy = static fn () => throw new RuntimeException('No notice grant');
            $request = TaskLiveFixture::request(['taskIds' => [TaskFixture::ID, str_repeat('b', 32), TaskFixture::ID], 'promptsListChanged' => true], $id);
            $body = $f->endpoint->handler->handle($request)->getBody();
            $ack = $this->next($body);
            self::assertSame(['taskIds' => [TaskFixture::ID]], $ack['params']['notifications']);
            self::assertSame('notifications/subscriptions/acknowledged', $ack['method']);
            self::assertSame($id, $ack['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
            $baseline = $this->next($body); $body->close();
            self::assertSame('notifications/tasks', $baseline['method']);
            self::assertSame($id, $baseline['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
            self::assertArrayNotHasKey('resultType', $baseline['params']);
            $read = $f->endpoint->call('tasks/get', ['taskId' => TaskFixture::ID])['result'];
            unset($read['resultType'], $baseline['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
            // Get has central server metadata; notifications have only permitted notification metadata.
            $read['_meta'] = [McpTasks::REVISION => $read['_meta'][McpTasks::REVISION]];
            self::assertSame($read, $baseline['params']);
            $context = json_decode($f->notices->db->fetchOne('SELECT context FROM fight_mcp_subscriptions'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([TaskFixture::ID, str_repeat('b', 32)], $context['requested']['taskIds']);
            self::assertSame([TaskFixture::ID], $context['accepted']['taskIds']);
            self::assertStringNotContainsString('taskRevision', json_encode($context));
            self::assertSame(0, (int) $f->notices->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
            self::assertSame([], $f->endpoint->diagnostics);
        }
    }

    public function test_that_missing_capability_malformed_ids_and_disabled_composition_do_not_disclose(): void
    {
        $f = $this->fixture();
        $response = $f->endpoint->handler->handle(TaskLiveFixture::request(capabilities: (object) []));
        $error = json_decode((string) $response->getBody(), true)['error'];
        self::assertSame(-32021, $error['code']); self::assertArrayHasKey('data', $error);
        foreach ([true, ['bad'], [null], array_fill(0, 17, TaskFixture::ID), ['key' => TaskFixture::ID]] as $bad) {
            $response = $f->endpoint->handler->handle(TaskLiveFixture::request(['taskIds' => $bad]));
            self::assertSame(-32602, json_decode((string) $response->getBody(), true)['error']['code']);
        }
        self::assertSame(0, (int) $f->notices->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        foreach ([[], [str_repeat('b', 32)]] as $ids) {
            $body = $f->endpoint->handler->handle(TaskLiveFixture::request(['taskIds' => $ids]))->getBody();
            self::assertSame([], $this->next($body)['params']['notifications']); $body->close();
        }
        $f->task->create(); $f->task->readAllowed = false;
        $body = $f->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
        self::assertSame([], $this->next($body)['params']['notifications']); $body->close();
        $subscriptions = new McpSubscriptions($f->task->queries, $f->notices->store, $f, NoticeSelection::create(['scope']), tools: false, resources: false, tasks: $f->endpoint->tasks);
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(new McpServerInfo('Invalid', '1'), [], $subscriptions);
    }

    public function test_that_actual_input_partial_confirmation_results_errors_and_terminal_baselines_share_get_semantics(): void
    {
        foreach (['working', 'input', 'partial', 'confirmation', 'completed', 'failed', 'cancelled'] as $state) {
            $f = $this->fixture(); $f->task->create();
            if (in_array($state, ['input', 'partial', 'confirmation'], true)) {
                $f->task->issue(confirmation: $state === 'confirmation', count: 2);
                if ($state === 'partial') { $f->task->supply([TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'PRIVATE ANSWER']]]); }
            } elseif ($state !== 'working') {
                $f->task->record(match ($state) { 'completed' => TaskFixture::completed(true), 'failed' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), default => TaskState::create('cancelled') });
            }
            $body = $f->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body); $data = $this->next($body)['params']; $body->close();
            unset($data['_meta']['io.modelcontextprotocol/subscriptionId']);
            $read = $f->endpoint->call('tasks/get', ['taskId' => TaskFixture::ID])['result']; unset($read['resultType']); $read['_meta'] = [McpTasks::REVISION => $read['_meta'][McpTasks::REVISION]];
            self::assertSame($read, $data);
            self::assertStringNotContainsString('PRIVATE ANSWER', json_encode($data));
            self::assertStringNotContainsString('PRIVATE ANSWER', $f->notices->db->fetchOne('SELECT context FROM fight_mcp_subscriptions'));
            self::assertSame(0, (int) $f->notices->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        }
    }

    public function test_that_ack_pause_baseline_races_equal_timestamp_coalescing_and_reconnect_reject_stale_reads(): void
    {
        $f = $this->fixture(); $f->task->create(); $old = $f->endpoint->tasks->current(TaskFixture::ID);
        $body = $f->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body);
        $f->task->record(TaskState::create('working', 'Before baseline'));
        $baseline = $this->next($body)['params'];
        self::assertSame('Before baseline', $baseline['statusMessage']);
        self::assertGreaterThan($old['_meta'][McpTasks::REVISION]->get('value'), $baseline['_meta'][McpTasks::REVISION]['value']);
        self::assertSame($old['lastUpdatedAt'], $baseline['lastUpdatedAt']);
        $f->task->record(TaskState::create('working', 'Coalesced intermediate'), 2);
        $f->task->record(TaskFixture::completed(), 3);
        $latest = $this->next($body)['params']; $body->close();
        self::assertSame('completed', $latest['status']);
        self::assertGreaterThan($baseline['_meta'][McpTasks::REVISION]['value'], $latest['_meta'][McpTasks::REVISION]['value']);
        $again = $f->endpoint->handler->handle(TaskLiveFixture::request(id: 'new'))->getBody(); $this->next($again);
        $reconnected = $this->next($again)['params']; $again->close();
        self::assertSame($latest['_meta'][McpTasks::REVISION], $reconnected['_meta'][McpTasks::REVISION]);
        self::assertSame([], $f->endpoint->diagnostics);
    }

    public function test_that_task_and_tool_streams_are_mixed_without_other_families_or_protected_notices(): void
    {
        $f = $this->fixture(mixed: true); $f->task->create();
        $body = $f->endpoint->handler->handle(TaskLiveFixture::request(['taskIds' => [TaskFixture::ID], 'toolsListChanged' => true]))->getBody();
        self::assertSame(['taskIds' => [TaskFixture::ID], 'toolsListChanged' => true], $this->next($body)['params']['notifications']);
        self::assertSame('notifications/tasks', $this->next($body)['method']);
        (new NoticeFixture($f->notices->db))->record(['id' => 'tool', 'type' => 'mcp.tools.list_changed']);
        self::assertSame('notifications/tools/list_changed', $this->next($body)['method']); $body->close();
        self::assertSame(1, (int) $f->notices->db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    public function test_that_fresh_revocation_disappearance_source_failure_and_visibility_restart_close_whole_stream_honestly(): void
    {
        foreach (['read', 'payload', 'signal', 'missing', 'source', 'scope', 'backward', 'tracking'] as $fault) {
            $f = $this->fixture(); $f->task->create(); $body = $f->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body);
            if (in_array($fault, ['scope', 'backward'], true)) { $this->next($body); }
            match ($fault) {
                'read' => $f->task->readAllowed = false,
                'payload' => $f->task->discloseAllowed = false,
                'signal' => $f->allowed = false,
                'missing' => $f->notices->db->executeStatement('DELETE FROM fight_tasks'),
                'source' => $f->notices->db->executeStatement('DROP TABLE fight_tasks'),
                'scope' => $f->task->comparison = 'new-visibility',
                'backward' => $f->notices->db->executeStatement("UPDATE fight_tasks SET payload = REPLACE(payload, '\"counter\":1', '\"counter\":0')"),
                'tracking' => $f->notices->db->executeStatement('UPDATE fight_mcp_subscriptions SET expires_at = ?', [time()])
            };
            $wire = $body->getContents(); self::assertStringNotContainsString('notifications/tasks', $wire, $fault); self::assertStringNotContainsString('resultType', $wire, $fault);
            self::assertNotEmpty($f->endpoint->diagnostics, $fault); $body->close();
        }
    }

    public function test_that_final_full_frame_budgets_include_nested_escaping_metadata_id_and_sse_exactly(): void
    {
        $f = $this->fixture(); $f->task->create();
        $f->task->record(TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'text', 'text' => str_repeat('雪"\\', 500)]], 'structuredContent' => (object) ['ok' => true], 'isError' => false])));
        $request = TaskLiveFixture::request(id: '雪"\\');
        $body = $f->endpoint->handler->handle($request)->getBody(); $this->next($body); $wire = $body->read(65536); $body->close();
        foreach ([strlen($wire), strlen($wire) - 1] as $budget) {
            $endpoint = new \Fight\Test\Common\Fixture\Mcp\TaskEndpoint($f->task, subscriptions: fn ($tasks) => new McpSubscriptions($f->notices->queries, $f->notices->store, $f, NoticeSelection::create(['scope']), tools: false, resources: false, maxFrameBytes: $budget, tasks: $tasks));
            $body = $endpoint->handler->handle($request)->getBody(); $this->next($body); $actual = $body->read(65536); $body->close();
            self::assertSame($budget === strlen($wire) ? $wire : '', $actual);
            self::assertCount($budget === strlen($wire) ? 0 : 1, $endpoint->diagnostics);
        }
    }

    public function test_that_stored_valid_state_can_exceed_even_the_maximum_final_serving_budget_without_a_partial_frame(): void
    {
        $f = $this->fixture(); $f->task->create();
        $f->task->record(TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'text', 'text' => str_repeat('x', 30000)]], 'structuredContent' => (object) ['ok' => true], 'isError' => false])));
        $body = $f->endpoint->handler->handle(TaskLiveFixture::request(id: str_repeat('i', 40000)))->getBody();
        self::assertSame('notifications/subscriptions/acknowledged', $this->next($body)['method']);
        self::assertSame('', $body->read(65536)); self::assertTrue($body->eof()); $body->close();
        self::assertNotEmpty($f->endpoint->diagnostics);
        self::assertSame('completed', $f->endpoint->tasks->current(TaskFixture::ID)['status']);
    }

    public function test_that_transport_failure_does_not_mutate_business_state_and_tracking_callbacks_cannot_freeze_grants(): void
    {
        $f = $this->fixture(); $f->task->create(); $before = $f->task->get()->toString();
        $request = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request()->getBody());
        $execution = $f->endpoint->registry->subscriptions()->execution($request);
        $calls = 0; $execution->run(static function () use (&$calls): void { if (++$calls === 2) { throw new RuntimeException('private transport'); } }, $f->endpoint);
        self::assertSame(2, $calls); self::assertSame($before, $f->task->get()->toString());
        self::assertNotEmpty($f->endpoint->diagnostics);
        $store = $this->mock(McpSubscriptionStore::class);
        $store->shouldReceive('open')->once(); $store->shouldReceive('close')->once();
        $store->shouldReceive('heartbeat')->andReturnUsing(function () use ($f): void { $f->task->readAllowed = false; });
        $f->task->readAllowed = true;
        $composition = new McpSubscriptions($f->notices->queries, $store, $f, NoticeSelection::create(['scope']), tools: false, resources: false, tasks: $f->endpoint->tasks);
        $composition->execution($request)->run(static fn () => self::fail('Opening lost its permission'), $f->endpoint);
    }

    public function test_that_idle_observations_coalesce_and_a_regressed_source_never_claims_newer_state(): void
    {
        $f = $this->fixture(); $f->task->create();
        $old = $f->notices->db->fetchOne('SELECT payload FROM fight_tasks');
        $f->task->record(TaskState::create('working', 'newer'));
        $body = $f->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body); $this->next($body);
        self::assertSame(": keepalive\n\n", $body->read(65536));
        self::assertSame(": keepalive\n\n", $body->read(65536));
        $f->notices->db->executeStatement('UPDATE fight_tasks SET payload = ?', [$old]);
        self::assertStringNotContainsString('resultType', $body->getContents());
        self::assertNotEmpty($f->endpoint->diagnostics); $body->close();
    }

    public function test_that_each_task_boundary_observes_cancellation_after_transport_tracking_and_query_callbacks(): void
    {
        foreach (['transport', 'tracking', 'query'] as $phase) {
            $f = $this->fixture(); $f->task->create(); $second = str_repeat('b', 32); $f->task->create(id: $second);
            $store = $this->mock(McpSubscriptionStore::class); $store->shouldReceive('open')->once(); $store->shouldReceive('close')->once();
            $execution = null; $heartbeats = 0;
            $store->shouldReceive('heartbeat')->andReturnUsing(static function () use (&$heartbeats, &$execution, $phase): void { if (++$heartbeats === 3 && $phase === 'tracking') { $execution->cancel(); } });
            $request = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request(['taskIds' => [TaskFixture::ID, $second]])->getBody());
            $composition = new McpSubscriptions($f->notices->queries, $store, $f, NoticeSelection::create(['scope']), tools: false, resources: false, tasks: $f->endpoint->tasks);
            $execution = $composition->execution($request); $frames = [];
            $execution->run(static function ($frame) use (&$frames, $execution, $phase, $f): void {
                $frames[] = $frame;
                if (count($frames) === 1 && $phase === 'query') { $f->task->onRead = static fn () => $execution->cancel(); }
                if (count($frames) === 2 && $phase === 'transport') { $execution->cancel(); }
            }, $f->endpoint);
            self::assertCount($phase === 'transport' ? 2 : 1, $frames); self::assertSame([], $f->endpoint->diagnostics);
        }
    }

    private function fixture(bool $mixed = false): TaskLiveFixture
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalTaskSchema())->install($db);
        return new TaskLiveFixture($db, mixed: $mixed);
    }

    private function next(StreamInterface $body): array
    {
        for ($i = 0; $i < 300 && !$body->eof(); ++$i) {
            $wire = $body->read(65536);
            if (str_starts_with($wire, 'data: ')) { return json_decode(substr($wire, 6), true, flags: JSON_THROW_ON_ERROR); }
        }
        self::fail('Expected next data frame');
    }
}
