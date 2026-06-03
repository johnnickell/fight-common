<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Class DoctrineRepository
 */
abstract class DoctrineRepository
{
    /**
     * Constructs DoctrineRepository
     */
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass
    ) {
    }

    /**
     * Creates a query builder for the entity
     */
    protected function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);
    }

    /**
     * Creates a paginator for the query
     *
     * @return Paginator<object>
     */
    protected function createPaginator(QueryBuilder|Query $query): Paginator
    {
        return new Paginator($query);
    }
}
