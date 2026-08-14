<?php

declare(strict_types=1);

namespace Prototype;

use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Application\Repository\UnitOfWork;
use LogicException;

/** PROTOTYPE — proves an adapter-local nesting guard without changing the shared port. */
final readonly class GuardedDoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function commit(): void
    {
        $this->entityManager->flush();
    }

    public function commitTransactional(callable $operation): mixed
    {
        if ($this->entityManager->getConnection()->getTransactionNestingLevel() > 0) {
            throw new LogicException('Nested UnitOfWork transactions are not supported.');
        }

        return $this->entityManager->wrapInTransaction($operation);
    }

    public function isClosed(): bool
    {
        return !$this->entityManager->isOpen();
    }
}
