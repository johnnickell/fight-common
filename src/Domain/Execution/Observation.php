<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class Observation
 *
 * Versioned evidence, never authoritative execution state
 */
final readonly class Observation
{
    /**
     * Constructs Observation
     */
    private function __construct(
        private ObservationId $id,
        private string $producerId,
        private SessionReference $session,
        private ?PhaseReference $phase,
        private DateTimeImmutable $occurredAt,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $endedAt,
        private ObservationOutcome $outcome,
        private ObservationExtensions $extensions
    ) {
    }

    /**
     * Creates a version-one observation
     */
    public static function create(
        ObservationId $id,
        string $producerId,
        SessionReference $session,
        ?PhaseReference $phase,
        DateTimeImmutable $occurredAt,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $endedAt = null,
        ObservationOutcome $outcome = ObservationOutcome::UNKNOWN,
        ?ObservationExtensions $extensions = null
    ): self {
        if ($phase !== null && $phase->sessionId() !== $session->sessionId()) {
            throw new DomainException('Invalid observation relationship.');
        }

        if ($startedAt !== null && $endedAt !== null && $endedAt < $startedAt) {
            throw new DomainException('Invalid observation timing.');
        }

        return new self(
            $id,
            ObservationFields::reference($producerId),
            $session,
            $phase,
            self::utc($occurredAt),
            $startedAt === null ? null : self::utc($startedAt),
            $endedAt === null ? null : self::utc($endedAt),
            $outcome,
            $extensions ?? ObservationExtensions::fromArray([])
        );
    }

    /**
     * Creates an observation from an exact supported representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== 1) {
            throw new DomainException('Unsupported observation version.');
        }

        ObservationFields::structure($data, [
            'version', 'observation_id', 'producer_id', 'session', 'phase',
            'occurred_at', 'started_at', 'ended_at', 'outcome', 'extensions'
        ]);
        if (
            !is_string($data['observation_id'])
            || preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $data['observation_id']) !== 1
            || !is_array($data['session']) || !is_array($data['extensions'])
            || ($data['phase'] !== null && !is_array($data['phase']))
            || !is_string($data['outcome'])
        ) {
            throw new DomainException('Invalid observation structure.');
        }

        $outcome = ObservationOutcome::tryFrom($data['outcome']);
        if ($outcome === null) {
            throw new DomainException('Invalid observation outcome.');
        }

        return self::create(
            ObservationId::fromString($data['observation_id']),
            ObservationFields::reference($data['producer_id']),
            SessionReference::fromArray($data['session']),
            $data['phase'] === null ? null : PhaseReference::fromArray($data['phase']),
            self::timestamp($data['occurred_at']),
            $data['started_at'] === null ? null : self::timestamp($data['started_at']),
            $data['ended_at'] === null ? null : self::timestamp($data['ended_at']),
            $outcome,
            ObservationExtensions::fromArray($data['extensions'])
        );
    }

    /**
     * Returns an exact canonical UTC timestamp
     */
    public static function timestamp(mixed $value): DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new DomainException('Invalid observation timestamp.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d\TH:i:s.u\Z') !== $value) {
            throw new DomainException('Invalid observation timestamp.');
        }

        return self::utc($date);
    }

    /**
     * Returns the observation identity
     */
    public function id(): ObservationId
    {
        return $this->id;
    }

    /**
     * Returns the instrumentation producer reference
     */
    public function producerId(): string
    {
        return $this->producerId;
    }

    /**
     * Returns the session reference
     */
    public function session(): SessionReference
    {
        return $this->session;
    }

    /**
     * Returns the optional phase reference
     */
    public function phase(): ?PhaseReference
    {
        return $this->phase;
    }

    /**
     * Returns the occurrence time
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Returns the optional observed start time
     */
    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * Returns the optional observed end time
     */
    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    /**
     * Returns the reported outcome
     */
    public function outcome(): ObservationOutcome
    {
        return $this->outcome;
    }

    /**
     * Returns the bounded extension snapshot
     */
    public function extensions(): ObservationExtensions
    {
        return $this->extensions;
    }

    /**
     * Returns the versioned submitted data without recording time
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'        => 1,
            'observation_id' => $this->id->toString(),
            'producer_id'    => $this->producerId,
            'session'        => $this->session->toArray(),
            'phase'          => $this->phase?->toArray(),
            'occurred_at'    => $this->occurredAt->format('Y-m-d\TH:i:s.u\Z'),
            'started_at'     => $this->startedAt?->format('Y-m-d\TH:i:s.u\Z'),
            'ended_at'       => $this->endedAt?->format('Y-m-d\TH:i:s.u\Z'),
            'outcome'        => $this->outcome->value,
            'extensions'     => $this->extensions->toArray()
        ];
    }

    /**
     * Returns deterministic version-one comparison bytes
     */
    public function comparison(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Returns a bounded UTC instant without changing supplied precision
     */
    private static function utc(DateTimeImmutable $date): DateTimeImmutable
    {
        $date = $date->setTimezone(new DateTimeZone('UTC'));
        if ((int) $date->format('Y') < 1 || (int) $date->format('Y') > 9999) {
            throw new DomainException('Observation timestamp exceeds bounds.');
        }

        return $date;
    }
}
