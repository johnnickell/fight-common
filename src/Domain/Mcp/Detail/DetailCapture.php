<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Value\ObservationFields;

/**
 * Class DetailCapture
 *
 * Safe capture metadata contains neither bodies nor body comparison material
 */
final readonly class DetailCapture
{
    /**
     * Constructs DetailCapture
     */
    private function __construct(
        private ObservationId $activityId,
        private DetailKind $kind,
        private string $contentType,
        private CaptureCompleteness $completeness,
        private ?OutputSegment $segment
    ) {
    }

    /**
     * Creates version-one capture metadata linked to the same producer's activity
     */
    public static function create(
        ObservationId $activityId,
        DetailKind $kind,
        string $contentType,
        CaptureCompleteness $completeness,
        ?OutputSegment $segment = null
    ): self {
        if (
            strlen($contentType) > 128
            || preg_match('/\A[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*\z/D', $contentType) !== 1
            || ($kind === DetailKind::PROCESS_OUTPUT) !== ($segment !== null)
        ) {
            throw new DomainException('Invalid detail capture metadata.');
        }

        return new self($activityId, $kind, $contentType, $completeness, $segment);
    }

    /**
     * Creates supported structured capture metadata
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== 1) {
            throw new DomainException('Unsupported detail capture version.');
        }

        ObservationFields::structure($data, [
            'version', 'activity_id', 'kind', 'content_type', 'completeness', 'segment'
        ]);
        if (
            !is_string($data['activity_id'])
            || preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $data['activity_id']) !== 1
            || !is_string($data['kind']) || !is_string($data['content_type']) || !is_string($data['completeness'])
            || ($data['segment'] !== null && !is_array($data['segment']))
        ) {
            throw new DomainException('Invalid detail capture metadata.');
        }

        $kind = DetailKind::tryFrom($data['kind']);
        $completeness = CaptureCompleteness::tryFrom($data['completeness']);
        if ($kind === null || $completeness === null) {
            throw new DomainException('Invalid detail capture metadata.');
        }

        return self::create(
            ObservationId::fromString($data['activity_id']),
            $kind,
            $data['content_type'],
            $completeness,
            $data['segment'] === null ? null : OutputSegment::fromArray($data['segment'])
        );
    }

    /**
     * Returns whether the target is activity within the exact submitted scope
     */
    public function acceptsActivity(Observation $detail, Observation $activity): bool
    {
        return $activity->activity() !== null && $this->activityId->equals($activity->id())
            && $detail->producerId() === $activity->producerId()
            && $detail->session()->toArray() === $activity->session()->toArray()
            && $detail->phase()?->toArray() === $activity->phase()?->toArray();
    }

    /**
     * Validates bounded UTF-8 content against honest capture metadata
     */
    public function validateBody(?string $body): void
    {
        $captured = in_array($this->completeness, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true);
        $limit = $this->kind === DetailKind::PROCESS_OUTPUT ? 16384 : 65536;
        if (
            $captured !== ($body !== null)
            || ($body !== null && (strlen($body) > $limit || preg_match('//u', $body) !== 1))
            || ($body !== null && $this->segment !== null && strlen($body) > 2147483647 - $this->segment->offset())
        ) {
            throw new DomainException('Invalid or oversized protected content.');
        }
    }

    /**
     * Returns the linked activity identity
     */
    public function activityId(): ObservationId
    {
        return $this->activityId;
    }

    /**
     * Returns the capture kind
     */
    public function kind(): DetailKind
    {
        return $this->kind;
    }

    /**
     * Returns the declared media type
     */
    public function contentType(): string
    {
        return $this->contentType;
    }

    /**
     * Returns supplied capture completeness independently of storage availability
     */
    public function completeness(): CaptureCompleteness
    {
        return $this->completeness;
    }

    /**
     * Returns optional output coordinates
     */
    public function segment(): ?OutputSegment
    {
        return $this->segment;
    }

    /**
     * Returns safe versioned metadata
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'      => 1,
            'activity_id'  => $this->activityId->toString(),
            'kind'         => $this->kind->value,
            'content_type' => $this->contentType,
            'completeness' => $this->completeness->value,
            'segment'      => $this->segment?->toArray()
        ];
    }
}
