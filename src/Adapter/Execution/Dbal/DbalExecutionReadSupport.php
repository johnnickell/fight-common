<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;

/**
 * Class DbalExecutionReadSupport
 *
 * @internal
 */
final readonly class DbalExecutionReadSupport
{
    /**
     * Constructs DbalExecutionReadSupport
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Returns SQL constraints intersecting selection and trusted scope before any reads
     *
     * @return array{string, list<string>}
     */
    public function constraints(ExecutionSelection $selection, ExecutionAccess $access): array
    {
        $parts = [];
        $parameters = [];
        foreach ($access->projects() as $project => $sessions) {
            $project = (string) $project;
            if (!in_array($project, $selection->projects(), true) || $sessions === []) {
                continue;
            }

            $part = '(s.project_id = ?';
            $parameters[] = $project;
            if ($sessions !== null) {
                $part .= ' AND s.session_id IN ('.implode(', ', array_fill(0, count($sessions), '?')).')';
                array_push($parameters, ...$sessions);
            }

            $parts[] = $part.')';
        }

        $where = $parts === [] ? '1 = 0' : '('.implode(' OR ', $parts).')';
        foreach ($selection->filters() as $field => $value) {
            $where .= ' AND s.'.$field.' = ?';
            $parameters[] = $value;
        }

        return [$where, $parameters];
    }

    /**
     * Returns phase constraints for already session-scoped observation or phase rows
     *
     * @return array{string, list<string>}
     */
    public function phaseConstraints(ExecutionAccess $access, string $alias): array
    {
        $where = '1 = 1';
        $parameters = [];
        foreach ($access->phaseGrants() as $project => $phases) {
            $where .= ' AND ('.$alias.'.project_id <> ? OR '.$alias.'.phase_id IS NULL';
            $parameters[] = (string) $project;
            if ($phases !== []) {
                $where .= ' OR '.$alias.'.phase_id IN ('.implode(', ', array_fill(0, count($phases), '?')).')';
                array_push($parameters, ...$phases);
            }

            $where .= ')';
        }

        return [$where, $parameters];
    }

    /**
     * Returns retained permitted evidence in finite keyset batches without protected storage
     *
     * @return iterable<Observation>
     */
    public function observations(
        string $project,
        string $session,
        ExecutionAccess $access,
        ?string $phase = null
    ): iterable {
        [$where, $parameters] = $this->phaseConstraints($access, 'o');
        $producer = '';
        $id = '';
        do {
            $sql = 'SELECT o.* FROM fight_execution_observations o';
            $sql .= ' WHERE o.project_id = ? AND o.session_id = ? AND '.$where;
            $args = [$project, $session, ...$parameters];
            if ($phase !== null) {
                $sql .= ' AND o.phase_id = ?';
                $args[] = $phase;
            }

            $sql .= ' AND ('.$this->binary('o.producer_id').' > ? OR (o.producer_id = ? AND o.observation_id > ?))';
            $sql .= ' ORDER BY '.$this->binary('o.producer_id').', o.observation_id';
            $rows = $this->connection->fetchAllAssociative(
                $this->limit($sql, 100),
                [...$args, $producer, $producer, $id]
            );
            foreach ($rows as $row) {
                $observation = $this->decode($row);
                if (
                    $observation->session()->projectId() !== $project
                    || $observation->session()->sessionId() !== $session
                    || $observation->phase()?->phaseId() !== $row['phase_id']
                ) {
                    throw new DomainException('Invalid stored observation relationship.');
                }

                yield $observation;
                $producer = $row['producer_id'];
                $id = $row['observation_id'];
            }
        } while (count($rows) === 100);
    }

    /**
     * Returns validated version-one recorded evidence without exposing stored diagnostics
     *
     * @param array<string, mixed> $row
     */
    public function decode(array $row): Observation
    {
        if ((int) $row['version'] !== 1) {
            throw new DomainException('Unsupported stored observation version.');
        }

        $data = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new DomainException('Invalid stored observation.');
        }

        $observation = Observation::fromArray($data);
        if (
            $observation->id()->toString() !== $row['observation_id']
            || $observation->producerId() !== $row['producer_id']
        ) {
            throw new DomainException('Invalid stored observation identity.');
        }

        return $observation->recorded(Observation::timestamp($row['recorded_at']));
    }

    /**
     * Returns bytewise reference ordering shared by supported databases
     */
    public function binary(string $field): string
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            return $field.' COLLATE "C"';
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return $field.' COLLATE utf8mb4_bin';
        }

        return $field.' COLLATE BINARY';
    }

    /**
     * Returns platform-correct finite SQL bounds
     */
    public function limit(string $sql, int $limit, int $offset = 0): string
    {
        return $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit, $offset);
    }
}
