<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;
use Fight\Common\Domain\Execution\Value\ObservationFields;

/**
 * Class DetailSelection
 */
final readonly class DetailSelection
{
    private GetExecution $execution;
    private string $phaseId;
    private string $producerId;
    private ObservationId $activityId;

    /**
     * Constructs DetailSelection
     */
    private function __construct(
        string $projectId,
        string $sessionId,
        string $phaseId,
        string $producerId,
        string $activityId
    ) {
        $this->execution = new GetExecution($projectId, $sessionId);
        $this->phaseId = ObservationFields::reference($phaseId);
        $this->producerId = ObservationFields::reference($producerId);
        if (preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $activityId) !== 1) {
            throw new DomainException('Invalid detail activity identity.');
        }

        $this->activityId = ObservationId::fromString($activityId);
    }

    /**
     * Creates an exact activity selection without read authority
     */
    public static function create(
        string $projectId,
        string $sessionId,
        string $phaseId,
        string $producerId,
        string $activityId
    ): self {
        return new self($projectId, $sessionId, $phaseId, $producerId, $activityId);
    }

    /**
     * Creates a strict selection representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        ObservationFields::structure($data, ['project_id', 'session_id', 'phase_id', 'producer_id', 'activity_id']);

        return self::create(
            ObservationFields::reference($data['project_id']),
            ObservationFields::reference($data['session_id']),
            ObservationFields::reference($data['phase_id']),
            ObservationFields::reference($data['producer_id']),
            ObservationFields::reference($data['activity_id'])
        );
    }

    /**
     * Returns whether retained detail belongs to the complete selected relationship
     */
    public function acceptsDetail(Observation $detail, DetailKind $kind): bool
    {
        $capture = $detail->detail();
        $execution = $this->execution->toArray();

        return $capture !== null && $capture->kind() === $kind
            && $capture->activityId()->equals($this->activityId)
            && $detail->producerId() === $this->producerId
            && $detail->session()->projectId() === $execution['project_id']
            && $detail->session()->sessionId() === $execution['session_id']
            && $detail->phase()?->phaseId() === $this->phaseId;
    }

    /**
     * Returns the exact selected execution
     */
    public function execution(): GetExecution
    {
        return $this->execution;
    }

    /**
     * Returns the selected phase
     */
    public function phaseId(): string
    {
        return $this->phaseId;
    }

    /**
     * Returns the selected producer
     */
    public function producerId(): string
    {
        return $this->producerId;
    }

    /**
     * Returns the linked activity identity
     */
    public function activityId(): ObservationId
    {
        return $this->activityId;
    }

    /**
     * Returns the strict selection representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->execution->toArray() + [
            'phase_id'    => $this->phaseId,
            'producer_id' => $this->producerId,
            'activity_id' => $this->activityId->toString()
        ];
    }
}
