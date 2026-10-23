<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Execution\Detail\Data\CapturedDetail;
use Fight\Common\Application\Execution\Detail\Data\ContentChunk;
use Fight\Common\Application\Execution\Detail\Data\DetailReference;
use Fight\Common\Application\Execution\Detail\Data\MessagePage;
use Fight\Common\Application\Execution\Detail\Service\DetailAccess;
use Fight\Common\Application\Execution\Detail\Service\DetailReader;
use Fight\Common\Application\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\DetailCapture;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Phase\PhaseReference;
use Throwable;

/**
 * Class DbalDetailReader
 */
final readonly class DbalDetailReader implements DetailReader
{
    private DbalExecutionReadSupport $sql;

    /**
     * Constructs DbalDetailReader
     */
    public function __construct(private Connection $connection)
    {
        $this->sql = new DbalExecutionReadSupport($connection);
    }

    /**
     * @inheritDoc
     */
    public function messages(ListCapturedMessages $query, DetailAccess $access): ?MessagePage
    {
        try {
            $activity = $this->activity($query->selection(), $access);
            if ($activity === null) {
                return null;
            }

            $messages = [];
            $total = 0;
            $direction = $query->pagination()->orderings()['recorded_at'] ?? 'DESC';
            foreach ($this->details($activity, $access, $direction) as [$detail, $capture, $present]) {
                if ($capture->kind() !== DetailKind::MESSAGE) {
                    continue;
                }

                if ($total >= $query->pagination()->offset() && count($messages) < $query->pagination()->limit()) {
                    $messages[] = $this->view($detail, $capture, $present, $access, 0, 1024);
                }

                ++$total;
            }

            return new MessagePage($messages, $total, $query->pagination()->page(), $query->pagination()->perPage());
        } catch (Throwable $throwable) {
            throw new ExecutionReadFailed($throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function get(GetCapturedDetail $query, DetailAccess $access): ?CapturedDetail
    {
        try {
            $activity = $this->activity($query->selection(), $access);
            if ($activity === null) {
                return null;
            }

            $details = $this->details($activity, $access, 'DESC', $query->detailId()->toString());
            foreach ($details as [$detail, $capture, $present]) {
                if ($capture->kind() === $query->kind()) {
                    return $this->view($detail, $capture, $present, $access, $query->offset(), $query->length());
                }
            }

            return null;
        } catch (Throwable $throwable) {
            throw new ExecutionReadFailed($throwable);
        }
    }

    /**
     * Returns an admitted activity only after intersecting protected scope and metadata authority
     */
    private function activity(DetailSelection $selection, DetailAccess $access): ?Observation
    {
        $scope = $access->scope();
        $project = $selection->execution()->toArray()['project_id'];
        $grants = $scope->detailGrants();
        if (!array_key_exists($project, $grants) || $grants[$project] === []) {
            return null;
        }

        [$where, $parameters] = $this->sql->constraints($selection->execution()->selection(), $scope);
        [$phases, $phaseParameters] = $this->sql->phaseConstraints($scope, 'p');
        $sql = 'SELECT o.*, s.consumer_reference, p.parent_phase_id, p.agent_id, p.name, p.attempt';
        $sql .= ' FROM fight_execution_observations o';
        $sql .= ' JOIN fight_execution_sessions s ON s.project_id = o.project_id AND s.session_id = o.session_id';
        $sql .= ' JOIN fight_execution_phases p ON p.project_id = o.project_id';
        $sql .= ' AND p.session_id = o.session_id AND p.phase_id = o.phase_id';
        $sql .= ' WHERE '.$where.' AND '.$phases.' AND o.phase_id = ? AND o.producer_id = ?';
        $sql .= ' AND o.observation_id = ? AND o.activity_id IS NULL';
        $row = $this->connection->fetchAssociative($sql, [
            ...$parameters, ...$phaseParameters, $selection->phaseId(),
            $selection->producerId(), $selection->activityId()->toString()
        ]);
        if ($row === false) {
            return null;
        }

        $activity = $this->sql->decode($row);
        $phase = PhaseReference::fromStrings(
            $row['session_id'],
            $row['phase_id'],
            $row['parent_phase_id'],
            $row['agent_id'],
            $row['name'],
            (int) $row['attempt']
        );
        if (
            $activity->activity() === null || $activity->detail() !== null
            || $activity->session()->projectId() !== $project
            || $activity->session()->sessionId() !== $row['session_id']
            || $activity->session()->consumerReference() !== $row['consumer_reference']
            || $activity->phase()?->toArray() !== $phase->toArray()
        ) {
            throw new DomainException('Invalid stored protected activity relationship.');
        }

        return $activity;
    }

    /**
     * Returns metadata-authorized detail rows in finite immutable recording-order batches
     *
     * @return iterable<array{Observation, DetailCapture, bool}>
     */
    private function details(
        Observation $activity,
        DetailAccess $access,
        string $direction,
        ?string $id = null
    ): iterable {
        $project = $activity->session()->projectId();
        $grants = $access->scope()->detailGrants()[$project];
        $where = 'd.project_id = ? AND d.session_id = ? AND d.phase_id = ? AND d.producer_id = ? AND d.activity_id = ?';
        $parameters = [
            $project, $activity->session()->sessionId(), $activity->phase()?->phaseId(),
            $activity->producerId(), $activity->id()->toString()
        ];
        if ($grants !== null) {
            $parts = [];
            foreach ($grants as $grant) {
                [$producer, $uuid] = explode('/', $grant, 2);
                $parts[] = '(d.producer_id = ? AND d.observation_id = ?)';
                array_push($parameters, $producer, $uuid);
            }

            $where .= ' AND ('.implode(' OR ', $parts).')';
        }

        if ($id !== null) {
            $where .= ' AND d.observation_id = ?';
            $parameters[] = $id;
        }

        $comparison = $direction === 'DESC' ? '<' : '>';
        $last = null;
        do {
            $sql = 'SELECT d.*, CASE WHEN EXISTS (SELECT 1 FROM fight_execution_contents c';
            $sql .= ' WHERE c.producer_id = d.producer_id AND c.observation_id = d.observation_id)';
            $sql .= ' THEN 1 ELSE 0 END AS content_present FROM fight_execution_observations d WHERE '.$where;
            $args = $parameters;
            if ($last !== null) {
                $sql .= ' AND (d.recorded_at '.$comparison.' ? OR (d.recorded_at = ?';
                $sql .= ' AND d.observation_id '.$comparison.' ?))';
                array_push($args, $last['recorded_at'], $last['recorded_at'], $last['observation_id']);
            }

            $sql .= ' ORDER BY d.recorded_at '.$direction.', d.observation_id '.$direction;
            $rows = $this->connection->fetchAllAssociative($this->sql->limit($sql, 100), $args);
            foreach ($rows as $row) {
                $last = $row;
                $detail = $this->sql->decode($row);
                if ($detail->detail() === null || !$detail->detail()->acceptsActivity($detail, $activity)) {
                    throw new DomainException('Invalid stored protected detail relationship.');
                }

                yield [$detail, $detail->detail(), (int) $row['content_present'] === 1];
            }
        } while (count($rows) === 100);
    }

    /**
     * Returns permitted metadata and separately admitted bounded bytes without retaining a read lease
     */
    private function view(
        Observation $detail,
        DetailCapture $capture,
        bool $present,
        DetailAccess $access,
        int $offset,
        int $length
    ): CapturedDetail {
        $permitted = $access->permitsBody(
            $detail->session()->projectId(),
            $detail->producerId(),
            $detail->id()->toString()
        );
        $chunk = null;
        if ($permitted) {
            $row = $this->connection->fetchAssociative(
                'SELECT payload FROM fight_execution_contents WHERE producer_id = ? AND observation_id = ?',
                [$detail->producerId(), $detail->id()->toString()]
            );
            $present = $row !== false;
            if ($row !== false) {
                $data = json_decode($row['payload'], true, 8, JSON_THROW_ON_ERROR);
                if (!is_array($data)) {
                    throw new DomainException('Invalid stored protected content.');
                }

                $body = ProtectedContent::fromArray($capture, $data)->body();
                if ($body !== null) {
                    $chunk = ContentChunk::fromBody($body, $offset, $length);
                }
            }
        }

        return new CapturedDetail(new DetailReference($detail, $capture, $present), $permitted, $chunk);
    }
}
