<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionStore;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeSchema;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\Notice\NoticeRecoveryFixture;
use Fight\Test\Common\TestCase\Notice\NoticeRemovalFixture;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class McpSubscriptionJourneyTest extends TestCase
{
    private string $directory;
    private Connection $db;
    private array $processes = [];
    private array $evidence = [];

    #[DataProvider('databases')]
    public function test_that_real_shared_database_live_streams_reused_ids_revocation_failure_and_polling_are_isolated(string $backend): void
    {
        $this->setupDatabase($backend);
        $a = $this->server('scope'); $b = $this->server('scope-2');
        $left = $this->open($a, ['toolsListChanged' => true]);
        $right = $this->open($b, ['toolsListChanged' => true, 'resourceSubscriptions' => ['file:///allowed']]);
        $ackA = $this->nextFrame($left); $ackB = $this->nextFrame($right);
        self::assertSame('notifications/subscriptions/acknowledged', $ackA['method']);
        self::assertSame('notifications/subscriptions/acknowledged', $ackB['method']);
        self::assertSame(7, $ackA['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        self::assertSame(7, $ackB['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        $rows = $this->db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions');
        self::assertCount(2, $rows); self::assertNotSame($rows[0]['registration_id'], $rows[1]['registration_id']);
        self::assertNotSame($rows[0]['owner'], $rows[1]['owner']);
        $this->worker(['operation' => 'record', 'notices' => [
            ['id' => 'a', 'scope' => 'scope', 'type' => 'mcp.tools.list_changed', 'metadata' => ['app/hidden' => 'CONCEALED-A']],
            ['id' => 'b-tool', 'scope' => 'scope-2', 'type' => 'mcp.tools.list_changed'],
            ['id' => 'b', 'scope' => 'scope-2', 'type' => 'mcp.resources.updated', 'metadata' => ['mcp/uri' => 'file:///allowed', 'app/hidden' => 'CONCEALED-B']],
            ['id' => 'hidden-resource', 'scope' => 'scope-2', 'type' => 'mcp.resources.updated', 'metadata' => ['mcp/uri' => 'file:///hidden']]
        ]]);
        $updateA = $this->nextFrame($left); $toolB = $this->nextFrame($right); $updateB = $this->nextFrame($right);
        self::assertSame('notifications/tools/list_changed', $updateA['method']);
        self::assertSame('notifications/tools/list_changed', $toolB['method']);
        self::assertSame('notifications/resources/updated', $updateB['method']);
        self::assertSame('file:///allowed', $updateB['params']['uri']);
        self::assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0'));
        touch($this->directory.'/revoke-scope');
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'after-revoke', 'type' => 'mcp.tools.list_changed']]]);
        // A deliberately paused reader resumes without conferring a permission lease on queued source rows.
        usleep(150000);
        $tailA = $this->drain($left); $tailB = $this->drain($right);
        self::assertCount(1, $tailA); self::assertCount(1, $tailB);
        self::assertSame('complete', $tailA[0]['result']['resultType']);
        self::assertSame('complete', $tailB[0]['result']['resultType']);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0'));
        $this->evidence['wire'] = compact('ackA', 'ackB', 'updateA', 'toolB', 'updateB', 'tailA', 'tailB');
        self::assertStringNotContainsString('CONCEALED', json_encode($this->evidence['wire']));
        unlink($this->directory.'/revoke-scope');
        $failed = $this->open($a, ['toolsListChanged' => true]); self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($failed)['method']);
        touch($this->directory.'/source-failure');
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'retained-after-failure', 'type' => 'mcp.tools.list_changed']]]);
        self::assertSame([], $this->drain($failed), 'Source failure must not masquerade as graceful completion');
        unlink($this->directory.'/source-failure');
        $poll = new NoticeRecoveryFixture($this->db);
        $recovered = $poll->call(['scopes' => ['scope']]);
        self::assertSame(200, $recovered['status']);
        $notices = $recovered['body']['result']['structuredContent']['notices'];
        self::assertSame(['a', 'after-revoke', 'retained-after-failure'], array_column($notices, 'id'));
        self::assertSame('complete', $recovered['body']['result']['structuredContent']['status']);
        $this->evidence['polling'] = $recovered;
        $this->evidence['storage'] = $this->db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions');
        self::assertFalse($this->db->isTransactionActive());
    }

    #[DataProvider('databases')]
    public function test_that_independent_routed_operator_queries_summarize_then_page_actual_streams_with_current_inspection_grants(string $backend): void
    {
        $this->setupDatabase($backend);
        $left = $this->open($this->server('scope'), ['toolsListChanged' => true, 'promptsListChanged' => true, 'resourceSubscriptions' => ['file:///allowed', 'file:///hidden']], 'psr&lifetime=15');
        $ackLeft = $this->nextFrame($left);
        $right = $this->open($this->server('scope'), ['toolsListChanged' => true], 'psr&lifetime=15');
        $ackRight = $this->nextFrame($right);
        $hidden = $this->open($this->server('CONCEALED'), ['toolsListChanged' => true], 'psr&lifetime=15');
        $this->nextFrame($hidden);
        self::assertSame(7, $ackLeft['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        self::assertSame(7, $ackRight['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        $before = $this->db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions ORDER BY registration_id');
        $base = ['scopes' => ['scope', 'CONCEALED'], 'granted_scopes' => ['scope']];
        $inspection = $this->inspectSubscribers([
            $base + ['query' => 'summary'], $base + ['query' => 'page', 'page' => 1], $base + ['query' => 'page', 'page' => 2],
            ['query' => 'summary', 'scopes' => ['scope', 'CONCEALED'], 'granted_scopes' => []],
            $base + ['query' => 'page', 'filters' => ['subscriber' => 'SUPER_ADMIN']]
        ]);
        self::assertNotSame(getmypid(), $inspection['process']); self::assertSame(5, $inspection['admissions']); self::assertFalse($inspection['transaction_active']);
        [$summary, $first, $second, $revoked, $notAuthority] = $inspection['results'];
        self::assertSame(2, $summary['subscriptions']); self::assertSame(1, $summary['subscriber_ids']); self::assertSame(2, $summary['states']['recently_observed']);
        self::assertSame(2, $first['total']); self::assertSame(2, $second['total']);
        self::assertNotSame($first['subscribers'][0]['registration_id'], $second['subscribers'][0]['registration_id']);
        $views = [...$first['subscribers'], ...$second['subscribers']];
        $withResources = array_values(array_filter($views, static fn ($view) => isset($view['requested']['resourceSubscriptions'])))[0];
        self::assertSame(['file:///allowed', 'file:///hidden'], $withResources['requested']['resourceSubscriptions']);
        self::assertSame(['file:///allowed'], $withResources['accepted']['resourceSubscriptions']);
        self::assertTrue($withResources['requested']['promptsListChanged']); self::assertArrayNotHasKey('promptsListChanged', $withResources['accepted']);
        self::assertSame(0, $revoked['subscriptions']); self::assertNull($revoked['latest_observed_at']); self::assertSame(0, $notAuthority['total']);
        self::assertStringNotContainsString('CONCEALED', json_encode($inspection)); self::assertStringNotContainsString('owner', json_encode($inspection));
        $after = $this->db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions ORDER BY registration_id');
        // Heartbeats may advance concurrently; reads must not manufacture closure or change opening/filter identity.
        foreach ($after as $index => $row) { self::assertSame($before[$index]['context'], $row['context']); self::assertSame($before[$index]['opened_at'], $row['opened_at']); self::assertSame(0, (int) $row['closed_at']); }
        stream_socket_shutdown($left, STREAM_SHUT_RDWR); fclose($left);
        $deadline = microtime(true) + 4;
        do { $closed = (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at > 0'); if ($closed > 0) { break; } usleep(10000); } while (microtime(true) < $deadline);
        self::assertSame(1, $closed);
        $lifecycle = $this->inspectSubscribers([$base + ['query' => 'summary']]);
        self::assertSame(1, $lifecycle['results'][0]['states']['disconnected']);
        self::assertSame(1, $lifecycle['results'][0]['states']['recently_observed']);
        $allowedId = $first['subscribers'][0]['registration_id'];
        $restricted = $this->inspectSubscribers([$base + ['query' => 'summary', 'granted_registrations' => [$allowedId]]]);
        self::assertSame(1, $restricted['results'][0]['subscriptions']);
        stream_socket_shutdown($right, STREAM_SHUT_RDWR); fclose($right); stream_socket_shutdown($hidden, STREAM_SHUT_RDWR); fclose($hidden);
        $this->evidence['operator_before'] = $inspection; $this->evidence['operator_after_disconnect'] = $lifecycle; $this->evidence['restricted'] = $restricted;
        $emptySocket = $this->open($this->server('scope'), []); $this->nextFrame($emptySocket);
        $emptyMap = $this->inspectSubscribers([$base + ['query' => 'page', 'per_page' => 16]]);
        // Same-second openings sort by random registration ID, not by insertion order.
        $emptyViews = array_values(array_filter($emptyMap['results'][0]['subscribers'], static fn ($view) => $view['requested'] === []));
        self::assertCount(1, $emptyViews); self::assertSame([], $emptyViews[0]['accepted']);
        $this->evidence['empty_maps'] = $emptyMap;
        stream_socket_shutdown($emptySocket, STREAM_SHUT_RDWR); fclose($emptySocket);
        $this->db->executeStatement('UPDATE fight_mcp_subscription_state SET version = 2');
        $unavailable = $this->inspectSubscribers([$base + ['query' => 'summary'], $base + ['query' => 'page']]);
        foreach ($unavailable['results'] as $result) { self::assertSame('Subscriber inspection unavailable.', $result['message']); self::assertArrayNotHasKey('subscriptions', $result); }
        $this->evidence['unavailable'] = $unavailable;
    }

    #[DataProvider('databases')]
    public function test_that_actual_prompt_serving_and_live_notifications_have_independent_current_authority_and_retained_recovery(string $backend): void
    {
        $this->setupDatabase($backend);
        $leftServer = $this->server('scope'); $rightServer = $this->server('scope-2'); $retrievalServer = $this->server('scope');
        $enabled = 'psr&prompts=1&servePrompts=1&lifetime=4';
        $ordinary = $this->rpc($retrievalServer, 'prompts/list', [], 'servePrompts=1');
        self::assertSame(['review'], array_column($ordinary['result']['prompts'], 'name'));
        $get = $this->rpc($retrievalServer, 'prompts/get', ['name' => 'review', 'arguments' => ['note' => 'PRIVATE ARGUMENT']], 'servePrompts=1');
        self::assertSame('Safe review', $get['result']['messages'][0]['content']['text']);
        $discovery = $this->rpc($retrievalServer, 'server/discover', [], 'servePrompts=1&prompts=1');
        self::assertTrue($discovery['result']['capabilities']['prompts']['listChanged']);
        $disabled = $this->rpc($retrievalServer, 'server/discover', [], 'servePrompts=1');
        self::assertFalse($disabled['result']['capabilities']['prompts']['listChanged']);
        $absent = $this->rpc($retrievalServer, 'server/discover', []);
        self::assertArrayNotHasKey('prompts', $absent['result']['capabilities']);
        $left = $this->open($leftServer, ['promptsListChanged' => true, 'toolsListChanged' => true, 'resourcesListChanged' => true], $enabled, '7');
        $right = $this->open($rightServer, ['promptsListChanged' => true], $enabled);
        $ackLeft = $this->nextFrame($left); $ackRight = $this->nextFrame($right);
        self::assertSame(['promptsListChanged' => true, 'toolsListChanged' => true, 'resourcesListChanged' => true], $ackLeft['params']['notifications']);
        self::assertSame(['promptsListChanged' => true], $ackRight['params']['notifications']);
        $this->worker(['operation' => 'record', 'notices' => [
            ['id' => 'hidden-prompt', 'type' => 'mcp.prompts.list_changed', 'metadata' => ['app/concealed' => true, 'app/private' => 'PRIVATE DEFINITION']],
            ['id' => 'prompt-a', 'type' => 'mcp.prompts.list_changed', 'metadata' => ['app/private' => 'PRIVATE BODY ARGUMENT']],
            ['id' => 'tool-a', 'type' => 'mcp.tools.list_changed'],
            ['id' => 'resource-a', 'type' => 'mcp.resources.list_changed'],
            ['id' => 'prompt-b', 'scope' => 'scope-2', 'type' => 'mcp.prompts.list_changed']
        ]]);
        $updates = [$this->nextFrame($left), $this->nextFrame($left), $this->nextFrame($left), $this->nextFrame($right)];
        self::assertSame(['notifications/prompts/list_changed', 'notifications/tools/list_changed', 'notifications/resources/list_changed', 'notifications/prompts/list_changed'], array_column($updates, 'method'));
        foreach (array_slice($updates, 0, 3) as $update) { self::assertSame('7', $update['params']['_meta']['io.modelcontextprotocol/subscriptionId']); }
        self::assertSame(7, $updates[3]['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        foreach ([$updates[0], $updates[3]] as $update) { self::assertSame(['_meta'], array_keys($update['params'])); }
        touch($this->directory.'/hide-prompts-scope');
        self::assertSame([], $this->rpc($retrievalServer, 'prompts/list', [], 'servePrompts=1')['result']['prompts']);
        self::assertSame(-32602, $this->rpc($retrievalServer, 'prompts/get', ['name' => 'review'], 'servePrompts=1')['error']['code']);
        touch($this->directory.'/deny-prompt-signals-scope');
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'revoked-prompt', 'type' => 'mcp.prompts.list_changed']]]);
        $tailLeft = $this->drain($left); $tailRight = $this->drain($right);
        self::assertCount(1, $tailLeft); self::assertCount(1, $tailRight); self::assertSame('complete', $tailLeft[0]['result']['resultType']);
        $runtime = json_decode(file_get_contents($this->directory.'/closed-scope.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $runtime['prompt_calls']); self::assertSame([], $runtime['hidden_prompt_calls']); self::assertSame(1, $runtime['prompt_metadata_calls']);
        self::assertSame([], $runtime['diagnostics']);
        $rows = $this->db->fetchAllAssociative('SELECT context FROM fight_mcp_subscriptions');
        self::assertCount(2, $rows);
        foreach ($rows as $row) { $context = json_decode($row['context'], true, flags: JSON_THROW_ON_ERROR); self::assertTrue($context['requested']['promptsListChanged']); self::assertTrue($context['accepted']['promptsListChanged']); }
        // Generic inspection uses the existing routed Queries, with no Prompt-specific presentation path.
        $inspection = $this->inspectSubscribers([['query' => 'page', 'scopes' => ['scope'], 'granted_scopes' => ['scope']]]);
        self::assertTrue($inspection['results'][0]['subscribers'][0]['accepted']['promptsListChanged']);
        unlink($this->directory.'/deny-prompt-signals-scope'); unlink($this->directory.'/hide-prompts-scope');
        $failed = $this->open($leftServer, ['promptsListChanged' => true], $enabled);
        $this->nextFrame($failed); touch($this->directory.'/source-failure');
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'retained-prompt', 'type' => 'mcp.prompts.list_changed']]]);
        self::assertSame([], $this->drain($failed)); unlink($this->directory.'/source-failure');
        $recovered = (new NoticeRecoveryFixture($this->db))->call(['scopes' => ['scope']])['body']['result']['structuredContent'];
        self::assertContains('retained-prompt', array_column($recovered['notices'], 'id')); self::assertSame('complete', $recovered['status']);
        // Requested but disabled, absent and enabled-but-unrequested remain distinct truthful compositions.
        foreach (['psr&servePrompts=1', 'psr', $enabled] as $composition) {
            $filter = $composition === $enabled ? ['toolsListChanged' => true] : ['promptsListChanged' => true];
            $socket = $this->open($leftServer, $filter, $composition);
            $ack = $this->nextFrame($socket); self::assertArrayNotHasKey('promptsListChanged', $ack['params']['notifications']);
            stream_socket_shutdown($socket, STREAM_SHUT_RDWR); fclose($socket);
            usleep(100000);
        }
        $this->evidence['prompt_wire'] = compact('ackLeft', 'ackRight', 'updates', 'tailLeft', 'tailRight');
        self::assertStringNotContainsString('PRIVATE', json_encode($this->evidence['prompt_wire']));
        $this->evidence['prompt_serving'] = compact('ordinary', 'get', 'discovery', 'disabled', 'absent');
        $this->evidence['prompt_runtime'] = $runtime; $this->evidence['prompt_recovery'] = $recovered; $this->evidence['prompt_tracking'] = $inspection;
    }

    #[DataProvider('compositions')]
    public function test_that_supported_native_compositions_emit_before_close_and_idle_disconnect_is_observed(string $composition): void
    {
        $this->setupDatabase('sqlite'); $server = $this->server('scope');
        $socket = $this->open($server, ['toolsListChanged' => true], $composition);
        $start = microtime(true); $ack = $this->nextFrame($socket);
        self::assertSame('notifications/subscriptions/acknowledged', $ack['method']);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'native', 'type' => 'mcp.tools.list_changed']]]);
        self::assertSame('notifications/tools/list_changed', $this->nextFrame($socket)['method']);
        self::assertLessThan(2.0, microtime(true) - $start);
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR); fclose($socket);
        $deadline = microtime(true) + 4;
        do { $closed = (int) $this->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'); if ($closed !== 0) { break; } usleep(10000); } while (microtime(true) < $deadline);
        self::assertGreaterThan(0, $closed);
        $this->evidence['composition'] = $composition;
        $this->evidence['disconnect_observed_seconds'] = microtime(true) - $start;
        $socket = $this->open($server, [], $composition.'&buffered=1');
        self::assertSame([], $this->drain($socket));
        $this->evidence['runtime'] = json_decode(file_get_contents($this->directory.'/closed-scope.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('cli-server', $this->evidence['runtime']['sapi']);
        self::assertSame('0', $this->evidence['runtime']['buffering']);
        self::assertSame('0', $this->evidence['runtime']['compression']);
    }

    #[DataProvider('databases')]
    public function test_that_abrupt_death_becomes_stale_and_cross_process_owner_updates_cannot_revive_closed_or_expired_rows(string $backend): void
    {
        $this->setupDatabase($backend); $server = $this->server('scope');
        $socket = $this->open($server, ['toolsListChanged' => true], 'psr&abrupt=1');
        self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($socket)['method']);
        self::assertSame([], $this->drain($socket));
        $row = $this->db->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
        self::assertSame(0, (int) $row['closed_at']);
        $this->worker(['operation' => 'close', 'arguments' => [$row['registration_id'], str_repeat('f', 32), time()]]);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        $deadline = microtime(true) + 7;
        while (time() < (int) $row['expires_at'] && microtime(true) < $deadline) { usleep(50000); }
        self::assertLessThanOrEqual(time(), (int) $row['expires_at']);
        $exit = $this->worker(['operation' => 'heartbeat', 'arguments' => [$row['registration_id'], $row['owner'], time(), time() + 5]], false);
        self::assertNotSame(0, $exit);
        self::assertSame($row['expires_at'], $this->db->fetchOne('SELECT expires_at FROM fight_mcp_subscriptions'));
        $this->evidence['abandoned'] = $row;
        $operation = ['query' => 'summary', 'scopes' => ['scope'], 'granted_scopes' => ['scope']];
        $unchanged = $this->db->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
        $stale = $this->inspectSubscribers([$operation]);
        self::assertSame(1, $stale['results'][0]['states']['stale_uncertain']);
        self::assertSame(0, $stale['results'][0]['states']['disconnected']);
        self::assertSame($unchanged, $this->db->fetchAssociative('SELECT * FROM fight_mcp_subscriptions'), 'Inspection neither renews nor cleans an abandoned row');
        self::assertSame(1, (new DbalSubscriptionStore($this->db))->cleanup(time(), 1));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        $cleaned = $this->inspectSubscribers([$operation]); self::assertSame(0, $cleaned['results'][0]['subscriptions']); self::assertNull($cleaned['results'][0]['latest_observed_at']);
        $this->evidence['operator_stale'] = $stale; $this->evidence['operator_cleaned'] = $cleaned;
    }

    #[DataProvider('databases')]
    public function test_that_concurrent_removal_and_over_batch_backlog_end_honestly_and_new_listen_is_not_replay(string $backend): void
    {
        $this->setupDatabase($backend); $server = $this->server('scope');
        foreach (['removal', 'overload'] as $mode) {
            $socket = $this->open($server, ['toolsListChanged' => true]);
            self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($socket)['method']);
            touch($this->directory.'/pause-source');
            $deadline = microtime(true) + 1;
            while (!file_exists($this->directory.'/source-paused') && microtime(true) < $deadline) { usleep(1000); clearstatcache(); }
            self::assertFileExists($this->directory.'/source-paused');
            $notices = [];
            for ($i = 0; $i < ($mode === 'removal' ? 1 : 33); ++$i) { $notices[] = ['id' => $mode.$i, 'type' => 'mcp.tools.list_changed']; }
            $this->worker(['operation' => 'record', 'notices' => $notices]);
            if ($mode === 'removal') {
                $position = (int) $this->db->fetchOne('SELECT position FROM fight_notices WHERE notice_id = ?', ['removal0']);
                $remover = new NoticeRemovalFixture($this->db);
                $remover->remove($remover->selection([NoticeRemovalFixture::target($position, 'removal0')]));
            }
            unlink($this->directory.'/pause-source'); unlink($this->directory.'/source-paused');
            self::assertSame([], $this->drain($socket), 'No healthy completion or truncated live success after '.$mode);
        }
        $poll = new NoticeRecoveryFixture($this->db);
        $first = $poll->call(['scopes' => ['scope']])['body']['result']['structuredContent'];
        self::assertCount(32, $first['notices']); self::assertSame('incomplete', $first['status']);
        $second = $poll->call(['scopes' => ['scope'], 'continuation' => $first['continuation']])['body']['result']['structuredContent'];
        self::assertCount(1, $second['notices']); self::assertSame('incomplete', $second['status'], 'Earlier history loss remains qualified');
        $socket = $this->open($server, ['toolsListChanged' => true]);
        self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($socket)['method']);
        $tail = $this->drain($socket); self::assertCount(1, $tail); self::assertSame('complete', $tail[0]['result']['resultType']);
        $this->evidence['recovery_after_loss_and_overload'] = [$first, $second];
    }

    #[DataProvider('contentionCases')]
    public function test_that_real_capacity_lock_waits_preserve_freshness_and_retained_recovery(string $backend, string $mode, string $transport): void
    {
        $this->setupDatabase($backend);
        $process = new Process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'zlib.output_compression=0', 'tests/Fixture/Mcp/subscription-contention.php', $this->directory, $mode, $transport], dirname(__DIR__, 2));
        $this->processes[] = $process;
        self::assertSame(0, $process->run(), $process->getErrorOutput());
        $result = json_decode(file_get_contents($this->directory.'/contention-result.json'), true, flags: JSON_THROW_ON_ERROR);
        $wire = $process->getOutput();
        $this->evidence['contention'] = compact('mode', 'transport', 'wire', 'result');
        self::assertSame(0, $result['worker_exit'], $result['worker_error']);
        self::assertFalse($result['transaction_active']);
        self::assertSame(1, $result['retained']);
        self::assertSame(['status' => 'complete', 'ids' => ['retained']], $result['recovery']);
        preg_match_all('/^data: (.+)$/m', $wire, $matches);
        $frames = array_map(static fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
        self::assertSame('notifications/subscriptions/acknowledged', $frames[0]['method']);
        foreach ($frames as $frame) { self::assertSame('7', $frame['params']['_meta']['io.modelcontextprotocol/subscriptionId']); }
        self::assertSame((int) $result['original']['expires_at'], (int) $result['locked']['row']['expires_at']);
        if ($mode === 'timeout') {
            self::assertGreaterThan($result['started'] + 0.9, $result['returned']);
            self::assertLessThan($result['locked']['at'], $result['returned'], 'SQL timeout returns before the independent lock holder releases');
        } else { self::assertGreaterThan($result['started'] + 2, $result['returned'], 'The independent process genuinely delayed renewal'); }
        if ($mode === 'fast') {
            self::assertLessThan((int) $result['original']['expires_at'], $result['locked']['at']);
            self::assertSame(['notifications/subscriptions/acknowledged', 'notifications/tools/list_changed'], array_column($frames, 'method'));
        } else {
            self::assertGreaterThanOrEqual((int) $result['original']['expires_at'], $result['locked']['at']);
            if ($mode !== 'overlong') { self::assertLessThan((int) $result['started'] + 5, $result['returned'], 'The proposed lease remains future'); }
            else { self::assertGreaterThanOrEqual((int) $result['started'] + 5, $result['returned']); }
            self::assertCount(1, $frames, 'No new change or completion after the acknowledgment');
            self::assertStringNotContainsString('resultType', $wire);
            self::assertStringNotContainsString('keepalive', $wire);
            self::assertSame((int) $result['original']['observed_at'], (int) $result['row']['observed_at']);
            self::assertSame((int) $result['original']['expires_at'], (int) $result['row']['expires_at'], 'No revival before suppressing output');
            self::assertSame($result['position_before'], $result['position_after']);
            self::assertTrue($result['eof']);
            self::assertNotEmpty($result['diagnostics']);
        }
    }

    #[DataProvider('databases')]
    public function test_that_same_second_noops_and_lost_ownership_preserve_dedicated_transaction_contracts(string $backend): void
    {
        $this->setupDatabase($backend);
        $store = new DbalSubscriptionStore($this->db);
        $id = str_repeat('1', 32); $owner = str_repeat('2', 32);
        $context = ['subscriber' => 'caller', 'selection' => [], 'requested' => [], 'accepted' => []];
        $store->open($id, $owner, $context, 100, 105);
        $store->heartbeat($id, $owner, 101, 106);
        $store->heartbeat($id, $owner, 101, 106);
        self::assertSame(101, (int) $this->db->fetchOne('SELECT observed_at FROM fight_mcp_subscriptions'));
        foreach (['foreign', 'expired', 'closed', 'cleaned'] as $condition) {
            if ($condition === 'closed') { $store->close($id, $owner, 102); }
            if ($condition === 'cleaned') { self::assertSame(1, $store->cleanup(102, 1)); }
            try {
                (new DbalSubscriptionStore($this->db))->heartbeat($id, $condition === 'foreign' ? str_repeat('3', 32) : $owner, $condition === 'expired' ? 106 : 102, $condition === 'expired' ? 111 : 107);
                self::fail('Lost ownership must reject: '.$condition);
            } catch (RuntimeException) { self::assertFalse($this->db->isTransactionActive()); }
        }
        $this->db->beginTransaction();
        $this->db->insert('fight_mcp_subscriptions', ['registration_id' => $id, 'owner' => $owner, 'context' => '{}', 'opened_at' => 100, 'observed_at' => 100, 'expires_at' => 105, 'closed_at' => 0]);
        try { (new DbalSubscriptionStore($this->db))->heartbeat($id, $owner, 101, 106); self::fail('Caller work'); }
        catch (LogicException) { self::assertTrue($this->db->isTransactionActive()); }
        $this->db->rollBack();
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
    }

    public static function contentionCases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach (['fast', 'expired', 'overlong', 'timeout'] as $mode) {
                foreach (['body', 'emitter'] as $transport) { yield $backend.'-'.$mode.'-'.$transport => [$backend, $mode, $transport]; }
            }
        }
    }

    public static function databases(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; } }
    public static function compositions(): iterable { foreach (['psr', 'symfony', 'laravel', 'yii', 'slim', 'codeigniter'] as $composition) { yield $composition => [$composition]; } }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) { $this->evidence['servers'][] = ['pid' => $process->getPid(), 'command' => $process->getCommandLine(), 'output' => $process->getErrorOutput()]; $process->stop(1); }
        $this->processes = [];
        if (isset($this->db)) {
            $manager = $this->db->createSchemaManager();
            foreach ([...array_reverse(DbalSubscriptionSchema::TABLES), ...array_reverse(DbalNoticeSchema::TABLES)] as $table) { if (in_array($table, $manager->listTableNames(), true)) { $manager->dropTable($table); } }
            $this->db->close();
        }
        if (isset($this->directory)) {
            $destination = getenv('FIGHT_MCP_SUBSCRIPTION_EVIDENCE_DIR');
            if ($destination !== false) { file_put_contents($destination.'/'.str_replace([' ', '"', '/'], '-', $this->nameWithDataSet()).'.json', json_encode($this->evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); }
            foreach (glob($this->directory.'/*') as $file) { if (is_file($file)) { unlink($file); } }
            rmdir($this->directory);
        }
        $this->evidence = [];
        parent::tearDown();
    }

    private function setupDatabase(string $backend): void
    {
        $this->directory = sys_get_temp_dir().'/fight-mcp-subscription-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($this->directory, 0700));
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory.'/db.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable database required'); $params = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($dsn); }
        file_put_contents($this->directory.'/connection.json', json_encode($params, JSON_THROW_ON_ERROR));
        $this->db = DriverManager::getConnection($params);
        if ($backend === 'sqlite') { $this->db->executeStatement('PRAGMA busy_timeout = 3000'); }
        // These DSNs are package-owned disposable fixtures; prior focused suites may leave their namespace behind.
        $manager = $this->db->createSchemaManager();
        foreach ([...array_reverse(DbalSubscriptionSchema::TABLES), ...array_reverse(DbalNoticeSchema::TABLES)] as $table) {
            if (in_array($table, $manager->listTableNames(), true)) { $manager->dropTable($table); }
        }
        (new DbalNoticeSchema())->install($this->db); (new DbalSubscriptionSchema())->install($this->db);
        $this->evidence['backend'] = $backend;
    }

    private function server(string $scope): string
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); self::assertIsResource($reservation, $error);
        $address = stream_socket_get_name($reservation, false); fclose($reservation);
        $process = new Process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'zlib.output_compression=0', '-d', 'display_errors=0', '-S', $address, 'tests/Fixture/Mcp/subscription-server.php'], dirname(__DIR__, 2), ['MCP_SUBSCRIPTION_DIRECTORY' => $this->directory, 'MCP_SUBSCRIPTION_SCOPE' => $scope]);
        $process->start(); $this->processes[] = $process;
        for ($i = 0; $i < 300; ++$i) { $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05); if (is_resource($probe)) { fclose($probe); return $address; } usleep(10000); }
        self::fail('Server did not start: '.$process->getErrorOutput());
    }

    private function open(string $address, array $filter, string $composition = 'psr', int|string $id = 7)
    {
        $socket = stream_socket_client('tcp://'.$address, $errno, $error, 5); self::assertIsResource($socket, $error); stream_set_timeout($socket, 5);
        $request = SubscriptionFixture::request($filter, $id); $body = (string) $request->getBody();
        $wire = "POST /mcp?composition=$composition HTTP/1.1\r\nHost: $address\r\nConnection: close\r\nContent-Length: ".strlen($body)."\r\n";
        foreach ($request->getHeaders() as $name => $values) { foreach ($values as $value) { $wire .= "$name: $value\r\n"; } }
        fwrite($socket, $wire."\r\n".$body); $headers = '';
        while (($line = fgets($socket)) !== false && $line !== "\r\n") { $headers .= $line; }
        $this->evidence['headers'][] = $headers;
        self::assertStringContainsString(str_contains($composition, 'buffered') ? '500' : '200 OK', $headers);
        if (!str_contains($composition, 'buffered')) { self::assertStringContainsString('text/event-stream', $headers); }
        return $socket;
    }

    private function rpc(string $address, string $method, array $params, string $composition = ''): array
    {
        $socket = stream_socket_client('tcp://'.$address, $errno, $error, 5); self::assertIsResource($socket, $error); stream_set_timeout($socket, 5);
        $body = json_encode(['jsonrpc' => '2.0', 'id' => 9, 'method' => $method, 'params' => (object) ($params + ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]])], JSON_THROW_ON_ERROR);
        $wire = "POST /mcp?$composition HTTP/1.1\r\nHost: $address\r\nConnection: close\r\nOrigin: https://client.test\r\nContent-Type: application/json\r\nAccept: application/json, text/event-stream\r\nMCP-Protocol-Version: 2026-07-28\r\nMcp-Method: $method\r\nContent-Length: ".strlen($body)."\r\n";
        if (isset($params['name'])) { $wire .= 'Mcp-Name: '.$params['name']."\r\n"; }
        fwrite($socket, $wire."\r\n".$body);
        while (($line = fgets($socket)) !== false && $line !== "\r\n") {}
        $result = stream_get_contents($socket); self::assertFalse(stream_get_meta_data($socket)['timed_out']); fclose($socket);
        return json_decode($result, true, flags: JSON_THROW_ON_ERROR);
    }

    private function nextFrame($socket): array
    {
        while (($line = fgets($socket)) !== false) {
            if (str_starts_with($line, 'data: ')) { $this->evidence['frames'][] = ['time' => microtime(true), 'wire' => $line]; return json_decode(substr($line, 6), true, flags: JSON_THROW_ON_ERROR); }
        }
        self::fail('Stream ended or timed out before expected frame');
    }

    private function drain($socket): array
    {
        $frames = [];
        while (($line = fgets($socket)) !== false) { if (str_starts_with($line, 'data: ')) { $frames[] = json_decode(substr($line, 6), true, flags: JSON_THROW_ON_ERROR); } }
        self::assertFalse(stream_get_meta_data($socket)['timed_out']); fclose($socket);
        return $frames;
    }

    private function inspectSubscribers(array $operations): array
    {
        file_put_contents($this->directory.'/inspection.json', json_encode($operations, JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, 'tests/Fixture/Mcp/subscriber-inspection-worker.php', $this->directory], dirname(__DIR__, 2));
        self::assertSame(0, $process->run(), $process->getErrorOutput());
        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function worker(array $operation, bool $success = true): int
    {
        file_put_contents($this->directory.'/operation.json', json_encode($operation, JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, 'tests/Fixture/Mcp/subscription-worker.php', $this->directory], dirname(__DIR__, 2)); $process->run();
        $this->evidence['workers'][] = ['exit' => $process->getExitCode(), 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        if ($success) { self::assertSame(0, $process->getExitCode(), $process->getErrorOutput()); }
        return $process->getExitCode();
    }
}
