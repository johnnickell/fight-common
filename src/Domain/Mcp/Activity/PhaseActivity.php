<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Usage\UsageMeasurement;
use Fight\Common\Domain\Mcp\Value\ObservationName;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class PhaseActivity
 *
 * Safe bounded evidence distinct from protected bodies and Workflow state
 */
final readonly class PhaseActivity extends ValueObject
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
        private array $usage,
        private ?ActivityExecution $execution
    ) {
    }

    /**
     * Creates safe activity with optional measured usage, model identity and typed execution
     *
     * @phpstan-param array<array-key, mixed> $usage
     */
    public static function create(
        string $kind,
        string $summary,
        ?string $modelId = null,
        array $usage = [],
        ?ActivityExecution $execution = null
    ): self {
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
            ObservationName::fromString($kind)->toString(),
            $summary,
            $modelId === null ? null : ObservationReference::fromString($modelId)->toString(),
            array_values($snapshot),
            $execution
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

        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['execution', 'kind', 'model_id', 'summary', 'usage', 'version']) {
            throw new DomainException('Invalid activity structure.');
        }

        if (
            !is_string($data['kind']) || !is_string($data['summary'])
            || ($data['model_id'] !== null && !is_string($data['model_id']))
            || ($data['execution'] !== null && !is_array($data['execution']))
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

        return self::create(
            $data['kind'],
            $data['summary'],
            $data['model_id'],
            $usage,
            $data['execution'] === null ? null : ActivityExecution::fromArray($data['execution'])
        );
    }

    /**
     * Creates activity from stable structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid activity text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable structured activity text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns structured safe activity without protected bodies
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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
     * Returns optional typed execution facts without business authority
     */
    public function execution(): ?ActivityExecution
    {
        return $this->execution;
    }

    /**
     * Returns versioned safe activity without protected bodies
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'   => 1,
            'kind'      => $this->kind,
            'summary'   => $this->summary,
            'model_id'  => $this->modelId,
            'execution' => $this->execution?->toArray(),
            'usage'     => array_map(
                static fn (UsageMeasurement $measurement): array => $measurement->toArray(),
                $this->usage
            )
        ];
    }
}
