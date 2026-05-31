<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Nonce;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Adapter\Auth\Nonce\DoctrineNonceRepository;
use Fight\Common\Application\Auth\Exception\AuthException;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineNonceRepository::class)]
class DoctrineNonceRepositoryTest extends UnitTestCase
{
    public function test_that_consume_inserts_nonce_row(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));

        /** @var MockInterface|Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('insert')
            ->once()
            ->withArgs(fn(string $table, array $data): bool =>
                $table === 'hmac_nonces' && $data['nonce'] === 'abc123'
            );

        $repo = new DoctrineNonceRepository($connection);
        $repo->consume($nonce);
    }

    public function test_that_consume_throws_when_unique_constraint_is_violated(): void
    {
        $nonce = new Nonce('abc123', new DateTimeImmutable('+5 minutes'));

        /** @var MockInterface|Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('insert')
            ->andThrow($this->mock(UniqueConstraintViolationException::class));

        $repo = new DoctrineNonceRepository($connection);

        $this->expectException(AuthException::class);
        $repo->consume($nonce);
    }

    public function test_that_purge_expired_deletes_old_rows(): void
    {
        /** @var MockInterface|Connection $connection */
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('executeStatement')
            ->once()
            ->withArgs(fn(string $sql, array $params): bool =>
                str_contains($sql, 'DELETE') && isset($params['now'])
            );

        $repo = new DoctrineNonceRepository($connection);
        $repo->purgeExpired();
    }
}
