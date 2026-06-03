<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Repository;

use ArrayIterator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Fight\Common\Adapter\Repository\DoctrineAuditRepository;
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
        $em = $this->mock(EntityManagerInterface::class);
        $em->shouldReceive('persist')->once()->with($entry);

        $repo = new DoctrineAuditRepository($em);
        $repo->add($entry);
    }

    public function test_that_get_by_actor_returns_result_set(): void
    {
        $entry = AuditEntry::record('user:1', 'login');
        $em = $this->mock(EntityManagerInterface::class);
        $qb = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);
        $qb->shouldReceive('select')->with('e')->once()->andReturn($qb);
        $qb->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($qb);
        $qb->shouldReceive('where')->with('e.actor = :actor')->once()->andReturn($qb);
        $qb->shouldReceive('setParameter')->with('actor', 'user:1')->once()->andReturn($qb);
        $qb->shouldReceive('setFirstResult')->with(0)->once()->andReturn($qb);
        $qb->shouldReceive('setMaxResults')->with(100)->once()->andReturn($qb);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $repo = new class($em, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $em, private readonly Paginator $paginator)
            {
                parent::__construct($em);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $result = $repo->getByActor('user:1', new Pagination());

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(1, $result->totalRecords());
        self::assertSame(1, $result->count());
        self::assertSame(1, $result->page());
        self::assertSame(100, $result->perPage());
        self::assertTrue($entry->id()->equals($result->records()->first()->id()));
    }

    public function test_that_get_by_action_returns_result_set(): void
    {
        $entry = AuditEntry::record('system', 'deploy');
        $em = $this->mock(EntityManagerInterface::class);
        $qb = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);
        $qb->shouldReceive('select')->with('e')->once()->andReturn($qb);
        $qb->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($qb);
        $qb->shouldReceive('where')->with('e.action = :action')->once()->andReturn($qb);
        $qb->shouldReceive('setParameter')->with('action', 'deploy')->once()->andReturn($qb);
        $qb->shouldReceive('setFirstResult')->with(0)->once()->andReturn($qb);
        $qb->shouldReceive('setMaxResults')->with(100)->once()->andReturn($qb);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $repo = new class($em, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $em, private readonly Paginator $paginator)
            {
                parent::__construct($em);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $result = $repo->getByAction('deploy', new Pagination());

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(1, $result->totalRecords());
    }

    public function test_that_get_between_returns_result_set(): void
    {
        $from = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $to = new DateTimeImmutable('2026-12-31T23:59:59Z');
        $entry = AuditEntry::record('user:1', 'login');
        $em = $this->mock(EntityManagerInterface::class);
        $qb = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);
        $qb->shouldReceive('select')->with('e')->once()->andReturn($qb);
        $qb->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($qb);
        $qb->shouldReceive('where')->with('e.timestamp >= :from AND e.timestamp <= :to')->once()->andReturn($qb);
        $qb->shouldReceive('setParameter')->with('from', $from)->once()->andReturn($qb);
        $qb->shouldReceive('setParameter')->with('to', $to)->once()->andReturn($qb);
        $qb->shouldReceive('setFirstResult')->with(0)->once()->andReturn($qb);
        $qb->shouldReceive('setMaxResults')->with(100)->once()->andReturn($qb);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $repo = new class($em, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $em, private readonly Paginator $paginator)
            {
                parent::__construct($em);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $result = $repo->getBetween($from, $to, new Pagination());

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(1, $result->totalRecords());
    }

    public function test_that_get_by_actor_applies_orderings(): void
    {
        $entry = AuditEntry::record('user:1', 'login');
        $em = $this->mock(EntityManagerInterface::class);
        $qb = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);
        $qb->shouldReceive('select')->with('e')->once()->andReturn($qb);
        $qb->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($qb);
        $qb->shouldReceive('where')->with('e.actor = :actor')->once()->andReturn($qb);
        $qb->shouldReceive('setParameter')->with('actor', 'user:1')->once()->andReturn($qb);
        $qb->shouldReceive('setFirstResult')->with(0)->once()->andReturn($qb);
        $qb->shouldReceive('setMaxResults')->with(50)->once()->andReturn($qb);
        $qb->shouldReceive('addOrderBy')->with('e.timestamp', 'DESC')->once()->andReturn($qb);

        $paginator->shouldReceive('count')->once()->andReturn(1);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([$entry]));

        $repo = new class($em, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $em, private readonly Paginator $paginator)
            {
                parent::__construct($em);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $pagination = new Pagination(1, 50, ['timestamp' => 'DESC']);
        $result = $repo->getByActor('user:1', $pagination);

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(50, $result->perPage());
    }

    public function test_that_get_by_actor_returns_empty_result_set(): void
    {
        $em = $this->mock(EntityManagerInterface::class);
        $qb = $this->mock(QueryBuilder::class);
        $paginator = $this->mock(Paginator::class);

        $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);
        $qb->shouldReceive('select')->with('e')->once()->andReturn($qb);
        $qb->shouldReceive('from')->with(AuditEntry::class, 'e')->once()->andReturn($qb);
        $qb->shouldReceive('where')->with('e.actor = :actor')->once()->andReturn($qb);
        $qb->shouldReceive('setParameter')->with('actor', 'nobody')->once()->andReturn($qb);
        $qb->shouldReceive('setFirstResult')->with(0)->once()->andReturn($qb);
        $qb->shouldReceive('setMaxResults')->with(100)->once()->andReturn($qb);

        $paginator->shouldReceive('count')->once()->andReturn(0);
        $paginator->shouldReceive('getIterator')->once()->andReturn(new ArrayIterator([]));

        $repo = new class($em, $paginator) extends DoctrineAuditRepository {
            public function __construct(EntityManagerInterface $em, private readonly Paginator $paginator)
            {
                parent::__construct($em);
            }

            protected function createPaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->paginator;
            }
        };

        $result = $repo->getByActor('nobody', new Pagination());

        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->totalRecords());
        self::assertSame(0, $result->count());
    }

    public function test_that_create_paginator_returns_paginator_instance(): void
    {
        $em = $this->mock(EntityManagerInterface::class);
        $query = $this->mock(Query::class);

        $repo = new class($em) extends DoctrineAuditRepository {
            public function exposeCreatePaginator(QueryBuilder|Query $query): Paginator
            {
                return $this->createPaginator($query);
            }
        };

        $result = $repo->exposeCreatePaginator($query);

        self::assertInstanceOf(Paginator::class, $result);
    }
}
