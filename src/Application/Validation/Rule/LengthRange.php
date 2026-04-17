<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class LengthRange
 */
final class LengthRange extends CompositeSpecification
{
    /**
     * Constructs LengthRange
     */
    public function __construct(private readonly int $minLength, private readonly int $maxLength)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::rangeLength(
            $candidate,
            $this->minLength,
            $this->maxLength
        );
    }
}
