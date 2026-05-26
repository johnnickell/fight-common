<?php

declare(strict_types=1);

namespace Fight\Common\Application\Repository;

use DateTimeImmutable;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;

/**
 * Interface AuditRepository
 */
interface AuditRepository
{
    /**
     * Persists an audit entry
     */
    public function save(AuditEntry $entry): void;

    /**
     * Retrieves entries by actor
     */
    public function findByActor(string $actor, Pagination $pagination): ResultSet;

    /**
     * Retrieves entries by action name
     */
    public function findByAction(string $action, Pagination $pagination): ResultSet;

    /**
     * Retrieves entries within a time range
     */
    public function findBetween(DateTimeImmutable $from, DateTimeImmutable $to, Pagination $pagination): ResultSet;
}
