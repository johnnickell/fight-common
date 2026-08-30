<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\CodeIgniter;

use CodeIgniter\Database\BaseConnection;
use Fight\Common\Adapter\Persistence\CodeIgniter\CodeIgniterTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\Repository\TransactionalUnitOfWorkConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;

#[CoversClass(CodeIgniterTransactionalUnitOfWork::class)]
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
/**
 * Class CodeIgniterTransactionalUnitOfWorkTest
 */
class CodeIgniterTransactionalUnitOfWorkTest extends TransactionalUnitOfWorkConformanceTestCase
{
    /**
     * Creates the transactional adapter under test.
     */
    protected function createTransactionalUnitOfWork(): TransactionalUnitOfWork
    {
        return new CodeIgniterTransactionalUnitOfWork($this->createConnection());
    }

    /**
     * Tests native CodeIgniter commit and rollback behavior.
     */
    public function test_that_it_commits_and_rolls_back_the_codeigniter_connection(): void
    {
        $connection = $this->createConnection();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);
        $table = $connection->getPrefix().'transaction_records';

        self::assertFalse($unitOfWork->isClosed());
        self::assertFalse($connection->getConnection());
        $connection->query('CREATE TABLE '.$table.' (name VARCHAR(255) NOT NULL)');
        self::assertSame('transaction result', $unitOfWork->commitTransactional(
            function () use ($connection, $table, $unitOfWork): string {
                self::assertFalse($unitOfWork->isClosed());
                $connection->query("INSERT INTO {$table} (name) VALUES ('committed')");

                return 'transaction result';
            }
        ));
        self::assertSame(1, $connection->table('transaction_records')->countAllResults());

        $failure = new RuntimeException('transaction failure');

        try {
            $unitOfWork->commitTransactional(function () use ($connection, $failure, $table): never {
                $connection->query("INSERT INTO {$table} (name) VALUES ('rolled back')");
                throw $failure;
            });
            self::fail('Expected the transaction failure to be propagated.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame(1, $connection->table('transaction_records')->countAllResults());
        $connection->close();
        self::assertTrue($unitOfWork->isClosed());
    }

    /**
     * Tests that a suppressed CodeIgniter query failure causes a rollback.
     */
    public function test_that_it_rolls_back_when_codeigniter_suppresses_a_query_failure(): void
    {
        $connection = \Config\Database::connect('tests', false);
        $connection->query(
            'CREATE TABLE '.$connection->getPrefix().'transaction_records (name VARCHAR(255) NOT NULL)'
        );
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        try {
            $unitOfWork->commitTransactional(function () use ($connection): string {
                self::assertFalse(
                    $connection->query("INSERT INTO missing_transaction_records (name) VALUES ('failed')")
                );

                return 'transaction result';
            });
            self::fail('Expected the transaction status failure to be propagated.');
        } catch (RuntimeException $failure) {
            self::assertSame('CodeIgniter transaction failed.', $failure->getMessage());
        }

        self::assertSame(0, $connection->table('transaction_records')->countAllResults());
        self::assertTrue($connection->transStatus());
        self::assertSame(
            'recovered transaction result',
            $unitOfWork->commitTransactional(
                function () use ($connection): string {
                    $connection->query(
                        "INSERT INTO {$connection->getPrefix()}transaction_records (name) VALUES ('recovered')"
                    );

                    return 'recovered transaction result';
                }
            )
        );
        self::assertSame(1, $connection->table('transaction_records')->countAllResults());
        $connection->close();
    }

    /**
     * Rejects a native transaction without altering its depth.
     */
    public function test_that_it_rejects_an_active_native_transaction_without_changing_its_depth(): void
    {
        $connection = $this->createConnection();
        $connection->query('CREATE TABLE '.$connection->getPrefix().'transaction_records (name VARCHAR(255) NOT NULL)');
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        self::assertTrue($connection->transBegin());
        self::assertSame(1, $connection->transDepth);

        try {
            $unitOfWork->commitTransactional(static fn(): null => null);
            self::fail('Expected active native transaction to be rejected.');
        } catch (\LogicException $failure) {
            self::assertSame('Nested transactional execution is not supported.', $failure->getMessage());
        } finally {
            self::assertSame(1, $connection->transDepth);
            self::assertTrue($connection->transRollback());
            $connection->close();
        }
    }

    /**
     * Distinguishes a fresh usable connection from a closed terminal connection.
     */
    public function test_that_it_reports_an_explicitly_closed_connection_as_terminal_without_reconnecting(): void
    {
        $connection = $this->createConnection();
        $unitOfWork = new CodeIgniterTransactionalUnitOfWork($connection);

        self::assertFalse($unitOfWork->isClosed());
        self::assertFalse($connection->getConnection());

        $connection->query('CREATE TABLE '.$connection->getPrefix().'transaction_records (name VARCHAR(255) NOT NULL)');
        self::assertFalse($unitOfWork->isClosed());

        $connection->close();

        self::assertTrue($unitOfWork->isClosed());
        self::assertFalse($connection->getConnection());

        try {
            $unitOfWork->commitTransactional(static fn(): null => null);
            self::fail('Expected terminal connection to reject transactional execution.');
        } catch (\LogicException $failure) {
            self::assertSame(
                'Transactional execution is not supported on a closed connection.',
                $failure->getMessage()
            );
        }

        self::assertFalse($connection->getConnection());
    }

    /**
     * Creates a new real CodeIgniter SQLite test connection.
     */
    private function createConnection(): BaseConnection
    {
        return \Config\Database::connect('tests', false);
    }

    /**
     * Boots CodeIgniter in the isolated PHPUnit process.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Container\Container::getInstance()->instance('config', new class {
            /**
             * Resolves CodeIgniter configuration through Laravel's global helper.
             */
            public function get(mixed $key, mixed $default = null): mixed
            {
                if (! is_string($key)) {
                    return $default;
                }

                return \CodeIgniter\Config\Factories::get('config', $key) ?? $default;
            }
        });

        require dirname(__DIR__, 4).'/vendor/codeigniter4/framework/system/Test/bootstrap.php';
    }
}
