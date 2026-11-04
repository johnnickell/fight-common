<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Usage;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationName;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\Basic\Decimal;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class UsageTotal
 */
final readonly class UsageTotal extends ValueObject
{
    /**
     * Constructs UsageTotal
     *
     * @phpstan-param array<string, int> $counts
     */
    private function __construct(
        private string $metric,
        private string $unit,
        private string $provenance,
        private ?string $measured,
        private array $counts
    ) {
    }

    /**
     * Creates a total preserving the measurement evidence
     */
    public static function fromMeasurement(UsageMeasurement $measurement): self
    {
        $counts = ['complete' => 0, 'partial' => 0, 'unknown' => 0, 'unavailable' => 0];
        $counts[$measurement->completeness()->value] = 1;

        return new self(
            $measurement->metric(),
            $measurement->unit(),
            $measurement->provenance(),
            $measurement->value(),
            $counts
        );
    }

    /**
     * Creates an exact total from stable structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid usage total text.');
        }

        return self::fromArray($data);
    }

    /**
     * Creates a total preserving exact amounts and explicit evidence counts
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['counts', 'measured', 'metric', 'provenance', 'unit'] || !is_array($data['counts'])) {
            throw new DomainException('Invalid usage total structure.');
        }

        $countKeys = array_keys($data['counts']);
        sort($countKeys);
        if ($countKeys !== ['complete', 'partial', 'unavailable', 'unknown']) {
            throw new DomainException('Invalid usage total counts.');
        }

        $counts = [];
        foreach (['complete', 'partial', 'unknown', 'unavailable'] as $kind) {
            $count = $data['counts'][$kind];
            if (!is_int($count) || $count < 0) {
                throw new DomainException('Invalid usage total count.');
            }

            $counts[$kind] = $count;
        }

        $hasMeasured = $counts['complete'] > 0 || $counts['partial'] > 0;
        $measured = $data['measured'];
        if (
            !array_filter($counts) || $hasMeasured !== ($measured !== null)
            || ($measured !== null && (!is_string($measured)
                || preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/D', $measured) !== 1))
        ) {
            throw new DomainException('Invalid usage total evidence.');
        }

        return new self(
            ObservationName::fromString($data['metric'])->toString(),
            ObservationReference::fromString($data['unit'])->toString(),
            ObservationReference::fromString($data['provenance'])->toString(),
            $measured === null ? null : Decimal::fromString($measured)->toString(),
            $counts
        );
    }

    /**
     * Returns stable exact total text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns structured evidence without casting decimal text to numbers
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the exact compatible grouping key
     */
    public function key(): string
    {
        return $this->metric.'|'.$this->unit.'|'.$this->provenance;
    }

    /**
     * Creates a compatible combined total without converting units or inventing missing values
     */
    public function plus(self $other): self
    {
        if ($this->key() !== $other->key()) {
            throw new DomainException('Incompatible usage totals.');
        }

        $measured = $this->measured ?? $other->measured;
        if ($this->measured !== null && $other->measured !== null) {
            $measured = Decimal::fromString($this->measured)->add(Decimal::fromString($other->measured))->toString();
        }

        $counts = $this->counts;
        foreach ($other->counts as $kind => $count) {
            $counts[$kind] += $count;
        }

        return new self($this->metric, $this->unit, $this->provenance, $measured, $counts);
    }

    /**
     * Returns measured evidence and explicit completeness counts, not a capture guarantee
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metric'     => $this->metric,
            'unit'       => $this->unit,
            'provenance' => $this->provenance,
            'measured'   => $this->measured,
            'counts'     => $this->counts
        ];
    }
}
