<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Socket\MercureHubPublisher;
use Fight\Common\Adapter\Socket\PrivateMercureHubPublisher;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Application\Notice\Exception\CommittedNoticeDispatchFailed;
use Fight\Common\Application\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Notice\Publication\NoticePublication;
use Fight\Common\Application\Notice\Publication\NoticePublicationDiagnostics;
use Fight\Common\Application\Notice\Publication\NoticePublicationGrant;
use Fight\Common\Application\Notice\Publication\NoticePublicationPolicy;
use Fight\Common\Application\Notice\Publication\NoticePublicationStatus;
use Fight\Common\Application\Notice\Publication\RecordNoticeAndPublish;
use Fight\Common\Application\Notice\Service\NoticeAccess;
use Fight\Common\Domain\Notice\Command\RecordNotice;
use Fight\Common\Domain\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\GetNotice;
use Fight\Common\Domain\Notice\Query\ListNotices;
use Fight\Common\Domain\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Notice\NoticeInspectionFixture;
use Fight\Test\Common\TestCase\Notice\NoticeRecoveryFixture;
use Fight\Test\Common\TestCase\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

#[CoversNothing]
final class NoticePublicationJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_actual_commits_current_policy_and_real_mercure_adapters_preserve_safe_relationships_and_recovery(string $backend): void
    {
        $db = $this->database($backend);
        $writer = new NoticeFixture($db);
        $other = $this->connect($db->getParams());
        $inspection = new NoticeInspectionFixture($other);
        $recovery = new NoticeRecoveryFixture($other);
        $grant = NoticePublicationGrant::create('https://feed.test/private', true, NoticePublicationGrant::FIELDS, ['app/label']);
        $policyCalls = 0;
        $policy = $this->mock(NoticePublicationPolicy::class);
        $policy->shouldReceive('currentGrant')->times(5)->andReturnUsing(
            static function (NoticeView $view) use ($db, $other, &$grant, &$policyCalls): ?NoticePublicationGrant {
                ++$policyCalls;
                self::assertFalse($db->isTransactionActive());
                self::assertFalse($other->isTransactionActive());
                self::assertGreaterThan(0, (int) $other->fetchOne('SELECT COUNT(*) FROM fight_notices'));
                self::assertSame('scope', $view->notice()->scope());
                return $grant;
            }
        );
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->andReturnUsing(static function (Throwable $failure): void {
            self::assertStringContainsString('PRIVATE hub credentials', $failure->getMessage());
            throw new RuntimeException('PRIVATE diagnostic failure');
        });
        $updates = [];
        $hub = $this->mock(HubInterface::class);
        $hub->shouldReceive('publish')->times(4)->andReturnUsing(static function (Update $update) use (&$updates, $db): string {
            self::assertFalse($db->isTransactionActive());
            $updates[] = $update;
            if (count($updates) === 2) {
                throw new RuntimeException('PRIVATE hub credentials');
            }
            return 'controlled-hub-id';
        });
        $operation = new NoticePublication($inspection->bus, $policy, $diagnostics, new MercureHubPublisher($hub), new PrivateMercureHubPublisher($hub));
        $composition = new RecordNoticeAndPublish($writer->bus, $operation);
        $command = RecordNotice::fromArray(NoticeFixture::data(['metadata' => ['app/label' => '雪"\\/', 'app/hidden' => 'NOT-FOR-BROADCAST']]));

        self::assertSame(NoticePublicationStatus::PUBLISHED, $composition->record($command));
        $saved = $inspection->bus->fetch(new GetNotice('scope', 'producer', 'notice-1'));
        self::assertSame(NoticePublicationStatus::FAILED, $composition->record($command));
        self::assertSame($saved->toArray(), $inspection->bus->fetch(new GetNotice('scope', 'producer', 'notice-1'))->toArray());
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        $grant = null;
        self::assertSame(NoticePublicationStatus::DENIED, $composition->record($command));
        self::assertSame(3, $policyCalls);
        self::assertCount(2, $updates);

        $grant = NoticePublicationGrant::create('https://feed.test/public', false, ['subject', 'recipients', 'author', 'command', 'event', 'action', 'occurred_at']);
        $unknown = ['subject' => null, 'recipients' => null, 'author' => null, 'command' => null, 'event' => null, 'action' => null];
        self::assertSame(NoticePublicationStatus::PUBLISHED, $composition->record(RecordNotice::fromArray(NoticeFixture::data(['id' => 'unknown', 'relationships' => $unknown, 'occurred_at' => null]))));
        $broadcast = array_replace($unknown, ['recipients' => []]);
        self::assertSame(NoticePublicationStatus::PUBLISHED, $composition->record(RecordNotice::fromArray(NoticeFixture::data(['id' => 'broadcast', 'relationships' => $broadcast]))));
        $writer->record(['scope' => 'scope-2', 'metadata' => ['app/label' => 'CONCEALED-SENTINEL']]);
        self::assertSame(NoticePublicationStatus::NOT_AVAILABLE, $operation->publish(new GetNotice('scope-2', 'producer', 'notice-1')));
        self::assertSame(5, $policyCalls);
        self::assertCount(4, $updates);
        foreach ($updates as $index => $update) {
            self::assertSame([$index < 2 ? 'https://feed.test/private' : 'https://feed.test/public'], $update->getTopics());
            self::assertSame($index < 2, $update->isPrivate());
            self::assertLessThanOrEqual(4096, strlen($update->getData()));
            self::assertStringNotContainsString('CONCEALED-SENTINEL', $update->getData());
            self::assertStringNotContainsString('NOT-FOR-BROADCAST', $update->getData());
            self::assertStringNotContainsString('PRIVATE', $update->getData());
        }
        $first = json_decode($updates[0]->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($command->notice()->relationships()->toArray(), $first['relationships']);
        self::assertSame(['app/label' => '雪"\\/'], $first['metadata']);
        self::assertSame($updates[0]->getData(), $updates[1]->getData(), 'Deduplicated recording can deliberately publish duplicate bytes.');
        self::assertSame($unknown, json_decode($updates[2]->getData(), true)['relationships']);
        self::assertNull(json_decode($updates[2]->getData(), true)['occurred_at']);
        self::assertSame($broadcast, json_decode($updates[3]->getData(), true)['relationships']);
        $page = $inspection->bus->fetch(new ListNotices(NoticeSelection::create(['scope', 'scope-2'])))->toArray();
        self::assertSame(3, $page['total']);
        self::assertStringNotContainsString('CONCEALED-SENTINEL', json_encode($page));
        $batch = $recovery->queries->fetch(new RecoverNotices(NoticeSelection::create(['scope', 'scope-2'])))->toArray();
        self::assertSame(['notice-1', 'unknown', 'broadcast'], array_column($batch['notices'], 'id'));
        self::assertSame('complete', $batch['status']);
        self::assertStringNotContainsString('CONCEALED-SENTINEL', json_encode($batch));
    }

    #[DataProvider('databases')]
    public function test_that_failed_precommit_recording_and_caller_transactions_send_nothing_and_preserve_unrelated_work(string $backend): void
    {
        $db = $this->database($backend);
        $writer = new NoticeFixture($db);
        $inspection = new NoticeInspectionFixture($db);
        $policy = $this->mock(NoticePublicationPolicy::class);
        $policy->shouldNotReceive('currentGrant');
        $hub = $this->mock(HubInterface::class);
        $hub->shouldNotReceive('publish');
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->withArgs(static fn ($failure) => $failure instanceof NoticeReadFailed);
        $operation = new NoticePublication($inspection->bus, $policy, $diagnostics, new MercureHubPublisher($hub), new PrivateMercureHubPublisher($hub));
        $composition = new RecordNoticeAndPublish($writer->bus, $operation);
        $command = RecordNotice::fromArray(NoticeFixture::data());
        if ($backend === 'sqlite') {
            $db->executeStatement("CREATE TRIGGER publication_fail BEFORE INSERT ON fight_notice_comparisons BEGIN SELECT RAISE(ABORT, 'PRIVATE storage'); END");
        } else {
            $db->executeStatement('ALTER TABLE fight_notice_comparisons ADD CONSTRAINT publication_fail CHECK (position < 0)');
        }
        try {
            $composition->record($command);
            self::fail('Real storage failure must remain distinct from publication status.');
        } catch (NoticeStorageFailed $failure) {
            self::assertFalse($failure->outcomeUncertain());
            self::assertSame('Notice storage failed.', $failure->getMessage());
        }
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        self::assertFalse($db->isTransactionActive());
        $db->executeStatement('CREATE TABLE publication_caller_work (id INTEGER)');
        $db->beginTransaction();
        $db->insert('publication_caller_work', ['id' => 42]);
        try {
            $composition->record($command);
            self::fail('Caller transactions must reject before recording.');
        } catch (LogicException) {
            self::assertTrue($db->isTransactionActive());
            self::assertSame(42, (int) $db->fetchOne('SELECT id FROM publication_caller_work'));
        }
        self::assertSame(NoticePublicationStatus::FAILED, $operation->publish(new GetNotice('scope', 'producer', 'notice-1')));
        self::assertTrue($db->isTransactionActive());
        $db->commit();
        self::assertSame(42, (int) $this->connect($db->getParams())->fetchOne('SELECT id FROM publication_caller_work'));
        $db->executeStatement('DROP TABLE publication_caller_work');
    }

    #[DataProvider('databases')]
    public function test_that_shared_history_polling_http_revocation_and_removal_remain_independent_of_optional_output(string $backend): void
    {
        $db = $this->database($backend);
        $removal = new NoticeRemovalFixture($db);
        $inspection = new NoticeInspectionFixture($db);
        $recovery = new NoticeRecoveryFixture($db);
        $policy = $this->mock(NoticePublicationPolicy::class);
        $policy->shouldReceive('currentGrant')->once()->andReturn(NoticePublicationGrant::create('https://feed.test/private', true));
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class);
        $diagnostics->shouldNotReceive('record');
        // No hub or publisher collaborators are constructed or resolved in this composition.
        $operation = new NoticePublication($inspection->bus, $policy, $diagnostics);
        self::assertSame(NoticePublicationStatus::UNSUPPORTED, (new RecordNoticeAndPublish($removal->recording->bus, $operation))->record(RecordNotice::fromArray(NoticeFixture::data())));
        $args = ['scopes' => ['scope']];
        $before = $recovery->call($args)['body']['result']['structuredContent'];
        self::assertSame(['notice-1'], array_column($before['notices'], 'id'));
        self::assertSame('complete', $before['status']);
        $capabilities = $recovery->request('server/discover')['body']['result']['capabilities'];
        self::assertArrayNotHasKey('subscriptions', $capabilities);
        self::assertSame(['notices.recover'], array_column($recovery->request('tools/list')['body']['result']['tools'], 'name'));
        $inspection->access = NoticeAccess::create([]);
        self::assertSame(NoticePublicationStatus::NOT_AVAILABLE, $operation->publish(new GetNotice('scope', 'producer', 'notice-1')));
        self::assertNull($inspection->bus->fetch(new GetNotice('scope', 'producer', 'notice-1')));
        $recovery->access = NoticeRecoveryFixture::access([], revision: 'revoked');
        $revoked = $recovery->call($args + ['continuation' => $before['continuation']]);
        self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $revoked['body']['error']);
        self::assertSame([], $recovery->call($args)['body']['result']['structuredContent']['notices']);
        $recovery->access = NoticeRecoveryFixture::access();
        $removal->remove($removal->selection([NoticeRemovalFixture::target()]));
        $after = $recovery->call($args)['body']['result']['structuredContent'];
        self::assertSame([], $after['notices']);
        self::assertSame('incomplete', $after['status'], 'Empty retained history is not proof of complete recovery.');
        $continued = $recovery->call($args + ['continuation' => $before['continuation']])['body']['result']['structuredContent'];
        self::assertSame([], $continued['notices']);
        self::assertSame('complete', $continued['status'], 'Removing an already examined notice does not lose a later boundary.');
        $inspection->access = NoticeAccess::create(['scope']);
        self::assertSame(NoticePublicationStatus::NOT_AVAILABLE, $operation->publish(new GetNotice('scope', 'producer', 'notice-1')));
        $recovery->access = NoticeRecoveryFixture::access(revision: null, evidence: false);
        self::assertSame('uncertain', $recovery->call($args)['body']['result']['structuredContent']['status']);
    }

    #[DataProvider('databases')]
    public function test_that_existing_committed_fact_dispatch_failure_stays_distinct_and_a_later_explicit_attempt_uses_retained_content(string $backend): void
    {
        $db = $this->database($backend);
        $writer = new NoticeFixture($db);
        $inspection = new NoticeInspectionFixture($db);
        $writer->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function (): void {
            throw new RuntimeException('PRIVATE recording subscriber failure');
        });
        $policy = $this->mock(NoticePublicationPolicy::class);
        $policy->shouldReceive('currentGrant')->once()->andReturn(NoticePublicationGrant::create('https://feed.test/private', true));
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class);
        $diagnostics->shouldNotReceive('record');
        $hub = $this->mock(HubInterface::class);
        $sent = 0;
        $hub->shouldReceive('publish')->once()->andReturnUsing(static function (Update $update) use (&$sent, $db): string {
            ++$sent;
            self::assertFalse($db->isTransactionActive());
            self::assertTrue($update->isPrivate());
            return 'controlled-id';
        });
        $operation = new NoticePublication($inspection->bus, $policy, $diagnostics, privatePublisher: new PrivateMercureHubPublisher($hub));
        try {
            (new RecordNoticeAndPublish($writer->bus, $operation))->record(RecordNotice::fromArray(NoticeFixture::data()));
            self::fail('Existing committed-fact failure must propagate, not pretend recording rolled back.');
        } catch (CommittedNoticeDispatchFailed $failure) {
            self::assertSame('notice-1', $failure->event()->toArray()['id']);
            self::assertSame(0, $sent);
            self::assertSame(0, $inspection->admissions);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
        }
        self::assertSame(NoticePublicationStatus::PUBLISHED, $operation->publish(new GetNotice('scope', 'producer', 'notice-1')));
        self::assertSame(1, $sent);
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend => [$backend];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $db) {
            $db->close();
        }
        foreach ($this->directories as $directory) {
            $this->removeTemporaryDirectory($directory, 'fight-notice-publication-');
        }
        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $directory = sys_get_temp_dir().'/fight-notice-publication-'.bin2hex(random_bytes(8));
            self::assertTrue(mkdir($directory, 0700));
            $this->directories[] = $directory;
            $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/notices.sqlite'];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual owned disposable database required; skips do not qualify.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $db = $this->connect($params);
        foreach (array_reverse(DbalNoticeSchema::TABLES) as $table) {
            $db->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
        (new DbalNoticeSchema())->install($db);
        return $db;
    }

    private function connect(array $params): Connection
    {
        $db = DriverManager::getConnection($params);
        $this->connections[] = $db;
        if ($params['driver'] === 'pdo_sqlite') {
            $db->executeStatement('PRAGMA foreign_keys = ON');
            $db->executeStatement('PRAGMA busy_timeout = 10000');
        }
        return $db;
    }
}
