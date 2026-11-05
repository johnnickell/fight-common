<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Repository\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Fight\Common\Adapter\Repository\DoctrineRepository;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Observability\AuditRepository;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Value\Basic\StringObject;

/**
 * Class DoctrineAuditRepository
 */
class DoctrineAuditRepository extends DoctrineRepository implements AuditRepository
{
    /**
     * Constructs DoctrineAuditRepository
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, AuditEntry::class);
    }

    /**
     * Retrieves entries by actor
     *
     * @return ResultSet<AuditEntry>
     */
    public function getByActor(string $actor, Pagination $pagination): ResultSet
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->where('e.actor = :actor')
            ->setParameter('actor', $actor);

        return $this->paginate($queryBuilder, $pagination);
    }

    /**
     * Retrieves entries by action name
     *
     * @return ResultSet<AuditEntry>
     */
    public function getByAction(string $action, Pagination $pagination): ResultSet
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->where('e.action = :action')
            ->setParameter('action', $action);

        return $this->paginate($queryBuilder, $pagination);
    }

    /**
     * Retrieves entries within a time range
     *
     * @return ResultSet<AuditEntry>
     */
    public function getBetween(DateTimeImmutable $from, DateTimeImmutable $to, Pagination $pagination): ResultSet
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->where('e.timestamp >= :from AND e.timestamp <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        return $this->paginate($queryBuilder, $pagination);
    }

    /**
     * @inheritDoc
     */
    public function add(AuditEntry $entry): void
    {
        $this->entityManager->persist($entry);
    }

    /**
     * Applies pagination to a query and returns a ResultSet
     *
     * @return ResultSet<AuditEntry>
     */
    private function paginate(QueryBuilder $queryBuilder, Pagination $pagination): ResultSet
    {
        $queryBuilder->setFirstResult($pagination->offset())
            ->setMaxResults($pagination->limit());

        foreach ($pagination->orderings() as $field => $direction) {
            $property = StringObject::create($field)->toCamelCase()->toString();
            $queryBuilder->addOrderBy(sprintf('e.%s', $property), $direction);
        }

        $paginator = $this->createPaginator($queryBuilder);
        $totalRecords = count($paginator);
        $items = iterator_to_array($paginator);
        $records = ArrayList::of(AuditEntry::class)->replace($items);

        return new ResultSet(
            $pagination->page(),
            $pagination->perPage(),
            $totalRecords,
            $records
        );
    }
}
