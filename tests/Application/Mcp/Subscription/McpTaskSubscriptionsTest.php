<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Closure;
use Doctrine\DBAL\DriverManager;
use Fiber;
use Fight\Common\Adapter\Mcp\Http\McpEventStream;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp as ReleasedMcp;
use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Composition\McpCompositionValidator;
use Fight\Common\Application\Mcp\Protocol\McpRequestDecoder;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionExecution;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionFilter;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpTaskDisclosureGuard;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

#[CoversClass(McpSubscriptions::class)]
#[CoversClass(McpSubscriptionFilter::class)]
#[CoversClass(McpSubscriptionExecution::class)]
#[CoversClass(ReleasedMcp\McpCapabilityRegistry::class)]
#[CoversClass(McpCompositionValidator::class)]
#[CoversClass(McpTasks::class)]
#[CoversClass(McpEventStream::class)]
final class McpTaskSubscriptionsTest extends UnitTestCase
{
    public function test_that_task_only_negotiation_subset_and_exact_typed_correlation_precede_full_baseline(): void
    {
        foreach ([7, '7', '雪"\\'] as $id) {
            $fixture = $this->fixture(); $fixture->taskFixture->create();
            // A Task-only request must not query retained-notice continuity or require its global grant.
            $fixture->subscriptionFixture->queryPolicy = static fn () => throw new RuntimeException('No notice grant');
            $request = TaskLiveFixture::request(['taskIds' => [TaskFixture::ID, str_repeat('b', 32), TaskFixture::ID], 'promptsListChanged' => true], $id);
            $body = $fixture->endpoint->handler->handle($request)->getBody();
            $ack = $this->next($body);
            self::assertSame(['taskIds' => [TaskFixture::ID]], $ack['params']['notifications']);
            self::assertSame('notifications/subscriptions/acknowledged', $ack['method']);
            self::assertSame($id, $ack['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
            $baseline = $this->next($body); $body->close();
            self::assertSame('notifications/tasks', $baseline['method']);
            self::assertSame($id, $baseline['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
            self::assertArrayNotHasKey('resultType', $baseline['params']);
            $read = $fixture->endpoint->call('tasks/get', ['taskId' => TaskFixture::ID])['result'];
            unset($read['resultType'], $baseline['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
            // Get has central server metadata; notifications have only permitted notification metadata.
            $read['_meta'] = [McpTasks::REVISION => $read['_meta'][McpTasks::REVISION]];
            self::assertSame($read, $baseline['params']);
            $context = json_decode($fixture->subscriptionFixture->connection->fetchOne('SELECT context FROM fight_mcp_subscriptions'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([TaskFixture::ID, str_repeat('b', 32)], $context['requested']['taskIds']);
            self::assertSame([TaskFixture::ID], $context['accepted']['taskIds']);
            self::assertStringNotContainsString('taskRevision', json_encode($context));
            self::assertSame(0, (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
            self::assertSame([], $fixture->endpoint->diagnostics);
        }
    }

    public function test_that_missing_capability_malformed_ids_and_disabled_composition_do_not_disclose(): void
    {
        $fixture = $this->fixture();
        $response = $fixture->endpoint->handler->handle(TaskLiveFixture::request(capabilities: (object) []));
        $error = json_decode((string) $response->getBody(), true)['error'];
        self::assertSame(-32021, $error['code']); self::assertArrayHasKey('data', $error);
        foreach ([true, ['bad'], [null], array_fill(0, 17, TaskFixture::ID), ['key' => TaskFixture::ID]] as $bad) {
            $response = $fixture->endpoint->handler->handle(TaskLiveFixture::request(['taskIds' => $bad]));
            self::assertSame(-32602, json_decode((string) $response->getBody(), true)['error']['code']);
        }
        self::assertSame(0, (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        foreach ([[], [str_repeat('b', 32)]] as $ids) {
            $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request(['taskIds' => $ids]))->getBody();
            self::assertSame([], $this->next($body)['params']['notifications']); $body->close();
        }
        $fixture->taskFixture->create(); $fixture->taskFixture->readAllowed = false;
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
        self::assertSame([], $this->next($body)['params']['notifications']); $body->close();
        $subscriptionComposition = new McpSubscriptions($fixture->taskFixture->queryBus, $fixture->subscriptionFixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, taskCapability: $fixture->endpoint->taskCapability, taskDisclosureGuard: $fixture);
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(new McpServerInfo('Invalid', '1'), [], $subscriptionComposition);
    }

    public function test_that_task_subscriptions_require_the_exact_serving_instance_even_with_complete_task_activation(): void
    {
        $fixture = $this->fixture();
        $endpoint = $fixture->endpoint;
        $otherTaskCapability = new McpTasks($fixture->taskFixture->queryBus, $fixture->taskFixture->commandBus, 'scope', 'caller');
        $subscriptionComposition = new McpSubscriptions($fixture->taskFixture->queryBus, $fixture->subscriptionFixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, taskCapability: $otherTaskCapability, taskDisclosureGuard: $fixture);
        $discovery = $endpoint->registry->capabilityFor('tools/list');
        foreach ([[$endpoint->taskCapability, $endpoint->invocation], [$endpoint->invocation, $endpoint->taskCapability]] as $capabilities) {
            try {
                new McpCapabilityRegistry(new McpServerInfo('Fixture', '1'), [$discovery, ...$capabilities], $subscriptionComposition);
                self::fail('Live Task delivery must not bind a nominally equivalent alternate instance.');
            } catch (DomainException $failure) {
                self::assertSame('Task notifications require the same complete Task capability.', $failure->getMessage());
            }
        }
        self::assertSame(0, $endpoint->tool->starts);
        self::assertSame(0, (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
    }

    public function test_that_actual_input_partial_confirmation_results_errors_and_terminal_baselines_share_get_semantics(): void
    {
        foreach (['working', 'input', 'partial', 'confirmation', 'completed', 'failed', 'cancelled'] as $state) {
            $fixture = $this->fixture(); $fixture->taskFixture->create();
            if (in_array($state, ['input', 'partial', 'confirmation'], true)) {
                $fixture->taskFixture->issue(confirmation: $state === 'confirmation', count: 2);
                if ($state === 'partial') { $fixture->taskFixture->supply([TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'PRIVATE ANSWER']]]); }
            } elseif ($state !== 'working') {
                $fixture->taskFixture->record(match ($state) { 'completed' => TaskFixture::completed(true), 'failed' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), default => TaskState::create('cancelled') });
            }
            $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body); $data = $this->next($body)['params']; $body->close();
            unset($data['_meta']['io.modelcontextprotocol/subscriptionId']);
            $read = $fixture->endpoint->call('tasks/get', ['taskId' => TaskFixture::ID])['result']; unset($read['resultType']); $read['_meta'] = [McpTasks::REVISION => $read['_meta'][McpTasks::REVISION]];
            self::assertSame($read, $data);
            self::assertStringNotContainsString('PRIVATE ANSWER', json_encode($data));
            self::assertStringNotContainsString('PRIVATE ANSWER', $fixture->subscriptionFixture->connection->fetchOne('SELECT context FROM fight_mcp_subscriptions'));
            self::assertSame(0, (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        }
    }

    public function test_that_ack_pause_baseline_races_equal_timestamp_coalescing_and_reconnect_reject_stale_reads(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create(); $old = $fixture->endpoint->taskCapability->current(TaskFixture::ID);
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body);
        $fixture->taskFixture->record(TaskState::create('working', 'Before baseline'));
        $baseline = $this->next($body)['params'];
        self::assertSame('Before baseline', $baseline['statusMessage']);
        self::assertGreaterThan($old['_meta'][McpTasks::REVISION]->get('value'), $baseline['_meta'][McpTasks::REVISION]['value']);
        self::assertSame($old['lastUpdatedAt'], $baseline['lastUpdatedAt']);
        $fixture->taskFixture->record(TaskState::create('working', 'Coalesced intermediate'), 2);
        $fixture->taskFixture->record(TaskFixture::completed(), 3);
        $latest = $this->next($body)['params']; $body->close();
        self::assertSame('completed', $latest['status']);
        self::assertGreaterThan($baseline['_meta'][McpTasks::REVISION]['value'], $latest['_meta'][McpTasks::REVISION]['value']);
        $again = $fixture->endpoint->handler->handle(TaskLiveFixture::request(id: 'new'))->getBody(); $this->next($again);
        $reconnected = $this->next($again)['params']; $again->close();
        self::assertSame($latest['_meta'][McpTasks::REVISION], $reconnected['_meta'][McpTasks::REVISION]);
        self::assertSame([], $fixture->endpoint->diagnostics);
    }

    public function test_that_task_and_tool_streams_are_mixed_without_other_families_or_protected_notices(): void
    {
        $fixture = $this->fixture(mixed: true); $fixture->taskFixture->create();
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request(['taskIds' => [TaskFixture::ID], 'toolsListChanged' => true]))->getBody();
        self::assertSame(['taskIds' => [TaskFixture::ID], 'toolsListChanged' => true], $this->next($body)['params']['notifications']);
        self::assertSame('notifications/tasks', $this->next($body)['method']);
        (new NoticeFixture($fixture->subscriptionFixture->connection))->record(['id' => 'tool', 'type' => 'mcp.tools.list_changed']);
        self::assertSame('notifications/tools/list_changed', $this->next($body)['method']); $body->close();
        self::assertSame(1, (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    public function test_that_fresh_revocation_disappearance_source_failure_and_visibility_restart_close_whole_stream_honestly(): void
    {
        foreach (['read', 'payload', 'signal', 'missing', 'source', 'scope', 'backward', 'tracking'] as $fault) {
            $fixture = $this->fixture(); $fixture->taskFixture->create(); $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body);
            if (in_array($fault, ['scope', 'backward'], true)) { $this->next($body); }
            match ($fault) {
                'read' => $fixture->taskFixture->readAllowed = false,
                'payload' => $fixture->taskFixture->discloseAllowed = false,
                'signal' => $fixture->allowed = false,
                'missing' => $fixture->subscriptionFixture->connection->executeStatement('DELETE FROM fight_tasks'),
                'source' => $fixture->subscriptionFixture->connection->executeStatement('DROP TABLE fight_tasks'),
                'scope' => $fixture->taskFixture->comparison = 'new-visibility',
                'backward' => $fixture->subscriptionFixture->connection->executeStatement("UPDATE fight_tasks SET payload = REPLACE(payload, '\"counter\":1', '\"counter\":0')"),
                'tracking' => $fixture->subscriptionFixture->connection->executeStatement('UPDATE fight_mcp_subscriptions SET expires_at = ?', [time()])
            };
            $wire = $body->getContents(); self::assertStringNotContainsString('notifications/tasks', $wire, $fault); self::assertStringNotContainsString('resultType', $wire, $fault);
            self::assertNotEmpty($fixture->endpoint->diagnostics, $fault); $body->close();
        }
    }

    public function test_that_final_full_frame_budgets_include_nested_escaping_metadata_id_and_sse_exactly(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create();
        $fixture->taskFixture->record(TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'text', 'text' => str_repeat('雪"\\', 500)]], 'structuredContent' => (object) ['ok' => true], 'isError' => false])));
        $request = TaskLiveFixture::request(id: '雪"\\');
        $body = $fixture->endpoint->handler->handle($request)->getBody(); $this->next($body); $wire = $body->read(65536); $body->close();
        foreach ([strlen($wire), strlen($wire) - 1] as $budget) {
            $endpoint = new \Fight\Test\Common\Fixture\Mcp\TaskEndpoint($fixture->taskFixture, subscriptionCompositionFactory: fn ($taskCapability) => new McpSubscriptions($fixture->subscriptionFixture->queryBus, $fixture->subscriptionFixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, maxFrameBytes: $budget, taskCapability: $taskCapability, taskDisclosureGuard: $fixture));
            $body = $endpoint->handler->handle($request)->getBody(); $this->next($body); $actual = $body->read(65536); $body->close();
            self::assertSame($budget === strlen($wire) ? $wire : '', $actual);
            self::assertCount($budget === strlen($wire) ? 0 : 1, $endpoint->diagnostics);
        }
    }

    public function test_that_stored_valid_state_can_exceed_even_the_maximum_final_serving_budget_without_a_partial_frame(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create();
        $fixture->taskFixture->record(TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'text', 'text' => str_repeat('x', 30000)]], 'structuredContent' => (object) ['ok' => true], 'isError' => false])));
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request(id: str_repeat('i', 40000)))->getBody();
        self::assertSame('notifications/subscriptions/acknowledged', $this->next($body)['method']);
        self::assertSame('', $body->read(65536)); self::assertTrue($body->eof()); $body->close();
        self::assertNotEmpty($fixture->endpoint->diagnostics);
        self::assertSame('completed', $fixture->endpoint->taskCapability->current(TaskFixture::ID)['status']);
    }

    public function test_that_transport_failure_does_not_mutate_business_state_and_tracking_callbacks_cannot_freeze_grants(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create(); $before = $fixture->taskFixture->get()->toString();
        $request = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request()->getBody());
        $execution = $fixture->endpoint->registry->subscriptions()->execution($request);
        $calls = 0; $execution->run(static function () use (&$calls): void { if (++$calls === 2) { throw new RuntimeException('private transport'); } }, $fixture->endpoint);
        self::assertSame(2, $calls); self::assertSame($before, $fixture->taskFixture->get()->toString());
        self::assertNotEmpty($fixture->endpoint->diagnostics);
        $subscriptionStore = $this->mock(McpSubscriptionStore::class);
        $subscriptionStore->shouldReceive('open')->once(); $subscriptionStore->shouldReceive('close')->once();
        $subscriptionStore->shouldReceive('heartbeat')->andReturnUsing(function () use ($fixture): void { $fixture->taskFixture->readAllowed = false; });
        $fixture->taskFixture->readAllowed = true;
        $subscriptionComposition = new McpSubscriptions($fixture->subscriptionFixture->queryBus, $subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, taskCapability: $fixture->endpoint->taskCapability, taskDisclosureGuard: $fixture);
        $subscriptionComposition->execution($request)->run(static fn () => self::fail('Opening lost its permission'), $fixture->endpoint);
    }

    public function test_that_idle_observations_coalesce_and_a_regressed_source_never_claims_newer_state(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create();
        $old = $fixture->subscriptionFixture->connection->fetchOne('SELECT payload FROM fight_tasks');
        $fixture->taskFixture->record(TaskState::create('working', 'newer'));
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body); $this->next($body);
        self::assertSame(": keepalive\n\n", $body->read(65536));
        self::assertSame(": keepalive\n\n", $body->read(65536));
        $fixture->subscriptionFixture->connection->executeStatement('UPDATE fight_tasks SET payload = ?', [$old]);
        self::assertStringNotContainsString('resultType', $body->getContents());
        self::assertNotEmpty($fixture->endpoint->diagnostics); $body->close();
    }

    public function test_that_each_task_boundary_observes_cancellation_after_transport_tracking_and_query_callbacks(): void
    {
        foreach (['transport', 'tracking', 'query'] as $phase) {
            $fixture = $this->fixture(); $fixture->taskFixture->create(); $second = str_repeat('b', 32); $fixture->taskFixture->create(id: $second);
            $subscriptionStore = $this->mock(McpSubscriptionStore::class); $subscriptionStore->shouldReceive('open')->once(); $subscriptionStore->shouldReceive('close')->once();
            $execution = null; $heartbeats = 0;
            $subscriptionStore->shouldReceive('heartbeat')->andReturnUsing(static function () use (&$heartbeats, &$execution, $phase): void { if (++$heartbeats === 3 && $phase === 'tracking') { $execution->cancel(); } });
            $request = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request(['taskIds' => [TaskFixture::ID, $second]])->getBody());
            $subscriptionComposition = new McpSubscriptions($fixture->subscriptionFixture->queryBus, $subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, taskCapability: $fixture->endpoint->taskCapability, taskDisclosureGuard: $fixture);
            $execution = $subscriptionComposition->execution($request); $frames = [];
            $execution->run(static function ($frame) use (&$frames, $execution, $phase, $fixture): void {
                $frames[] = $frame;
                if (count($frames) === 1 && $phase === 'query') { $fixture->taskFixture->onRead = static fn () => $execution->cancel(); }
                if (count($frames) === 2 && $phase === 'transport') { $execution->cancel(); }
            }, $fixture->endpoint);
            self::assertCount($phase === 'transport' ? 2 : 1, $frames); self::assertSame([], $fixture->endpoint->diagnostics);
        }
    }

    public function test_that_native_handoff_occurs_inside_guard_and_conflicting_writer_waits_until_release(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create();
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
        $wire = [];
        $sink = static function (string $bytes) use ($fixture, &$wire): void {
            self::assertTrue($fixture->guarded);
            self::assertNull(Fiber::getCurrent(), 'Native handoff must not suspend the producer under protection');
            $wire[] = $bytes;
        };
        $body->emitNext($sink);
        $body->emitNext(static function (string $bytes) use ($fixture, $sink): void {
            $fixture->writeAuthority(static function () use ($fixture): void { $fixture->allowed = false; });
            self::assertTrue($fixture->allowed, 'Conflicting writer must wait for bounded output');
            $sink($bytes);
        });
        self::assertFalse($fixture->allowed); self::assertFalse($fixture->guarded);
        $fixture->taskFixture->record(TaskState::create('working', 'MUST NOT DISCLOSE'));
        self::assertSame('', str_replace(": keepalive\n\n", '', $body->getContents())); $body->close();
        self::assertCount(2, $wire); self::assertCount(2, $fixture->guardedStates);
        self::assertSame([TaskFixture::ID], $fixture->guardedStates[0]);
        self::assertSame([TaskFixture::ID], $fixture->guardedStates[1]);
        self::assertNotEmpty($fixture->endpoint->diagnostics);
    }

    public function test_that_fresh_guard_decisions_cover_whole_state_fields_faults_and_all_opening_ids(): void
    {
        foreach (['read', 'payload', 'signal', 'fault', 'owner'] as $fault) {
            $fixture = $this->fixture(); $fixture->taskFixture->create();
            $second = str_repeat('b', 32); $fixture->taskFixture->create(id: $second);
            $fixture->onGuard = static function (array $states) use ($fixture, $fault, $second): void {
                self::assertSame([TaskFixture::ID, $second], array_keys($states));
                self::assertSame('working', $states[$second]['status']);
                match ($fault) {
                    'read' => $fixture->taskFixture->readAllowed = false,
                    'payload' => $fixture->taskFixture->discloseAllowed = false,
                    'signal' => $fixture->allowed = false,
                    'fault' => throw new RuntimeException('PRIVATE GUARD FAULT'),
                    'owner' => $fixture->subscriptionFixture->connection->executeStatement('DELETE FROM fight_mcp_subscriptions')
                };
            };
            $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request(['taskIds' => [TaskFixture::ID, $second]]))->getBody();
            self::assertSame('', $body->getContents(), $fault); $body->close();
            self::assertNotEmpty($fixture->endpoint->diagnostics, $fault);
        }
    }

    public function test_that_guard_omission_late_repeat_and_wrong_fiber_actions_fail_without_duplicate_output(): void
    {
        foreach (['omit', 'repeat', 'fiber', 'throw'] as $fault) {
            $fixture = $this->fixture(); $fixture->taskFixture->create(); $saved = null;
            $guard = $this->mock(McpTaskDisclosureGuard::class);
            $guard->shouldReceive('run')->once()->andReturnUsing(static function ($subscriber, $states, Closure $disclose) use ($fault, &$saved): void {
                $saved = $disclose;
                if ($fault === 'throw') { throw new RuntimeException('PRIVATE GUARD'); }
                if ($fault === 'fiber') { (new Fiber($disclose))->start(); }
                if ($fault === 'repeat') { $disclose(); $disclose(); }
            });
            $request = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request()->getBody());
            $execution = new McpSubscriptions($fixture->subscriptionFixture->queryBus, $fixture->subscriptionFixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, taskCapability: $fixture->endpoint->taskCapability, taskDisclosureGuard: $guard)->execution($request);
            $frames = []; $execution->run(static function ($frame) use (&$frames): void { $frames[] = $frame; }, $fixture->endpoint);
            self::assertCount($fault === 'repeat' ? 1 : 0, $frames);
            self::assertNotEmpty($fixture->endpoint->diagnostics);
            try { $saved(); self::fail('Saved callback admitted'); } catch (RuntimeException) { self::assertTrue(true); }
        }
        $this->expectException(DomainException::class);
        new McpSubscriptions($fixture->subscriptionFixture->queryBus, $fixture->subscriptionFixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), taskCapability: $fixture->endpoint->taskCapability);
    }

    public function test_that_fragmented_reads_reauthorize_each_handoff_and_stop_after_revocation_or_state_change(): void
    {
        foreach (['revoke', 'change', 'complete'] as $mode) {
            $fixture = $this->fixture(); $fixture->taskFixture->create();
            $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body);
            $first = $body->read(8); self::assertSame('data: {"', $first);
            if ($mode === 'complete') {
                $wire = $first;
                while (!str_ends_with($wire, "\n\n")) { $wire .= $body->read(8); }
                self::assertSame('working', json_decode(substr($wire, 6), true)['params']['status']);
                self::assertSame([], $fixture->endpoint->diagnostics);
            } else {
                if ($mode === 'revoke') { $fixture->allowed = false; } else { $fixture->taskFixture->record(TaskState::create('working', 'changed')); }
                self::assertSame('', $body->getContents()); self::assertNotEmpty($fixture->endpoint->diagnostics);
            }
            $body->close();
        }
    }

    public function test_that_cancellation_during_final_guard_and_each_post_source_tracking_boundary_suppresses_output(): void
    {
        foreach (['opening-guard', 'task-guard', 'task-before', 'task-after'] as $phase) {
            $fixture = $this->fixture(); $fixture->taskFixture->create(); $execution = null; $heartbeats = 0; $guards = 0;
            $subscriptionStore = $this->mock(McpSubscriptionStore::class);
            $subscriptionStore->shouldReceive('open')->once(); $subscriptionStore->shouldReceive('close')->once();
            $subscriptionStore->shouldReceive('heartbeat')->andReturnUsing(static function () use (&$heartbeats, &$execution, $phase): void {
                ++$heartbeats;
                if (($phase === 'task-before' && $heartbeats === 4) || ($phase === 'task-after' && $heartbeats === 5)) { $execution->cancel(); }
            });
            $fixture->onGuard = static function () use (&$guards, &$execution, $phase): void {
                ++$guards;
                if (($phase === 'opening-guard' && $guards === 1) || ($phase === 'task-guard' && $guards === 2)) { $execution->cancel(); }
            };
            $request = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request()->getBody());
            $execution = new McpSubscriptions($fixture->subscriptionFixture->queryBus, $subscriptionStore, $fixture, NoticeSelection::create(['scope']), tools: false, resources: false, taskCapability: $fixture->endpoint->taskCapability, taskDisclosureGuard: $fixture)->execution($request);
            $wire = []; $execution->run(static function ($frame) use (&$wire): void { $wire[] = $frame; }, $fixture->endpoint);
            self::assertCount($phase === 'opening-guard' ? 0 : 1, $wire, $phase);
            self::assertSame([], $fixture->endpoint->diagnostics);
        }
    }

    public function test_that_missing_internal_guard_denies_and_initial_signal_denial_acknowledges_no_task(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create();
        $request = (new McpRequestDecoder())->decode((string) TaskLiveFixture::request()->getBody());
        $execution = new McpSubscriptionExecution($request->id(), McpSubscriptionFilter::fromRequest($request), ['taskIds'], $fixture->subscriptionFixture->queryBus, $fixture->subscriptionFixture->subscriptionStore, $fixture, NoticeSelection::create(['scope']), 1, 10000, 65536, $fixture->endpoint->taskCapability);
        $execution->run(static fn () => self::fail('Missing guard disclosed'), $fixture->endpoint);
        self::assertNotEmpty($fixture->endpoint->diagnostics);
        $fixture->allowed = false;
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
        self::assertSame([], $this->next($body)['params']['notifications']); $body->close();
    }

    public function test_that_body_close_during_guard_admission_suppresses_the_in_progress_native_handoff(): void
    {
        foreach ([false, true] as $fault) {
            $fixture = $this->fixture(); $fixture->taskFixture->create();
            $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
            $fixture->onGuard = static function () use ($body, $fault): void {
                $body->close();
                if ($fault) { throw new RuntimeException('PRIVATE RETURNED GUARD FAULT'); }
            };
            $body->emitNext(static fn () => self::fail('Closed body disclosed'));
            self::assertTrue($body->eof()); self::assertSame(0, $body->tell());
            $body->emitNext(static fn () => self::fail('Closed body restarted'));
            self::assertCount($fault ? 1 : 0, $fixture->endpoint->diagnostics);
        }
    }

    public function test_that_unchanged_state_revocation_is_not_hidden_by_coalescing_at_lifetime_completion(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create(); $before = $fixture->taskFixture->get()->toString();
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
        $this->next($body); $this->next($body);
        $fixture->taskFixture->onComparison = static function () use ($fixture): void {
            $fixture->allowed = false;
            // The returned Query crosses lifetime, not the independent five-second technical lease.
            usleep(1100000);
        };
        try { $wire = $body->getContents(); } finally { $body->close(); $fixture->taskFixture->onComparison = null; }
        self::assertFalse($fixture->allowed);
        self::assertSame($before, $fixture->taskFixture->get()->toString());
        self::assertSame(['wire' => '', 'diagnosed' => true, 'active' => 0], [
            'wire' => str_replace(": keepalive\n\n", '', $wire),
            'diagnosed' => $fixture->endpoint->diagnostics !== [],
            'active' => (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0')
        ]);
    }

    public function test_that_lifetime_interruption_during_a_partial_psr_handoff_never_appends_successful_completion(): void
    {
        $fixture = $this->fixture(); $fixture->taskFixture->create(); $before = $fixture->taskFixture->get()->toString();
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody(); $this->next($body);
        self::assertSame('data: {"', $body->read(8));
        usleep(1100000);
        try { $tail = $body->getContents(); } finally { $body->close(); }
        self::assertSame($before, $fixture->taskFixture->get()->toString());
        self::assertSame(['tail' => '', 'diagnosed' => true], ['tail' => $tail, 'diagnosed' => $fixture->endpoint->diagnostics !== []]);
    }

    public static function disclosurePhases(): array
    {
        return ['opening last pass' => ['opening'], 'after acknowledgment' => ['baseline'], 'subsequent update' => ['update']];
    }

    #[DataProvider('disclosurePhases')]
    public function test_that_signal_revocation_during_task_sourcing_prevents_new_disclosure(string $phase): void
    {
        $fixture = $this->fixture();
        $fixture->taskFixture->create();
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
        $this->reachDisclosure($fixture, $body, $phase);
        $comparisons = 0;
        $fixture->taskFixture->onComparison = static function () use ($fixture, $phase, &$comparisons): void {
            if (++$comparisons === ($phase === 'opening' ? 3 : 1)) {
                $fixture->allowed = false;
            }
        };
        try {
            $wire = $body->getContents();
        } finally {
            $body->close();
        }
        self::assertSame([
            'signal' => false, 'wire' => '', 'diagnosed' => true, 'active' => 0
        ], [
            'signal' => $fixture->allowed, 'wire' => str_replace(": keepalive\n\n", '', $wire), 'diagnosed' => $fixture->endpoint->diagnostics !== [],
            'active' => (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0')
        ]);
    }

    public static function ownerLossPhases(): array
    {
        $cases = [];
        foreach (['opening', 'baseline', 'update'] as $phase) {
            foreach ([false, true] as $cleanup) {
                $cases[$phase.($cleanup ? ' close and cleanup' : ' close')] = [$phase, $cleanup];
            }
        }
        return $cases;
    }

    #[DataProvider('ownerLossPhases')]
    public function test_that_native_owner_close_during_task_sourcing_prevents_new_disclosure(string $phase, bool $cleanup): void
    {
        $fixture = $this->fixture();
        $fixture->taskFixture->create();
        $before = $fixture->taskFixture->get()->toString();
        $body = $fixture->endpoint->handler->handle(TaskLiveFixture::request())->getBody();
        $this->reachDisclosure($fixture, $body, $phase);
        $comparisons = 0;
        $closed = false;
        $fixture->taskFixture->onComparison = static function () use ($fixture, $phase, $cleanup, &$comparisons, &$closed): void {
            if (++$comparisons !== ($phase === 'opening' ? 3 : 1)) {
                return;
            }
            $row = $fixture->subscriptionFixture->connection->fetchAssociative('SELECT registration_id, owner FROM fight_mcp_subscriptions');
            $fixture->subscriptionFixture->subscriptionStore->close($row['registration_id'], $row['owner'], time());
            if ($cleanup) {
                self::assertSame(1, $fixture->subscriptionFixture->subscriptionStore->cleanup(time(), 16));
            }
            $closed = true;
        };
        try {
            $wire = $body->getContents();
        } finally {
            $body->close();
            $fixture->taskFixture->onComparison = null;
        }
        if ($cleanup) {
            self::assertSame(0, (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        }
        if ($phase !== 'update') {
            self::assertSame($before, $fixture->taskFixture->get()->toString());
        }
        self::assertSame([
            'closed' => true, 'wire' => '', 'diagnosed' => true, 'active' => 0
        ], [
            'closed' => $closed, 'wire' => str_replace(": keepalive\n\n", '', $wire), 'diagnosed' => $fixture->endpoint->diagnostics !== [],
            'active' => (int) $fixture->subscriptionFixture->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0')
        ]);
    }

    private function reachDisclosure(TaskLiveFixture $fixture, StreamInterface $body, string $phase): void
    {
        if ($phase !== 'opening') {
            self::assertSame('notifications/subscriptions/acknowledged', $this->next($body)['method']);
        }
        if ($phase === 'update') {
            self::assertSame('notifications/tasks', $this->next($body)['method']);
            $fixture->taskFixture->record(TaskState::create('working', 'PRIVATE AFTER OWNER LOSS'));
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
