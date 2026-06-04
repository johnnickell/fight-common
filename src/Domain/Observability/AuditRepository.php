<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Observability;

use DateTimeImmutable;
use Exception;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;

/**
 * Interface AuditRepository
 */
interface AuditRepository
{
    /**
     * Retrieves entries by actor
     *
     * @throws Exception When an error occurs
     */
    public function getByActor(string $actor, Pagination $pagination): ResultSet;

    /**
     * Retrieves entries by action name
     *
     * @throws Exception When an error occurs
     */
    public function getByAction(string $action, Pagination $pagination): ResultSet;

    /**
     * Retrieves entries within a time range
     *
     * @throws Exception When an error occurs
     */
    public function getBetween(DateTimeImmutable $from, DateTimeImmutable $to, Pagination $pagination): ResultSet;

    /**
     * Adds an audit entry
     *
     * @throws Exception When an error occurs
     */
    public function add(AuditEntry $entry): void;
}
