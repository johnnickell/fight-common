<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Observation;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class ObservationOccurrence
 *
 * Reported UTC time retaining zero through six fractional digits
 */
final readonly class ObservationOccurrence
{
    /**
     * Constructs ObservationOccurrence
     */
    private function __construct(private string $value, private DateTimeImmutable $instant)
    {
    }

    /**
     * Creates an occurrence without inventing source precision
     */
    public static function fromString(string $value): self
    {
        $pattern = '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.([0-9]{1,6}))?Z\z/D';
        if (preg_match($pattern, $value, $parts) !== 1) {
            throw new DomainException('Invalid observation occurrence.');
        }

        $fraction = $parts[1] ?? '';
        $canonical = substr($value, 0, 19).'.'.str_pad($fraction, 6, '0').'Z';

        return new self($value, Observation::timestamp($canonical));
    }

    /**
     * Returns the reported text with its original precision
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Returns the instant without asserting microsecond source precision
     */
    public function instant(): DateTimeImmutable
    {
        return $this->instant;
    }
}
