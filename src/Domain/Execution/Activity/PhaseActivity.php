<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Usage\UsageMeasurement;
use Fight\Common\Domain\Execution\Value\ObservationFields;

/**
 * Class PhaseActivity
 *
 * Safe bounded evidence distinct from protected bodies and Workflow state
 */
final readonly class PhaseActivity
{
    /**
     * Constructs PhaseActivity
     *
     * @phpstan-param list<UsageMeasurement> $usage
     */
    private function __construct(
        private string $kind,
        private string $summary,
        private ?string $modelId,
        private array $usage
    ) {
    }

    /**
     * Creates safe activity with optional measured usage and model identity
     *
     * @phpstan-param array<array-key, mixed> $usage
     */
    public static function create(string $kind, string $summary, ?string $modelId = null, array $usage = []): self
    {
        if (
            strlen($summary) > 512 || preg_match('//u', $summary) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $summary) === 1
            || !array_is_list($usage) || count($usage) > 32
        ) {
            throw new DomainException('Invalid phase activity.');
        }

        $snapshot = [];
        foreach ($usage as $measurement) {
            if (!$measurement instanceof UsageMeasurement || isset($snapshot[$measurement->metric()])) {
                throw new DomainException('Invalid activity usage.');
            }

            $snapshot[$measurement->metric()] = $measurement;
        }

        ksort($snapshot, SORT_STRING);

        return new self(
            ObservationFields::name($kind),
            $summary,
            $modelId === null ? null : ObservationFields::reference($modelId),
            array_values($snapshot)
        );
    }

    /**
     * Creates activity from an exact version-one representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['version'] ?? null) !== 1) {
            throw new DomainException('Unsupported activity version.');
        }

        ObservationFields::structure($data, ['version', 'kind', 'summary', 'model_id', 'usage']);
        if (
            !is_string($data['kind']) || !is_string($data['summary'])
            || ($data['model_id'] !== null && !is_string($data['model_id']))
            || !is_array($data['usage']) || !array_is_list($data['usage']) || count($data['usage']) > 32
        ) {
            throw new DomainException('Invalid activity structure.');
        }

        $usage = [];
        foreach ($data['usage'] as $measurement) {
            if (!is_array($measurement)) {
                throw new DomainException('Invalid activity usage.');
            }

            $usage[] = UsageMeasurement::fromArray($measurement);
        }

        return self::create($data['kind'], $data['summary'], $data['model_id'], $usage);
    }

    /**
     * Returns the consumer-owned kind
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * Returns the consumer-redacted safe summary
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * Returns the optional model reference, not producer or performing Agent identity
     */
    public function modelId(): ?string
    {
        return $this->modelId;
    }

    /**
     * Returns measured or explicitly missing evidence ordered by metric
     *
     * @phpstan-return list<UsageMeasurement>
     */
    public function usage(): array
    {
        return $this->usage;
    }

    /**
     * Returns versioned safe activity without protected bodies
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'  => 1,
            'kind'     => $this->kind,
            'summary'  => $this->summary,
            'model_id' => $this->modelId,
            'usage'    => array_map(
                static fn (UsageMeasurement $measurement): array => $measurement->toArray(),
                $this->usage
            )
        ];
    }
}
