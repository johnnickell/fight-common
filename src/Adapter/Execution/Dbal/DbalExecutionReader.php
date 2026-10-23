<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Fight\Common\Application\Execution\Session\Data\ExecutionPage;
use Fight\Common\Application\Execution\Session\Data\ExecutionSummary;
use Fight\Common\Application\Execution\Session\Data\ExecutionView;
use Fight\Common\Application\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Application\Execution\Session\Service\ExecutionReader;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationOutcome;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\ListExecutions;
use Fight\Common\Domain\Execution\Session\SessionReference;
use Fight\Common\Domain\Execution\Usage\UsageTotal;
use Throwable;

/**
 * Class DbalExecutionReader
 *
 * Direct live reads with bounded batches, not a projection or cross-request snapshot
 */
final readonly class DbalExecutionReader implements ExecutionReader
{
    private const int BATCH_SIZE = 100;
    private const int MAX_USAGE_GROUPS = 128;

    /**
     * Constructs DbalExecutionReader
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function summarize(ExecutionSelection $selection, ExecutionAccess $access): ExecutionSummary
    {
        try {
            [$where, $parameters] = $this->constraints($selection, $access);
            $outcomes = ['unknown' => 0, 'succeeded' => 0, 'failed' => 0, 'cancelled' => 0];
            $executions = 0;
            $activities = 0;
            $withoutUsage = 0;
            $latest = null;
            $usage = [];
            $afterProject = '';
            $afterSession = '';
            do {
                $sql = $this->selectExecutions().' WHERE '.$where;
                $sql .= ' AND ('.$this->binary('s.project_id').' > ? OR (';
                $sql .= 's.project_id = ? AND '.$this->binary('s.session_id').' > ?))';
                $sql .= ' ORDER BY '.$this->binary('s.project_id').', '.$this->binary('s.session_id');
                $rows = $this->connection->fetchAllAssociative(
                    $this->limit($sql, self::BATCH_SIZE),
                    [...$parameters, $afterProject, $afterProject, $afterSession]
                );
                foreach ($rows as $row) {
                    $view = $this->view($row);
                    ++$executions;
                    ++$outcomes[$view->outcome()->value];
                    $time = $view->latestRecordedAt();
                    if ($time !== null && ($latest === null || $time > $latest)) {
                        $latest = $time;
                    }

                    foreach ($this->observations($row['project_id'], $row['session_id']) as $observation) {
                        $activity = $observation->activity();
                        if ($activity === null) {
                            continue;
                        }

                        ++$activities;
                        if ($activity->usage() === []) {
                            ++$withoutUsage;
                        }

                        foreach ($activity->usage() as $measurement) {
                            $total = UsageTotal::fromMeasurement($measurement);
                            $key = $total->key();
                            $usage[$key] = isset($usage[$key]) ? $usage[$key]->plus($total) : $total;
                            if (count($usage) > self::MAX_USAGE_GROUPS) {
                                throw new DomainException('Execution usage group limit exceeded.');
                            }
                        }
                    }

                    $afterProject = $row['project_id'];
                    $afterSession = $row['session_id'];
                }
            } while (count($rows) === self::BATCH_SIZE);

            ksort($usage, SORT_STRING);

            return new ExecutionSummary(
                $executions,
                $outcomes,
                $activities,
                $withoutUsage,
                array_values($usage),
                $latest
            );
        } catch (Throwable $throwable) {
            throw new ExecutionReadFailed($throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function list(ListExecutions $query, ExecutionAccess $access): ExecutionPage
    {
        try {
            [$where, $parameters] = $this->constraints($query->selection(), $access);
            $total = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM fight_execution_sessions s WHERE '.$where,
                $parameters
            );
            $pagination = $query->pagination();
            $direction = $pagination->orderings()['latest_recorded_at'] ?? 'DESC';
            $sql = $this->selectExecutions().' WHERE '.$where.' ORDER BY latest_recorded_at '.$direction;
            $sql .= ', '.$this->binary('s.project_id').' ASC, '.$this->binary('s.session_id').' ASC';
            $rows = $this->connection->fetchAllAssociative(
                $this->limit($sql, $pagination->limit(), $pagination->offset()),
                $parameters
            );

            return new ExecutionPage(
                array_map($this->view(...), $rows),
                $total,
                $pagination->page(),
                $pagination->perPage()
            );
        } catch (Throwable $throwable) {
            throw new ExecutionReadFailed($throwable);
        }
    }

    /**
     * Returns SQL constraints intersecting selection and trusted scope before any reads
     *
     * @return array{string, list<string>}
     */
    private function constraints(ExecutionSelection $selection, ExecutionAccess $access): array
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
     * Returns execution SQL without joining multiplicative phase or activity rows
     */
    private function selectExecutions(): string
    {
        $scope = 'o.project_id = s.project_id AND o.session_id = s.session_id';

        $sql = 'SELECT s.project_id, s.session_id, s.consumer_reference, ';
        $sql .= "(SELECT COALESCE(MAX(o.recorded_at), '') FROM fight_execution_observations o WHERE ".$scope.')';
        $latestSession = ' FROM fight_execution_observations o WHERE '.$scope;
        $latestSession .= ' AND o.phase_id IS NULL ORDER BY o.recorded_at DESC, o.observation_id DESC, ';
        $latestSession .= $this->binary('o.producer_id').' DESC LIMIT 1)';
        $sql .= ' AS latest_recorded_at, (SELECT o.payload'.$latestSession.' AS session_payload, ';
        $sql .= '(SELECT o.version'.$latestSession.' AS session_version FROM fight_execution_sessions s';

        return $sql;
    }

    /**
     * Returns only the safe typed execution projection
     *
     * @param array<string, mixed> $row
     */
    private function view(array $row): ExecutionView
    {
        $outcome = ObservationOutcome::UNKNOWN;
        if ($row['session_payload'] !== null) {
            if ((int) $row['session_version'] !== 1) {
                throw new DomainException('Unsupported stored observation version.');
            }

            $outcome = $this->decode($row['session_payload'])->outcome();
        }

        $latest = null;
        if ($row['latest_recorded_at'] !== '') {
            $latest = Observation::timestamp($row['latest_recorded_at'])->format('Y-m-d\TH:i:s.u\Z');
        }

        return new ExecutionView(
            SessionReference::fromStrings($row['project_id'], $row['session_id'], $row['consumer_reference']),
            $outcome,
            $latest
        );
    }

    /**
     * Returns retained safe evidence in finite keyset batches without loading protected content
     *
     * @return iterable<Observation>
     */
    private function observations(string $project, string $session): iterable
    {
        $producer = '';
        $id = '';
        do {
            $sql = 'SELECT producer_id, observation_id, version, payload FROM fight_execution_observations';
            $sql .= ' WHERE project_id = ? AND session_id = ? AND (';
            $sql .= $this->binary('producer_id').' > ? OR (producer_id = ? AND observation_id > ?))';
            $sql .= ' ORDER BY '.$this->binary('producer_id').', observation_id';
            $rows = $this->connection->fetchAllAssociative(
                $this->limit($sql, self::BATCH_SIZE),
                [$project, $session, $producer, $producer, $id]
            );
            foreach ($rows as $row) {
                if ((int) $row['version'] !== 1) {
                    throw new DomainException('Unsupported stored observation version.');
                }

                yield $this->decode($row['payload']);
                $producer = $row['producer_id'];
                $id = $row['observation_id'];
            }
        } while (count($rows) === self::BATCH_SIZE);
    }

    /**
     * Returns validated version-one evidence without exposing stored diagnostics
     */
    private function decode(string $payload): Observation
    {
        $data = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new DomainException('Invalid stored observation.');
        }

        return Observation::fromArray($data);
    }

    /**
     * Returns bytewise reference ordering shared by the supported databases
     */
    private function binary(string $field): string
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
    private function limit(string $sql, int $limit, int $offset = 0): string
    {
        return $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit, $offset);
    }
}
