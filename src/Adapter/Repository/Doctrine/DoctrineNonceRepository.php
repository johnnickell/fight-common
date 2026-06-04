<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Repository\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Adapter\Repository\DoctrineRepository;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Common\Domain\Auth\Exception\NonceAlreadyConsumedException;
use Fight\Common\Domain\Auth\NonceRepository;

/**
 * Class DoctrineNonceRepository
 *
 * Persists consumed nonces using the table configured in the ORM XML mapping.
 * Schema: nonce VARCHAR(64) PRIMARY KEY, expires_at DATETIME NOT NULL
 */
class DoctrineNonceRepository extends DoctrineRepository implements NonceRepository
{
    private readonly string $table;

    /**
     * Constructs DoctrineNonceRepository
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, Nonce::class);
        $this->table = $entityManager->getClassMetadata(Nonce::class)->getTableName();
    }

    /**
     * @inheritDoc
     */
    public function consume(Nonce $nonce): void
    {
        try {
            $this->entityManager->getConnection()->insert($this->table, [
                'nonce'      => $nonce->value(),
                'expires_at' => $nonce->expiresAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new NonceAlreadyConsumedException('Nonce already consumed');
        }
    }

    /**
     * @inheritDoc
     */
    public function purgeExpired(): void
    {
        $this->entityManager->getConnection()->executeStatement(
            sprintf('DELETE FROM %s WHERE expires_at < :now', $this->table),
            ['now' => new DateTimeImmutable()->format('Y-m-d H:i:s')]
        );
    }
}
