<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRepository;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTransaction;
use Fight\Common\Application\Mcp\Notice\Exception\CommittedNoticeDispatchFailed;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeConflict;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Mcp\Notice\Command\RemoveNotices;
use Fight\Common\Domain\Mcp\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversNothing]
final class DbalNoticeConstraintIntegrationTest extends UnitTestCase
{
    #[DataProvider('appendWrites')]
    public function test_that_a_real_append_write_failure_rolls_back_related_evidence_and_allows_retry(string $backend, string $stage): void
    {
        $path = $backend === 'sqlite' ? tempnam(sys_get_temp_dir(), 'notice-append-') : null;
        $connection = $path === null ? self::connect($backend) : DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $observer = DriverManager::getConnection($connection->getParams());
        // Dedicated names let this fixture clean up independently of the other Notice suites.
        $tables = new DbalNoticeTables('NoticeConstraintState', 'NoticeConstraintRows', 'NoticeConstraintRelationships', 'NoticeConstraintComparisons');
        $platform = $connection->getDatabasePlatform();
        $comparison = $tables->comparisonTable()->quoted($platform);
        $relationships = $tables->relationshipTable()->quoted($platform);
        $records = $tables->noticeTable()->quoted($platform);
        $state = $tables->stateTable()->quoted($platform);
        $failingTable = match ($stage) {
            'notice' => $records,
            'relationship' => $relationships,
            'comparison' => $comparison,
            'commit' => null
        };

        try {
            (new DbalNoticeSchema($tables))->install($connection);
            if ($failingTable !== null) {
                $connection->executeStatement(match ($backend) {
                    'sqlite' => "CREATE TRIGGER notice_constraint_fail BEFORE INSERT ON $failingTable BEGIN SELECT RAISE(ABORT, 'private'); END",
                    default => "ALTER TABLE $failingTable ADD CONSTRAINT notice_constraint_fail CHECK (position < 0)"
                });
            }
            $fixture = new NoticeFixture($failingTable === null ? $this->commitFailure($connection, false) : $connection, $tables);
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $notice = RecordNotice::fromArray(NoticeFixture::data());
            $append = CommandMessage::create($notice);
            $events = 0;
            $fixture->eventDispatcher->addHandler(ClassName::underscore(NoticeRecorded::class), static function () use (&$events): void {
                ++$events;
            });

            try {
                $fixture->recordNoticeHandler->handle($append);
                self::fail('Expected recording to fail at '.$stage.'.');
            } catch (NoticeStorageFailed $failure) {
                self::assertSame($stage === 'commit', $failure->outcomeUncertain());
                self::assertSame('Notice storage failed.', $failure->getMessage());
            }
            self::assertFalse($connection->isTransactionActive());
            self::assertSame($stage === 'commit', $fixture->transactionalUnitOfWork->isClosed());
            self::assertSame(0, $events);
            self::assertSame([], $reader->readAfter('scope', 0, 100));
            foreach ([$records, $relationships, $comparison] as $table) {
                self::assertSame(0, (int) $observer->fetchOne("SELECT COUNT(*) FROM $table"));
            }
            self::assertSame(0, (int) $observer->fetchOne("SELECT last_position FROM $state"));

            if ($failingTable !== null) {
                $connection->executeStatement(match ($backend) {
                    'sqlite' => 'DROP TRIGGER notice_constraint_fail',
                    'mysql' => "ALTER TABLE $failingTable DROP CHECK notice_constraint_fail",
                    'postgresql' => "ALTER TABLE $failingTable DROP CONSTRAINT notice_constraint_fail"
                });
            }
            (new NoticeFixture($connection, $tables))->recordNoticeHandler->handle($append);

            $retained = $reader->readAfter('scope', 0, 100);
            self::assertCount(1, $retained);
            self::assertSame(1, $retained[0]->position());
            self::assertSame(Notice::fromArray(NoticeFixture::data())->toArray(), $retained[0]->notice()->toArray());
        } finally {
            $observer->close();
            try {
                foreach ([$comparison, $relationships, $records, $state] as $table) {
                    $connection->executeStatement("DROP TABLE IF EXISTS $table");
                }
            } finally {
                $connection->close();
                if ($path !== null) {
                    unlink($path);
                }
            }
        }
    }

    #[DataProvider('databases')]
    public function test_that_recorded_notice_facts_distinguish_lost_acknowledgment_from_failed_dispatch(string $backend): void
    {
        $this->checkRecordingBoundary($backend, function (Connection $connection, Connection $observer, DbalNoticeTables $tables): void {
            $fixture = new NoticeFixture($connection, $tables);
            $recovery = new DbalNoticeRecoveryReader($observer, $tables);
            $calls = 0;
            $fixture->eventDispatcher->addHandler(ClassName::underscore(NoticeRecorded::class), static function (EventMessage $event) use ($connection, $recovery, &$calls): void {
                ++$calls;
                self::assertFalse($connection->isTransactionActive());
                self::assertSame($event->payload()->toArray()['position'], $recovery->readAfter('scope', 0, 1)[0]->position());
                throw new RuntimeException('Private delivery failure');
            });
            $first = self::record();
            try {
                $fixture->recordNoticeHandler->handle($first);
                self::fail('A committed notice cannot acknowledge failed dispatch.');
            } catch (CommittedNoticeDispatchFailed $failure) {
                self::assertSame(1, $failure->event()->toArray()['position']);
            }
            $fixture->recordNoticeHandler->handle($first);
            self::assertSame(1, $calls);

            $second = self::record(['id' => 'notice-2']);
            $uncertain = new NoticeFixture($this->commitFailure($connection, true), $tables);
            $unexpectedEvent = static fn () => self::fail('Only a confirmed new commit may dispatch.');
            $uncertain->eventDispatcher->addHandler(ClassName::underscore(NoticeRecorded::class), $unexpectedEvent);
            try {
                $uncertain->recordNoticeHandler->handle($second);
                self::fail('A lost acknowledgment must be reported as uncertain.');
            } catch (NoticeStorageFailed $failure) {
                self::assertTrue($failure->outcomeUncertain());
                self::assertTrue($uncertain->transactionalUnitOfWork->isClosed());
            }
            $rows = $recovery->readAfter('scope', 0, 100);
            self::assertSame([1, 2], array_map(static fn ($row) => $row->position(), $rows));
            $retry = new NoticeFixture($observer, $tables);
            $retry->eventDispatcher->addHandler(ClassName::underscore(NoticeRecorded::class), $unexpectedEvent);
            $retry->recordNoticeHandler->handle($second);
            self::assertSame($rows[1]->recordedAt(), $recovery->readAfter('scope', 1, 100)[0]->recordedAt());
            self::assertCount(2, $recovery->readAfter('scope', 0, 100));
        });
    }

    #[DataProvider('databases')]
    public function test_that_notice_recording_preserves_foreign_transactions_and_rejects_position_overflow(string $backend): void
    {
        $this->checkRecordingBoundary($backend, static function (Connection $connection, Connection $observer, DbalNoticeTables $tables): void {
            $fixture = new NoticeFixture($connection, $tables);
            $state = $tables->stateTable()->quoted($connection->getDatabasePlatform());
            $connection->executeStatement('CREATE TABLE append_boundary_caller (id INTEGER)');
            try {
                $connection->beginTransaction();
                $connection->insert('append_boundary_caller', ['id' => 42]);
                try {
                    $fixture->recordNoticeHandler->handle(self::record());
                    self::fail('Recording must not accept a foreign transaction.');
                } catch (LogicException) {
                    self::assertTrue($connection->isTransactionActive());
                    self::assertSame(0, $fixture->admissions);
                    self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM append_boundary_caller'));
                    self::assertSame(0, (int) $observer->fetchOne('SELECT COUNT(*) FROM append_boundary_caller'));
                    self::assertSame([], (new DbalNoticeRecoveryReader($observer, $tables))->readAfter('scope', 0, 1));
                }
                try {
                    (new DbalNoticeReader($connection, $tables))->inspect(new GetNotice('scope', 'producer', 'notice-1'), NoticeAccess::create(['scope']));
                    self::fail('An inspect inside caller work cannot be treated as a missing notice.');
                } catch (NoticeReadFailed) {
                    self::assertTrue($connection->isTransactionActive());
                    self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM append_boundary_caller'));
                }
                $connection->commit();
                self::assertSame(42, (int) $observer->fetchOne('SELECT id FROM append_boundary_caller'));
            } finally {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $connection->executeStatement('DROP TABLE append_boundary_caller');
            }
            $connection->update($state, ['last_position' => RecordedNotice::MAX_POSITION - 1], ['singleton' => 1]);
            $fixture->recordNoticeHandler->handle(self::record());
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            self::assertSame(RecordedNotice::MAX_POSITION, $reader->readAfter('scope', 0, 1)[0]->position());
            try {
                $fixture->recordNoticeHandler->handle(self::record(['id' => 'overflow']));
                self::fail('A new record cannot coerce an overflowed allocation.');
            } catch (DomainException) {
                self::assertSame(RecordedNotice::MAX_POSITION, (int) $observer->fetchOne("SELECT last_position FROM $state"));
            }
            $fixture->recordNoticeHandler->handle(self::record());
            self::assertCount(1, $reader->readAfter('scope', 0, 100));
        });
    }

    #[DataProvider('databases')]
    public function test_that_notice_removal_rejects_a_caller_transaction_and_commits_before_returning(string $backend): void
    {
        $this->checkRecordingBoundary($backend, static function (Connection $connection, Connection $observer, DbalNoticeTables $tables): void {
            $fixture = new NoticeRemovalFixture($connection, $tables);
            $fixture->recording->recordNoticeHandler->handle(self::record());
            $remove = CommandMessage::create(new RemoveNotices($fixture->selection([NoticeRemovalFixture::target()])));
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $connection->executeStatement('CREATE TABLE removal_boundary_caller (id INTEGER)');
            try {
                $connection->beginTransaction();
                $connection->insert('removal_boundary_caller', ['id' => 42]);
                try {
                    $fixture->removeNoticesHandler->handle($remove);
                    self::fail('Removal must not acknowledge a savepoint as a commit.');
                } catch (LogicException) {
                    self::assertTrue($connection->isTransactionActive());
                    self::assertSame(1, $connection->getTransactionNestingLevel());
                    self::assertSame(0, $fixture->admissions);
                    self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM removal_boundary_caller'));
                    self::assertSame(0, (int) $observer->fetchOne('SELECT COUNT(*) FROM removal_boundary_caller'));
                    self::assertCount(1, $reader->readAfter('scope', 0, 100));
                }
                $connection->commit();
                self::assertSame(42, (int) $observer->fetchOne('SELECT id FROM removal_boundary_caller'));
            } finally {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $connection->executeStatement('DROP TABLE removal_boundary_caller');
            }
            $fixture->removeNoticesHandler->handle($remove);
            self::assertFalse($connection->isTransactionActive(), 'Consumer work starts outside the removal transaction.');
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame([], $window->notices());
            self::assertSame(1, $window->lostThrough());
        });
    }

    public function test_that_removal_at_the_exact_target_limit_keeps_unselected_rows(): void
    {
        $this->checkRecordingBoundary('mysql', static function (Connection $connection, Connection $observer, DbalNoticeTables $tables): void {
            $fixture = new NoticeRemovalFixture($connection, $tables);
            $targets = [];
            for ($position = 1; $position <= 100; ++$position) {
                $id = 'target-'.$position;
                $fixture->recording->recordNoticeHandler->handle(self::record(['id' => $id]));
                $targets[] = NoticeRemovalFixture::target($position, $id);
            }
            $fixture->recording->recordNoticeHandler->handle(self::record(['id' => 'survivor']));
            $selection = $fixture->selection($targets);
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $platform = $observer->getDatabasePlatform();
            $rows = $tables->noticeTable()->quoted($platform);
            try {
                $fixture->selection([...$targets, NoticeRemovalFixture::target(101, 'survivor')]);
                self::fail('A batch of 101 targets must reject before dispatch.');
            } catch (DomainException) {
                self::assertSame(0, $fixture->admissions);
                self::assertSame(101, (int) $observer->fetchOne("SELECT COUNT(*) FROM $rows"));
                self::assertSame(0, $reader->readWindow('scope', 0, 100)->lostThrough());
            }
            $fixture->removeNoticesHandler->handle(CommandMessage::create(new RemoveNotices($selection)));
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame(['survivor'], array_map(static fn ($row) => $row->notice()->id(), $window->notices()));
            self::assertSame(100, $window->lostThrough());
            self::assertSame(101, $window->highWater());
            foreach ([$tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()] as $table) {
                $name = $table->quoted($platform);
                self::assertSame([101], array_map('intval', $observer->fetchFirstColumn("SELECT position FROM $name")));
            }
        });
    }

    public function test_that_repeated_removal_retains_one_bounded_loss_marker_across_namespaces(): void
    {
        $this->checkRecordingBoundary('postgresql', function (Connection $connection, Connection $observer, DbalNoticeTables $tables): void {
            $transaction = new DbalNoticeTransaction($connection, $tables);
            $repository = new DbalNoticeRepository($transaction);
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $platform = $observer->getDatabasePlatform();
            $state = $tables->stateTable()->quoted($platform);
            $namespace = $reader->readWindow('scope', 0, 1)->namespace();
            $record = static fn (string $scope) => $transaction->commitTransactional(
                static fn () => $repository->append(Notice::fromArray(NoticeFixture::data(['scope' => $scope])), '2026-10-25T01:02:04.000000Z')
            );
            for ($position = 1; $position <= 128; ++$position) {
                $scope = $position % 2 === 0 ? 'scope-2' : 'scope';
                $record($scope);
                $selection = NoticeRemovalSelection::fromArray([
                    'version' => 1, 'namespace' => $namespace,
                    'targets' => [NoticeRemovalFixture::target($position, 'notice-1', $scope)]
                ]);
                $transaction->commitTransactional(static fn () => $repository->remove($selection));
            }
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame([], $window->notices());
            self::assertSame(128, $window->highWater());
            self::assertSame(128, $window->lostThrough());
            self::assertFalse($window->hasUnbrokenHistoryAfter($namespace, 0));
            $rows = $observer->fetchAllAssociative("SELECT * FROM $state");
            self::assertCount(1, $rows);
            self::assertSame(['singleton', 'version', 'last_position', 'namespace', 'lost_through'], array_keys($rows[0]));
            foreach ([$tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()] as $table) {
                $name = $table->quoted($platform);
                self::assertSame(0, (int) $observer->fetchOne("SELECT COUNT(*) FROM $name"));
            }
            $record('scope');
            self::assertSame(129, $reader->readAfter('scope', 128, 100)[0]->position());
            $stale = NoticeRemovalSelection::fromArray([
                'version' => 1, 'namespace' => $namespace,
                'targets' => [NoticeRemovalFixture::target(129)]
            ]);
            $connection->update($state, ['namespace' => bin2hex(random_bytes(16)), 'lost_through' => 129], ['singleton' => 1]);
            self::assertFalse($reader->readWindow('scope', 128, 100)->hasUnbrokenHistoryAfter($namespace, 129));
            try {
                $transaction->commitTransactional(static fn () => $repository->remove($stale));
                self::fail('A retired namespace cannot remove current data.');
            } catch (NoticeRemovalRejected) {
                self::assertCount(1, $reader->readAfter('scope', 0, 100));
            }
            $connection->delete($state, ['singleton' => 1]);
            $this->expectException(RuntimeException::class);
            $reader->readWindow('scope', 0, 100);
        });
    }

    public function test_that_notice_admission_precedes_identity_lookup_for_new_and_retried_records(): void
    {
        $this->checkRecordingBoundary('postgresql', static function (Connection $connection, Connection $observer, DbalNoticeTables $tables): void {
            $fixture = new NoticeFixture($connection, $tables);
            $fixture->recordNoticeHandler->handle(self::record());
            $references = $fixture->references;
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $assertUnchanged = static function () use ($reader): void {
                $window = $reader->readWindow('scope', 0, 100);
                self::assertSame(1, $window->highWater());
                self::assertSame(['notice-1'], array_map(static fn ($row) => $row->notice()->id(), $window->notices()));
            };
            $denials = [
                'producer' => ['producer' => 'SUPER_ADMIN'],
                'scope' => ['scope' => 'SUPER_ADMIN'],
                'subject' => 'subject-agent', 'recipient' => 'recipient-agent', 'author' => 'author-agent',
                'command' => 'command-id', 'event' => 'event-id', 'action' => 'action-id'
            ];
            foreach ($denials as $denial) {
                $fields = is_array($denial) ? $denial : [];
                $fixture->references = is_string($denial) ? array_values(array_diff($references, [$denial])) : $references;
                foreach (['new', 'notice-1'] as $id) {
                    try {
                        $fixture->recordNoticeHandler->handle(self::record($fields + ['id' => $id]));
                        self::fail('Current admission must precede original or retry comparison.');
                    } catch (NoticeRejected) {
                        $assertUnchanged();
                    }
                }
            }
            $fixture->references = $references;
            $fixture->allowed = false;
            try {
                $fixture->recordNoticeHandler->handle(self::record(['topic' => 'conflicting']));
                self::fail('Current denial must precede content conflict.');
            } catch (NoticeRejected) {
                $assertUnchanged();
            }
            $fixture->allowed = true;
            foreach ([
                ['topic' => 'changed'], ['type' => 'changed'], ['occurred_at' => '2026-10-25T01:02:03.120Z'],
                ['metadata' => ['app/revision' => 4]],
                ['relationships' => array_replace(NoticeFixture::data()['relationships'], ['author' => null])]
            ] as $fields) {
                try {
                    $fixture->recordNoticeHandler->handle(self::record($fields));
                    self::fail('Changed accepted content cannot replace the stored receipt.');
                } catch (NoticeConflict) {
                    $assertUnchanged();
                }
            }
            $admissions = $fixture->admissions;
            try {
                $fixture->recordNoticeHandler->handle(self::record(['metadata' => ['app/body' => ['protected']]]));
                self::fail('Invalid protected shapes cannot enter admission.');
            } catch (DomainException) {
                self::assertSame($admissions, $fixture->admissions);
                $assertUnchanged();
            }
        });
    }

    public function test_that_notice_removal_checks_every_retained_reference_before_changing_rows(): void
    {
        $this->checkRecordingBoundary('sqlite', static function (Connection $connection, Connection $observer, DbalNoticeTables $tables): void {
            $fixture = new NoticeRemovalFixture($connection, $tables);
            $fixture->recording->recordNoticeHandler->handle(self::record(['relationships' => [
                'subject' => null, 'recipients' => [], 'author' => null, 'command' => null, 'event' => null, 'action' => null
            ]]));
            $fixture->recording->recordNoticeHandler->handle(self::record(['id' => 'notice-2']));
            $first = NoticeRemovalFixture::target();
            $second = NoticeRemovalFixture::target(2, 'notice-2');
            $missing = NoticeRemovalFixture::target(3, 'missing');
            $reader = new DbalNoticeRecoveryReader($observer, $tables);
            $remove = static fn (array $targets) => $fixture->removeNoticesHandler->handle(CommandMessage::create(new RemoveNotices($fixture->selection($targets))));
            $reject = static function (array $targets) use ($remove, $reader): void {
                try {
                    $remove($targets);
                    self::fail('Rejected selection must not remove even an allowed row.');
                } catch (NoticeRemovalRejected $failure) {
                    self::assertSame('Notice removal rejected.', $failure->getMessage());
                    $window = $reader->readWindow('scope', 0, 100);
                    self::assertSame([1, 2], array_map(static fn ($row) => $row->position(), $window->notices()));
                    self::assertSame(0, $window->lostThrough());
                }
            };
            foreach ($fixture->recording->references as $reference) {
                $fixture->deniedReferences = [$reference];
                $reject([$first, $second]);
            }
            $fixture->deniedReferences = [];
            $reject([$second, NoticeRemovalFixture::target(1, 'notice-1', 'SUPER_ADMIN')]);
            $reject([$second, NoticeRemovalFixture::target(1, 'notice-1', 'scope', 'cleanup-caller')]);
            $fixture->allowed = false;
            $reject([$first, $second]);
            $fixture->allowed = true;
            $fixture->deniedReferences = ['subject-agent'];
            $reject([$second]);
            $reject([$missing]);
            $fixture->deniedReferences = [];
            $reject([$first, $missing]);
            $fixture->recording->allowed = false;
            $remove([$first, $second]);
            self::assertSame([], $reader->readAfter('scope', 0, 100));
        });
    }

    public static function appendWrites(): iterable
    {
        foreach (self::databases() as [$backend]) {
            foreach (['notice', 'relationship', 'comparison', 'commit'] as $stage) {
                yield $backend.'-'.$stage => [$backend, $stage];
            }
        }
    }

    #[DataProvider('databases')]
    public function test_that_rejected_loss_write_restores_cascaded_rows_and_leaves_removal_reusable(string $backend): void
    {
        $connection = self::connect($backend);
        $tables = new DbalNoticeTables('RemovalConstraintState', 'RemovalConstraintRows', 'RemovalConstraintRelationships', 'RemovalConstraintComparisons');
        $platform = $connection->getDatabasePlatform();
        $state = $tables->stateTable()->quoted($platform);
        $evidenceTables = array_map(
            static fn ($table) => $table->quoted($platform),
            [$tables->comparisonTable(), $tables->relationshipTable(), $tables->noticeTable()]
        );

        try {
            (new DbalNoticeSchema($tables))->install($connection);
            $transaction = new DbalNoticeTransaction($connection, $tables);
            $repository = new DbalNoticeRepository($transaction);
            $reader = new DbalNoticeRecoveryReader($connection, $tables);
            $transaction->commitTransactional(static function () use ($repository): void {
                foreach (['notice-1', 'survivor'] as $id) {
                    $repository->append(Notice::fromArray(NoticeFixture::data(['id' => $id])), '2026-10-25T01:02:04.000000Z');
                }
            });
            $selection = NoticeRemovalSelection::fromArray([
                'version'   => 1,
                'namespace' => $reader->readWindow('scope', 0, 100)->namespace(),
                'targets'   => [['scope' => 'scope', 'producer' => 'producer', 'id' => 'notice-1', 'position' => 1]]
            ]);
            $connection->executeStatement(match ($backend) {
                'sqlite' => "CREATE TRIGGER removal_loss_failure BEFORE UPDATE OF lost_through ON $state BEGIN SELECT RAISE(ABORT, 'private'); END",
                default  => "ALTER TABLE $state ADD CONSTRAINT removal_loss_failure CHECK (lost_through = 0)"
            });
            $remove = static fn () => $repository->remove($selection);

            try {
                $transaction->commitTransactional($remove);
                self::fail('Expected removal to fail when the database rejects its loss marker.');
            } catch (NoticeStorageFailed $failure) {
                self::assertFalse($failure->outcomeUncertain());
                self::assertSame('Notice storage failed.', $failure->getMessage());
            }
            self::assertFalse($connection->isTransactionActive());
            self::assertFalse($transaction->isClosed());
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame(0, $window->lostThrough());
            self::assertSame(['notice-1', 'survivor'], array_map(static fn ($row) => $row->notice()->id(), $window->notices()));
            foreach ($evidenceTables as $table) {
                self::assertSame([1, 2], array_map('intval', $connection->fetchFirstColumn("SELECT position FROM $table ORDER BY position")));
            }

            $connection->executeStatement(match ($backend) {
                'sqlite'     => 'DROP TRIGGER removal_loss_failure',
                'mysql'      => "ALTER TABLE $state DROP CHECK removal_loss_failure",
                'postgresql' => "ALTER TABLE $state DROP CONSTRAINT removal_loss_failure"
            });
            $transaction->commitTransactional($remove);
            $window = $reader->readWindow('scope', 0, 100);
            self::assertSame(1, $window->lostThrough());
            self::assertSame(2, $window->highWater());
            self::assertSame(['survivor'], array_map(static fn ($row) => $row->notice()->id(), $window->notices()));
            foreach ($evidenceTables as $table) {
                self::assertSame([2], array_map('intval', $connection->fetchFirstColumn("SELECT position FROM $table")));
            }
        } finally {
            foreach ([...$evidenceTables, $state] as $table) {
                $connection->executeStatement("DROP TABLE IF EXISTS $table");
            }
            $connection->close();
        }
    }

    public static function databases(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
        yield 'postgresql' => ['postgresql'];
    }

    private static function record(array $fields = []): CommandMessage
    {
        return CommandMessage::create(RecordNotice::fromArray(NoticeFixture::data($fields)));
    }

    private function checkRecordingBoundary(string $backend, callable $check): void
    {
        $path = $backend === 'sqlite' ? tempnam(sys_get_temp_dir(), 'notice-boundary-') : null;
        $connection = $path === null ? self::connect($backend) : DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $observer = DriverManager::getConnection($connection->getParams());
        $tables = match ($backend) {
            'sqlite' => new DbalNoticeTables(),
            'mysql' => new DbalNoticeTables(stateTable: 'AppendBoundaryState', comparisonTable: 'AppendBoundaryComparisons'),
            'postgresql' => new DbalNoticeTables('AppendBoundaryState', 'AppendBoundaryNotices', 'AppendBoundaryRelations', 'AppendBoundaryComparisons')
        };
        try {
            (new DbalNoticeSchema($tables))->install($connection);
            $check($connection, $observer, $tables);
        } finally {
            $observer->close();
            try {
                foreach ([$tables->comparisonTable(), $tables->relationshipTable(), $tables->noticeTable(), $tables->stateTable()] as $table) {
                    $connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($connection->getDatabasePlatform()));
                }
            } finally {
                $connection->close();
                if ($path !== null) {
                    unlink($path);
                }
            }
        }
    }

    private function commitFailure(Connection $writer, bool $after): Connection
    {
        $proxy = $this->mock(Connection::class);
        foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'isAutoCommit', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'insert', 'update', 'rollBack'] as $method) {
            $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $writer->$method(...$arguments));
        }
        $failure = new class ('Injected private storage diagnostic.') extends RuntimeException implements Exception {};
        $proxy->shouldReceive('commit')->once()->andReturnUsing(static function () use ($writer, $after, $failure): void {
            if ($after) {
                $writer->commit();
            }
            throw $failure;
        });

        return $proxy;
    }

    private static function connect(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        }

        $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
        self::assertIsString($dsn, 'Actual disposable server database required.');
        $parser = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']);

        return DriverManager::getConnection($parser->parse($dsn));
    }
}
