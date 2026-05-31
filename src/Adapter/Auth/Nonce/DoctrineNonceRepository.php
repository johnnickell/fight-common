<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Nonce;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Application\Auth\Exception\AuthException;
use Fight\Common\Application\Auth\NonceRepository;
use Fight\Common\Application\HttpFoundation\HttpStatus;
use Fight\Common\Domain\Auth\Nonce;

/**
 * Class DoctrineNonceRepository
 *
 * Persists consumed nonces to a `hmac_nonces` table.
 * Schema: nonce VARCHAR(64) PRIMARY KEY, expires_at DATETIME NOT NULL
 */
final readonly class DoctrineNonceRepository implements NonceRepository
{
    private const string TABLE = 'hmac_nonces';

    /**
     * Constructs DoctrineNonceRepository
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function consume(Nonce $nonce): void
    {
        try {
            $this->connection->insert(self::TABLE, [
                'nonce'      => $nonce->value(),
                'expires_at' => $nonce->expiresAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new AuthException('Nonce already consumed', HttpStatus::UNAUTHORIZED);
        }
    }

    /**
     * @inheritDoc
     */
    public function purgeExpired(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM '.self::TABLE.' WHERE expires_at < :now',
            ['now' => new DateTimeImmutable()->format('Y-m-d H:i:s')]
        );
    }
}
