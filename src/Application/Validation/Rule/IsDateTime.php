<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use DateTimeImmutable;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Throwable;

/**
 * Class IsDateTime
 */
final class IsDateTime extends CompositeSpecification
{
    /**
     * Constructs IsDateTime
     */
    public function __construct(private readonly string $format)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        try {
            $dateTime = DateTimeImmutable::createFromFormat(
                $this->format,
                $candidate
            );
            if ($dateTime === false) {
                return false;
            }

            return $dateTime->format($this->format) === $candidate;
        } catch (Throwable) {
            return false;
        }
    }
}
