<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class InstantRange
 *
 * Represents a half-open interval of exact instants, independent of endpoint timezone labels
 */
final readonly class InstantRange extends ValueObject
{
    /**
     * Constructs InstantRange
     *
     * @throws DomainException When the start follows the end by instant
     */
    private function __construct(private DateTime $start, private DateTime $end)
    {
        if ($start->compareInstantTo($end) > 0) {
            throw new DomainException('Instant range start follows end');
        }
    }

    /**
     * Creates a half-open range from ordered instant endpoints
     *
     * @throws DomainException When the start follows the end by instant
     */
    public static function fromInstants(DateTime $start, DateTime $end): self
    {
        return new self($start, $end);
    }

    /**
     * Creates a range from its exact versioned instant coordinates
     *
     * @throws DomainException When the frame, coordinates or ordering are invalid
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/\Ainstant-range:v1:([A-Za-z0-9+\/=]+):([A-Za-z0-9+\/=]+)\z/', $value, $matches) !== 1) {
            throw new DomainException('Invalid instant range representation');
        }

        $start = base64_decode($matches[1], true);
        $end = base64_decode($matches[2], true);
        if (
            $start === false || $end === false || base64_encode($start) !== $matches[1]
            || base64_encode($end) !== $matches[2]
        ) {
            throw new DomainException('Invalid instant range endpoints');
        }

        return new self(DateTime::fromString($start), DateTime::fromString($end));
    }

    /**
     * Returns the inclusive starting instant with its original timezone context
     */
    public function start(): DateTime
    {
        return $this->start;
    }

    /**
     * Returns the exclusive ending instant with its original timezone context
     */
    public function end(): DateTime
    {
        return $this->end;
    }

    /**
     * Returns whether the candidate lies at or after start and strictly before end
     */
    public function contains(DateTime $candidate): bool
    {
        return $this->start->compareInstantTo($candidate) <= 0 && $candidate->compareInstantTo($this->end) < 0;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return 'instant-range:v1:'.base64_encode($this->start->toString()).':'.base64_encode($this->end->toString());
    }

    /**
     * Returns equality by the ordered instant pair, ignoring endpoint timezone context
     */
    public function equals(mixed $object): bool
    {
        return $object instanceof self
            && $this->start->isSameInstantAs($object->start)
            && $this->end->isSameInstantAs($object->end);
    }

    /**
     * Returns the ordered instant coordinates as the timezone-independent hash
     */
    public function hashValue(): string
    {
        return sprintf(
            'instant-range:v1:%s:%s:%s:%s',
            $this->start->instantSeconds(),
            $this->start->toNative()->format('u'),
            $this->end->instantSeconds(),
            $this->end->toNative()->format('u')
        );
    }
}
