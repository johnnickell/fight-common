<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class DateTime
 */
final readonly class DateTime extends ValueObject implements Comparable
{
    /**
     * Constructs DateTime
     */
    private function __construct(private DateTimeImmutable $instant)
    {
        Date::fromNative($instant);
    }

    /**
     * Creates a strictly validated local occurrence in an explicit timezone
     *
     * @throws DomainException When the time is nonexistent, ambiguous or the offset does not select an occurrence
     */
    public static function fromLocal(Date $date, Time $time, Timezone $timezone, ?int $offsetSeconds = null): self
    {
        $zone = new DateTimeZone($timezone->value());
        $wall = $date->toString().' '.$time->toString();
        $utc = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $wall, new DateTimeZone('UTC'));
        // Offset probes also work for fixed-offset and abbreviation zones without transition tables.
        $offsets = [];
        foreach ([-3, -2, -1, 0, 1, 2, 3] as $days) {
            $probe = $utc->modify(sprintf('%+d days', $days));
            $offsets[] = $zone->getOffset($probe);
        }

        $nativeLocal = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $wall, $zone);
        if ($nativeLocal !== false) {
            $offsets[] = $nativeLocal->getOffset();
        }

        // Probe offsets on both sides of every nearby transition; historical shifts need not be one hour.
        // Text-to-native casts clamp out-of-range seconds on narrow runtimes; the probes above
        // remain the source of candidate offsets when a transition query cannot span this date.
        $start = (int) $utc->modify('-3 days')->format('U');
        $end = (int) $utc->modify('+3 days')->format('U');
        foreach ($zone->getTransitions($start, $end) ?: [] as $transition) {
            $offsets[] = $transition['offset'];
        }

        $matches = [];
        foreach (array_unique($offsets) as $offset) {
            $candidate = $utc->modify(sprintf('%+d seconds', -$offset))->setTimezone($zone);
            if ($candidate->format('Y-m-d H:i:s.u') === $wall && $candidate->getOffset() === $offset) {
                $matches[$offset] = $candidate;
            }
        }

        if ($matches === [] || ($offsetSeconds === null && count($matches) !== 1)) {
            throw new DomainException('Nonexistent or ambiguous local date and time');
        }

        if ($offsetSeconds !== null && !isset($matches[$offsetSeconds])) {
            throw new DomainException('Offset does not select a local occurrence');
        }

        return new self($matches[$offsetSeconds ?? array_key_first($matches)]);
    }

    /**
     * Creates a zoned value from the exact native instant and its timezone identifier
     *
     * @throws DomainException When the local date is outside the supported range
     */
    public static function fromNative(DateTimeInterface $value): self
    {
        return new self(DateTimeImmutable::createFromInterface($value));
    }

    /**
     * Creates a zoned value from exact Unix seconds and microseconds
     *
     * @throws DomainException When the coordinates or zoned date are unsupported
     */
    public static function fromInstant(string $seconds, int $microsecond, Timezone $timezone): self
    {
        if (preg_match('/\A(0|-?[1-9][0-9]*)\z/', $seconds) !== 1 || $microsecond < 0 || $microsecond > 999999) {
            throw new DomainException('Invalid instant coordinates');
        }

        $native = DateTimeImmutable::createFromFormat(
            '!U.u',
            $seconds.'.'.sprintf('%06d', $microsecond),
            new DateTimeZone('UTC')
        );
        if ($native === false || $native->format('U.u') !== $seconds.'.'.sprintf('%06d', $microsecond)) {
            throw new DomainException('Unsupported instant coordinates');
        }

        return new self($native->setTimezone(new DateTimeZone($timezone->value())));
    }

    /**
     * Creates a value from exact instant coordinates and a native timezone identifier
     *
     * @throws DomainException When the frame, identifier or coordinates are invalid
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/\Azoned:v1:(0|-?[1-9][0-9]*):([0-9]{6}):([A-Za-z0-9+\/=]*)\z/', $value, $parts) !== 1) {
            throw new DomainException('Invalid zoned date and time representation');
        }

        $name = base64_decode($parts[3], true);
        if ($name === false || $name === '' || base64_encode($name) !== $parts[3]) {
            throw new DomainException('Invalid timezone identifier');
        }

        return self::fromInstant($parts[1], (int) $parts[2], Timezone::fromString($name));
    }

    /**
     * Returns the local date in this value's timezone
     */
    public function date(): Date
    {
        return Date::fromNative($this->instant);
    }

    /**
     * Returns the local time in this value's timezone
     */
    public function time(): Time
    {
        return Time::fromNative($this->instant);
    }

    /**
     * Returns the native timezone identifier without changing Timezone equality
     */
    public function timezone(): Timezone
    {
        return Timezone::fromString($this->instant->getTimezone()->getName());
    }

    /**
     * Returns an immutable native timestamp for the exact instant
     */
    public function toNative(): DateTimeImmutable
    {
        return $this->instant;
    }

    /**
     * Returns the exact Unix second coordinate
     */
    public function instantSeconds(): string
    {
        return $this->instant->format('U');
    }

    /**
     * Returns instant ordering without timezone identity
     */
    public function compareInstantTo(self $other): int
    {
        return $this->instant <=> $other->instant;
    }

    /**
     * Returns whether both values identify the same instant
     */
    public function isSameInstantAs(self $other): bool
    {
        return $this->compareInstantTo($other) === 0;
    }

    /**
     * Converts the instant to another timezone without reinterpreting local components
     *
     * @throws DomainException When the target local date is unsupported
     */
    public function inTimezone(Timezone $timezone): self
    {
        return new self($this->instant->setTimezone(new DateTimeZone($timezone->value())));
    }

    /**
     * Creates a value with the same local components in another timezone under strict DST rules
     *
     * @throws DomainException When the target local occurrence is invalid or ambiguous
     */
    public function reinterpretInTimezone(Timezone $timezone, ?int $offsetSeconds = null): self
    {
        return self::fromLocal($this->date(), $this->time(), $timezone, $offsetSeconds);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return sprintf(
            'zoned:v1:%s:%s:%s',
            $this->instant->format('U'),
            $this->instant->format('u'),
            base64_encode($this->instant->getTimezone()->getName())
        );
    }

    /**
     * Returns instant-first ordering with a native timezone-identifier tie-breaker
     *
     * @throws DomainException When the other value is not a DateTime
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('DateTime comparison requires a DateTime');
        }

        $instant = $this->compareInstantTo($other);
        if ($instant !== 0) {
            return $instant;
        }

        return strcmp($this->instant->getTimezone()->getName(), $other->instant->getTimezone()->getName()) <=> 0;
    }
}
