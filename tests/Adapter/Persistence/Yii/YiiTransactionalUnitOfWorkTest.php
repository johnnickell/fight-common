<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\Yii;

use Fight\Common\Adapter\Persistence\Yii\YiiTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\Repository\TransactionalUnitOfWorkConformanceTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

#[CoversClass(YiiTransactionalUnitOfWork::class)]
/**
 * Class YiiTransactionalUnitOfWorkTest
 */
class YiiTransactionalUnitOfWorkTest extends TransactionalUnitOfWorkConformanceTestCase
{
    /**
     * Creates the transactional adapter under test.
     */
    protected function createTransactionalUnitOfWork(): TransactionalUnitOfWork
    {
        return new YiiTransactionalUnitOfWork($this->createConnection());
    }

    /**
     * Tests native Yii commit, rollback, and closed-connection behavior.
     */
    public function test_that_yii_transactional_unit_of_work_commits_and_rolls_back_the_connection(): void
    {
        $connection = $this->createConnection();
        $connection->createCommand('CREATE TABLE transaction_records (name VARCHAR(255) NOT NULL)')->execute();
        $unitOfWork = new YiiTransactionalUnitOfWork($connection);

        self::assertFalse($unitOfWork->isClosed());
        self::assertSame('transaction result', $unitOfWork->commitTransactional(
            function () use ($connection, $unitOfWork): string {
                self::assertNotNull($connection->getTransaction());
                self::assertFalse($unitOfWork->isClosed());
                $connection->createCommand("INSERT INTO transaction_records (name) VALUES ('committed')")->execute();

                return 'transaction result';
            }
        ));
        self::assertSame(1, $this->transactionRecordCount($connection));

        $failure = new RuntimeException('transaction failure');

        try {
            $unitOfWork->commitTransactional(function () use ($connection, $failure): never {
                $connection->createCommand("INSERT INTO transaction_records (name) VALUES ('rolled back')")->execute();
                throw $failure;
            });
            self::fail('Expected the transaction failure to be propagated.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame(1, $this->transactionRecordCount($connection));
        $connection->close();
        self::assertTrue($unitOfWork->isClosed());

        try {
            $unitOfWork->commitTransactional(static fn (): null => null);
            self::fail('Expected the closed connection to reject transactional execution.');
        } catch (\LogicException $failure) {
            self::assertSame(
                'Transactional execution is not supported on a closed connection.',
                $failure->getMessage()
            );
        }
    }

    /**
     * Creates a real Yii SQLite test connection.
     */
    private function createConnection(): Connection
    {
        /** @var MockInterface&CacheInterface $cache */
        $cache = $this->mock(CacheInterface::class);

        return new Connection(
            new Driver('sqlite::memory:'),
            new SchemaCache($cache)
        );
    }

    /**
     * Counts the persisted transaction records.
     */
    private function transactionRecordCount(Connection $connection): int
    {
        return (int) $connection->createCommand('SELECT COUNT(*) FROM transaction_records')->queryScalar();
    }
}
