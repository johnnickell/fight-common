<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Class ObservationRecorded
 *
 * Safe committed fact: no extensions, free text or protected bodies
 */
final readonly class ObservationRecorded implements Event
{
    /**
     * Constructs ObservationRecorded
     */
    private function __construct(
        public ObservationId $id,
        public string $producerId,
        public string $projectId,
        public string $sessionId,
        public ?string $phaseId,
        public DateTimeImmutable $recordedAt
    ) {
    }

    /**
     * Creates a safe fact from committed evidence
     */
    public static function fromRecorded(RecordedObservation $recorded): self
    {
        $observation = $recorded->observation;

        return new self(
            $observation->id,
            $observation->producerId,
            $observation->session->projectId,
            $observation->session->sessionId,
            $observation->phase?->phaseId,
            $recorded->recordedAt
        );
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        ObservationFields::structure($data, [
            'observation_id', 'producer_id', 'project_id', 'session_id', 'phase_id', 'recorded_at'
        ]);
        $id = ObservationFields::reference($data['observation_id']);
        if (preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $id) !== 1) {
            throw new DomainException('Invalid observation identity.');
        }

        return new self(
            ObservationId::fromString($id),
            ObservationFields::reference($data['producer_id']),
            ObservationFields::reference($data['project_id']),
            ObservationFields::reference($data['session_id']),
            $data['phase_id'] === null ? null : ObservationFields::reference($data['phase_id']),
            Observation::timestamp($data['recorded_at'])
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'observation_id' => $this->id->toString(),
            'producer_id'    => $this->producerId,
            'project_id'     => $this->projectId,
            'session_id'     => $this->sessionId,
            'phase_id'       => $this->phaseId,
            'recorded_at'    => $this->recordedAt->format('Y-m-d\TH:i:s.u\Z')
        ];
    }
}
