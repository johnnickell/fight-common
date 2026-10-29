<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class NoticeOccurrence
 */
final readonly class NoticeOccurrence
{
    /**
     * Constructs NoticeOccurrence
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Creates a UTC occurrence retaining supplied zero through six fractional digits
     */
    public static function fromString(string $value): self
    {
        $pattern = '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.([0-9]{1,6}))?Z\z/D';
        if (preg_match($pattern, $value, $parts) !== 1 || str_starts_with($value, '0000')) {
            throw new DomainException('Invalid notice occurrence.');
        }

        $canonical = substr($value, 0, 19).'.'.str_pad($parts[1] ?? '', 6, '0').'Z';
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $canonical, new DateTimeZone('UTC'));
        if ($instant === false || $instant->format('Y-m-d\TH:i:s.u\Z') !== $canonical) {
            throw new DomainException('Invalid notice occurrence.');
        }

        return new self($value);
    }

    /**
     * Returns the original precision without inferring clock accuracy
     */
    public function toString(): string
    {
        return $this->value;
    }
}
