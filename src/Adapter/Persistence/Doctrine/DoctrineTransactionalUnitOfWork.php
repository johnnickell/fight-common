<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use LogicException;

/**
 * Class DoctrineTransactionalUnitOfWork
 */
final readonly class DoctrineTransactionalUnitOfWork implements TransactionalUnitOfWork
{
    /**
     * Constructs DoctrineTransactionalUnitOfWork
     */
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        return $this->entityManager->wrapInTransaction($operation);
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return !$this->entityManager->isOpen();
    }
}
