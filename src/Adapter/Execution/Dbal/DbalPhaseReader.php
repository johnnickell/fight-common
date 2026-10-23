<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Execution\Observation\Data\ObservationView;
use Fight\Common\Application\Execution\Phase\Data\PhasePage;
use Fight\Common\Application\Execution\Phase\Data\PhaseView;
use Fight\Common\Application\Execution\Phase\Service\PhaseReader;
use Fight\Common\Application\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Phase\PhaseReference;
use Fight\Common\Domain\Execution\Phase\Query\ListExecutionPhases;
use Throwable;

/**
 * Class DbalPhaseReader
 */
final readonly class DbalPhaseReader implements PhaseReader
{
    private DbalExecutionReadSupport $sql;

    /**
     * Constructs DbalPhaseReader
     */
    public function __construct(private Connection $connection)
    {
        $this->sql = new DbalExecutionReadSupport($connection);
    }

    /**
     * @inheritDoc
     */
    public function list(ListExecutionPhases $query, ExecutionAccess $access): ?PhasePage
    {
        try {
            [$sessionWhere, $sessionParameters] = $this->sql->constraints($query->execution()->selection(), $access);
            $session = $this->connection->fetchAssociative(
                'SELECT s.* FROM fight_execution_sessions s WHERE '.$sessionWhere,
                $sessionParameters
            );
            if ($session === false) {
                return null;
            }

            [$visible, $visibleParameters] = $this->sql->phaseConstraints($access, 'p');
            $where = 'p.project_id = ? AND p.session_id = ? AND '.$visible;
            $parameters = [$session['project_id'], $session['session_id'], ...$visibleParameters];
            foreach ($query->filters() as $field => $value) {
                $where .= ' AND p.'.$field.' = ?';
                $parameters[] = $value;
            }

            $total = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM fight_execution_phases p WHERE '.$where,
                $parameters
            );
            [$parentWhere, $parentParameters] = $this->sql->phaseConstraints($access, 'parent');
            $sql = 'SELECT p.*, parent.phase_id AS visible_parent';
            $scope = ' FROM fight_execution_observations o WHERE o.project_id = p.project_id';
            $scope .= ' AND o.session_id = p.session_id AND o.phase_id = p.phase_id';
            $scope .= ' ORDER BY o.recorded_at DESC, o.observation_id DESC, ';
            $scope .= $this->sql->binary('o.producer_id').' DESC LIMIT 1)';
            foreach (['recorded_at', 'observation_id', 'producer_id'] as $field) {
                $expression = "COALESCE((SELECT o.".$field.$scope.", '')";
                if ($field === 'producer_id') {
                    $expression = $this->sql->binary($expression);
                }

                $sql .= ', '.$expression.' AS latest_'.$field;
            }

            $sql .= ' FROM fight_execution_phases p LEFT JOIN fight_execution_phases parent';
            $sql .= ' ON parent.project_id = p.project_id AND parent.session_id = p.session_id';
            $sql .= ' AND parent.phase_id = p.parent_phase_id AND '.$parentWhere;
            $direction = $query->pagination()->orderings()['latest_recorded_at'] ?? 'DESC';
            $sql .= ' WHERE '.$where.' ORDER BY latest_recorded_at '.$direction;
            $sql .= ', latest_observation_id '.$direction.', latest_producer_id '.$direction;
            $sql .= ', '.$this->sql->binary('p.phase_id').' ASC';
            $rows = $this->connection->fetchAllAssociative(
                $this->sql->limit($sql, $query->pagination()->limit(), $query->pagination()->offset()),
                [...$parentParameters, ...$parameters]
            );
            $views = [];
            foreach ($rows as $row) {
                $views[] = $this->view($row, $session['consumer_reference'], $access);
            }

            return new PhasePage($views, $total, $query->pagination()->page(), $query->pagination()->perPage());
        } catch (Throwable $throwable) {
            throw new ExecutionReadFailed($throwable);
        }
    }

    /**
     * Returns only authorized consistent phase relationships and bounded evidence metadata
     *
     * @param array<string, mixed> $row
     */
    private function view(array $row, string $consumerReference, ExecutionAccess $access): PhaseView
    {
        $phase = PhaseReference::fromStrings(
            $row['session_id'],
            $row['phase_id'],
            $row['parent_phase_id'],
            $row['agent_id'],
            $row['name'],
            (int) $row['attempt']
        );
        $latestState = null;
        $models = [];
        $observations = $this->sql->observations($row['project_id'], $row['session_id'], $access, $row['phase_id']);
        foreach ($observations as $evidence) {
            if (
                $evidence->phase()?->toArray() !== $phase->toArray()
                || $evidence->session()->consumerReference() !== $consumerReference
            ) {
                throw new DomainException('Invalid stored phase relationship.');
            }

            $model = $evidence->activity()?->modelId();
            if ($model !== null) {
                $models[$model] = $model;
                if (count($models) > 128) {
                    throw new DomainException('Phase model reference limit exceeded.');
                }
            }

            if ($evidence->activity() !== null || $evidence->detail() !== null) {
                continue;
            }

            if (
                $latestState === null || $evidence->compareRecordingTo($latestState) > 0
                || ($evidence->compareRecordingTo($latestState) === 0
                    && strcmp($evidence->producerId(), $latestState->producerId()) > 0)
            ) {
                $latestState = $evidence;
            }
        }

        sort($models, SORT_STRING);
        $safePhase = PhaseReference::fromStrings(
            $phase->sessionId(),
            $phase->phaseId(),
            $row['visible_parent'],
            $phase->agentId(),
            $phase->name(),
            $phase->attempt()
        );
        $latest = null;
        if ($row['latest_recorded_at'] !== '') {
            $latest = Observation::timestamp($row['latest_recorded_at'])->format('Y-m-d\TH:i:s.u\Z');
        }

        return new PhaseView(
            $safePhase,
            $latestState === null ? null : new ObservationView($latestState),
            $latest,
            $models
        );
    }
}
