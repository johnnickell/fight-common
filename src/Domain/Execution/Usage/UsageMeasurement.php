<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Usage;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Value\ObservationFields;
use Fight\Common\Domain\Value\Basic\Decimal;

/**
 * Class UsageMeasurement
 *
 * Consumer-reported exact measurement, never an estimate or billing calculation
 */
final readonly class UsageMeasurement
{
    /**
     * Constructs UsageMeasurement
     */
    private function __construct(
        private string $metric,
        private string $unit,
        private string $provenance,
        private UsageCompleteness $completeness,
        private ?string $value
    ) {
    }

    /**
     * Creates a bounded exact nonnegative measurement or explicit missing evidence
     */
    public static function create(
        string $metric,
        string $unit,
        string $provenance,
        UsageCompleteness $completeness,
        ?string $value
    ): self {
        $measured = $completeness === UsageCompleteness::COMPLETE || $completeness === UsageCompleteness::PARTIAL;
        if ($measured !== ($value !== null)) {
            throw new DomainException('Invalid usage completeness.');
        }

        if ($value !== null) {
            if (strlen($value) > 128 || preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/D', $value) !== 1) {
                throw new DomainException('Invalid usage value.');
            }

            $value = Decimal::fromString($value)->toString();
        }

        return new self(
            ObservationFields::name($metric),
            ObservationFields::reference($unit),
            ObservationFields::reference($provenance),
            $completeness,
            $value
        );
    }

    /**
     * Creates a measurement from its exact version-one representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== 1) {
            throw new DomainException('Unsupported usage version.');
        }

        ObservationFields::structure($data, ['version', 'metric', 'unit', 'provenance', 'completeness', 'value']);
        if (
            !is_string($data['metric']) || !is_string($data['unit']) || !is_string($data['provenance'])
            || !is_string($data['completeness']) || ($data['value'] !== null && !is_string($data['value']))
        ) {
            throw new DomainException('Invalid usage structure.');
        }

        $completeness = UsageCompleteness::tryFrom($data['completeness']);
        if ($completeness === null) {
            throw new DomainException('Invalid usage completeness.');
        }

        return self::create($data['metric'], $data['unit'], $data['provenance'], $completeness, $data['value']);
    }

    /**
     * Returns the consumer-owned metric name
     */
    public function metric(): string
    {
        return $this->metric;
    }

    /**
     * Returns the explicitly supplied unit
     */
    public function unit(): string
    {
        return $this->unit;
    }

    /**
     * Returns the evidence source reference, not performing identity or admission
     */
    public function provenance(): string
    {
        return $this->provenance;
    }

    /**
     * Returns the reported completeness
     */
    public function completeness(): UsageCompleteness
    {
        return $this->completeness;
    }

    /**
     * Returns the exact measured value or null without fabricating zero
     */
    public function value(): ?string
    {
        return $this->value;
    }

    /**
     * Returns the versioned measurement
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'      => 1,
            'metric'       => $this->metric,
            'unit'         => $this->unit,
            'provenance'   => $this->provenance,
            'completeness' => $this->completeness->value,
            'value'        => $this->value
        ];
    }
}
