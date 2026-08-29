<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\Laravel;

use Fight\Common\Adapter\Persistence\Laravel\LaravelTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\Repository\TransactionalUnitOfWorkConformanceTestCase;
use Illuminate\Database\Capsule\Manager;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(LaravelTransactionalUnitOfWork::class)]
class LaravelTransactionalUnitOfWorkTest extends TransactionalUnitOfWorkConformanceTestCase
{
    protected function createTransactionalUnitOfWork(): TransactionalUnitOfWork
    {
        $capsule = new Manager();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        return new LaravelTransactionalUnitOfWork($capsule->getConnection());
    }

    public function test_that_it_commits_and_rolls_back_the_laravel_connection(): void
    {
        $capsule = new Manager();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $connection = $capsule->getConnection();
        $connection->statement('CREATE TABLE transaction_records (name VARCHAR(255) NOT NULL)');

        $unitOfWork = new LaravelTransactionalUnitOfWork($connection);

        self::assertFalse($unitOfWork->isClosed());
        self::assertSame('transaction result', $unitOfWork->commitTransactional(
            function () use ($connection, $unitOfWork): string {
                self::assertSame(1, $connection->transactionLevel());
                self::assertFalse($unitOfWork->isClosed());
                $connection->insert('INSERT INTO transaction_records (name) VALUES (?)', ['committed']);

                return 'transaction result';
            }
        ));
        self::assertSame(1, $connection->table('transaction_records')->count());

        $failure = new RuntimeException('transaction failure');

        try {
            $unitOfWork->commitTransactional(function () use ($connection, $failure): never {
                $connection->insert('INSERT INTO transaction_records (name) VALUES (?)', ['rolled back']);
                throw $failure;
            });
            self::fail('Expected the transaction failure to be propagated.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame(1, $connection->table('transaction_records')->count());
        $connection->disconnect();
        self::assertTrue($unitOfWork->isClosed());
    }
}
