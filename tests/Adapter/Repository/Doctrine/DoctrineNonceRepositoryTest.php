<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Fight\Common\Adapter\Repository\Doctrine\DoctrineNonceRepository;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Common\Domain\Auth\Exception\NonceAlreadyConsumedException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineNonceRepository::class)]
class DoctrineNonceRepositoryTest extends UnitTestCase
{
    /**
     * @param MockInterface&Connection $connection
     */
    private function makeRepo(
        MockInterface $connection,
        string $table = 'nonces',
        ?QuoteStrategy $quoteStrategy = null
    ): DoctrineNonceRepository
    {
        $nonceMetadata = new ClassMetadata(Nonce::class);
        $nonceMetadata->setPrimaryTable(['name' => $table]);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new SQLitePlatform());

        /** @var MockInterface&EntityManagerInterface $entityManager */
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('getClassMetadata')->with(Nonce::class)->andReturn($nonceMetadata);
        $entityManager->shouldReceive('getConnection')->andReturn($connection);
        $configuration = new Configuration();
        if ($quoteStrategy !== null) {
            $configuration->setQuoteStrategy($quoteStrategy);
        }
        $entityManager->shouldReceive('getConfiguration')->andReturn($configuration);

        return new DoctrineNonceRepository($entityManager);
    }

    public function test_that_consume_inserts_nonce_row(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));

        /** @var MockInterface&Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('insert')
            ->once()
            ->withArgs(fn(string $table, array $data): bool =>
                $table === 'nonces' && $data['nonce'] === 'abc123'
            );

        $this->makeRepo($connection)->consume($nonce);
    }

    public function test_that_consume_uses_table_name_from_metadata(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));

        /** @var MockInterface&Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('insert')
            ->once()
            ->withArgs(fn(string $table, array $data): bool => $table === 'hmac_nonces');

        $this->makeRepo($connection, 'hmac_nonces')->consume($nonce);
    }

    public function test_that_nonce_sql_honors_the_consumers_configured_quote_strategy(): void
    {
        $quoteStrategy = $this->mock(QuoteStrategy::class);
        $quoteStrategy->shouldReceive('getTableName')->once()->withArgs(
            fn (ClassMetadata $metadata, SQLitePlatform $platform): bool => $metadata->getName() === Nonce::class
        )->andReturn('"native"."selected"');
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('insert')->once()->withArgs(
            fn (string $table, array $data): bool => $table === '"native"."selected"'
        );
        $this->makeRepo($connection, quoteStrategy: $quoteStrategy)->consume(
            new Nonce('native', new DateTimeImmutable('+1 day'))
        );
    }

    public function test_that_raw_nonce_paths_use_native_quoted_metadata(): void
    {
        /** @var MockInterface&Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('insert')->once()->withArgs(
            fn (string $table, array $data): bool => $table === '"request-nonces"' && $data['nonce'] === 'quoted'
        );
        $connection->shouldReceive('executeStatement')->once()->withArgs(
            fn (string $sql, array $params): bool => $sql === 'DELETE FROM "request-nonces" WHERE expires_at < :now'
                && isset($params['now'])
        );
        $nonceRepository = $this->makeRepo($connection, '`request-nonces`');
        $nonceRepository->consume(new Nonce('quoted', new DateTimeImmutable('+1 day')));
        $nonceRepository->purgeExpired();
    }

    public function test_that_consume_throws_when_unique_constraint_is_violated(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));

        /** @var MockInterface&Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('insert')
            ->andThrow($this->mock(UniqueConstraintViolationException::class));

        $this->expectException(NonceAlreadyConsumedException::class);
        $this->makeRepo($connection)->consume($nonce);
    }

    public function test_that_purge_expired_deletes_old_rows(): void
    {
        /** @var MockInterface&Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('executeStatement')
            ->once()
            ->withArgs(fn(string $sql, array $params): bool =>
                str_contains($sql, 'DELETE') && isset($params['now'])
            );

        $this->makeRepo($connection)->purgeExpired();
    }
}
