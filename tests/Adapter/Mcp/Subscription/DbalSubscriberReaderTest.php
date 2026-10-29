<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Subscription;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriberReader;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\Exception\SubscriberReadFailed;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberAccess;
use Fight\Common\Domain\Mcp\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalSubscriberReader::class)]
final class DbalSubscriberReaderTest extends UnitTestCase
{
    public function test_that_actual_tracking_rows_are_counted_once_and_live_pages_are_bounded_with_deterministic_ties(): void
    {
        $db = $this->database(); $store = new DbalSubscriptionStore($db);
        for ($i = 1; $i <= 40; ++$i) { $store->open(self::id($i), self::id(99), self::context('agent-'.($i % 2)), 100, 105); }
        $store->open(self::id(41), self::id(99), self::context('CONCEALED', ['concealed']), 100, 105);
        $store->open(self::id(42), self::id(99), self::context('CONCEALED-MIX', ['scope', 'concealed']), 100, 105);
        $store->open(self::id(43), self::id(99), self::context('unknown-scope', []), 100, 105);
        $before = $db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions ORDER BY registration_id');
        $reader = new DbalSubscriberReader($db, 'shared-db', static fn () => 103);
        $selection = SubscriberSelection::create(['scope', 'concealed']); $access = SubscriberAccess::create(['scope']);
        $summary = $reader->summarize($selection, $access)->toArray();
        self::assertSame(40, $summary['subscriptions']); self::assertSame(2, $summary['subscriber_ids']);
        self::assertSame(['recently_observed' => 40, 'disconnected' => 0, 'stale_uncertain' => 0], $summary['states']);
        $page = $reader->list(new ListSubscribers($selection, 2, 16), $access)->toArray();
        self::assertSame(40, $page['total']); self::assertCount(16, $page['subscribers']);
        self::assertSame(array_map(self::id(...), range(24, 9)), array_column($page['subscribers'], 'registration_id'));
        self::assertStringNotContainsString('CONCEALED', json_encode($page));
        $asc = $reader->list(new ListSubscribers($selection, 3, 16, ['opened_at' => 'ASC']), $access)->toArray();
        self::assertSame(array_map(self::id(...), range(33, 40)), array_column($asc['subscribers'], 'registration_id'));
        $restricted = SubscriberAccess::create(['scope'], [self::id(1)]);
        self::assertSame(1, $reader->summarize($selection, $restricted)->toArray()['subscriptions']);
        self::assertSame(0, $reader->list(new ListSubscribers(SubscriberSelection::create(['scope'], ['subscriber' => 'SUPER_ADMIN'])), $access)->toArray()['total']);
        self::assertSame($before, $db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions ORDER BY registration_id'));
        self::assertFalse($db->isTransactionActive());
        $store->close(self::id(1), self::id(99), 103);
        self::assertSame(1, $reader->summarize(SubscriberSelection::create(['scope'], ['state' => 'disconnected']), $access)->toArray()['subscriptions']);
        self::assertSame([], $reader->list(new ListSubscribers($selection, 100, 16), $access)->toArray()['subscribers']);
        self::assertGreaterThan(0, (new DbalSubscriberReader($db, 'shared-db'))->summarize($selection, $access)->toArray()['scope']['queried_at']);
        $store->open(self::id(44), self::id(99), array_replace(self::context(), ['requested' => [], 'accepted' => []]), 100, 105);
        $emptyMaps = $reader->list(new ListSubscribers(SubscriberSelection::create(['scope'], ['registration_id' => self::id(44)])), $access)->subscribers()[0]->toArray();
        self::assertSame('{}', $emptyMaps['requested']->toString()); self::assertSame('{}', $emptyMaps['accepted']->toString());
    }

    public function test_that_denial_and_empty_selection_do_not_probe_concealed_storage_but_admitted_storage_failure_is_not_empty(): void
    {
        $db = $this->database(); $reader = new DbalSubscriberReader($db, 'db', static fn () => 100); $selection = SubscriberSelection::create(['scope']);
        $db->executeStatement('DROP TABLE fight_mcp_subscriptions');
        self::assertSame(0, $reader->summarize($selection, SubscriberAccess::create([]))->toArray()['subscriptions']);
        self::assertSame(0, $reader->summarize(SubscriberSelection::create([]), SubscriberAccess::create(['scope']))->toArray()['subscriptions']);
        $this->failed(fn () => $reader->summarize($selection, SubscriberAccess::create(['scope'])));
        $this->failed(fn () => $reader->list(new ListSubscribers($selection), SubscriberAccess::create(['scope'])));
        $db->beginTransaction();
        $this->failed(fn () => $reader->summarize($selection, SubscriberAccess::create(['scope']))); self::assertTrue($db->isTransactionActive()); $db->rollBack();
        $db->setTransactionIsolation(\Doctrine\DBAL\TransactionIsolationLevel::READ_UNCOMMITTED);
        $this->failed(fn () => $reader->summarize($selection, SubscriberAccess::create(['scope'])));
        $db->setTransactionIsolation(\Doctrine\DBAL\TransactionIsolationLevel::SERIALIZABLE);
        $db->executeStatement('UPDATE fight_mcp_subscription_state SET version = 2');
        $this->failed(fn () => $reader->summarize($selection, SubscriberAccess::create(['scope'])));
        $platform = $this->mock(Connection::class); $platform->shouldReceive('getDatabasePlatform')->andReturn(new OraclePlatform());
        try { new DbalSubscriberReader($platform, 'db'); self::fail('Unsupported backend'); } catch (InvalidArgumentException) { self::assertTrue(true); }
    }

    public function test_that_corrupt_oversized_context_and_unbounded_concurrent_churn_fail_instead_of_partial_statistics(): void
    {
        $db = $this->database(); $store = new DbalSubscriptionStore($db); $selection = SubscriberSelection::create(['scope']); $access = SubscriberAccess::create(['scope']);
        $store->open(self::id(1), self::id(99), self::context(), 100, 105);
        $reader = new DbalSubscriberReader($db, 'db', static fn () => 103);
        foreach (['{bad', str_repeat('x', 65537), '{}', '{"subscriber":"a","selection":false,"requested":{},"accepted":{}}'] as $context) {
            $db->executeStatement('UPDATE fight_mcp_subscriptions SET context = ?', [$context]);
            $this->failed(fn () => $reader->summarize($selection, $access));
        }
        $connection = $this->mock(Connection::class); $connection->shouldReceive('getDatabasePlatform')->andReturn(new SQLitePlatform());
        $connection->shouldReceive('isTransactionActive')->andReturn(false); $connection->shouldReceive('getTransactionIsolation')->andReturn(\Doctrine\DBAL\TransactionIsolationLevel::READ_COMMITTED); $connection->shouldReceive('fetchOne')->andReturn(1);
        $row = ['registration_id' => self::id(1), 'context' => json_encode(self::context()), 'opened_at' => 100, 'observed_at' => 100, 'expires_at' => 105, 'closed_at' => 0];
        $connection->shouldReceive('fetchAllAssociative')->times(257)->andReturn(array_fill(0, 32, $row));
        $this->failed(fn () => (new DbalSubscriberReader($connection, 'db', static fn () => 103))->summarize($selection, $access));
    }

    public static function id(int $value): string { return str_pad(dechex($value), 32, '0', STR_PAD_LEFT); }
    public static function context(string $subscriber = 'agent', array $scopes = ['scope']): array { return ['subscriber' => $subscriber, 'selection' => ['scopes' => $scopes, 'filters' => ['topic' => 'topic']], 'requested' => ['toolsListChanged' => true, 'promptsListChanged' => false, 'resourceSubscriptions' => ['file:///allowed', 'file:///denied']], 'accepted' => ['toolsListChanged' => true, 'resourceSubscriptions' => ['file:///allowed']]]; }
    private function database(): Connection { $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalSubscriptionSchema())->install($db); return $db; }
    private function failed(callable $operation): void { try { $operation(); self::fail('No empty or partial success'); } catch (SubscriberReadFailed $error) { self::assertSame('Subscriber inspection unavailable.', $error->getMessage()); self::assertNotNull($error->getPrevious()); } }
}
