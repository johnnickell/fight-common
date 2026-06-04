<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
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
    private function makeRepo(MockInterface $connection, string $table = 'nonces'): DoctrineNonceRepository
    {
        /** @var MockInterface&ClassMetadata<Nonce> $meta */
        $meta = $this->mock(ClassMetadata::class);
        $meta->shouldReceive('getTableName')->andReturn($table);

        /** @var MockInterface&EntityManagerInterface $em */
        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('getClassMetadata')->with(Nonce::class)->andReturn($meta);
        $em->shouldReceive('getConnection')->andReturn($connection);

        return new DoctrineNonceRepository($em);
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
