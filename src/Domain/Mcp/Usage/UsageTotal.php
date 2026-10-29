<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Usage;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\Decimal;

/**
 * Class UsageTotal
 */
final readonly class UsageTotal
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
