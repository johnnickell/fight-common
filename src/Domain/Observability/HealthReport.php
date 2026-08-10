<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Observability;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

/**
 * Class HealthReport
 */
final readonly class HealthReport implements JsonSerializable
{
    /**
     * Constructs HealthReport
     *
     * @param HealthStatus $overall
     * @param array<HealthResult> $results
     * @param DateTimeImmutable $timestamp
     */
    public function __construct(
        private HealthStatus $overall,
        private array $results,
        private DateTimeImmutable $timestamp
    ) {
    }

    /**
     * Creates a report from a set of results, computing the overall status
     *
     * @param array<HealthResult> $results
     */
    public static function fromResults(array $results): static
    {
        $overall = HealthStatus::healthy();

        foreach ($results as $result) {
            $overall = $overall->worst($result->status());
        }

        return new static($overall, $results, new DateTimeImmutable());
    }

    /**
     * Retrieves the overall status
     */
    public function overall(): HealthStatus
    {
        return $this->overall;
    }

    /**
     * Retrieves per-check results
     *
     * @return array<HealthResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Retrieves the report timestamp
     */
    public function timestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Checks if overall status is healthy
     */
    public function isHealthy(): bool
    {
        return $this->overall->isHealthy();
    }

    /**
     * Retrieves an array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'    => $this->overall->toString(),
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'checks'    => array_map(fn(HealthResult $r): array => $r->toArray(), $this->results)
        ];
    }

    /**
     * Returns data for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
