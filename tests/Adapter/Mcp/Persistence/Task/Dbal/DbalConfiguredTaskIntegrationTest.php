<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTransaction;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCheckConnection;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use LogicException;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DbalConfiguredTaskIntegrationTest extends UnitTestCase
{
    private const array DEFAULT_TASK_TABLE_NAMES = ['fight_task_state', 'fight_tasks'];

    private array $connections = [];
    private array $sqlitePaths = [];

    #[DataProvider('selections')]
    public function test_that_each_selection_owns_lifecycle_and_committed_reads_without_default_fallback(string $backend, array $selection): void
    {
        $connection = $this->database($backend);
        $tables = new DbalTaskTables(...$selection);
        $platform = $connection->getDatabasePlatform();
        $schema = new DbalTaskSchema($tables);
        $schema->install($connection);
        $schema->install($connection);

        $selectedTask = $tables->taskTable()->quoted($platform);
        $selectedState = $tables->stateTable()->quoted($platform);

        // Explicit operator composition adds only the absent default counterparts, never runtime creation.
        $defaults = new DbalTaskTables();
        $manager = $connection->createSchemaManager();
        $definition = $manager->introspectSchema();
        $empty = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
        (new DbalTaskSchema())->addTo($empty, $platform);
        foreach ([$defaults->stateTable(), $defaults->taskTable()] as $table) {
            if (!$definition->hasTable($table->name())) {
                $manager->createTable($empty->getTable($table->schemaName()));
                if ($table->name() === 'fight_task_state') {
                    $connection->insert($table->quoted($platform), ['singleton' => 1, 'version' => 1]);
                }
            }
        }

        $defaultFixture = new TaskFixture($connection);
        if ($tables->taskTable()->name() !== 'fight_tasks') {
            $defaultFixture->create(5000, 99);
        }

        $defaultPayload = $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id');
        if ($tables->stateTable()->name() !== 'fight_task_state') {
            // An incompatible populated default state must be irrelevant to the selected namespace.
            $connection->update('fight_task_state', ['version' => 77], ['singleton' => 1]);
        }

        $schema->install($connection);
        $fixture = new TaskFixture($connection, $tables);
        self::assertNull($fixture->get(), 'A selected empty Task table is not a migration or a default-table read.');
        $fixture->create(1000, 25);
        $original = $fixture->get();
        self::assertSame(25, $original->get('poll_interval'));
        $fixture->create(1000, 25);
        $fresh = new TaskFixture($this->connect($connection->getParams()), $tables);
        self::assertSame($original->toString(), $fresh->get()->toString());
        $fixture->issue(confirmation: true);
        $fixture->supply([TaskFixture::KEY_1 => (object) ['action' => 'accept', 'content' => (object) ['answer' => 'approved']]]);
        self::assertTrue($fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('ready'));
        $fixture->cancel();
        self::assertNotNull($fresh->cancellation()->get('cancellation'));
        $fixture->acquire();
        $fixture->record(TaskFixture::completed(), 5);
        self::assertSame('completed', $fresh->get()->get('state')->get('status'));
        try {
            $fixture->record(TaskState::create('cancelled'), 6);
            self::fail('Terminal state cannot be replaced.');
        } catch (TaskConflict) {
            self::assertSame('completed', $fresh->get()->get('state')->get('status'));
        }

        $before = $connection->fetchOne('SELECT payload FROM '.$selectedTask.' WHERE scope = ?', ['scope']);
        try {
            $fixture->transactionalUnitOfWork->commitTransactional(function () use ($fixture): void {
                $fixture->taskRepository->remove(TaskSelection::fromIds('scope', [TaskFixture::id()]));
                throw new RuntimeException('Confirmed rollback');
            });
            self::fail('Expected rollback.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Confirmed rollback', $runtimeException->getMessage());
        }

        self::assertSame($before, $connection->fetchOne('SELECT payload FROM '.$selectedTask.' WHERE scope = ?', ['scope']));
        $fresh->now += 1000;
        self::assertNull($fresh->get());
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$selectedTask));
        try {
            $fresh->create(1000, 25);
            self::fail('Expiry must not renew retention.');
        } catch (TaskRejected) {
            self::assertNull($fresh->get());
        }

        $fresh->remove();
        $fresh->remove();
        self::assertNull($fixture->get());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$selectedTask));
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM '.$selectedState.' WHERE singleton = 1'));
        if ($tables->taskTable()->name() !== 'fight_tasks') {
            self::assertSame($defaultPayload, $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id'));
        }

        if ($tables->stateTable()->name() !== 'fight_task_state') {
            self::assertSame(77, (int) $connection->fetchOne('SELECT version FROM fight_task_state WHERE singleton = 1'));
        }
    }

    #[DataProvider('databases')]
    public function test_that_changed_missing_partial_and_incompatible_selections_never_install_or_copy_at_runtime(string $backend): void
    {
        $connection = $this->database($backend);
        (new DbalTaskSchema())->install($connection);
        $defaultFixture = new TaskFixture($connection);
        $defaultFixture->create();

        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks');
        $tables = new DbalTaskTables('Order', 'Group');
        $selected = new TaskFixture($connection, $tables);
        foreach ([fn () => $selected->get(), fn () => $selected->create()] as $operation) {
            try { $operation(); self::fail('Missing selected storage must fail explicitly.'); }
            catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        }

        self::assertFalse($connection->createSchemaManager()->tablesExist(['Group']));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
        $schema = new DbalTaskSchema($tables);
        $schema->install($connection);
        self::assertNull($selected->get());
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
        $stateTable = $tables->stateTable()->quoted($connection->getDatabasePlatform());
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $connection->update($stateTable, ['version' => 2], ['singleton' => 1]);
        try { $schema->install($connection); self::fail('Selected incompatible version must reject.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Unsupported Task schema.', $runtimeException->getMessage()); }

        try { $selected->create(); self::fail('Runtime must check selected version.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Unsupported Task schema.', $runtimeException->getMessage()); }

        $connection->update($stateTable, ['version' => 1], ['singleton' => 1]);
        $connection->executeStatement('ALTER TABLE '.$taskTable.' DROP COLUMN payload');
        try { $schema->install($connection); self::fail('Selected incompatible payload schema must reject.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Unsupported Task schema.', $runtimeException->getMessage()); }

        $connection->executeStatement('DROP TABLE '.$taskTable);
        try { $schema->install($connection); self::fail('Partial selected installation must reject.'); }
        catch (RuntimeException $runtimeException) { self::assertSame('Incomplete Task schema.', $runtimeException->getMessage()); }

        self::assertFalse($connection->createSchemaManager()->tablesExist(['Group']));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
    }

    #[DataProvider('databases')]
    public function test_that_task_transaction_rejection_leaves_caller_work_uncommitted_and_intact(string $backend): void
    {
        // The boundary must reject before it accesses any Task schema.
        $connection = $this->disposableConnection($backend);
        $transaction = new DbalTaskTransaction($connection);
        $observer = $this->connect($connection->getParams());
        $connection->executeStatement('CREATE TABLE task_transaction_caller (id INTEGER)');

        try {
            $connection->beginTransaction();
            $connection->insert('task_transaction_caller', ['id' => 7]);

            try {
                $transaction->commitTransactional(static fn () => self::fail('Caller-owned work must reject before admission.'));
                self::fail('Expected rejection of the existing transaction.');
            } catch (LogicException) {
                self::assertTrue($connection->isTransactionActive());
                self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM task_transaction_caller'));
                self::assertSame(0, (int) $observer->fetchOne('SELECT COUNT(*) FROM task_transaction_caller'));
            }

            $connection->commit();
            self::assertSame(7, (int) $observer->fetchOne('SELECT id FROM task_transaction_caller'));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $connection->executeStatement('DROP TABLE task_transaction_caller');
        }
    }

    #[DataProvider('inputMessages')]
    public function test_that_committed_task_reads_keep_the_producer_message_until_the_last_input_expires(
        string $backend,
        ?string $message,
        DbalTaskTables $tables
    ): void {
        $connection = $this->disposableConnection($backend);
        $platform = $connection->getDatabasePlatform();
        $taskTable = $tables->taskTable()->quoted($platform);
        $stateTable = $tables->stateTable()->quoted($platform);
        $observer = $this->connect($connection->getParams());
        try {
            (new DbalTaskSchema($tables))->install($connection);
            $writer = new TaskFixture($connection, $tables);
            $writer->create();
            $issuedAt = $writer->now;
            $writer->issue(expiresAt: $issuedAt + 100);
            $writer->issue(expectedVersion: 2, expiresAt: $issuedAt + 200);
            $id = TaskFixture::id();
            $requests = $writer->committedTaskReader->read('scope', $id)->state()->data()->get('input');
            $writer->recordTaskStateHandler->handle(CommandMessage::create(new RecordTaskState(
                'scope', $id, 'producer', 3, TaskState::create('input_required', $message, $requests)
            )));

            $reader = new TaskFixture($observer, $tables);
            $query = QueryMessage::create(new GetTask('scope', $id, 'caller'));
            $before = $connection->fetchOne("SELECT payload FROM $taskTable");
            self::assertSame($message, $reader->committedTaskReader->read('scope', $id)->state()->data()->get('message'));
            foreach ([0, 99, 100, 199, 200] as $elapsed) {
                $reader->now = $issuedAt + $elapsed;
                $view = $reader->getTaskHandler->handle($query);
                $state = $view->get('state');
                $input = $state->get('input');
                $expectedKeys = match (true) {
                    $elapsed < 100 => [TaskFixture::KEY_1, TaskFixture::KEY_2],
                    $elapsed < 200 => [TaskFixture::KEY_2],
                    default => []
                };
                self::assertSame($elapsed < 200 ? 'input_required' : 'working', $state->get('status'));
                self::assertSame($elapsed < 200 ? $message : null, $state->get('message'));
                self::assertSame($expectedKeys, $input === null ? [] : array_keys($input->properties()));
                foreach ($expectedKeys as $key) {
                    self::assertSame($requests->get($key)->toString(), $input->get($key)->toString());
                }
                self::assertSame(4, $view->get('version'));
                self::assertSame($issuedAt, $view->get('updated_at'));
                self::assertSame($before, $connection->fetchOne("SELECT payload FROM $taskTable"));
            }
        } finally {
            $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
        }
    }

    public static function inputMessages(): iterable
    {
        yield 'sqlite-nonempty' => ['sqlite', 'Waiting for both authorized answers', new DbalTaskTables()];
        yield 'mysql-configured-empty' => ['mysql', '', new DbalTaskTables('InputMessageState', 'InputMessageRows')];
        yield 'postgresql-null' => ['postgresql', null, new DbalTaskTables()];
    }

    #[DataProvider('databases')]
    public function test_that_supply_handler_cannot_partially_commit_a_batch_rejected_by_storage(string $backend): void
    {
        $connection = $this->disposableConnection($backend);
        $tables = new DbalTaskTables('InputConstraintState', 'InputConstraintRows');
        $platform = $connection->getDatabasePlatform();
        $taskTable = $tables->taskTable()->quoted($platform);
        $stateTable = $tables->stateTable()->quoted($platform);

        try {
            (new DbalTaskSchema($tables))->install($connection);
            $fixture = new TaskFixture($connection, $tables);
            $fixture->create();
            $fixture->issue(count: 2);
            $observer = $this->connect($connection->getParams());
            $readPayload = static fn () => $observer->fetchOne("SELECT payload FROM $taskTable");
            $before = $readPayload();
            $connection->executeStatement(match ($backend) {
                'sqlite' => "CREATE TRIGGER input_constraint_failure BEFORE INSERT ON $taskTable BEGIN SELECT RAISE(ABORT, 'private'); END",
                default  => "ALTER TABLE $taskTable ADD CONSTRAINT input_constraint_failure CHECK (payload NOT LIKE '%accepted-body%')"
            });
            $response = (object) ['action' => 'accept', 'content' => (object) ['answer' => 'accepted-body']];
            $command = new SupplyTaskInput('scope', TaskFixture::id(), 'caller', StrictJson::fromObject([
                TaskFixture::KEY_1 => $response,
                TaskFixture::KEY_2 => $response
            ]));

            $message = CommandMessage::create($command);
            try {
                $fixture->supplyTaskInputHandler->handle($message);
                self::fail('Expected the database to reject both answers.');
            } catch (TaskStorageFailed $failure) {
                self::assertFalse($failure->outcomeUncertain());
            }
            self::assertFalse($connection->isTransactionActive());
            self::assertSame($before, $readPayload());

            $connection->executeStatement(match ($backend) {
                'sqlite'     => 'DROP TRIGGER input_constraint_failure',
                'mysql'      => "ALTER TABLE $taskTable DROP CHECK input_constraint_failure",
                'postgresql' => "ALTER TABLE $taskTable DROP CONSTRAINT input_constraint_failure"
            });
            $fixture->supplyTaskInputHandler->handle($message);
            $read = new TaskFixture($observer, $tables);
            $query = QueryMessage::create(new GetTaskInput('scope', TaskFixture::id(), 'caller'));
            $group = $read->getTaskInputHandler->handle($query)->get('groups')->get(TaskFixture::KEY_1);
            self::assertTrue($group->get('ready'));
            foreach ([TaskFixture::KEY_1, TaskFixture::KEY_2] as $key) {
                self::assertSame('accepted-body', $group->get('answers')->get($key)->get('content')->get('answer'));
            }
        } finally {
            $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
        }
    }

    #[DataProvider('limitSelections')]
    public function test_that_task_input_identity_remains_finite_after_retirement_and_reconnect(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend);
        $observer = $this->connect($connection->getParams());
        $platform = $connection->getDatabasePlatform();
        $taskTable = $tables->taskTable()->quoted($platform);
        $stateTable = $tables->stateTable()->quoted($platform);
        $id = TaskFixture::id();
        $input = QueryMessage::create(new GetTaskInput('scope', $id, 'caller'));
        $task = QueryMessage::create(new GetTask('scope', $id, 'caller'));
        $seed = new TaskFixture($connection, $tables);
        $writer = new TaskFixture($observer, $tables);
        $retire = static fn (int $version, string $key) => CommandMessage::create(new RetireTaskInput('scope', $id, 'producer', $version, $key));
        $submit = static fn (array $answers) => $writer->supplyTaskInputHandler->handle(CommandMessage::create(new SupplyTaskInput('scope', $id, 'caller', StrictJson::fromObject($answers))));
        $answer = static fn (string $text) => ['action' => 'accept', 'content' => (object) ['answer' => $text]];
        $issue = static fn (int $version) => $writer->requestTaskInputHandler->handle(CommandMessage::create(new RequestTaskInput(
            'scope', $id, 'producer', $version,
            TaskInputForms::batch(McpInputRequired::fromRequests(['form-0' => TaskFixture::form()]), 'caller', $writer->now + 1000)
        )));
        $read = static fn () => $connection->fetchOne("SELECT payload FROM $taskTable");
        try {
            (new DbalTaskSchema($tables))->install($connection);
            $seed->create();
            $seed->issue(confirmation: true, count: 2);
            $submit([TaskFixture::KEY_1 => $answer('partial')]);
            $writer->retireTaskInputHandler->handle($retire(3, TaskFixture::KEY_1));
            $issue(4);
            self::assertSame([TaskFixture::KEY_3], array_keys($seed->getTaskHandler->handle($task)->get('state')->get('input')->properties()));
            $before = $read();
            $submit([TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => false]);
            self::assertSame($before, $read());
            try {
                $submit([TaskFixture::KEY_3 => $answer(str_repeat('é', 17000))]);
                self::fail('The encoded byte bound must reject even without transport.');
            } catch (DomainException) {
                self::assertSame($before, $read());
            }
            $writer->now += 1000;
            $submit([TaskFixture::KEY_3 => $answer('expired')]);
            $group = $writer->getTaskInputHandler->handle($input)->get('groups')->get(TaskFixture::KEY_3);
            self::assertTrue($group->get('expired'));
            self::assertSame([], $group->get('answers')->properties());
            $writer->retireTaskInputHandler->handle($retire(5, TaskFixture::KEY_3));
            // Seed the persisted near-limit state; Task Unit tests exercise all 1,024 rounds.
            $data = $seed->committedTaskReader->read('scope', $id)->toArray();
            $data['inputs'] = $data['inputs']->with('issued', 1023);
            $seed->transactionalUnitOfWork->commitTransactional(fn () => $seed->taskRepository->save(Task::fromArray($data)));
            $issue(6);
            $last = TaskFixture::KEY_PREFIX.'1024';
            $seed->now = $writer->now;
            self::assertSame([$last], array_keys($seed->getTaskHandler->handle($task)->get('state')->get('input')->properties()));
            $writer->retireTaskInputHandler->handle($retire(7, $last));
            $before = $read();
            try {
                $issue(8);
                self::fail('Retiring payloads must not let lifetime identities wrap.');
            } catch (TaskConflict) {
                self::assertSame($before, $read());
            }
            self::assertSame([], $seed->getTaskInputHandler->handle($input)->get('groups')->properties());
        } finally {
            $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
        }
    }

    #[DataProvider('limitSelections')]
    public function test_that_input_handler_commit_failures_are_resolved_through_an_independent_reader(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend);
        $params = $connection->getParams();
        $observer = $this->connect($params);
        $platform = $connection->getDatabasePlatform();
        $taskTable = $tables->taskTable()->quoted($platform);
        $stateTable = $tables->stateTable()->quoted($platform);
        $id = TaskFixture::id();
        $view = QueryMessage::create(new GetTaskInput('scope', $id, 'caller'));
        $query = QueryMessage::create(new GetTask('scope', $id, 'caller'));
        $writer = new TaskFixture($connection, $tables);
        $reader = new TaskFixture($observer, $tables);
        $issue = static fn (int $version) => CommandMessage::create(new RequestTaskInput('scope', $id, 'producer', $version,
            TaskInputForms::batch(McpInputRequired::fromRequests(['form-0' => TaskFixture::form()]), 'caller', $writer->now + 1000)
        ));
        $answer = CommandMessage::create(new SupplyTaskInput('scope', $id, 'caller', StrictJson::fromObject([
            TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'valid']]
        ])));
        $acquire = CommandMessage::create(new AcquireTaskInput('scope', $id, 'caller', TaskFixture::KEY_1));
        $payload = static fn () => $observer->fetchOne("SELECT payload FROM $taskTable");
        $ownsCallerTable = false;
        try {
            (new DbalTaskSchema($tables))->install($connection);
            $writer->create();
            $operations = [
                static fn (TaskFixture $target) => $target->requestTaskInputHandler->handle($issue(1)),
                static fn (TaskFixture $target) => $target->supplyTaskInputHandler->handle($answer),
                static fn (TaskFixture $target) => $target->acquireTaskInputHandler->handle($acquire)
            ];
            foreach ($operations as $stage => $operation) {
                $before = $payload();
                foreach ([false, true] as $committed) {
                    $checked = new TaskCheckConnection($params, $connection->getDriver());
                    try {
                        $fault = static fn () => throw new RuntimeException('Injected commit boundary failure');
                        if ($committed) {
                            $checked->afterCommit = $fault;
                        } else {
                            $checked->beforeCommit = $fault;
                        }
                        $candidate = new TaskFixture($checked, $tables);
                        $candidate->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static fn () => self::fail('Uncertain commit must not emit an event.'));
                        try {
                            $operation($candidate);
                            self::fail('Commit boundary failure cannot report a successful input change.');
                        } catch (TaskStorageFailed $failure) {
                            self::assertTrue($failure->outcomeUncertain());
                            self::assertTrue($candidate->transactionalUnitOfWork->isClosed());
                            self::assertFalse($checked->isTransactionActive());
                        }
                    } finally {
                        $checked->close();
                    }
                    if (!$committed) {
                        self::assertSame($before, $payload());
                    }
                }
                $group = $reader->getTaskInputHandler->handle($view)->get('groups')->get(TaskFixture::KEY_1);
                self::assertNotNull($group);
                self::assertSame($stage + 2, $reader->getTaskHandler->handle($query)->get('version'));
                self::assertSame($stage === 0 ? null : 'valid', $group->get('answers')->get(TaskFixture::KEY_1)?->get('content')->get('answer'));
                self::assertSame($stage === 2, $group->get('consumed'));
            }

            $calls = 0;
            $writer->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void {
                ++$calls;
                throw new RuntimeException('Private postcommit delivery');
            });
            $writer->supplyTaskInputHandler->handle(CommandMessage::create(new SupplyTaskInput('scope', $id, 'caller', StrictJson::fromObject([TaskFixture::KEY_1 => false]))));
            self::assertSame(0, $calls);
            try {
                $writer->requestTaskInputHandler->handle($issue(4));
                self::fail('A failed postcommit subscriber cannot acknowledge delivery.');
            } catch (CommittedTaskDispatchFailed) {
                self::assertSame([TaskFixture::KEY_2], array_keys($reader->getTaskHandler->handle($query)->get('state')->get('input')->properties()));
            }
            self::assertSame(1, $calls);

            $connection->executeStatement('CREATE TABLE task_input_fault_caller (id INTEGER)');
            $ownsCallerTable = true;
            $connection->beginTransaction();
            $connection->insert('task_input_fault_caller', ['id' => 7]);
            $before = $payload();
            foreach ($operations as $operation) {
                try {
                    $operation($writer);
                    self::fail('Input handlers must not take ownership of caller work.');
                } catch (LogicException) {
                    self::assertTrue($connection->isTransactionActive());
                    self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM task_input_fault_caller'));
                    self::assertSame(0, (int) $observer->fetchOne('SELECT COUNT(*) FROM task_input_fault_caller'));
                    self::assertSame($before, $payload());
                }
            }
            $connection->commit();
            self::assertSame(7, (int) $observer->fetchOne('SELECT id FROM task_input_fault_caller'));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            if ($ownsCallerTable) {
                $connection->executeStatement('DROP TABLE task_input_fault_caller');
            }
            $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
            $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
        }
    }

    #[DataProvider('creationConstraintSelections')]
    public function test_that_creation_constraint_rejection_leaves_no_row_and_allows_a_fresh_commit(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend);
        $platform = $connection->getDatabasePlatform();
        $table = $tables->taskTable()->quoted($platform);
        $state = $tables->stateTable()->quoted($platform);
        try {
            $connection->executeStatement("DROP TABLE IF EXISTS $table");
            $connection->executeStatement("DROP TABLE IF EXISTS $state");
            (new DbalTaskSchema($tables))->install($connection);
            $writer = new TaskFixture($connection, $tables);
            $reader = $this->connect($connection->getParams());
            $observer = new TaskFixture($reader, $tables);
            $message = CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working')));
            $connection->executeStatement("ALTER TABLE $table ADD CONSTRAINT reject_task_creation CHECK (scope <> 'scope')");
            try {
                $writer->createTaskHandler->handle($message);
                self::fail('Creation succeeded despite a real table constraint.');
            } catch (TaskStorageFailed) {
                self::assertFalse($connection->isTransactionActive());
                self::assertSame([], $reader->fetchFirstColumn("SELECT task_id FROM $table"));
                self::assertNull($observer->committedTaskReader->read('scope', TaskFixture::id()));
            }
            $drop = $backend === 'mysql' ? 'CHECK' : 'CONSTRAINT';
            $connection->executeStatement("ALTER TABLE $table DROP $drop reject_task_creation");
            $writer->createTaskHandler->handle($message);
            $committed = $observer->committedTaskReader->read('scope', TaskFixture::id());
            self::assertSame(1, $committed->version());
            self::assertSame('working', $committed->state()->status());
        } finally {
            $connection->executeStatement("DROP TABLE IF EXISTS $table");
            $connection->executeStatement("DROP TABLE IF EXISTS $state");
        }
    }

    public static function creationConstraintSelections(): iterable
    {
        foreach (['mysql', 'postgresql'] as $backend) {
            yield $backend.'-configured' => [$backend, new DbalTaskTables('TaskState', 'TaskRows')];
        }
    }

    #[DataProvider('limitSelections')]
    public function test_that_persisted_timestamp_and_polling_limits_survive_a_creation_retry(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend);
        (new DbalTaskSchema($tables))->install($connection);
        $writer = new TaskFixture($connection, $tables);
        $reader = $this->connect($connection->getParams());
        $observer = new TaskFixture($reader, $tables);
        $taskTable = $tables->taskTable()->quoted($reader->getDatabasePlatform());
        $readPayloads = static fn () => $reader->fetchFirstColumn("SELECT payload FROM $taskTable");
        $message = CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working'), 100, 2147483647));
        $writer->now = Task::MAX_TIME - 100;
        $writer->createTaskHandler->handle($message);
        $before = $observer->committedTaskReader->read('scope', TaskFixture::id());
        self::assertSame(Task::MAX_TIME - 100, $before->createdAt());
        self::assertSame(100, $before->ttl());
        $payloads = $readPayloads();
        self::assertCount(1, $payloads);

        $writer->now = Task::MAX_TIME - 1;
        $writer->createTaskHandler->handle($message);
        $observer->now = $writer->now;
        $query = QueryMessage::create(new GetTask('scope', TaskFixture::id()));
        $view = $observer->getTaskHandler->handle($query);
        self::assertSame(2147483647, $view->get('poll_interval'));
        self::assertSame(1, $view->get('version'));

        $writer->now = Task::MAX_TIME;
        $observer->now = $writer->now;
        self::assertNull($observer->getTaskHandler->handle($query));
        try {
            $writer->createTaskHandler->handle($message);
            self::fail('A retry cannot renew expired retention.');
        } catch (TaskRejected) {
            self::assertSame($payloads, $readPayloads());
        }
    }

    #[DataProvider('limitSelections')]
    public function test_that_the_maximum_original_context_round_trips_and_is_comparable_after_reconnect(string $backend, DbalTaskTables $tables): void
    {
        $connection = $this->database($backend);
        (new DbalTaskSchema($tables))->install($connection);
        $writer = new TaskFixture($connection, $tables);
        $nested = 'leaf';
        for ($depth = 0; $depth < 63; ++$depth) {
            $nested = (object) ['node' => $nested];
        }
        $operation = TaskFixture::definition()->operation()->with('retained', $nested, 64);
        $command = new CreateTask(TaskFixture::definition(operation: $operation), TaskState::create('working'));
        $writer->createTaskHandler->handle(CommandMessage::create($command));
        $connection->close();

        $observer = new TaskFixture($this->connect($connection->getParams()), $tables);
        $task = $observer->committedTaskReader->read('scope', TaskFixture::id());
        self::assertSame('working', $task->state()->status());
        self::assertSame($operation->toString(), $task->definition()->operation()->toString());
        $observer->createTaskHandler->handle(CommandMessage::create($command));
        self::assertSame(1, $observer->committedTaskReader->read('scope', TaskFixture::id())->version());
    }

    #[DataProvider('writerOrders')]
    #[DataProvider('inputWriterOrders')]
    public function test_that_task_writers_recheck_admission_and_read_only_committed_lifecycle_state(
        string $backend,
        bool $configured,
        string $mode,
        bool $seed,
        string $secondOutcome,
        array $eventCounts,
        ?string $status,
        ?int $version,
        bool $inputRace = false
    ): void {
        $connection = $this->database($backend);
        $tables = $configured ? new DbalTaskTables('Order', 'Group') : new DbalTaskTables();
        (new DbalTaskSchema($tables))->install($connection);
        $taskTable = $tables->taskTable()->quoted($connection->getDatabasePlatform());
        $params = $connection->getParams();
        $reader = $this->connect($params);
        $fixtures = new TaskFixture($reader, $tables);
        if ($seed) {
            $fixtures->createTaskHandler->handle(CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working'))));
        }
        if ($inputRace) {
            $fixtures->issue(confirmation: in_array($mode, ['refusal', 'partial-refusal'], true), count: $mode === 'partial-refusal' ? 2 : 1);
        }
        $before = $reader->fetchOne('SELECT payload FROM '.$taskTable);
        self::assertSame($inputRace ? 'input_required' : ($seed ? 'working' : null), $fixtures->committedTaskReader->read('scope', TaskFixture::id())?->state()->status());
        $directory = sys_get_temp_dir().'/fight-task-writers-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $config = $directory.'/config.json';
        file_put_contents($config, json_encode([
            'params' => $params,
            'mode' => $mode,
            'stateTable' => $tables->stateTable()->name(),
            'taskTable' => $tables->taskTable()->name(),
            'direct' => true
        ], JSON_THROW_ON_ERROR));
        $worker = dirname(__DIR__, 5).'/TestCase/Mcp/Task/'.($inputRace ? 'input-worker.php' : 'state-worker.php');
        $first = new Process([PHP_BINARY, $worker, $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, $worker, $config, 'second'], timeout: 25);
        try {
            $first->start();
            $this->waitForWriter($directory.'/first-precommit', $first);
            $second->start();
            $this->waitForWriter($directory.'/second-started', $second);
            usleep(150000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted');
            self::assertSame($before, $reader->fetchOne('SELECT payload FROM '.$taskTable));
            if (in_array($mode, ['revoked', 'remove-revoked'], true)) {
                touch($directory.'/revoked');
            }
            touch($directory.'/release');
            self::assertSame(0, $first->wait(), $first->getErrorOutput());
            self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $one = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $two = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($eventCounts[0] === 0 ? TaskStorageFailed::class : 'committed', $one['status']);
            self::assertSame(['status' => $secondOutcome, 'events' => $eventCounts[1], 'admissions' => 1], $two);
            self::assertSame($eventCounts[0], $one['events']);
            $current = $fixtures->committedTaskReader->read('scope', TaskFixture::id());
            self::assertSame($status, $current?->state()->status());
            self::assertSame($version, $current?->version());
            self::assertSame($status === null ? 0 : 1, (int) $reader->fetchOne('SELECT COUNT(*) FROM '.$taskTable));
            if ($inputRace) {
                $view = $fixtures->getTaskInputHandler->handle(QueryMessage::create(new GetTaskInput('scope', TaskFixture::id(), 'caller')));
                $group = $view->get('groups')->get(TaskFixture::KEY_1);
                $answers = $group->get('answers');
                self::assertSame(in_array($mode, ['refusal', 'partial-refusal'], true), $group->get('refused'));
                self::assertFalse($group->get('consumed'));
                if ($status !== 'working') {
                    self::assertSame([], $answers->properties());
                } elseif ($mode === 'refusal') {
                    self::assertSame('decline', $answers->get(TaskFixture::KEY_1)->get('action'));
                } else {
                    self::assertSame('first-answer', $answers->get(TaskFixture::KEY_1)->get('content')->get('answer'));
                    if ($mode === 'partial-refusal') {
                        self::assertSame('cancel', $answers->get(TaskFixture::KEY_2)->get('action'));
                    }
                }
            }
        } finally {
            touch($directory.'/release');
            $first->stop();
            $second->stop();
            $this->removeTemporaryDirectory($directory, 'fight-task-writers-');
            if ($inputRace) {
                $connection->executeStatement("DROP TABLE IF EXISTS $taskTable");
                $stateTable = $tables->stateTable()->quoted($connection->getDatabasePlatform());
                $connection->executeStatement("DROP TABLE IF EXISTS $stateTable");
            }
        }
    }

    public static function inputWriterOrders(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.'-completed-before-input' => [$backend, false, 'completed-first', true, 'committed', [1, 0], 'completed', 3, true];
            yield $backend.'-input-before-completed' => [$backend, true, 'input-first', true, TaskConflict::class, [1, 0], 'working', 3, true];
        }
        yield 'sqlite-duplicate-input' => ['sqlite', false, 'duplicate', true, 'committed', [1, 0], 'working', 3, true];
        yield 'mysql-conflicting-input' => ['mysql', true, 'conflict', true, 'committed', [1, 0], 'working', 3, true];
        yield 'postgresql-failed-before-input' => ['postgresql', false, 'failed-first', true, 'committed', [1, 0], 'failed', 3, true];
        yield 'sqlite-cancelled-before-input' => ['sqlite', true, 'cancelled-first', true, 'committed', [1, 0], 'cancelled', 3, true];
        yield 'mysql-refusal' => ['mysql', false, 'refusal', true, 'committed', [1, 0], 'working', 3, true];
        yield 'postgresql-partial-refusal' => ['postgresql', true, 'partial-refusal', true, 'committed', [1, 1], 'working', 4, true];
        yield 'postgresql-revoked-input' => ['postgresql', true, 'revoked', true, TaskRejected::class, [1, 0], 'working', 3, true];
    }

    public static function writerOrders(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.'-duplicate' => [$backend, false, 'duplicate', false, 'committed', [1, 0], 'working', 1];
            yield $backend.'-conflict' => [$backend, true, 'conflict', false, TaskConflict::class, [1, 0], 'working', 1];
            yield $backend.'-create-remove' => [$backend, false, 'create-remove', false, 'committed', [1, 1], null, null];
            if ($backend !== 'sqlite') {
                yield $backend.'-remove-create' => [$backend, true, 'remove-create', true, 'committed', [1, 1], 'working', 1];
            }
        }
        yield 'sqlite-rollback' => ['sqlite', false, 'rollback', false, 'committed', [0, 1], 'working', 1];
        yield 'mysql-revoked' => ['mysql', true, 'revoked', false, TaskRejected::class, [1, 0], 'working', 1];
        yield 'postgresql-late-input' => ['postgresql', false, 'late-input', true, TaskConflict::class, [1, 0], 'completed', 2];
        yield 'sqlite-late-progress' => ['sqlite', true, 'late-progress', true, TaskConflict::class, [1, 0], 'completed', 2];
        yield 'postgresql-outcome-retry' => ['postgresql', true, 'outcome-retry', true, 'committed', [1, 0], 'completed', 2];
        yield 'sqlite-remove-progress' => ['sqlite', false, 'remove-progress', true, TaskRejected::class, [1, 0], null, null];
        yield 'mysql-remove-outcome' => ['mysql', true, 'remove-outcome', true, TaskRejected::class, [1, 0], null, null];
        yield 'postgresql-update-remove' => ['postgresql', false, 'update-remove', true, 'committed', [1, 1], null, null];
        yield 'sqlite-remove-repeat' => ['sqlite', true, 'remove-repeat', true, 'committed', [1, 0], null, null];
        yield 'mysql-remove-revoked' => ['mysql', false, 'remove-revoked', true, TaskRejected::class, [1, 0], null, null];
        yield 'postgresql-remove-rollback-update' => ['postgresql', true, 'remove-rollback-update', true, 'committed', [0, 1], 'completed', 2];
    }

    private function waitForWriter(string $path, Process $worker): void
    {
        $deadline = hrtime(true) + 15000000000;
        while (!is_file($path)) {
            if (!$worker->isRunning() || hrtime(true) >= $deadline) {
                self::fail('Task writer did not reach '.$path.': '.$worker->getOutput().$worker->getErrorOutput());
            }
            usleep(10000);
        }
    }

    public static function limitSelections(): iterable
    {
        yield 'sqlite' => ['sqlite', new DbalTaskTables()];
        yield 'mysql-configured' => ['mysql', new DbalTaskTables('Order', 'Group')];
        yield 'postgresql' => ['postgresql', new DbalTaskTables()];
    }

    public static function selections(): iterable
    {
        foreach (self::databases() as $backend => [$name]) {
            foreach (['defaults' => [], 'state-only' => ['stateTable' => 'Order'], 'task-only' => ['taskTable' => 'Group'], 'both' => ['stateTable' => 'Order', 'taskTable' => 'Group']] as $mode => $selection) {
                yield $backend.'-'.$mode => [$name, $selection];
            }
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }

        foreach ($this->sqlitePaths as $path) { unlink($path); }

        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        $connection = $this->disposableConnection($backend);
        $platform = $connection->getDatabasePlatform();
        foreach (['Group', 'Order', ...array_reverse(self::DEFAULT_TASK_TABLE_NAMES)] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$platform->quoteSingleIdentifier($table));
        }

        return $connection;
    }

    private function disposableConnection(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $path = tempnam(sys_get_temp_dir(), 'task-tables-');
            $this->sqlitePaths[] = $path;
            $params = ['driver' => 'pdo_sqlite', 'path' => $path];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual disposable server database required.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }

        return $this->connect($params);
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        $this->connections[] = $connection;
        return $connection;
    }
}
