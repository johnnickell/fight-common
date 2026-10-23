<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Observation\ObservationRepository;

/**
 * Class DbalObservationRepository
 */
final readonly class DbalObservationRepository implements ObservationRepository
{
    /**
     * Constructs DbalObservationRepository
     */
    public function __construct(private DbalRecordingTransaction $transaction)
    {
    }

    /**
     * @inheritDoc
     */
    public function getById(string $producerId, ObservationId $id): ?Observation
    {
        $sql = 'SELECT version, payload, recorded_at FROM fight_execution_observations';
        $sql .= ' WHERE producer_id = ? AND observation_id = ?';
        $row = $this->transaction->connection()->fetchAssociative($sql, [$producerId, $id->toString()]);
        if ($row === false) {
            return null;
        }

        if ((int) $row['version'] !== 1) {
            throw new DomainException('Unsupported observation version.');
        }

        $data = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? null) !== (int) $row['version']) {
            throw new DomainException('Invalid stored observation structure.');
        }

        return Observation::fromArray($data)->recorded(Observation::timestamp($row['recorded_at']));
    }

    /**
     * @inheritDoc
     */
    public function add(Observation $observation): void
    {
        $this->transaction->requireOwnedTransaction();
        $recordedAt = $observation->recordedAt();
        $this->transaction->connection()->insert('fight_execution_observations', [
            'producer_id'    => $observation->producerId(),
            'observation_id' => $observation->id()->toString(),
            'project_id'     => $observation->session()->projectId(),
            'session_id'     => $observation->session()->sessionId(),
            'phase_id'       => $observation->phase()?->phaseId(),
            'activity_id'    => $observation->detail()?->activityId()->toString(),
            'version'        => $observation->toArray()['version'],
            'payload'        => $observation->comparison(),
            'recorded_at'    => $recordedAt->format('Y-m-d\TH:i:s.u\Z')
        ]);
    }
}
