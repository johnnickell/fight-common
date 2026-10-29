<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Mcp\Execution\Detail\Data\DetailReference;
use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityPage;
use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityView;
use Fight\Common\Application\Mcp\Execution\Observation\Service\ActivityReader;
use Fight\Common\Application\Mcp\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Activity\PhaseActivity;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Mcp\Phase\PhaseReference;
use Throwable;

/**
 * Class DbalActivityReader
 */
final readonly class DbalActivityReader implements ActivityReader
{
    private DbalExecutionReadSupport $sql;

    /**
     * Constructs DbalActivityReader
     */
    public function __construct(private Connection $connection)
    {
        $this->sql = new DbalExecutionReadSupport($connection);
    }

    /**
     * @inheritDoc
     */
    public function list(ListPhaseActivity $query, ExecutionAccess $access): ?ActivityPage
    {
        try {
            [$scope, $parameters] = $this->sql->constraints($query->execution()->selection(), $access);
            [$visible, $phaseParameters] = $this->sql->phaseConstraints($access, 'p');
            $sql = 'SELECT p.*, s.consumer_reference FROM fight_execution_phases p';
            $sql .= ' JOIN fight_execution_sessions s ON s.project_id = p.project_id AND s.session_id = p.session_id';
            $sql .= ' WHERE '.$scope.' AND '.$visible.' AND p.phase_id = ?';
            $phase = $this->connection->fetchAssociative(
                $sql,
                [...$parameters, ...$phaseParameters, $query->phaseId()]
            );
            if ($phase === false) {
                return null;
            }

            $rows = [];
            $total = 0;
            foreach ($this->activities($phase, $query, $access) as $evidence) {
                if ($total >= $query->pagination()->offset() && count($rows) < $query->pagination()->limit()) {
                    $rows[] = $evidence;
                }

                ++$total;
            }

            $views = [];
            foreach ($rows as [$evidence, $activity]) {
                $views[] = new ActivityView($evidence, $activity, $this->details($evidence, $access));
            }

            $page = new ActivityPage($views, $total, $query->pagination()->page(), $query->pagination()->perPage());
            if (strlen(json_encode($page->toArray(), JSON_THROW_ON_ERROR)) > 1048576) {
                throw new DomainException('Activity response limit exceeded.');
            }

            return $page;
        } catch (Throwable $throwable) {
            throw new ExecutionReadFailed($throwable);
        }
    }

    /**
     * Returns bounded batches of authorized activity in immutable recording order
     *
     * @phpstan-param array<string, mixed> $phase
     *
     * @return iterable<array{Observation, PhaseActivity}>
     */
    private function activities(array $phase, ListPhaseActivity $query, ExecutionAccess $access): iterable
    {
        $project = $phase['project_id'];
        $grants = $access->activityGrants();
        [$allowed, $grantParameters] = $this->grants($grants[$project] ?? null, 'o');
        $where = 'o.project_id = ? AND o.session_id = ? AND o.phase_id = ? AND o.activity_id IS NULL AND '.$allowed;
        $parameters = [$project, $phase['session_id'], $phase['phase_id'], ...$grantParameters];
        if (isset($query->filters()['producer_id'])) {
            $where .= ' AND o.producer_id = ?';
            $parameters[] = $query->filters()['producer_id'];
        }

        $direction = $query->pagination()->orderings()['recorded_at'] ?? 'DESC';
        $comparison = $direction === 'DESC' ? '<' : '>';
        $producer = $this->sql->binary('o.producer_id');
        $last = null;
        do {
            $sql = 'SELECT o.* FROM fight_execution_observations o WHERE '.$where;
            $args = $parameters;
            if ($last !== null) {
                $sql .= ' AND (o.recorded_at '.$comparison.' ? OR (o.recorded_at = ? AND (o.observation_id ';
                $sql .= $comparison.' ? OR (o.observation_id = ? AND '.$producer.' '.$comparison.' ?))))';
                array_push($args, $last['recorded_at'], $last['recorded_at'], $last['observation_id']);
                array_push($args, $last['observation_id'], $last['producer_id']);
            }

            $sql .= ' ORDER BY o.recorded_at '.$direction.', o.observation_id '.$direction;
            $sql .= ', '.$producer.' '.$direction;
            $batch = $this->connection->fetchAllAssociative($this->sql->limit($sql, 100), $args);
            foreach ($batch as $row) {
                $last = $row;
                $evidence = $this->sql->decode($row);
                $this->validateScope($evidence, $phase);
                if ($evidence->detail() !== null) {
                    throw new DomainException('Invalid stored activity relationship.');
                }

                $activity = $evidence->activity();
                if ($activity === null) {
                    continue;
                }

                $filters = $query->filters();
                if (
                    (isset($filters['kind']) && $filters['kind'] !== $activity->kind())
                    || (isset($filters['model_id']) && $filters['model_id'] !== $activity->modelId())
                ) {
                    continue;
                }

                yield [$evidence, $activity];
            }
        } while (count($batch) === 100);
    }

    /**
     * Returns only currently permitted bounded reference metadata and row presence
     *
     * @return list<DetailReference>
     */
    private function details(Observation $activity, ExecutionAccess $access): array
    {
        $grants = $access->detailGrants();
        $project = $activity->session()->projectId();
        $ids = array_key_exists($project, $grants) ? $grants[$project] : [];
        if ($ids === []) {
            return [];
        }

        [$allowed, $parameters] = $this->grants($ids, 'd');
        $sql = 'SELECT d.*, CASE WHEN EXISTS (SELECT 1 FROM fight_execution_contents c';
        $sql .= ' WHERE c.producer_id = d.producer_id AND c.observation_id = d.observation_id)';
        $sql .= ' THEN 1 ELSE 0 END AS content_present FROM fight_execution_observations d';
        $sql .= ' WHERE d.project_id = ? AND d.session_id = ? AND d.phase_id = ?';
        $sql .= ' AND d.producer_id = ? AND d.activity_id = ? AND '.$allowed;
        $sql .= ' ORDER BY d.recorded_at DESC, d.observation_id DESC';
        $rows = $this->connection->fetchAllAssociative($this->sql->limit($sql, 33), [
            $project, $activity->session()->sessionId(), $activity->phase()?->phaseId(),
            $activity->producerId(), $activity->id()->toString(), ...$parameters
        ]);
        if (count($rows) > 32) {
            throw new DomainException('Activity detail reference limit exceeded.');
        }

        $references = [];
        foreach ($rows as $row) {
            $detail = $this->sql->decode($row);
            $capture = $detail->detail();
            if ($capture === null || !$capture->acceptsActivity($detail, $activity)) {
                throw new DomainException('Invalid stored detail relationship.');
            }

            $references[] = new DetailReference($detail, $capture, (int) $row['content_present'] === 1);
        }

        return $references;
    }

    /**
     * Returns SQL enforcing supplied producer-scoped reference constraints before decoding
     *
     * @param list<string>|null $ids
     *
     * @return array{string, list<string>}
     */
    private function grants(?array $ids, string $alias): array
    {
        if ($ids === null) {
            return ['1 = 1', []];
        }

        $parts = [];
        $parameters = [];
        foreach ($ids as $id) {
            [$producer, $uuid] = explode('/', $id, 2);
            $parts[] = '('.$alias.'.producer_id = ? AND '.$alias.'.observation_id = ?)';
            array_push($parameters, $producer, $uuid);
        }

        return [$parts === [] ? '1 = 0' : '('.implode(' OR ', $parts).')', $parameters];
    }

    /**
     * Validates stored evidence against the selected subject owner
     *
     * @phpstan-param array<string, mixed> $phase
     */
    private function validateScope(Observation $evidence, array $phase): void
    {
        $reference = PhaseReference::fromStrings(
            $phase['session_id'],
            $phase['phase_id'],
            $phase['parent_phase_id'],
            $phase['agent_id'],
            $phase['name'],
            (int) $phase['attempt']
        );
        if (
            $evidence->session()->projectId() !== $phase['project_id']
            || $evidence->session()->sessionId() !== $phase['session_id']
            || $evidence->session()->consumerReference() !== $phase['consumer_reference']
            || $evidence->phase()?->toArray() !== $reference->toArray()
        ) {
            throw new DomainException('Invalid stored activity scope.');
        }
    }
}
