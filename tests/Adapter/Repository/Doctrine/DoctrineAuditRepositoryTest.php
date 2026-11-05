<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository\Doctrine;

use ArrayIterator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Fight\Common\Adapter\Repository\Doctrine\DoctrineAuditRepository;
use Fight\Common\Adapter\Repository\DoctrineRepository;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineAuditRepository::class)]
#[CoversClass(DoctrineRepository::class)]
class DoctrineAuditRepositoryTest extends UnitTestCase
{
    public function test_that_add_persists_entry(): void
    {
        $entry = AuditEntry::record('user:1', 'login');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $entityManager->shouldReceive('persist')->once()->with($entry);

        $auditRepository = new DoctrineAuditRepository($entityManager);
        $auditRepository->add($entry);
    }

    public function test_that_get_by_actor_returns_result_set(): void
    {
        $entry = AuditEntry::record('user:1', 'login');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $queryBuilder = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $entityManager->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('select')->with('e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('where')->with('e.actor = :actor')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setParameter')->with('actor', 'user:1')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setFirstResult')->with(0)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setMaxResults')->with(100)->once()->andReturn($queryBuilder);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $auditRepository = new class($entityManager, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $entityManager, private readonly Paginator $paginator)
            {
                parent::__construct($entityManager);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $resultSet = $auditRepository->getByActor('user:1', new Pagination());

        self::assertInstanceOf(ResultSet::class, $resultSet);
        self::assertSame(1, $resultSet->totalRecords());
        self::assertSame(1, $resultSet->count());
        self::assertSame(1, $resultSet->page());
        self::assertSame(100, $resultSet->perPage());
        self::assertTrue($entry->id()->equals($resultSet->records()->first()->id()));
    }

    public function test_that_get_by_action_returns_result_set(): void
    {
        $entry = AuditEntry::record('system', 'deploy');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $queryBuilder = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $entityManager->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('select')->with('e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('where')->with('e.action = :action')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setParameter')->with('action', 'deploy')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setFirstResult')->with(0)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setMaxResults')->with(100)->once()->andReturn($queryBuilder);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $auditRepository = new class($entityManager, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $entityManager, private readonly Paginator $paginator)
            {
                parent::__construct($entityManager);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $resultSet = $auditRepository->getByAction('deploy', new Pagination());

        self::assertInstanceOf(ResultSet::class, $resultSet);
        self::assertSame(1, $resultSet->totalRecords());
    }

    public function test_that_get_between_returns_result_set(): void
    {
        $from = new DateTimeImmutable('2026-01-01T00:00:00.000001Z');
        $to = new DateTimeImmutable('2026-12-31T23:59:59.999999Z');
        $entry = AuditEntry::record('user:1', 'login');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $queryBuilder = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $entityManager->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('select')->with('e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('where')->with('e.timestamp >= :from AND e.timestamp <= :to')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setParameter')->with('from', $from)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setParameter')->with('to', $to)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setFirstResult')->with(0)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setMaxResults')->with(100)->once()->andReturn($queryBuilder);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $auditRepository = new class($entityManager, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $entityManager, private readonly Paginator $paginator)
            {
                parent::__construct($entityManager);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $resultSet = $auditRepository->getBetween($from, $to, new Pagination());

        self::assertInstanceOf(ResultSet::class, $resultSet);
        self::assertSame(1, $resultSet->totalRecords());
    }

    public function test_that_get_by_actor_applies_orderings(): void
    {
        $entry = AuditEntry::record('user:1', 'login');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $queryBuilder = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $entityManager->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('select')->with('e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('where')->with('e.actor = :actor')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setParameter')->with('actor', 'user:1')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setFirstResult')->with(0)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setMaxResults')->with(50)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('addOrderBy')->with('e.timestamp', 'DESC')->once()->andReturn($queryBuilder);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $auditRepository = new class($entityManager, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $entityManager, private readonly Paginator $paginator)
            {
                parent::__construct($entityManager);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $pagination = new Pagination(1, 50, ['timestamp' => 'DESC']);
        $resultSet = $auditRepository->getByActor('user:1', $pagination);

        self::assertInstanceOf(ResultSet::class, $resultSet);
        self::assertSame(50, $resultSet->perPage());
    }

    public function test_that_get_by_actor_consumes_strict_pagination_without_signature_changes(): void
    {
        $entry = AuditEntry::record('user:1', 'login');
        $entityManager = $this->mock(EntityManagerInterface::class);
        $queryBuilder = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $entityManager->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('select')->with('e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('where')->with('e.actor = :actor')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setParameter')->with('actor', 'user:1')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setFirstResult')->with(50)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setMaxResults')->with(25)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('addOrderBy')->with('e.timestamp', 'DESC')->once()->ordered()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('addOrderBy')->with('e.action', 'ASC')->once()->ordered()->andReturn($queryBuilder);
        $paginator->shouldReceive('count')->once()->andReturn(51);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $auditRepository = new class($entityManager, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $entityManager, private readonly Paginator $paginator)
            {
                parent::__construct($entityManager);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $pagination = Pagination::strict(3, 25, ['timestamp' => 'desc', 'action' => 'asc']);
        $resultSet = $auditRepository->getByActor('user:1', $pagination);

        self::assertSame(3, $resultSet->page());
        self::assertSame(25, $resultSet->perPage());
        self::assertSame(51, $resultSet->totalRecords());
        self::assertSame(1, $resultSet->count());
        self::assertSame($entry, $resultSet->records()->first());
    }

    public function test_that_get_by_actor_returns_empty_result_set(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $queryBuilder = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $entityManager->shouldReceive('createQueryBuilder')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('select')->with('e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('where')->with('e.actor = :actor')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setParameter')->with('actor', 'nobody')->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setFirstResult')->with(0)->once()->andReturn($queryBuilder);
        $queryBuilder->shouldReceive('setMaxResults')->with(100)->once()->andReturn($queryBuilder);

        $paginator->shouldReceive('count')->once()->andReturn(0);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([]));

        $auditRepository = new class($entityManager, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $entityManager, private readonly Paginator $paginator)
            {
                parent::__construct($entityManager);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $resultSet = $auditRepository->getByActor('nobody', new Pagination());

        self::assertTrue($resultSet->isEmpty());
        self::assertSame(0, $resultSet->totalRecords());
        self::assertSame(0, $resultSet->count());
    }

    public function test_that_create_paginator_returns_paginator_instance(): void
    {
        $entityManager = $this->mock(EntityManagerInterface::class);
        $query = $this->mock(Query::class);

        $auditRepository = new class($entityManager) extends DoctrineAuditRepository {
            public function exposeCreatePaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->createPaginator($query);
            }
        };

        $paginator = $auditRepository->exposeCreatePaginator($query);

        self::assertInstanceOf(Paginator::class, $paginator);
    }
}
