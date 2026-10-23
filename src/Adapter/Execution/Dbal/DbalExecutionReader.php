<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Execution\Observation\Data\ObservationView;
use Fight\Common\Application\Execution\Session\Data\ExecutionInspection;
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
use Fight\Common\Domain\Execution\Session\Query\GetExecution;
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

    private DbalExecutionReadSupport $sql;

    /**
     * Constructs DbalExecutionReader
     */
    public function __construct(private Connection $connection)
    {
        $this->sql = new DbalExecutionReadSupport($connection);
    }

    /**
     * @inheritDoc
     */
    public function summarize(ExecutionSelection $selection, ExecutionAccess $access): ExecutionSummary
    {
        try {
            [$where, $parameters] = $this->sql->constraints($selection, $access);
            [$select, $selectParameters] = $this->selectExecutions($access);
            $outcomes = ['unknown' => 0, 'succeeded' => 0, 'failed' => 0, 'cancelled' => 0];
            $executions = 0;
            $activities = 0;
            $withoutUsage = 0;
            $latest = null;
            $usage = [];
            $afterProject = '';
            $afterSession = '';
            do {
                $sql = $select.' WHERE '.$where;
                $sql .= ' AND ('.$this->sql->binary('s.project_id').' > ? OR (';
                $sql .= 's.project_id = ? AND '.$this->sql->binary('s.session_id').' > ?))';
                $sql .= ' ORDER BY '.$this->sql->binary('s.project_id').', '.$this->sql->binary('s.session_id');
                $rows = $this->connection->fetchAllAssociative(
                    $this->sql->limit($sql, self::BATCH_SIZE),
                    [...$selectParameters, ...$parameters, $afterProject, $afterProject, $afterSession]
                );
                foreach ($rows as $row) {
                    $view = $this->view($row);
                    ++$executions;
                    ++$outcomes[$view->outcome()->value];
                    $time = $view->latestRecordedAt();
                    if ($time !== null && ($latest === null || $time > $latest)) {
                        $latest = $time;
                    }

                    $observations = $this->sql->observations($row['project_id'], $row['session_id'], $access);
                    foreach ($observations as $observation) {
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
            [$where, $parameters] = $this->sql->constraints($query->selection(), $access);
            $total = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM fight_execution_sessions s WHERE '.$where,
                $parameters
            );
            $pagination = $query->pagination();
            $direction = $pagination->orderings()['latest_recorded_at'] ?? 'DESC';
            [$select, $selectParameters] = $this->selectExecutions($access);
            $sql = $select.' WHERE '.$where.' ORDER BY latest_recorded_at '.$direction;
            $sql .= ', '.$this->sql->binary('s.project_id').' ASC, '.$this->sql->binary('s.session_id').' ASC';
            $rows = $this->connection->fetchAllAssociative(
                $this->sql->limit($sql, $pagination->limit(), $pagination->offset()),
                [...$selectParameters, ...$parameters]
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
     * @inheritDoc
     */
    public function inspect(GetExecution $query, ExecutionAccess $access): ?ExecutionInspection
    {
        try {
            [$where, $parameters] = $this->sql->constraints($query->selection(), $access);
            [$select, $selectParameters] = $this->selectExecutions($access);
            $row = $this->connection->fetchAssociative(
                $select.' WHERE '.$where,
                [...$selectParameters, ...$parameters]
            );
            if ($row === false) {
                return null;
            }

            $observation = $this->sessionEvidence($row);

            return new ExecutionInspection(
                $this->view($row),
                $observation === null ? null : new ObservationView($observation),
                $this->summarize($query->selection(), $access)
            );
        } catch (Throwable $throwable) {
            throw new ExecutionReadFailed($throwable);
        }
    }

    /**
     * Returns execution SQL without multiplicative joins or concealed phase freshness
     *
     * @return array{string, list<string>}
     */
    private function selectExecutions(ExecutionAccess $access): array
    {
        $scope = 'o.project_id = s.project_id AND o.session_id = s.session_id';
        [$visible, $parameters] = $this->sql->phaseConstraints($access, 'o');
        $sql = 'SELECT s.project_id, s.session_id, s.consumer_reference, ';
        $sql .= "(SELECT COALESCE(MAX(o.recorded_at), '') FROM fight_execution_observations o WHERE ";
        $sql .= $scope.' AND '.$visible.') AS latest_recorded_at';
        $latestSession = ' FROM fight_execution_observations o WHERE '.$scope;
        $latestSession .= ' AND o.phase_id IS NULL ORDER BY o.recorded_at DESC, o.observation_id DESC, ';
        $latestSession .= $this->sql->binary('o.producer_id').' DESC LIMIT 1)';
        foreach (['payload', 'version', 'observation_id', 'producer_id', 'recorded_at'] as $field) {
            $sql .= ', (SELECT o.'.$field.$latestSession.' AS session_'.$field;
        }

        return [$sql.' FROM fight_execution_sessions s', $parameters];
    }

    /**
     * Returns only the safe typed execution projection
     *
     * @param array<string, mixed> $row
     */
    private function view(array $row): ExecutionView
    {
        $observation = $this->sessionEvidence($row);
        $latest = null;
        if ($row['latest_recorded_at'] !== '') {
            $latest = Observation::timestamp($row['latest_recorded_at'])->format('Y-m-d\TH:i:s.u\Z');
        }

        return new ExecutionView(
            SessionReference::fromStrings($row['project_id'], $row['session_id'], $row['consumer_reference']),
            $observation?->outcome() ?? ObservationOutcome::UNKNOWN,
            $latest
        );
    }

    /**
     * Returns the latest consistent session observation, never phase or activity completion
     *
     * @param array<string, mixed> $row
     */
    private function sessionEvidence(array $row): ?Observation
    {
        if ($row['session_payload'] === null) {
            return null;
        }

        $evidence = [];
        foreach (['payload', 'version', 'observation_id', 'producer_id', 'recorded_at'] as $field) {
            $evidence[$field] = $row['session_'.$field];
        }

        $observation = $this->sql->decode($evidence);
        if (
            $observation->phase() !== null
            || $observation->session()->toArray() !== [
                'project_id'         => $row['project_id'],
                'session_id'         => $row['session_id'],
                'consumer_reference' => $row['consumer_reference']
            ]
        ) {
            throw new DomainException('Invalid stored session relationship.');
        }

        return $observation;
    }
}
