<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Subscription;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionStore;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Throwable;

#[CoversClass(DbalSubscriptionStore::class)]
#[CoversClass(DbalSubscriptionSchema::class)]
final class DbalSubscriptionStoreTest extends UnitTestCase
{
    private const string ID = '11111111111111111111111111111111';
    private const string OWNER = '22222222222222222222222222222222';

    public function test_that_real_owned_registration_freshness_closure_and_cleanup_never_resurrect_or_touch_other_owners(): void
    {
        $db = $this->database(); $store = new DbalSubscriptionStore($db);
        $store->open(self::ID, self::OWNER, self::context(), 100, 105);
        $store->heartbeat(self::ID, self::OWNER, 101, 106);
        $store->heartbeat(self::ID, self::OWNER, 101, 106);
        $store->close(self::ID, str_repeat('3', 32), 102);
        self::assertSame(0, (int) $db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        $store->close(self::ID, self::OWNER, 102);
        $store->close(self::ID, self::OWNER, 103);
        self::assertSame(102, (int) $db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        try { $store->heartbeat(self::ID, self::OWNER, 103, 108); self::fail('Closed'); } catch (RuntimeException) { self::assertTrue(true); }
        self::assertSame(1, (new DbalSubscriptionStore($db))->cleanup(103, 1));
        self::assertSame(0, (new DbalSubscriptionStore($db))->cleanup(103, 1));
        $store = new DbalSubscriptionStore($db);
        $store->open(self::ID, self::OWNER, self::context(), 104, 109);
        try { $store->heartbeat(self::ID, self::OWNER, 109, 114); self::fail('Expired'); } catch (RuntimeException) { self::assertTrue(true); }
        self::assertSame(1, (new DbalSubscriptionStore($db))->cleanup(109, 256));
        self::assertFalse($db->isTransactionActive());
    }

    public function test_that_capacity_clock_owner_and_context_bounds_reject_without_unbounded_rows_or_caller_transaction_effects(): void
    {
        $db = $this->database();
        foreach ([0, 257] as $limit) { try { new DbalSubscriptionStore($db, $limit); self::fail('Capacity'); } catch (LogicException) { self::assertTrue(true); } }
        foreach ([['id' => 'bad'], ['owner' => 'BAD'], ['now' => 0], ['now' => 2147483648], ['expires' => 100], ['expires' => 106], ['now' => 2147483646, 'expires' => 2147483648]] as $change) {
            $args = array_replace(['id' => self::ID, 'owner' => self::OWNER, 'context' => self::context(), 'now' => 100, 'expires' => 105], $change);
            try { (new DbalSubscriptionStore($db))->open(...$args); self::fail('Bounds'); } catch (LogicException) { self::assertTrue(true); }
        }
        foreach ([[0, 1], [2147483648, 1], [100, 0], [100, 257]] as $args) { try { (new DbalSubscriptionStore($db))->cleanup(...$args); self::fail('Cleanup'); } catch (LogicException) { self::assertTrue(true); } }
        try { (new DbalSubscriptionStore($db))->open(self::ID, self::OWNER, array_replace(self::context(), ['requested' => ['huge' => str_repeat('x', 65536)]]), 100, 105); self::fail('Context'); } catch (RuntimeException) { self::assertTrue(true); }
        $store = new DbalSubscriptionStore($db, 1); $store->open(self::ID, self::OWNER, self::context(), 100, 105);
        try { $store->open(str_repeat('3', 32), self::OWNER, self::context(), 100, 105); self::fail('Capacity'); } catch (RuntimeException) { self::assertTrue(true); }
        try { $store->cleanup(100, 1); self::fail('Failed store'); } catch (LogicException) { self::assertTrue(true); }
        $db->beginTransaction();
        try { (new DbalSubscriptionStore($db))->cleanup(100, 1); self::fail('Caller transaction'); } catch (LogicException) { self::assertTrue($db->isTransactionActive()); }
        $db->rollBack(); self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
        $db->executeStatement('UPDATE fight_mcp_subscription_state SET version = 2');
        try { (new DbalSubscriptionStore($db))->cleanup(100, 1); self::fail('Version'); } catch (RuntimeException) { self::assertTrue(true); }
    }

    public function test_that_total_capacity_is_bounded_even_when_abandoned_rows_are_no_longer_fresh(): void
    {
        $db = $this->database(); $store = new DbalSubscriptionStore($db);
        $db->beginTransaction();
        for ($i = 0; $i < 4096; ++$i) { $db->insert('fight_mcp_subscriptions', ['registration_id' => str_pad(dechex($i), 32, '0', STR_PAD_LEFT), 'owner' => self::OWNER, 'context' => '{}', 'opened_at' => 1, 'observed_at' => 1, 'expires_at' => 2, 'closed_at' => 0]); }
        $db->commit();
        try { $store->open(self::ID, self::OWNER, self::context(), 100, 105); self::fail('Total capacity'); } catch (RuntimeException) { self::assertTrue(true); }
        self::assertSame(256, (new DbalSubscriptionStore($db))->cleanup(100, 256));
        (new DbalSubscriptionStore($db))->open(self::ID, self::OWNER, self::context(), 100, 105);
        self::assertSame(3841, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
    }

    public function test_that_store_faults_are_terminal_and_noop_mysql_updates_verify_real_ownership(): void
    {
        foreach (['begin', 'commit', 'rollback', 'noop'] as $failure) {
            $db = $this->mock(Connection::class);
            $db->shouldReceive('getDatabasePlatform')->andReturn(new SQLitePlatform());
            $db->shouldReceive('isTransactionActive')->andReturn(false, $failure !== 'begin');
            $db->shouldReceive('beginTransaction')->andReturnUsing(static function () use ($failure): void { if ($failure === 'begin') { throw new RuntimeException('begin'); } });
            $db->shouldReceive('executeStatement')->andReturn(0);
            $db->shouldReceive('fetchOne')->andReturn(1, $failure === 'noop' ? self::ID : false);
            $db->shouldReceive('commit')->andReturnUsing(static function () use ($failure): void { if ($failure !== 'noop') { throw new RuntimeException('commit'); } });
            $db->shouldReceive('rollBack')->andReturnUsing(static function () use ($failure): void { if ($failure === 'rollback') { throw new RuntimeException('rollback'); } });
            $store = new DbalSubscriptionStore($db);
            try { $store->heartbeat(self::ID, self::OWNER, 100, 105); self::assertSame('noop', $failure); } catch (RuntimeException) { self::assertNotSame('noop', $failure); }
        }
    }

    public function test_that_schema_installation_is_idle_versioned_complete_and_public_api_mysql_compatible(): void
    {
        $db = $this->database(); $schema = new DbalSubscriptionSchema(); $schema->install($db);
        $db->beginTransaction();
        try { $schema->install($db); self::fail('Idle'); } catch (RuntimeException) { self::assertTrue($db->isTransactionActive()); }
        $db->rollBack();
        $db->executeStatement('UPDATE fight_mcp_subscription_state SET version = 2');
        try { $schema->install($db); self::fail('Version'); } catch (RuntimeException) { self::assertTrue(true); }
        $db->executeStatement('DROP TABLE fight_mcp_subscriptions');
        try { $schema->install($db); self::fail('Partial'); } catch (RuntimeException) { self::assertTrue(true); }
        $manager = $this->database()->createSchemaManager(); $model = $manager->introspectSchema();
        foreach (DbalSubscriptionSchema::TABLES as $table) { $model->dropTable($table); }
        $schema->addTo($model, new MySQL84Platform());
        self::assertSame('InnoDB', $model->getTable('fight_mcp_subscriptions')->getOption('engine'));
    }

    public static function context(): array { return ['subscriber' => 'caller', 'selection' => ['scopes' => ['scope'], 'filters' => []], 'requested' => ['toolsListChanged' => true], 'accepted' => ['toolsListChanged' => true]]; }
    private function database(): Connection { $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalSubscriptionSchema())->install($db); return $db; }
}
