<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Common\Domain\Mcp\Session\SessionId;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class DetailSelection
 */
final readonly class DetailSelection extends ValueObject
{
    private SessionId $session;
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
        $this->session = SessionId::fromStrings($projectId, $sessionId);
        $this->phaseId = ObservationReference::fromString($phaseId)->toString();
        $this->producerId = ObservationReference::fromString($producerId)->toString();
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
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['activity_id', 'phase_id', 'producer_id', 'project_id', 'session_id']) {
            throw new DomainException('Invalid detail selection structure.');
        }

        return self::create(
            ObservationReference::fromString($data['project_id'])->toString(),
            ObservationReference::fromString($data['session_id'])->toString(),
            ObservationReference::fromString($data['phase_id'])->toString(),
            ObservationReference::fromString($data['producer_id'])->toString(),
            ObservationReference::fromString($data['activity_id'])->toString()
        );
    }

    /**
     * Creates an exact selection from stable text without granting access
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid detail selection text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable selection text without grants
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns structured selection data rather than encoded text
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns whether retained detail belongs to the complete selected relationship
     */
    public function acceptsDetail(Observation $detail, DetailKind $kind): bool
    {
        $capture = $detail->detail();
        $session = SessionId::fromStrings($detail->session()->projectId(), $detail->session()->sessionId());

        return $capture !== null && $capture->kind() === $kind
            && $capture->activityId()->equals($this->activityId)
            && $detail->producerId() === $this->producerId
            && $this->session->equals($session)
            && $detail->phase()?->phaseId() === $this->phaseId;
    }

    /**
     * Returns the exact selected execution
     */
    public function execution(): GetExecution
    {
        return new GetExecution($this->session->projectId(), $this->session->sessionId());
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
        return [
            'project_id'  => $this->session->projectId(),
            'session_id'  => $this->session->sessionId(),
            'phase_id'    => $this->phaseId,
            'producer_id' => $this->producerId,
            'activity_id' => $this->activityId->toString()
        ];
    }
}
