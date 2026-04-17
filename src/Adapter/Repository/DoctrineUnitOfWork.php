<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Repository;

use Fight\Common\Application\Repository\UnitOfWork;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class DoctrineUnitOfWork
 */
final readonly class DoctrineUnitOfWork implements UnitOfWork
{
    /**
     * Constructs DoctrineUnitOfWork
     */
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
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
