<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class NumberRange
 */
final class NumberRange extends CompositeSpecification
{
    /**
     * Constructs NumberRange
     */
    public function __construct(private readonly int|float $minNumber, private readonly int|float $maxNumber)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::rangeNumber(
            $candidate,
            $this->minNumber,
            $this->maxNumber
        );
    }
}
